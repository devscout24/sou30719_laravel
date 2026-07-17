<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ChatAttachmentUploadService
{
    /**
     * Store an uploaded chat attachment on the public disk and return its relative path.
     */
    public function store(UploadedFile $file): string
    {
        return Storage::disk('public')->putFile('messages', $file);
    }

    /**
     * @param  UploadedFile[]  $files
     * @return string[]
     */
    public function storeMany(array $files): array
    {
        return array_map(fn (UploadedFile $file) => $this->store($file), $files);
    }
}
