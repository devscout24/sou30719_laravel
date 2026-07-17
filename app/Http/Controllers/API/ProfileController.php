<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    use ApiResponse;
    // Profile
    public function profile()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

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
            'latitude' => $user->latitude,
            'longitude' => $user->longitude,
        ];

        return $this->success($profile_data, 'Profile Information', 200);
    }

    public function updateProfile(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'name'      => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone'     => 'sometimes|string|max:20',
            'address'   => 'sometimes|string|max:500',
            'location'  => 'sometimes|string|max:255',
            'latitude'  => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'avatar'    => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
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
            if ($request->filled('latitude') && $request->filled('longitude')) {
                $user->latitude = $request->latitude;
                $user->longitude = $request->longitude;

                if (!$request->has('location')) {
                    $resolvedLocation = $this->reverseGeocode($request->latitude, $request->longitude);
                    if ($resolvedLocation) {
                        $user->location = $resolvedLocation;
                    }
                }
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

    /**
     * Resolve a human-readable location string from coordinates via OpenStreetMap Nominatim.
     * Returns null (instead of throwing) on any failure so profile updates never block on it.
     */
    private function reverseGeocode(float $latitude, float $longitude): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.name', 'Laravel') . ' (' . config('mail.from.address', 'contact@example.com') . ')',
            ])
                ->timeout(5)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format'         => 'jsonv2',
                    'lat'            => $latitude,
                    'lon'            => $longitude,
                    'zoom'           => 10,
                    'addressdetails' => 1,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $address = $response->json('address', []);
            $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['county'] ?? null;
            $country = $address['country'] ?? null;

            if ($city && $country) {
                return "{$city}, {$country}";
            }

            return $response->json('display_name');
        } catch (\Exception $e) {
            Log::warning('Reverse geocoding failed: ' . $e->getMessage());

            return null;
        }
    }

    public function deleteUser(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

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

        return $this->success([], 'Account deleted successfully', 200);
    }

    public function changePassword(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

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
            return $this->error([], 'Current password is incorrect', 200);
        }

        $user->password = bcrypt($request->new_password);
        $user->save();

        return $this->success(null, 'Password changed successfully', 200);
    }

    // ─────────────────────────────────────────────
    // BASIC INFO TAB
    // ─────────────────────────────────────────────

    public function getBasicInfo()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        return $this->success([
            'name'      => $user->name,
            'avatar'    => asset($user->avatar ?? 'user.png'),
            'location'  => $user->location,
            'bio'       => $user->bio,
            'interests' => $user->interests ?? [],
        ], 'Basic info fetched successfully', 200);
    }

    public function updateBasicInfo(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'name'      => 'sometimes|string|max:255',
            'location'  => 'sometimes|string|max:255',
            'bio'       => 'sometimes|string|max:1000',
            'interests' => 'sometimes|array',
            'interests.*' => 'string|max:50',
            'avatar'    => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        try {
            $user->fill($request->only(['name', 'location', 'bio']));

            if ($request->has('interests')) {
                $user->interests = $request->interests;
            }

            if ($request->hasFile('avatar')) {
                $oldImage = $user->avatar != 'user.png' ? $user->avatar : null;
                $avatar = $this->uploadImage($request->file('avatar'), $oldImage, 'uploads/avatar', 300, 300, 'avatar_' . $user->id);
                $user->avatar = $avatar;
            }

            $user->save();

            return $this->success([], 'Basic info updated successfully', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to update basic info: ' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────
    // GALLERY TAB
    // ─────────────────────────────────────────────

    public function getGallery()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $images = $user->galleryImages()->get()->map(function ($img) {
            return [
                'id'    => $img->id,
                'url'   => $img->full_url,
                'order' => $img->sort_order,
            ];
        });

        return $this->success([
            'images' => $images,
            'used'   => $images->count(),
            'max'    => 6,
        ], 'Gallery fetched successfully', 200);
    }

    public function uploadGalleryImage(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        $currentCount = $user->galleryImages()->count();

        if ($currentCount >= 6) {
            return $this->error([], 'Gallery limit reached. Maximum 6 photos allowed.', 422);
        }

        try {
            $path = $this->uploadImage($request->file('image'), null, 'uploads/gallery', 800, 800, 'gallery_' . $user->id . '_' . time());

            $image = $user->galleryImages()->create([
                'image_path' => $path,
                'sort_order' => $currentCount,
            ]);

            return $this->success([
                'id'  => $image->id,
                'url' => $image->full_url,
            ], 'Photo uploaded successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to upload photo: ' . $e->getMessage(), 500);
        }
    }

    public function deleteGalleryImage($id)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $image = $user->galleryImages()->where('id', $id)->first();

        if (!$image) {
            return $this->error([], 'Image not found', 404);
        }

        $image->delete();

        return $this->success([], 'Photo deleted successfully', 200);
    }

    // ─────────────────────────────────────────────
    // DATING PREFERENCES — toggle, distance, age range
    // ─────────────────────────────────────────────

    public function getDatingPreferences()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $pref = $user->datingPreference;

        return $this->success([
            'is_active'     => optional($user->datingProfile)->is_active ?? false,
            'max_distance'  => optional($pref)->max_distance ?? 25,
            'min_age'       => optional($pref)->min_age ?? 18,
            'max_age'       => optional($pref)->max_age ?? 50,
        ], 'Dating preferences fetched successfully', 200);
    }

    public function updateDatingPreferences(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'is_active'    => 'sometimes|boolean',
            'max_distance' => 'sometimes|integer|min:1|max:500',
            'min_age'      => 'sometimes|integer|min:18',
            'max_age'      => 'sometimes|integer|min:18',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        try {
            if ($request->has('is_active')) {
                $datingProfile = $user->datingProfile()->firstOrCreate([]);
                $datingProfile->update(['is_active' => $request->is_active]);
            }

            $pref = $user->datingPreference()->firstOrCreate([]);
            $pref->update($request->only(['max_distance', 'min_age', 'max_age']));

            return $this->success([], 'Dating preferences updated successfully', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to update dating preferences: ' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────
    // PROFILE SET-UP SUB-TAB
    // ─────────────────────────────────────────────

    public function getProfileSetup()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $datingProfile = $user->datingProfile;

        return $this->success([
            'showcase_page' => optional($datingProfile)->showcase_page ?? false,
            'nickname'      => optional($datingProfile)->nickname,
            'country'       => optional($datingProfile)->dating_country,
            'city'          => optional($datingProfile)->city,
            'about'         => optional($datingProfile)->about,
            'media'         => optional($datingProfile)->profile_setup_media
                ? asset($datingProfile->profile_setup_media)
                : null,
        ], 'Profile setup fetched successfully', 200);
    }

    public function updateProfileSetup(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'showcase_page' => 'sometimes|boolean',
            'nickname'      => 'sometimes|string|max:100',
            'country'       => 'sometimes|string|max:100',
            'city'          => 'sometimes|string|max:100',
            'about'         => 'sometimes|string|max:1000',
            'media'         => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        try {
            $datingProfile = $user->datingProfile()->firstOrCreate([]);

            $datingProfile->fill($request->only(['showcase_page', 'nickname', 'city', 'about']));

            if ($request->has('country')) {
                $datingProfile->dating_country = $request->country;
            }

            if ($request->hasFile('media')) {
                $oldImage = $datingProfile->profile_setup_media;
                $datingProfile->profile_setup_media = $this->uploadImage($request->file('media'), $oldImage, 'uploads/profile-setup', 800, 800, 'setup_' . $user->id);
            }

            $datingProfile->save();

            return $this->success([], 'Profile setup updated successfully', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to update profile setup: ' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────
    // IDENTITY & LOCATION SUB-TAB
    // ─────────────────────────────────────────────

    public function getIdentityLocation()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $datingProfile = $user->datingProfile;

        return $this->success([
            'nickname'            => optional($datingProfile)->dating_nickname,
            'dob'                 => optional($datingProfile)->dating_dob?->format('Y-m-d'),
            'full_name'           => optional($datingProfile)->dating_full_name,
            'relationship_status' => optional($datingProfile)->relationship_status,
            'gender'              => optional($datingProfile)->dating_gender,
            'email'               => optional($datingProfile)->dating_email,
            'location'            => optional($datingProfile)->dating_location,
            'country'             => optional($datingProfile)->dating_country,
            'address_1'           => optional($datingProfile)->address_1,
            'address_2'           => optional($datingProfile)->address_2,
            'connections_view'    => optional($datingProfile)->connections_view ?? false,
        ], 'Identity & location fetched successfully', 200);
    }

    public function updateIdentityLocation(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'nickname'             => 'required|string|max:100',
            'dob'                  => 'required|date|before:-18 years',
            'full_name'            => 'sometimes|string|max:255',
            'relationship_status'  => 'sometimes|in:single,married,divorced,separated,widowed',
            'gender'               => 'required|in:male,female',
            'email'                => 'sometimes|email',
            'location'             => 'required|string|max:255',
            'country'              => 'required|string|max:100',
            'address_1'            => 'sometimes|string|max:255',
            'address_2'            => 'sometimes|string|max:255',
            'connections_view'     => 'sometimes|boolean',
        ], [
            'dob.before' => 'You must be at least 18 years old.',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        try {
            $datingProfile = $user->datingProfile()->firstOrCreate([]);

            $datingProfile->dating_nickname     = $request->nickname;
            $datingProfile->dating_dob          = $request->dob;
            $datingProfile->dating_full_name    = $request->full_name ?? $datingProfile->dating_full_name;
            $datingProfile->relationship_status = $request->relationship_status ?? $datingProfile->relationship_status;
            $datingProfile->dating_gender       = $request->gender;
            $datingProfile->dating_email        = $request->email ?? $datingProfile->dating_email;
            $datingProfile->dating_location     = $request->location;
            $datingProfile->dating_country      = $request->country;
            $datingProfile->address_1           = $request->address_1 ?? $datingProfile->address_1;
            $datingProfile->address_2           = $request->address_2 ?? $datingProfile->address_2;

            if ($request->has('connections_view')) {
                $datingProfile->connections_view = $request->connections_view;
            }

            $datingProfile->save();

            return $this->success([], 'Identity & location updated successfully', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to update identity & location: ' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────
    // VISUAL INFO SUB-TAB
    // ─────────────────────────────────────────────

    public function getVisualInfo()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $images = $user->datingProfile
            ? $user->datingProfile->images()->get()->map(fn($img) => [
                'id'  => $img->id,
                'url' => $img->full_url,
            ])
            : [];

        return $this->success([
            'description' => optional($user->datingProfile)->visual_description,
            'photos'      => $images,
        ], 'Visual info fetched successfully', 200);
    }

    public function updateVisualInfo(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'description' => 'sometimes|string|max:2000',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        $datingProfile = $user->datingProfile()->firstOrCreate([]);
        $datingProfile->visual_description = $request->description;
        $datingProfile->save();

        return $this->success([], 'Visual info updated successfully', 200);
    }

    public function uploadVisualInfoPhoto(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        try {
            $datingProfile = $user->datingProfile()->firstOrCreate([]);

            $path = $this->uploadImage($request->file('photo'), null, 'uploads/dating', 800, 800, 'dating_' . $user->id . '_' . time());

            $image = $datingProfile->images()->create([
                'image_path' => $path,
                'sort_order' => $datingProfile->images()->count(),
            ]);

            return $this->success([
                'id'  => $image->id,
                'url' => $image->full_url,
            ], 'Photo uploaded successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to upload photo: ' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────
    // APPEARANCE & LIFESTYLE SUB-TAB
    // ─────────────────────────────────────────────

    public function getAppearanceLifestyle()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $datingProfile = $user->datingProfile;

        return $this->success([
            'height'            => optional($datingProfile)->height,
            'occupation'        => optional($datingProfile)->occupation,
            'education'         => optional($datingProfile)->education,
            'lifestyle_habits'  => optional($datingProfile)->lifestyle_habits,
            'body_type'         => optional($datingProfile)->body_type,
            'ethnicity'         => optional($datingProfile)->ethnicity,
            'religious_beliefs' => optional($datingProfile)->religious_beliefs,
            'languages'         => optional($datingProfile)->languages ?? [],
        ], 'Appearance & lifestyle fetched successfully', 200);
    }

    public function updateAppearanceLifestyle(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'height'            => 'sometimes|string|max:20',
            'occupation'        => 'sometimes|string|max:255',
            'education'         => 'sometimes|string|max:255',
            'lifestyle_habits'  => 'sometimes|in:active,moderate,sedentary',
            'body_type'         => 'sometimes|string|max:50',
            'ethnicity'         => 'sometimes|string|max:50',
            'religious_beliefs' => 'sometimes|string|max:50',
            'languages'         => 'sometimes|array',
            'languages.*'       => 'string|max:50',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        try {
            $datingProfile = $user->datingProfile()->firstOrCreate([]);

            $datingProfile->fill($request->only([
                'height',
                'occupation',
                'education',
                'lifestyle_habits',
                'body_type',
                'ethnicity',
                'religious_beliefs',
            ]));

            if ($request->has('languages')) {
                $datingProfile->languages = $request->languages;
            }

            $datingProfile->save();

            return $this->success([], 'Appearance & lifestyle updated successfully', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to update appearance & lifestyle: ' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────
    // INTERESTS & PERSONALITY SUB-TAB
    // ─────────────────────────────────────────────

    public function getInterestsPersonality()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $datingProfile = $user->datingProfile;

        return $this->success([
            'hobbies'            => optional($datingProfile)->hobbies ?? [],
            'personality_traits' => optional($datingProfile)->personality_traits ?? [],
            'pet_preference'     => optional($datingProfile)->pet_preference,
            'political_views'    => optional($datingProfile)->political_views,
            'family_plans'       => optional($datingProfile)->family_plans,
            'children_status'    => optional($datingProfile)->children_status,
            'prompt_question'    => optional($datingProfile)->prompt_question,
            'prompt_answer'      => optional($datingProfile)->prompt_answer,
        ], 'Interests & personality fetched successfully', 200);
    }

    public function updateInterestsPersonality(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'hobbies'             => 'sometimes|array',
            'hobbies.*'           => 'string|max:50',
            'personality_traits'  => 'sometimes|array',
            'personality_traits.*' => 'string|max:50',
            'pet_preference'      => 'sometimes|in:loves_pets,no_pets,allergic,has_pets',
            'political_views'     => 'sometimes|string|max:50',
            'family_plans'        => 'sometimes|in:want_kids,open_to_kids,dont_want_kids,not_sure',
            'children_status'     => 'sometimes|in:no_kids,has_kids',
            'prompt_question'     => 'sometimes|string|max:255',
            'prompt_answer'       => 'sometimes|string|max:1000',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        try {
            $datingProfile = $user->datingProfile()->firstOrCreate([]);

            $datingProfile->fill($request->only([
                'pet_preference',
                'political_views',
                'family_plans',
                'children_status',
                'prompt_question',
                'prompt_answer',
            ]));

            if ($request->has('hobbies')) {
                $datingProfile->hobbies = $request->hobbies;
            }
            if ($request->has('personality_traits')) {
                $datingProfile->personality_traits = $request->personality_traits;
            }

            $datingProfile->save();

            return $this->success([], 'Interests & personality updated successfully', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to update interests & personality: ' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────
    // MATCHING CRITERIA SUB-TAB
    // ─────────────────────────────────────────────

    public function getMatchingCriteria()
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $pref = $user->datingPreference;

        return $this->success([
            'relationship_goal'   => optional($pref)->relationship_goal,
            'deal_breakers'       => optional($pref)->deal_breakers,
            'partner_preferences' => optional($pref)->partner_preferences,
        ], 'Matching criteria fetched successfully', 200);
    }

    public function updateMatchingCriteria(Request $request)
    {
        $user = User::query()->where('id', Auth::guard('api')->id())->first();

        if (!$user) {
            return $this->error('User not found Or Invalid Token', 404);
        }

        $validate = Validator::make($request->all(), [
            'relationship_goal'   => 'sometimes|in:casual,long_term,marriage,friendship,not_sure',
            'deal_breakers'       => 'sometimes|string|max:255',
            'partner_preferences' => 'sometimes|string|max:2000',
        ]);

        if ($validate->fails()) {
            return $this->error($validate->errors()->first(), $validate->errors()->first(), 200);
        }

        $pref = $user->datingPreference()->firstOrCreate([]);
        $pref->fill($request->only(['relationship_goal', 'deal_breakers', 'partner_preferences']));
        $pref->save();

        return $this->success([], 'Matching criteria updated successfully', 200);
    }
}
