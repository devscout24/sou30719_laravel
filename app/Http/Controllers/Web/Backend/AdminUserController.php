<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Mail\ResetMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    // Profile View
    public function profile()
    {
        return view('backend.layouts.user.profile');
    }

    public function editProfile(Request $request)
    {
        $type = $request->query('type', 'profile');

        if (! in_array($type, ['profile', 'email', 'password'])) {
            return redirect()->route('admin.user.profile.edit', ['type' => 'profile']);
        }

        return view('backend.layouts.user.edit-profile', compact('type'));
    }


    public function updateProfile(Request $request)
    {
        $user = Auth::user();


        if (! Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'Invalid current password');
        }

        switch ($request->type) {

            case 'profile':
                $validate = Validator::make($request->all(), [
                    'name' => 'required|string|max:255',
                    'profile_photo' => 'nullable|image|max:2048',
                ]);

                if ($validate->fails()) {
                    return back()->with('error', $validate->errors()->first());
                }

                $user->name = $request->name;

                if ($request->hasFile('profile_photo')) {
                    $oldAvatar = null;
                    if ($user->avatar != 'user.png') {
                        $oldAvatar = $user->avatar;
                    }
                    $user->avatar = $this->uploadImage($request->profile_photo, $oldAvatar, 'uploads/avatars', 150, 150, 'avatar_' . $user->id);
                }

                $user->save();
                break;

            case 'password':
                $validate = Validator::make($request->all(), [
                    'new_password' => 'required|string|min:8|confirmed',
                ]);

                if ($validate->fails()) {
                    return back()->with('error', $validate->errors()->first());
                }

                $user->password = Hash::make($request->new_password);
                $user->save();
                break;

            case 'email':

                $validate = Validator::make($request->all(), [
                    'new_email' => 'required|email|unique:users,email,' . $user->id,
                ]);

                if ($validate->fails()) {
                    return back()->with('error', $validate->errors()->first());
                }

                $otp = rand(100000, 999999);
                $token = Str::random(64);

                $user->update([
                    'otp' => $otp,
                    'pending_email' => $request->new_email,
                    'otp_expired_at' => now()->addMinutes(10),
                    'email_change_token' => $token,
                    'email_change_token_expires_at' => now()->addMinutes(30),
                    'otp_verified_at' => null,
                ]);

                $link = route('email.change.verify', $token);

                Mail::to($user->email)->send(
                    new ResetMail($user, $request->new_email, $otp, $link)
                );


                return back()->with('success', 'Verification link sent to your current email.');
        }

        return back()->with('success', 'Updated successfully');
    }

    public function verifyEmailOtp(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'otp' => 'required|digits:6',
            'current_password' => 'required',
        ]);

        // Check password
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Invalid password']);
        }
        if (!$user->otp || !$user->otp_expired_at) {
            return back()->withErrors(['otp' => 'No OTP request found']);
        }


        // OTP validation
        if (
            $user->otp !== $request->otp ||
            now()->greaterThan($user->otp_expired_at)
        ) {
            return back()->withErrors(['otp' => 'OTP is invalid or expired']);
        }



        // Mark OTP verified
        $user->otp_verified_at = now();
        $user->email = $user->pending_email;
        $user->pending_email = null;
        $user->email_verified_at = now();
        $user->otp = null;
        $user->otp_expired_at = null;
        $user->otp_verified_at = now();
        $user->save();


        return redirect()
            ->route('admin.user.profile')
            ->with('success', 'Email updated successfully');
    }

    public function showEmailChangeForm($token)
    {
        $user = User::where('email_change_token', $token)
            ->where('email_change_token_expires_at', '>', now())
            ->firstOrFail();

        return view('backend.layouts.user.email-change', compact('token'));
    }

    public function confirmEmailChange(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'password' => 'required',
            'otp' => 'required',
        ]);

        $user = User::where('email_change_token', $request->token)
            ->where('email_change_token_expires_at', '>', now())
            ->first();

        if (! $user) {
            return back()->with('error', 'Invalid or expired link.');
        }

        if (! Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Incorrect password.');
        }

        if (
            $user->otp !== $request->otp ||
            now()->gt($user->otp_expired_at)
        ) {
            return back()->with('error', 'Invalid or expired OTP.');
        }

        $user->update([
            'email' => $user->pending_email,
            'pending_email' => null,
            'otp' => null,
            'otp_expired_at' => null,
            'email_change_token' => null,
            'email_change_token_expires_at' => null,
            'otp_verified_at' => now(),
        ]);

        return redirect()->route('admin.user.profile')->with('success', 'Email updated successfully.');
    }
}
