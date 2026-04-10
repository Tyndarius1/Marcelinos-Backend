<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MaintenanceModeController extends Controller
{
    public function show(): JsonResponse
    {
        $cached = Cache::get('maintenance_mode_config');

        if (is_array($cached)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'enabled' => (bool) ($cached['enabled'] ?? false),
                    'badge' => (string) ($cached['badge'] ?? 'Scheduled Maintenance'),
                    'title' => (string) ($cached['title'] ?? 'We are improving your experience'),
                    'description' => (string) ($cached['description'] ?? 'Our website is currently under maintenance. Please check back again shortly.'),
                    'eta' => (string) ($cached['eta'] ?? ''),
                ],
            ]);
        }

        $data = [
            'enabled' => filter_var(env('MAINTENANCE_MODE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'badge' => (string) env('MAINTENANCE_MODE_BADGE', 'Scheduled Maintenance'),
            'title' => (string) env('MAINTENANCE_MODE_TITLE', 'We are improving your experience'),
            'description' => (string) env('MAINTENANCE_MODE_DESCRIPTION', 'Our website is currently under maintenance. Please check back again shortly.'),
            'eta' => (string) env('MAINTENANCE_MODE_ETA', ''),
        ];

        Cache::forever('maintenance_mode_config', $data);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

