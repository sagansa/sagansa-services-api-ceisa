<?php

namespace App\Services;

/**
 * CNPIBK Service — CEISA 4.0 OpenAPI v2 (cnpibk-controller).
 *
 * Barang kiriman (postal items, e-commerce cross-border). Enterprise tier only.
 *
 * Dikelompokkan menjadi 5 kategori:
 *
 * 1. Kirim Data (3 endpoint):
 *    POST /cnpibk/kirim-data-cnpibk        → CN/PIBK impor
 *    POST /cnpibk/kirim-data-cnpibk-ekspor → CN ekspor
 *    POST /cnpibk/kirim-data-pkbk          → PKBK ekspor
 *
 * 2. BC 1.1 / BC 1.4 (4 endpoint):
 *    POST /cnpibk/bc11/update-bc11         → update BC 1.1
 *    POST /cnpibk/bc14/kirim-data          → kirim BC 1.4
 *    POST /cnpibk/bc14/pecah-pos           → pecah pos BC 1.4
 *    GET  /cnpibk/bc14/cek-status-bc14     → cek status BC 1.4
 *
 * 3. Billing & Referensi (4 endpoint):
 *    GET  /cnpibk/billing-konsolidasi/tarik-billing           → tarik billing
 *    GET  /cnpibk/billing-konsolidasi/tarik-billing-by-kodeBilling → by kode billing
 *    POST /cnpibk/daftar-tertentu/kirim-data                  → barang tertentu
 *    GET  /cnpibk/e-catalogue/getResponse                     → e-catalogue
 *    GET  /cnpibk/e-invoice/getResponse                       → e-invoice
 *
 * 4. Respon (6 endpoint):
 *    GET /cnpibk/respon/tarik-respon           → tarik respon
 *    GET /cnpibk/respon/tarik-respon-by-aju    → by AJU
 *    GET /cnpibk/respon/tarik-respon-by-status → by status
 *    GET /cnpibk/respon/ekspor-by-dokumen      → ekspor by dokumen
 *    GET /cnpibk/respon/ekspor-by-tanggal-daftar   → by tanggal daftar
 *    GET /cnpibk/respon/ekspor-by-tanggal-submit   → by tanggal submit
 *
 * 5. X-Ray (3 endpoint):
 *    POST /cnpibk/xray/add-foto-xray    → add foto X-ray
 *    GET  /cnpibk/xray/get-foto-xray    → get foto X-ray
 *    POST /cnpibk/xray/kirim-foto-xray  → kirim foto X-ray
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: cnpibk-controller.
 */
class CnpibkService extends CeisaBaseService
{
    // ===== 1. Kirim Data =====

    /** POST /cnpibk/kirim-data-cnpibk — Kirim CN/PIBK impor. */
    public function kirimImpor(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_kirim_impor'), $payload, 'manifes'),
            'kirimImpor',
        );
    }

    /** POST /cnpibk/kirim-data-cnpibk-ekspor — Kirim CN ekspor. */
    public function kirimEkspor(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_kirim_ekspor'), $payload, 'manifes'),
            'kirimEkspor',
        );
    }

    /** POST /cnpibk/kirim-data-pkbk — Kirim PKBK ekspor. */
    public function kirimPkbk(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_pkbk'), $payload, 'manifes'),
            'kirimPkbk',
        );
    }

    // ===== 2. BC 1.1 / BC 1.4 =====

    /** POST /cnpibk/bc11/update-bc11 — Update BC 1.1. */
    public function updateBc11(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_bc11_update'), $payload, 'manifes'),
            'updateBc11',
        );
    }

    /** POST /cnpibk/bc14/kirim-data — Kirim BC 1.4. */
    public function kirimBc14(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_bc14_kirim'), $payload, 'manifes'),
            'kirimBc14',
        );
    }

    /** POST /cnpibk/bc14/pecah-pos — Pecah pos BC 1.4. */
    public function pecahPosBc14(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_bc14_pecah_pos'), $payload, 'manifes'),
            'pecahPosBc14',
        );
    }

    /** GET /cnpibk/bc14/cek-status-bc14 — Cek status BC 1.4. */
    public function statusBc14(string $noAju): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_bc14_status'), ['noAju' => $noAju], 'manifes'),
            'statusBc14',
        );
    }

    // ===== 3. Billing & Referensi =====

    /** GET /cnpibk/billing-konsolidasi/tarik-billing — Tarik billing konsolidasi. */
    public function tarikBilling(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_billing_tarik'), [], 'manifes'),
            'tarikBilling',
        );
    }

    /** GET /cnpibk/billing-konsolidasi/tarik-billing-by-kodeBilling — By kode billing. */
    public function tarikBillingByKode(string $kodeBilling): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_billing_by_kode'), ['kodeBilling' => $kodeBilling], 'manifes'),
            'tarikBillingByKode',
        );
    }

    /** POST /cnpibk/daftar-tertentu/kirim-data — Kirim barang tertentu. */
    public function kirimDaftarTertentu(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_daftar_tertentu'), $payload, 'manifes'),
            'kirimDaftarTertentu',
        );
    }

    /** GET /cnpibk/e-catalogue/getResponse — E-catalogue. */
    public function eCatalogue(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_e_catalogue'), [], 'manifes'),
            'eCatalogue',
        );
    }

    /** GET /cnpibk/e-invoice/getResponse — E-invoice. */
    public function eInvoice(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_e_invoice'), [], 'manifes'),
            'eInvoice',
        );
    }

    // ===== 4. Respon =====

    /** GET /cnpibk/respon/tarik-respon — Tarik respon CNPIBK. */
    public function tarikRespon(string $nomorAju): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_respon_tarik'), ['nomorAju' => $nomorAju], 'manifes'),
            'tarikRespon',
        );
    }

    /** GET /cnpibk/respon/tarik-respon-by-aju — By AJU. */
    public function responByAju(string $nomorAju): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_respon_by_aju'), ['nomorAju' => $nomorAju], 'manifes'),
            'responByAju',
        );
    }

    /** GET /cnpibk/respon/tarik-respon-by-status — By status. */
    public function responByStatus(string $status): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_respon_by_status'), ['status' => $status], 'manifes'),
            'responByStatus',
        );
    }

    /** GET /cnpibk/respon/ekspor-by-dokumen — Ekspor by dokumen. */
    public function eksporByDokumen(string $nomorAju): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_ekspor_by_dokumen'), ['nomorAju' => $nomorAju], 'manifes'),
            'eksporByDokumen',
        );
    }

    /** GET /cnpibk/respon/ekspor-by-tanggal-daftar — By tanggal daftar. */
    public function eksporByTglDaftar(string $tanggal): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_ekspor_by_tgl_daftar'), ['tanggal' => $tanggal], 'manifes'),
            'eksporByTglDaftar',
        );
    }

    /** GET /cnpibk/respon/ekspor-by-tanggal-submit — By tanggal submit. */
    public function eksporByTglSubmit(string $tanggal): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_ekspor_by_tgl_submit'), ['tanggal' => $tanggal], 'manifes'),
            'eksporByTglSubmit',
        );
    }

    // ===== 5. X-Ray =====

    /** POST /cnpibk/xray/add-foto-xray — Add foto X-ray. */
    public function addFotoXray(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_xray_add'), $payload, 'manifes'),
            'addFotoXray',
        );
    }

    /** GET /cnpibk/xray/get-foto-xray — Get foto X-ray. */
    public function getFotoXray(string $id): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cnpibk_xray_get'), ['id' => $id], 'manifes'),
            'getFotoXray',
        );
    }

    /** POST /cnpibk/xray/kirim-foto-xray — Kirim foto X-ray. */
    public function kirimFotoXray(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cnpibk_xray_kirim'), $payload, 'manifes'),
            'kirimFotoXray',
        );
    }
}
