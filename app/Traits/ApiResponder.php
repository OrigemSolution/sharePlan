<?php
namespace App\Traits;

use App\Services\ApiResponseService;

trait ApiResponder
{
    protected function success($data = null, string $message = 'Success', int $code = 200)
    {
        return app(ApiResponseService::class)->success($data, $message, $code);
    }

    protected function error($errors = null, string $message = 'Error', int $code = 400)
    {
        return app(ApiResponseService::class)->error($errors, $message, $code);
    }

    protected function validation($errors, string $message = 'Validation Failed', int $code = 422)
    {
        return app(ApiResponseService::class)->validation($errors, $message, $code);
    }
}
