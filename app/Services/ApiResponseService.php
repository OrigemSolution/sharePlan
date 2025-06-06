<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class ApiResponseService
{
    public function success($data = null, string $message = 'success', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public function error($errors = null, string $message = 'error', int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }

    public function validation($errors, string $message = 'validation failed', int $code = 422): JsonResponse
    {
        return $this->error($errors, $message, $code);
    }
}
