<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SubmitPibJob;
use App\Models\PibDocument;
use App\Services\PibSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fase 3 (submit) + Fase 6 (list/detail PIB & NOTUL).
 */
class PibController extends Controller
{
    /**
     * Fase 3 — POST /v1/pib/submit
     * Terima data PIB dari ERP, enqueue async job.
     */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'aju_number' => ['required', 'string', 'max:64'],
            'kantor_pabean' => ['nullable', 'string', 'max:16'],
            'importir_npwp' => ['nullable', 'string', 'max:32'],
            'importir_name' => ['nullable', 'string', 'max:255'],
            'ppjk_npwp' => ['nullable', 'string', 'max:32'],
            'jenis_transaksi' => ['nullable', 'string', 'max:16'],
            'sarana_angkut' => ['nullable', 'string', 'max:64'],
            'pelabuhan_muat' => ['nullable', 'string', 'max:16'],
            'pelabuhan_bongkar' => ['nullable', 'string', 'max:16'],
            'valuation_declaration' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.seri' => ['required_with:items', 'integer', 'min:1'],
            'items.*.hs_code' => ['nullable', 'string', 'max:32'],
            'items.*.uraian_barang' => ['nullable', 'string', 'max:500'],
            'items.*.negara_asal' => ['nullable', 'string', 'max:4'],
            'items.*.jumlah_satuan' => ['nullable', 'numeric', 'min:0'],
            'items.*.satuan' => ['nullable', 'string', 'max:16'],
            'items.*.nilai_cif' => ['nullable', 'numeric', 'min:0'],
            'items.*.bea_masuk' => ['nullable', 'numeric', 'min:0'],
            'items.*.ppn' => ['nullable', 'numeric', 'min:0'],
            'items.*.pph' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Create PIB document + items
        $pib = PibDocument::create(collect($data)->except('items')->toArray());

        if (!empty($data['items'])) {
            $pib->items()->createMany($data['items']);
        }

        // Enqueue async submission.
        //
        // Catatan: ketika QUEUE_CONNECTION=sync, dispatch() menjalankan job
        // seketika dan setiap exception dari job akan terlempar ke sini.
        // Kita tangkap agar endpoint tetap mengembalikan 202 (document sudah
        // tersimpan). Status terbaru di-reflect dari model yang sudah di-refresh.
        $syncError = null;
        try {
            SubmitPibJob::dispatch($pib->id);
        } catch (\Throwable $e) {
            $syncError = $e->getMessage();
            Log::warning('PIB submit job failed synchronously', [
                'pib_id' => $pib->id,
                'error' => $syncError,
            ]);
        }

        // Refresh untuk mendapatkan status terbaru yang mungkin diubah job.
        $pib->refresh();

        $payload = [
            'message' => $syncError === null
                ? 'PIB enqueued for submission.'
                : 'PIB saved, but synchronous submission failed. Use retry endpoint to resend.',
            'pib_id' => $pib->id,
            'aju_number' => $pib->aju_number,
            'status' => $pib->status,
        ];

        if ($syncError !== null) {
            $payload['submission_error'] = $syncError;
            $payload['mock_hint'] = 'Enable CEISA_MOCK_ENABLED=true for local end-to-end testing, or set the real OpenAPI endpoint via CEISA_PIB_SUBMIT_PATH.';
        }

        return response()->json($payload, 202);
    }

    /**
     * Fase 3 — Manual retry.
     */
    public function retry(int $id, PibSubmissionService $service): JsonResponse
    {
        $result = $service->retry($id);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Fase 6 — GET /v1/pib (list with filters + pagination).
     */
    public function index(Request $request): JsonResponse
    {
        $query = PibDocument::query()
            ->with(['latestNotul']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($request->boolean('underprice')) {
            $query->where('is_underprice', true);
        }
        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    /**
     * Fase 6 — GET /v1/pib/{id} (detail).
     */
    public function show(int $id): JsonResponse
    {
        $pib = PibDocument::with([
            'items',
            'supportingDocs',
            'notulDocuments',
            'statusHistories' => fn ($q) => $q->latest()->limit(20),
        ])->findOrFail($id);

        return response()->json($pib);
    }

    /**
     * Fase 6 — GET /v1/pib/notul (list NOTUL/underprice).
     */
    public function notul(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);

        $pibs = PibDocument::underprice()
            ->with('latestNotul')
            ->latest()
            ->paginate($perPage);

        return response()->json($pibs);
    }
}