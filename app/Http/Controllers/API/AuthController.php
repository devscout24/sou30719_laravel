<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Str;
use App\Mail\OtpSend;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function signup(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'     => 'nullable|string|max:100',
                'email'    => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:6|confirmed',
            ],
            [
                'email.required'     => 'Email is required',
                'email.email'        => 'Email must be valid',
                'email.unique'       => 'Email already exists',
                'password.required'  => 'Password is required',
                'password.min'       => 'Password must be at least 6 characters',
                'password.confirmed' => 'Password confirmation does not match',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        // Create user
        $user = User::create([
            'name'          => $request->name ?? null,
            'email'         => $request->email,
            'password'      => bcrypt($request->password),
            'status'        => 'active',
            'last_login_at' => now(),
        ]);

        // Assign default role
        $user->assignRole('user');

        // JWT login
        $token = Auth::guard('api')->attempt([
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        if (!$token) {
            return $this->error([], 'Registration successful but token generation failed', 500);
        }

        return $this->success([
            'id'     => $user->id,
            'token'  => $token,
            'name'   => $user->name ?? '',
            'email'  => $user->email,
            'role'   => $user->getRoleNames()->first(),
            'status' => $user->status,
        ], 'Registration successful', 200);
    }


    public function signin(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email'    => 'required|email',
                'password' => 'required|string|min:6',
            ],
            [
                'email.required'    => 'Email is required',
                'email.email'       => 'Invalid email address',
                'password.required' => 'Password is required',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        $credentials = $request->only('email', 'password');

        $token = Auth::guard('api')->attempt($credentials);

        if (!$token) {
            return $this->error([], 'Invalid email or password', 401);
        }

        $user = Auth::guard('api')->user();

        $user->update([
            'last_login_at' => now(),
        ]);

        return $this->success([
            'id'       => $user->id,
            'token'    => $token,
            'name'     => $user->name ?? '',
            'email'    => $user->email,
            'username' => $user->username,
            'role'     => $user->getRoleNames()->first(),
            'status'   => $user->status,
        ], 'Login successful', 200);
    }



    public function logout()
    {
        try {
            // Get token from request
            $token = JWTAuth::getToken();

            if (!$token) {
                return $this->error([], 'Token not provided', 401);
            }

            // Invalidate token
            JWTAuth::invalidate($token);

            return $this->success([], 'Successfully logged out', 200);
        } catch (JWTException $e) {
            return $this->error([], 'Failed to logout. ' . $e->getMessage(), 500);
        }
    }



    public function sendOtp(Request $request)
    {
        // Validate incoming email
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->error([], 'User with this email does not exist.', 404);
        }

        // Generate OTP and expiry
        $otp = rand(1000, 9999);
        $expiresAt = Carbon::now()->addMinutes(15);

        // Save OTP and expiry to user
        $user->update([
            'otp' => $otp,
            'otp_expired_at' => $expiresAt,
        ]);

        // Send OTP via email
        // Mail::to($user->email)->send(new OtpSend($otp)); // Temporary comment

        // Return success response (without exposing OTP in production)
        return $this->success([
            'id' => $user->id,
            'email' => $user->email,
            'otp' => $otp, // Remove this line in production
            'expires_at' => $expiresAt,
        ], 'OTP sent successfully.', 200);
    }



    public function verifyOtp(Request $request)
    {

        $request->validate([
            'otp' => 'required',
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->where('otp', $request->otp)->first();

        if (!$user) {
            return $this->error([], 'Invalid otp', 400);
        } else if ($user->otp_expired_at < Carbon::now()) {

            $user->otp = null;
            $user->otp_expired_at = null;
            $user->save();

            return $this->error([], 'OTP expired', 400);
        }

        $user->otp_verified_at                 = Carbon::now();
        $user->password_reset_token            = Str::random(64);
        $user->password_reset_token_expires_at = Carbon::now()->addMinutes(15);
        $user->save();

        return $this->success([
            'id' => $user->id,
            'email' => $user->email,
            'reset_token' => $user->password_reset_token,
        ], 'OTP verified successfully', 200);
    }


    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'       => 'required|email',
            'password'    => 'required|min:4|confirmed',
            'reset_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Error in Validation', 422);
        }

        $user = User::where('email', $request->email)
            ->where('password_reset_token', $request->reset_token)
            ->first();

        if (!$user) {
            return $this->error([], 'Invalid token or email.', 400);
        }

        if ($user->password_reset_token_expires_at < Carbon::now()) {
            return $this->error([], 'Token expired.', 400);
        }

        $user->password = bcrypt($request->password);



        // Attempt login with email and password
        $credentials = $request->only('email', 'password');
        Auth::guard('api')->attempt($credentials);

        $token = Auth::guard('api')->attempt($credentials);

        // Format user data
        $userData = [
            'id'     => $user->id,
            'token'  => $token,
            'name'   => $user->name,
            'email'  => $user->email,
            'avatar' => asset($user->avatar == null ? 'user.png' : $user->avatar),
        ];

        // Invalidate token after use
        $user->password_reset_token = null;
        $user->password_reset_token_expires_at = null;
        $user->save();



        return $this->success($userData, 'Login Successful', 200);
    }


    public function storeFcmToken(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Error in Validation', 422);
        }

        $user = Auth::guard('api')->user();

        // Check if device exists
        $existing = $user->fcmTokens()->where('device_id', $request->device_id)->first();

        if ($existing) {
            $existing->update(['token' => $request->token]);
        } else {
            $user->fcmTokens()->create([
                'device_id' => $request->device_id,
                'token' => $request->token,
            ]);
        }

        $fcmToken = $user->fcmTokens()->where('device_id', $request->device_id)->first();

        $response = [
            'id' => $fcmToken->id,
            'device_id' => $fcmToken->device_id,
            'token' => $fcmToken->token,
        ];

        return $this->success($response, 'FCM token stored successfully', 200);
    }

    public function deleteFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Error in Validation', 422);
        }

        $user = Auth::guard('api')->user();

        $user->fcmTokens()->where('device_id', $request->device_id)->delete();

        return $this->success([], 'FCM token deleted successfully', 200);
    }

    public function deleteUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), $validator->errors()->first(), 422);
        }

        // Match credentials
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('api')->attempt($credentials)) {
            return $this->error([], 'Invalid email or password', 401);
        }

        // Match password
        if (! Hash::check($request->password, Auth::guard('api')->user()->password)) {
            return $this->error([], 'Invalid email or password', 401);
        }

        // Update user
        $user = Auth::guard('api')->user();

        if (!$user) {
            return $this->error([], 'Unauthenticated', 401);
        }

        $user->delete();

        return $this->success([], 'User deleted successfully', 200);
    }
}
