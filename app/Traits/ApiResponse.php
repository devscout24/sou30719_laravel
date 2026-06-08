<?php

namespace App\Traits;

trait ApiResponse
{
    public function success(mixed $data = [], $message = null, $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ], $code);
    }

    public function error(Mixed $data = [], $message = null, $code = 500)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ], $code);
    }
}
