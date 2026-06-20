<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FcmDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 5.1 — Register FCM device token dari mobiles/ceisa.
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_token' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', 'in:android,ios,web'],
            'user_id' => ['required', 'string', 'max:64'],
        ]);

        $token = FcmDeviceToken::register(
            $data['user_id'],
            $data['device_token'],
            $data['platform'] ?? 'android',
        );

        return response()->json(['message' => 'Token registered.', 'id' => $token->id]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_token' => ['required', 'string'],
        ]);

        FcmDeviceToken::where('device_token', $data['device_token'])
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Token deactivated.']);
    }
}