<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CeisaApiLog;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fase 6 — Dasbor: API logs, notification logs, settings.
 */
class DashboardController extends Controller
{
    /**
     * GET /v1/api-logs — Log H2H & webhook.
     */
    public function apiLogs(Request $request): JsonResponse
    {
        $query = CeisaApiLog::query();
        if ($d = $request->get('direction')) {
            $query->where('direction', $d);
        }

        return response()->json($query->latest()->paginate(min((int) $request->get('per_page', 50), 200)));
    }

    /**
     * GET /v1/notification-logs — Log pengiriman notifikasi.
     */
    public function notificationLogs(Request $request): JsonResponse
    {
        $query = NotificationLog::query()->with('pibDocument:id,aju_number,status');
        if ($c = $request->get('channel')) {
            $query->where('channel', $c);
        }
        if ($s = $request->get('status')) {
            $query->where('status', $s);
        }

        return response()->json($query->latest()->paginate(min((int) $request->get('per_page', 50), 200)));
    }

    /**
     * GET /v1/notification-settings — Daftar setting channel.
     */
    public function notificationSettings(): JsonResponse
    {
        return response()->json(NotificationSetting::all());
    }

    /**
     * PATCH /v1/notification-settings/{channel} — Toggle / update channel.
     */
    public function updateNotificationSettings(Request $request, string $channel): JsonResponse
    {
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'notify_normal' => ['nullable', 'boolean'],
            'notify_urgent' => ['nullable', 'boolean'],
            'target_recipient' => ['nullable', 'array'],
        ]);

        $setting = NotificationSetting::firstOrCreate(
            ['channel' => $channel],
            ['is_enabled' => true, 'notify_normal' => false, 'notify_urgent' => true],
        );
        $setting->update($data);

        return response()->json($setting->fresh());
    }
}