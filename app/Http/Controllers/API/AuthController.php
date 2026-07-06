<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Mail\OtpSend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    use ApiResponse;

    // -----------------------------------------------------------------------
    // REGISTRATION
    // -----------------------------------------------------------------------

    /**
     * Register a new user.
     * - Auto-generates a demo name and username from the email prefix.
     * - Sends a 6-digit OTP to the email for verification.
     * - Returns NO token — user must verify email before logging in.
     */
    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'                 => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password'              => 'required|string|min:6|confirmed',
        ], [
            'email.required'        => 'Email is required.',
            'email.email'           => 'Email must be valid.',
            'email.unique'          => 'This email is already registered.',
            'password.required'     => 'Password is required.',
            'password.min'          => 'Password must be at least 6 characters.',
            'password.confirmed'    => 'Password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        // Auto-generate demo name and unique username from the email prefix
        $prefix   = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $request->email)[0]));
        $name     = ucfirst($prefix);
        $username = $prefix . rand(1000, 9999);

        while (User::query()->where('username', $username)->exists()) {
            $username = $prefix . rand(1000, 9999);
        }

        $user = User::create([
            'name'     => $name,
            'username' => $username,
            'email'    => $request->email,
            'password' => $request->password,  // 'hashed' cast handles bcrypt
            'status'   => 'active',
        ]);

        $roleName = 'user';
        $guardName = config('auth.defaults.guard', 'web');
        Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guardName]);
        $user->assignRole($roleName);

        // Generate and send email-verification OTP
        [$otp, $expiresAt] = $this->generateOtp($user);

        Mail::to($user->email)->send(new OtpSend($otp, 'Verify Your Email'));

        return $this->success([
            'email' => $user->email,
            // In Seconds since epoch
            'expires_at' => $expiresAt->format('U'),
        ], 'Registration successful. Please check your email for the verification code.', 201);
    }

    // -----------------------------------------------------------------------
    // EMAIL VERIFICATION
    // -----------------------------------------------------------------------

    /**
     * Verify the user's email with the OTP sent during registration (or re-sent on login).
     * On success, marks email as verified and returns a JWT token (auto-login).
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$user) {
            return $this->error([], 'Invalid OTP.', 400);
        }

        if (Carbon::now()->gt($user->otp_expired_at)) {
            $user->update(['otp' => null, 'otp_expired_at' => null]);
            return $this->error([], 'OTP has expired. Please try logging in again to receive a new code.', 400);
        }

        $user->update([
            'email_verified_at' => Carbon::now(),
            'otp'               => null,
            'otp_expired_at'    => null,
            'last_login_at'     => now(),
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->success([
            'id'       => $user->id,
            'token'    => $token,
            'name'     => $user->name,
            'email'    => $user->email,
            'username' => $user->username,
            'role'     => $user->getRoleNames()->first(),
            'status'   => $user->status,
        ], 'Email verified successfully.', 200);
    }

    // -----------------------------------------------------------------------
    // LOGIN
    // -----------------------------------------------------------------------

    /**
     * Sign in.
     * - If credentials are wrong: 401.
     * - If email is NOT verified: sends a fresh OTP and returns 403.
     * - If email IS verified: returns JWT token.
     */
    public function signin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ], [
            'email.required'    => 'Email is required.',
            'email.email'       => 'Invalid email address.',
            'password.required' => 'Password is required.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $credentials = $request->only('email', 'password');
        $token       = Auth::guard('api')->attempt($credentials);

        if (!$token) {
            return $this->error([], 'Invalid email or password.', 401);
        }

        $user = Auth::guard('api')->user();

        // Block login if email not verified — invalidate JWT, send fresh OTP
        if (!$user->email_verified_at) {
            Auth::guard('api')->logout();

            [$otp, $expiresAt] = $this->generateOtp($user);

            Mail::to($user->email)->send(new OtpSend($otp, 'Verify Your Email'));

            return $this->error([
                'email' => $user->email,
            ], 'Your email is not verified. A new verification code has been sent to your email.', 403);
        }

        $user->update(['last_login_at' => now()]);

        return $this->success([
            'id'       => $user->id,
            'token'    => $token,
            'name'     => $user->name ?? '',
            'email'    => $user->email,
            'username' => $user->username,
            'role'     => $user->getRoleNames()->first(),
            'status'   => $user->status,
        ], 'Login successful.', 200);
    }

    // -----------------------------------------------------------------------
    // LOGOUT
    // -----------------------------------------------------------------------

    public function logout()
    {
        try {
            $token = JWTAuth::getToken();

            if (!$token) {
                return $this->error([], 'Token not provided.', 401);
            }

            JWTAuth::invalidate($token);

            return $this->success([], 'Successfully logged out.', 200);
        } catch (JWTException $e) {
            return $this->error([], 'Failed to logout. ' . $e->getMessage(), 500);
        }
    }

    // -----------------------------------------------------------------------
    // FORGOT PASSWORD FLOW
    // -----------------------------------------------------------------------

    /**
     * Send a password-reset OTP.
     * Works even if the user's email is not yet verified.
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->error([], 'No account found with this email address.', 404);
        }

        [$otp, $expiresAt] = $this->generateOtp($user);

        // Clear any stale password reset token from a previous incomplete attempt
        $user->update([
            'password_reset_token'            => null,
            'password_reset_token_expires_at' => null,
        ]);

        Mail::to($user->email)->send(new OtpSend($otp, 'Password Reset Code'));

        return $this->success([
            'email' => $user->email,
        ], 'A password reset code has been sent to your email.', 200);
    }

    /**
     * Verify the password-reset OTP and return a short-lived reset token.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$user) {
            return $this->error([], 'Invalid OTP.', 400);
        }

        if (Carbon::now()->gt($user->otp_expired_at)) {
            $user->update(['otp' => null, 'otp_expired_at' => null]);
            return $this->error([], 'OTP has expired. Please request a new one.', 400);
        }

        $resetToken = Str::random(64);

        $user->update([
            'otp'                          => null,
            'otp_expired_at'               => null,
            'otp_verified_at'              => Carbon::now(),
            'password_reset_token'         => $resetToken,
            'password_reset_token_expires_at' => Carbon::now()->addMinutes(15),
        ]);

        return $this->success([
            'email'       => $user->email,
            'reset_token' => $resetToken,
        ], 'OTP verified successfully.', 200);
    }

    /**
     * Reset password using the token from verifyOtp.
     * - If the user's email was already verified: auto-login and return JWT.
     * - If email was NOT verified: just return success; user must login and then verify email.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'       => 'required|email',
            'password'    => 'required|string|min:6|confirmed',
            'reset_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)
            ->where('password_reset_token', $request->reset_token)
            ->first();

        if (!$user) {
            return $this->error([], 'Invalid reset token or email.', 400);
        }

        if (Carbon::now()->gt($user->password_reset_token_expires_at)) {
            return $this->error([], 'Reset token has expired. Please request a new one.', 400);
        }

        $user->password                        = $request->password;  // 'hashed' cast handles bcrypt
        $user->password_reset_token            = null;
        $user->password_reset_token_expires_at = null;
        $user->save();

        // Auto-login only if the email was already verified
        if ($user->email_verified_at) {
            $token = Auth::guard('api')->login($user);
            $user->update(['last_login_at' => now()]);

            return $this->success([
                'id'       => $user->id,
                'token'    => $token,
                'name'     => $user->name,
                'email'    => $user->email,
                'username' => $user->username,
                'role'     => $user->getRoleNames()->first(),
                'status'   => $user->status,
            ], 'Password reset successful.', 200);
        }

        // Email not yet verified — user must login (which will trigger OTP re-send)
        return $this->success([
            'email' => $user->email,
        ], 'Password reset successful. Please login to verify your email.', 200);
    }

    // -----------------------------------------------------------------------
    // FCM TOKENS
    // -----------------------------------------------------------------------

    public function storeFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
            'token'     => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $user     = Auth::guard('api')->user();
        $existing = $user->fcmTokens()->where('device_id', $request->device_id)->first();

        if ($existing) {
            $existing->update(['token' => $request->token]);
        } else {
            $user->fcmTokens()->create([
                'device_id' => $request->device_id,
                'token'     => $request->token,
            ]);
        }

        $fcm = $user->fcmTokens()->where('device_id', $request->device_id)->first();

        return $this->success([
            'device_id' => $fcm->device_id,
            'token'     => $fcm->token,
        ], 'FCM token stored successfully.', 200);
    }

    public function deleteFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        Auth::guard('api')->user()->fcmTokens()->where('device_id', $request->device_id)->delete();

        return $this->success([], 'FCM token deleted successfully.', 200);
    }

    // -----------------------------------------------------------------------
    // DELETE ACCOUNT
    // -----------------------------------------------------------------------

    public function deleteUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $user = Auth::guard('api')->user();

        if (!Hash::check($request->password, $user->password)) {
            return $this->error([], 'Incorrect password.', 401);
        }

        // Free up the unique email/username so the account is soft-deleted
        // (preserved for history/relations) without blocking re-registration.
        $user->update([
            'email'    => "deleted_user_{$user->id}@deleted.local",
            'username' => null,
        ]);

        $user->delete();

        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->success([], 'Account deleted successfully.', 200);
    }

    // -----------------------------------------------------------------------
    // PRIVATE HELPERS
    // -----------------------------------------------------------------------

    /**
     * Generate a 6-digit OTP with a 15-minute expiry and persist it on the user.
     * Returns [$otp, $expiresAt].
     */
    private function generateOtp(User $user): array
    {
        $otp       = (string) rand(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(15);

        $user->update([
            'otp'            => $otp,
            'otp_expired_at' => $expiresAt,
        ]);

        return [$otp, $expiresAt];
    }

    /**
     * Resend the email-verification OTP for a user who hasn't verified yet.
     * Used by the "Resend OTP" button on the Verify Email (signup) screen.
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $user = User::query()->where('email', $request->email)->first();

        if (!$user) {
            return $this->error([], 'No account found with this email address.', 404);
        }

        if ($user->email_verified_at) {
            return $this->error([], 'This email is already verified. Please login.', 400);
        }

        [$otp, $expiresAt] = $this->generateOtp($user);

        Mail::to($user->email)->send(new OtpSend($otp, 'Verify Your Email'));

        return $this->success([
            'email' => $user->email,
        ], 'A new verification code has been sent to your email.', 200);
    }

    /**
     * Complete profile setup after email verification.
     * Sets: full name, DOB, gender, country, location (lat/lng).
     * Marks profile_completed = true.
     */
    public function setupProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:100',
            'dob'       => 'required|date|before:-18 years',
            'gender'    => 'required|in:male,female',
            'country'   => 'required|string|max:100',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], [
            'dob.before' => 'You must be at least 18 years old.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $user = Auth::guard('api')->user();

        $user->update([
            'name'              => $request->name,
            'dob'               => $request->dob,
            'gender'            => $request->gender,
            'country'           => $request->country,
            'latitude'          => $request->latitude,
            'longitude'         => $request->longitude,
            'profile_completed' => true,
        ]);

        return $this->success([
            'id'                => $user->id,
            'name'              => $user->name,
            'dob'               => $user->dob,
            'gender'            => $user->gender,
            'country'           => $user->country,
            'profile_completed' => $user->profile_completed,
        ], 'Profile setup completed successfully.', 200);
    }


}
