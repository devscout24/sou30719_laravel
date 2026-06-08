<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

abstract class Controller
{
    public function uploadImage($image, $oldImage = null, $folder = 'uploads', $width = 150, $height = 150, $customName = 'image')
    {
        if ($image && $image->isValid()) {
            // Delete old image if exists
            if ($oldImage && File::exists(public_path($oldImage))) {
                File::delete(public_path($oldImage));
            }

            // Ensure the folder exists, create if not
            $folderPath = public_path($folder);
            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0755, true); // recursive = true
            }

            // Generate new image name with custom name + timestamp
            $extension = $image->getClientOriginalExtension();
            $image_name = $customName . '-' . time() . '.' . $extension;
            $image_path = $folder . '/' . $image_name;

            // Resize and save the image
            Image::make($image)->resize($width, $height)->save(public_path($image_path));

            return $image_path; // Return new image path
        }

        return $oldImage; // Return old image if no new image is uploaded
    }
    public static function fileUpload($file, $folder)
    {
        try {
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $targetPath = public_path('uploads/' . $folder);

            if (!File::exists($targetPath)) {
                File::makeDirectory($targetPath, 0777, true, true);
            }

            $file->move($targetPath, $fileName);

            return 'uploads/' . $folder . '/' . $fileName;
        } catch (\Exception $e) {
            Log::error('File upload failed: ' . $e->getMessage());
            return null;
        }
    }

    public function generateUniqueSlug($name, $modelClass)
    {
        $slug = Str::slug($name);
        $count = $modelClass::where('slug', 'LIKE', "$slug%")->count();

        return $count ? "{$slug}-{$count}" : $slug;
    }

    public function generateCode($prefix, $model)
    {
        $code = "000001";
        $model = '\\App\\Models\\' . $model;
        $usesSoftDeletes = in_array(
            'Illuminate\Database\Eloquent\SoftDeletes',
            class_uses($model)
        );
        $num_rows = $usesSoftDeletes ? $model::withTrashed()->count() : $model::count();

        if ($num_rows != 0) {
            $newCode = $num_rows + 1;
            $zeros = ['0', '00', '000', '0000', '00000'];
            $code = strlen($newCode) > count($zeros) ? $newCode : $zeros[count($zeros) - strlen($newCode)] . $newCode;
        }
        return $prefix . $code;
    }

    public function generateInvoiceNo($model)
    {
        $code = "000001";
        $model = '\\App\\Models\\' . $model;
        $usesSoftDeletes = in_array(
            'Illuminate\Database\Eloquent\SoftDeletes',
            class_uses($model)
        );
        $num_rows = $usesSoftDeletes ? $model::withTrashed()->count() : $model::count();

        if ($num_rows != 0) {
            $newCode = $num_rows + 1;
            $zeros = ['0', '00', '000', '0000', '00000'];
            $code = strlen($newCode) > count($zeros) ? $newCode : $zeros[count($zeros) - strlen($newCode)] . $newCode;
        }
        return $code;
    }

    public function checkUserName($userName)
    {
        // Clean the username
        $baseUsername = Str::slug($userName); // removes special chars/spaces if any

        // If it's already available, return it
        if (!User::where('username', $baseUsername)->exists()) {
            return $baseUsername;
        }

        // Try finding a unique one by appending numbers
        for ($i = 1; $i <= 9999; $i++) {
            $newUsername = $baseUsername . $i;

            if (!User::where('username', $newUsername)->exists()) {
                return $newUsername;
            }
        }

        // As a last fallback — use a UUID suffix
        $finalUsername = $baseUsername . '_' . Str::random(6);
        return $finalUsername;
    }
}
