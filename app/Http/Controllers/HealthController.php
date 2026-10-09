<?php

namespace App\Http\Controllers;

use App\Support\HealthStatus;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthStatus $health): JsonResponse
    {
        $db = $health->db();

        return response()->json([
            'version' => $health->version(),
            'uptime' => $health->uptime(),
            'db' => $db ? 'ok' : 'error',
            'cache' => $health->cache() ? 'ok' : 'error',
            'scheduler' => $health->scheduler(),
        ], $db ? 200 : 503);
    }
}
