<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    use ApiResponse;
    // Profile
    public function profile()
    {
        $user = User::where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $profile_data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo' => asset($user->avatar ?? 'user.png'),
            'address' => $user->address,
            'phone' => $user->phone,
            'location' => $user->location,
        ];

        return $this->success($profile_data, 'Profile Information', 200);
    }

    public function updateProfile(Request $request)
    {
        $user = User::where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone'    => 'sometimes|string|max:20',
            'address'  => 'sometimes|string|max:500',
            'location' => 'sometimes|string|max:255',
            'avatar'   => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        try {
            if ($request->has('name')) {
                $user->name = $request->name;
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }
            if ($request->has('address')) {
                $user->address = $request->address;
            }
            if ($request->has('location')) {
                $user->location = $request->location;
            }
            if ($request->hasFile('avatar')) {
                $oldImage = $user->avatar != 'user.png' ? $user->avatar : null;
                $avatar = $this->uploadImage($request->file('avatar'), $oldImage, 'uploads/avatar', 300, 300, 'avatar_' . $user->id);
                $user->avatar = $avatar;
            }

            $user->save();

            return $this->success([], 'Profile updated successfully', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    public function changePassword(Request $request)
    {
        $user = User::where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validation = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validation->fails()) {
            return $this->error($validation->errors()->first(), $validation->errors()->first(), 200);
        }

        if (!password_verify($request->current_password, $user->password)) {
            return $this->error([] ,'Current password is incorrect', 200);
        }

        $user->password = bcrypt($request->new_password);
        $user->save();

        return $this->success(null, 'Password changed successfully', 200);
    }
}
