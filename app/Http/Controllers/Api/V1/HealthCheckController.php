<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends BaseApiController
{
    /**
     * Check API status and environment info.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'operational',
            'api_version' => 'v1',
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
        ], 'API American Shelf Abobo v1 opérationnelle.');
    }
}
