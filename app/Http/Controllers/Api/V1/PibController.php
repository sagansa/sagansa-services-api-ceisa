<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\SubmitPibJob;
use App\Models\PibDocument;
use App\Services\PibSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fase 3 (submit) + Fase 6 (list/detail PIB & NOTUL) + Wizard (draft/update).
 */
class PibController extends Controller
{
    /**
     * Wizard Step 0 — POST /v1/pib/draft
     *
     * Auto-create PIB draft dengan AJU auto-generated.
     * Kode kantor diambil dari credentials aktif (ceisa_credentials).
     *
     * Dipanggil saat user buka PIB submit screen pertama kali.
     */
    public function draft(Request $request): JsonResponse
    {
        // Kode kantor: belum ada kolom kode_kantor di credentials table.
        // Pakai env override (CEISA_DEFAULT_KANTOR) atau fallback "000000".
        // TODO: tambah kolom kode_kantor ke ceisa_credentials saat UI
        // credentials mendukung input kode kantor BC user.
        $kodeKantor = (string) (config('ceisa.default_kantor') ?? env('CEISA_DEFAULT_KANTOR', '000000'));

        // Generate AJU CEISA 4.0: KANTOR(6) + 07 + F + 70 + YYYYMMDD + SEQ(6)
        $ajuNumber = $this->generateAjuNumber($kodeKantor);

        $pib = PibDocument::create([
            'aju_number'   => $ajuNumber,
            'kantor_pabean' => $kodeKantor,
            'status'       => 'draft',
        ]);

        return ApiResponse::success($pib->fresh(), 'PIB draft created', 201);
    }

    /**
     * Wizard — PATCH /v1/pib/{id}
     *
     * Partial update PIB draft untuk auto-save wizard.
     * Hanya field yang dikirim yang di-update.
     */
    public function updateDraft(int $id, Request $request): JsonResponse
    {
        $pib = PibDocument::find($id);
        if (!$pib) {
            return ApiResponse::error('PIB not found', 404);
        }

        // Validasi field yang boleh di-update via wizard.
        $data = $request->validate([
            'pelabuhan_muat'   => ['nullable', 'string', 'max:32'],
            'pelabuhan_bongkar'=> ['nullable', 'string', 'max:32'],
            'jenis_pib'        => ['nullable', 'string', 'max:4'],
            'jenis_impor'      => ['nullable', 'string', 'max:4'],
            'cara_pembayaran'  => ['nullable', 'string', 'max:4'],
            'jenis_transaksi'  => ['nullable', 'string', 'max:16'],
            // Entitas (step 2).
            'importir_npwp'    => ['nullable', 'string', 'max:32'],
            'importir_nitku'   => ['nullable', 'string', 'max:32'],
            'importir_name'    => ['nullable', 'string', 'max:255'],
            'importir_alamat'  => ['nullable', 'string', 'max:500'],
            'importir_negara'  => ['nullable', 'string', 'max:4'],
            'pemilik_nib'      => ['nullable', 'string', 'max:32'],
            'pemilik_nama'     => ['nullable', 'string', 'max:255'],
            'pemilik_alamat'   => ['nullable', 'string', 'max:500'],
            'pemusatan_nib'    => ['nullable', 'string', 'max:32'],
            'pemusatan_nama'   => ['nullable', 'string', 'max:255'],
            'pemusatan_alamat' => ['nullable', 'string', 'max:500'],
            'pengirim_nama'    => ['nullable', 'string', 'max:255'],
            'pengirim_alamat'  => ['nullable', 'string', 'max:500'],
            'pengirim_negara'  => ['nullable', 'string', 'max:4'],
            'penjual_nama'     => ['nullable', 'string', 'max:255'],
            'penjual_alamat'   => ['nullable', 'string', 'max:500'],
            'penjual_negara'   => ['nullable', 'string', 'max:4'],
            'sarana_angkut'    => ['nullable', 'string', 'max:64'],
            'aju_number'       => ['nullable', 'string', 'max:64'],
        ]);

        // Filter null values — hanya update field yang benar-benar dikirim.
        $updateData = array_filter($data, fn ($v) => $v !== null);
        $pib->update($updateData);

        return ApiResponse::success($pib->fresh(), 'PIB draft updated');
    }

    /**
     * Generate nomor AJU CEISA 4.0.
     *
     * Format: KANTOR(6) + KODE_DOK(07) + FUNGSI(F) + PEA(70) + YYYYMMDD(8) + SEQ(6) = 25 digit.
     *
     * Sequence: ambil count PIB untuk kantor+tanggal sebagai basis (inkremental).
     */
    private function generateAjuNumber(string $kodeKantor): string
    {
        $kantor = str_pad(substr($kodeKantor, 0, 6), 6, '0', STR_PAD_LEFT);
        $today = now()->format('Ymd');

        // Sequence: count existing PIB untuk kantor+tanggal + 1.
        // Lebih presisi dari random — tapi tetap bukan sequence BC resmi
        // (BC assign nomor final saat submit diterima).
        $prefix = "{$kantor}07F70{$today}";
        $count = PibDocument::where('aju_number', 'like', "{$prefix}%")->count();
        $seq = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);

        return "{$prefix}{$seq}";
    }

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