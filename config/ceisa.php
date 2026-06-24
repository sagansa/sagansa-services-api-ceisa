<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CEISA 4.0 Gateway Mode
    |--------------------------------------------------------------------------
    | Determines which base URL the CeisaClient will use.
    | Values: 'sandbox' | 'production'
    */
    'gateway_mode' => env('CEISA_GATEWAY_MODE', 'sandbox'),

    // Catatan: `env('KEY', 'default')` mengembalikan string kosong (BUKAN default)
    // bila env di-set kosong (mis. `CEISA_GATEWAY_PRODUCTION=`). Karena itu kita
    // pakai `?:` agar empty-string dianggap unset dan fallback ke URL resmi BC.
    // Ini mencegah mode=production ter-silent-fallback ke sandbox hanya karena
    // env production dikosongkan di .env.
    'gateways' => [
        'sandbox'    => env('CEISA_GATEWAY_SANDBOX', 'https://apisdev-gw.beacukai.go.id')
                        ?: 'https://apisdev-gw.beacukai.go.id',
        'production' => env('CEISA_GATEWAY_PRODUCTION', 'https://apis-gw.beacukai.go.id')
                        ?: 'https://apis-gw.beacukai.go.id',
    ],

    /*
    |--------------------------------------------------------------------------
    | CEISA OpenAPI Version (v2 — UNIFIED base path)
    |--------------------------------------------------------------------------
    | Sejak v2, seluruh endpoint CEISA 4.0 (manifes, PIB, status, CNPIBK,
    | gate, file, referensi, dll) berada di bawah SATU base path yang sama:
    |
    |   {gateway}/v2/openapi
    |
    | Tidak lagi ada pemisahan /v1/openapi-manifes vs /v1/openapi-pib.
    | Sumber: doc/json/Export_openapi_v2_*.json (OpenAPI 3.0.1, version 2.0).
    |   server produksi = https://apis-gw.beacukai.go.id/v2/openapi
    */
    'api_version' => env('CEISA_API_VERSION', 'v2'),
    'openapi_path' => env('CEISA_OPENAPI_PATH', '/v2/openapi'),

    /*
    |--------------------------------------------------------------------------
    | CEISA H2H User Authentication API (openapi-auth v1)
    |--------------------------------------------------------------------------
    | Sumber: doc/json/Export_openapi-auth-v2.json
    |   Base URL: {gateway}/v1/openapi-auth
    |   - POST /user/login
    |   - POST /user/update-token
    |
    | Berbeda dari /v2/openapi (service endpoint), API auth user berada pada
    | base path terpisah /v1/openapi-auth.
    */
    'openapi_auth_path' => env('CEISA_OPENAPI_AUTH_PATH', '/v1/openapi-auth'),
    'auth_endpoints' => [
        'user_login'        => env('CEISA_USER_LOGIN_PATH', '/user/login'),
        'user_update_token' => env('CEISA_USER_UPDATE_TOKEN_PATH', '/user/update-token'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backward-compat: service_paths (DEPRECATED sejak v2)
    |--------------------------------------------------------------------------
    | Di v2 base URL sudah unified. Key 'manifes' & 'pib' tetap dipertahankan
    | agar pemanggilan CeisaClient->client('manifes') lama tidak break, tetapi
    | sekarang keduanya resolve ke openapi_path yang sama (empty string dari
    | sudut pandang service_paths, base path ditangani di buildServiceBaseUrl).
    |
    | Pengecualian: 'cukai' tetap punya base path terpisah (/v1/openapi-cukai)
    | karena openapi-cukai adalah API berbeda (Host to Host Cukai, mesin
    | produksi & GPS BKC) — tidak di-unify ke /v2/openapi.
    */
    'service_paths' => [
        'manifes' => env('CEISA_MANIFES_PATH', ''),
        'pib'     => env('CEISA_PIB_PATH', ''),
        // API Host to Host Cukai (BKC) — base path terpisah dari openapi v2.
        // Sumber: portal BC → openapi-cukai v1.0
        //   server produksi = https://apis-gw.beacukai.go.id/v1/openapi-cukai
        'cukai'   => env('CEISA_CUKAI_PATH', '/v1/openapi-cukai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mock Mode
    |--------------------------------------------------------------------------
    | When enabled, CeisaClient returns synthetic success responses instead
    | of calling the real gateway. Useful for local/sandbox end-to-end testing
    | when the real OpenAPI endpoint is not yet available or IP is not whitelisted.
    | All mock calls are still logged to ceisa_api_logs for traceability.
    */
    'mock_enabled' => env('CEISA_MOCK_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | OSS/RBA API URL (NIB lookup)
    |--------------------------------------------------------------------------
    | API publik OSS untuk lookup data perusahaan berdasarkan NIB.
    | Bila kosong/null, OssService pakai fallback data demo.
    | API publik OSS: https://pbcb.oss.go.id/oss/api/v1/nib
    */
    'oss_api_url' => env('OSS_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | CEISA API Endpoints v2 (path relatif terhadap {gateway}{openapi_path})
    |--------------------------------------------------------------------------
    | Sumber: doc/json/Export_openapi_v2_*.json (OpenAPI 3.0.1, version 2.0).
    | Semua endpoint FLAT di bawah /v2/openapi. Saat path berubah, cukup
    | update env tanpa ubah kode.
    */
    'endpoints' => [
        /*
        |======================================================================
        | STATUS (status-controller) — Unified status dokumen pabean
        |======================================================================
        | GET /status?idPerusahaan=        → status by NITKU (belum diambil)
        | GET /status/{nomorAju}           → status by nomorAju (history + respon)
        */
        'status_by_nitku'      => env('CEISA_STATUS_BY_NITKU_PATH', '/status'),
        'status_by_aju'        => env('CEISA_STATUS_BY_AJU_PATH', '/status/{nomorAju}'),

        /*
        |======================================================================
        | DOCUMENT (document-controller) — Kirim & cek dokumen pabean (PIB/BC)
        |======================================================================
        | POST /document?isFinal=&isRevision=              → kirim dokumen pabean
        | POST /document/check                              → cek JSON Schema
        | GET  /document/detail/{jenisDokumen}/{nomorAju}/{kodeKantor} → detail dokumen
        */
        'document_submit'      => env('CEISA_DOCUMENT_SUBMIT_PATH', '/document'),
        'document_check'       => env('CEISA_DOCUMENT_CHECK_PATH', '/document/check'),
        'document_detail'      => env('CEISA_DOCUMENT_DETAIL_PATH', '/document/detail/{jenisDokumen}/{nomorAju}/{kodeKantor}'),

        /*
        |======================================================================
        | MANIFES BC 1.1 (Inward Manifes) — v2 flat endpoint
        |======================================================================
        | GET /manifes-bc11?nama=&tglHostBl=&kodeKantor=&noHostBl=
        |     → Inward Manifes BC 1.1 (menggantikan /manifes/inward v1)
        */
        'manifes_bc11'         => env('CEISA_MANIFES_BC11_PATH', '/manifes-bc11'),

        /*
        |======================================================================
        | RESpon / PDF (respon-controller)
        |======================================================================
        | GET /respon/pdf?kodeRespon=&nomorAju=               → PDF Respon
        | GET /respon/billing?kodeBilling=                    → Billing PDF
        | GET /respon/cetak-formulir?nomorAju=                → Formulir PDF
        | GET /respon/cetak-formulir/draft?nomorAju=          → Formulir Draft PDF
        | GET /respon/cetak-formulir/final?nomorAju=          → Formulir Final PDF
        | GET /respon/npe-bc33/{kodeDokumen}/{tanggalDokumen}?kodeGudang= → NPE BC 3.3
        | GET /download-respon?path=                          → Download PDF Respon
        */
        'respon_pdf'             => env('CEISA_RESPON_PDF_PATH', '/respon/pdf'),
        'respon_billing'         => env('CEISA_RESPON_BILLING_PATH', '/respon/billing'),
        'respon_cetak_formulir'  => env('CEISA_RESPON_CETAK_FORMULIR_PATH', '/respon/cetak-formulir'),
        'respon_formulir_draft'  => env('CEISA_RESPON_FORMULIR_DRAFT_PATH', '/respon/cetak-formulir/draft'),
        'respon_formulir_final'  => env('CEISA_RESPON_FORMULIR_FINAL_PATH', '/respon/cetak-formulir/final'),
        'respon_npe_bc33'        => env('CEISA_RESPON_NPE_BC33_PATH', '/respon/npe-bc33/{kodeDokumen}/{tanggalDokumen}'),
        'download_respon'        => env('CEISA_DOWNLOAD_RESPON_PATH', '/download-respon'),

        /*
        |======================================================================
        | GATE (Gate In/Out TPB Mandiri)
        |======================================================================
        | GET  /gate/dokumen?nomorAju=                  → data dokumen pabean gate-in
        | POST /gate/kemasan/gate-in                    → gate-in kemasan
        | POST /gate/kemasan/gate-out                   → gate-out kemasan
        | POST /gate/kontainer/gate-in                  → gate-in kontainer
        | POST /gate/rekam-hasil-bongkar                → rekam hasil bongkar (sesuai)
        | POST /gate/rekam-hasil-stuffing               → rekam hasil stuffing (sesuai)
        */
        'gate_dokumen'        => env('CEISA_GATE_DOKUMEN_PATH', '/gate/dokumen'),
        'gate_kemasan_in'     => env('CEISA_GATE_KEMASAN_IN_PATH', '/gate/kemasan/gate-in'),
        'gate_kemasan_out'    => env('CEISA_GATE_KEMASAN_OUT_PATH', '/gate/kemasan/gate-out'),
        'gate_kontainer_in'   => env('CEISA_GATE_KONTAINER_IN_PATH', '/gate/kontainer/gate-in'),
        'gate_rekam_bongkar'  => env('CEISA_GATE_REKAM_BONGKAR_PATH', '/gate/rekam-hasil-bongkar'),
        'gate_rekam_stuffing' => env('CEISA_GATE_REKAM_STUFFING_PATH', '/gate/rekam-hasil-stuffing'),

        /*
        |======================================================================
        | FILE (file-controller) — Upload file barang / dokumen / NPD
        |======================================================================
        | POST /file/barang              → upload file barang
        | POST /file/dokumen             → upload file dokumen
        | POST /file/upload-dokap-npd    → upload file DOKAP NPD
        */
        'file_barang'           => env('CEISA_FILE_BARANG_PATH', '/file/barang'),
        'file_dokumen'          => env('CEISA_FILE_DOKUMEN_PATH', '/file/dokumen'),
        'file_upload_dokap_npd' => env('CEISA_FILE_UPLOAD_DOKAP_NPD_PATH', '/file/upload-dokap-npd'),

        /*
        |======================================================================
        | KURS & PUNGUTAN
        |======================================================================
        | GET /kurs/{kodeValuta}                    → Kurs Valuta (NDPBM)
        | GET /generate-pungutan/20/{nomorAju}      → Generate pungutan BC 2.0
        */
        'kurs'                   => env('CEISA_KURS_PATH', '/kurs/{kodeValuta}'),
        'generate_pungutan_bc20' => env('CEISA_GENERATE_PUNGUTAN_PATH', '/generate-pungutan/20/{nomorAju}'),

        /*
        |======================================================================
        | REFERENSI
        |======================================================================
        | GET /referensi/pelabuhan-dalam-negeri/{kodeKantor}  → pelabuhan dalam negeri
        | GET /referensi/pelabuhan-luar-negeri/{kata}         → pelabuhan luar negeri
        | GET /referensi/tps-gudang/{kodeKantor}              → kode gudang TPS
        */
        'ref_pelabuhan_dalam' => env('CEISA_REF_PELABUHAN_DALAM_PATH', '/referensi/pelabuhan-dalam-negeri/{kodeKantor}'),
        'ref_pelabuhan_luar'  => env('CEISA_REF_PELABUHAN_LUAR_PATH', '/referensi/pelabuhan-luar-negeri/{kata}'),
        'ref_tps_gudang'      => env('CEISA_REF_TPS_GUDANG_PATH', '/referensi/tps-gudang/{kodeKantor}'),
        // Kantor Pabean (kode kantor Bea Cukai, 6 digit).
        // Dipakai untuk lookup dropdown "Kantor Pabean" di form PIB/PEB.
        // Endpoint BC: GET /referensi/kantor
        'ref_kantor'          => env('CEISA_REF_KANTOR_PATH', '/referensi/kantor'),

        /*
        |======================================================================
        | E-MONITORING (H@H Service) — Laporan inventori & mutasi
        |======================================================================
        */
        'e_monitoring_status'    => env('CEISA_E_MONITORING_STATUS_PATH', '/e-monitoring/laporan/get-status-Monitoring-laporan'),
        'e_monitoring_inventori' => env('CEISA_E_MONITORING_INVENTORI_PATH', '/e-monitoring/laporan/inventori'),
        'e_monitoring_mutasi'    => env('CEISA_E_MONITORING_MUTASI_PATH', '/e-monitoring/laporan/mutasi'),

        /*
        |======================================================================
        | CNPIBK (CN / PIBK — Barang Kiriman)
        |======================================================================
        | POST /cnpibk/kirim-data-cnpibk                  → kirim CN/PIBK impor
        | POST /cnpibk/kirim-data-cnpibk-ekspor           → kirim CN ekspor
        | POST /cnpibk/kirim-data-pkbk                    → kirim PKBK ekspor
        | POST /cnpibk/bc11/update-bc11                   → update BC 1.1
        | POST /cnpibk/bc14/kirim-data                    → kirim BC 1.4
        | POST /cnpibk/bc14/pecah-pos                     → pecah pos BC 1.4
        | GET  /cnpibk/bc14/cek-status-bc14?noAju=        → cek status BC 1.4
        | GET  /cnpibk/billing-konsolidasi/tarik-billing  → tarik billing konsolidasi
        | GET  /cnpibk/billing-konsolidasi/tarik-billing-by-kodeBilling?kodeBilling=
        | POST /cnpibk/daftar-tertentu/kirim-data         → barang tertentu
        | GET  /cnpibk/e-catalogue/getResponse            → e-catalogue
        | GET  /cnpibk/e-invoice/getResponse              → e-invoice
        | GET  /cnpibk/respon/tarik-respon?nomorAju=       → tarik respon CNPIBK
        | GET  /cnpibk/respon/tarik-respon-by-aju?nomorAju=
        | GET  /cnpibk/respon/tarik-respon-by-status?status=
        | GET  /cnpibk/respon/ekspor-by-dokumen?nomorAju=
        | GET  /cnpibk/respon/ekspor-by-tanggal-daftar?tanggal=
        | GET  /cnpibk/respon/ekspor-by-tanggal-submit?tanggal=
        | POST /cnpibk/xray/add-foto-xray                 → add foto X-ray
        | GET  /cnpibk/xray/get-foto-xray                 → get foto X-ray
        | POST /cnpibk/xray/kirim-foto-xray               → kirim foto X-ray
        */
        'cnpibk_kirim_impor'         => env('CEISA_CNPIBK_KIRIM_IMPOR_PATH', '/cnpibk/kirim-data-cnpibk'),
        'cnpibk_kirim_ekspor'        => env('CEISA_CNPIBK_KIRIM_EKSPOR_PATH', '/cnpibk/kirim-data-cnpibk-ekspor'),
        'cnpibk_pkbk'                => env('CEISA_CNPIBK_PKBK_PATH', '/cnpibk/kirim-data-pkbk'),
        'cnpibk_bc11_update'         => env('CEISA_CNPIBK_BC11_UPDATE_PATH', '/cnpibk/bc11/update-bc11'),
        'cnpibk_bc14_kirim'          => env('CEISA_CNPIBK_BC14_KIRIM_PATH', '/cnpibk/bc14/kirim-data'),
        'cnpibk_bc14_pecah_pos'      => env('CEISA_CNPIBK_BC14_PECAH_POS_PATH', '/cnpibk/bc14/pecah-pos'),
        'cnpibk_bc14_status'         => env('CEISA_CNPIBK_BC14_STATUS_PATH', '/cnpibk/bc14/cek-status-bc14'),
        'cnpibk_billing_tarik'       => env('CEISA_CNPIBK_BILLING_TARIK_PATH', '/cnpibk/billing-konsolidasi/tarik-billing'),
        'cnpibk_billing_by_kode'     => env('CEISA_CNPIBK_BILLING_BY_KODE_PATH', '/cnpibk/billing-konsolidasi/tarik-billing-by-kodeBilling'),
        'cnpibk_daftar_tertentu'     => env('CEISA_CNPIBK_DAFAR_TERTENTU_PATH', '/cnpibk/daftar-tertentu/kirim-data'),
        'cnpibk_e_catalogue'         => env('CEISA_CNPIBK_E_CATALOGUE_PATH', '/cnpibk/e-catalogue/getResponse'),
        'cnpibk_e_invoice'           => env('CEISA_CNPIBK_E_INVOICE_PATH', '/cnpibk/e-invoice/getResponse'),
        'cnpibk_respon_tarik'        => env('CEISA_CNPIBK_RESPON_TARIK_PATH', '/cnpibk/respon/tarik-respon'),
        'cnpibk_respon_by_aju'       => env('CEISA_CNPIBK_RESPON_BY_AJU_PATH', '/cnpibk/respon/tarik-respon-by-aju'),
        'cnpibk_respon_by_status'    => env('CEISA_CNPIBK_RESPON_BY_STATUS_PATH', '/cnpibk/respon/tarik-respon-by-status'),
        'cnpibk_ekspor_by_dokumen'   => env('CEISA_CNPIBK_EKSPOR_BY_DOKUMEN_PATH', '/cnpibk/respon/ekspor-by-dokumen'),
        'cnpibk_ekspor_by_tgl_daftar'=> env('CEISA_CNPIBK_EKSPOR_BY_TGL_DAFTAR_PATH', '/cnpibk/respon/ekspor-by-tanggal-daftar'),
        'cnpibk_ekspor_by_tgl_submit'=> env('CEISA_CNPIBK_EKSPOR_BY_TGL_SUBMIT_PATH', '/cnpibk/respon/ekspor-by-tanggal-submit'),
        'cnpibk_xray_add'            => env('CEISA_CNPIBK_XRAY_ADD_PATH', '/cnpibk/xray/add-foto-xray'),
        'cnpibk_xray_get'            => env('CEISA_CNPIBK_XRAY_GET_PATH', '/cnpibk/xray/get-foto-xray'),
        'cnpibk_xray_kirim'          => env('CEISA_CNPIBK_XRAY_KIRIM_PATH', '/cnpibk/xray/kirim-foto-xray'),

        /*
        |======================================================================
        | CONSUMER WEBHOOK (check-log)
        |======================================================================
        */
        'consumer_webhook_check' => env('CEISA_CONSUMER_WEBHOOK_CHECK_PATH', '/consumer-webhook/check-log'),

        /*
        |======================================================================
        | CUKAI (openapi-cukai v1.0 — Host to Host Cukai / BKC)
        |======================================================================
        | API terpisah dari /v2/openapi. Base path: /v1/openapi-cukai
        | (di-resolve via service_paths.cukai di buildServiceBaseUrl).
        |
        | Fokus: monitoring & pelaporan mesin produksi Barang Kena Cukai
        | (rokok, MMEA/alkohol, mirasantisa) + tracking GPS lokasi mesin.
        |
        | Sumber: portal BC → openapi-cukai v1.0 (openapicukai_openapi.json)
        |   server produksi = https://apis-gw.beacukai.go.id/v1/openapi-cukai
        |
        | 13 endpoint dikelompokkan 4 kategori:
        |   GPS        → 3 endpoint (list, by-id, create)
        |   Mesin      → 4 endpoint (list, create, update, delete)
        |   Produksi   → 5 endpoint (list + 4 create batang/kemasan single/batch)
        |   Referensi  → 4 endpoint (jenis-mesin, tipe-mesin, kondisi, status-kepemilikan)
        */
        // GPS — tracking lokasi mesin produksi
        'cukai_gps_list'           => env('CEISA_CUKAI_GPS_LIST_PATH', '/h2h/gps'),
        'cukai_gps_by_id'          => env('CEISA_CUKAI_GPS_BY_ID_PATH', '/h2h/gps/{id}'),
        // Mesin — CRUD mesin produksi BKC
        'cukai_mesin_list'         => env('CEISA_CUKAI_MESIN_LIST_PATH', '/h2h/mesin'),
        'cukai_mesin_by_id'        => env('CEISA_CUKAI_MESIN_BY_ID_PATH', '/h2h/mesin/{id}'),
        // Produksi — laporan produksi (batang = per keping, kemasan = per pack)
        'cukai_produksi_list'      => env('CEISA_CUKAI_PRODUKSI_LIST_PATH', '/h2h/mesin/produksi'),
        'cukai_produksi_batang'    => env('CEISA_CUKAI_PRODUKSI_BATANG_PATH', '/h2h/mesin/produksi/batang'),
        'cukai_produksi_batang_batch' => env('CEISA_CUKAI_PRODUKSI_BATANG_BATCH_PATH', '/h2h/mesin/produksi/batang/batch'),
        'cukai_produksi_kemasan'   => env('CEISA_CUKAI_PRODUKSI_KEMASAN_PATH', '/h2h/mesin/produksi/kemasan'),
        'cukai_produksi_kemasan_batch' => env('CEISA_CUKAI_PRODUKSI_KEMASAN_BATCH_PATH', '/h2h/mesin/produksi/kemasan/batch'),
        // Referensi — master data untuk dropdown form mesin
        'cukai_ref_jenis_mesin'    => env('CEISA_CUKAI_REF_JENIS_MESIN_PATH', '/h2h/referensi/jenis-mesin'),
        'cukai_ref_tipe_mesin'     => env('CEISA_CUKAI_REF_TIPE_MESIN_PATH', '/h2h/referensi/tipe-mesin'),
        'cukai_ref_kondisi'        => env('CEISA_CUKAI_REF_KONDISI_PATH', '/h2h/referensi/kondisi'),
        'cukai_ref_status_kepemilikan' => env('CEISA_CUKAI_REF_STATUS_KEPEMILIKAN_PATH', '/h2h/referensi/status-kepemilikan'),

        /*
        |======================================================================
        | LEGACY ALIASES (v1 → v2 mapping for backward compat in services)
        |======================================================================
        | Service-service lama (ManifesService, PibSubmissionService) masih
        | memakai key lama. Alias di bawah mengarahkan ke endpoint v2 baru.
        | Preferensi: gunakan endpoint baru (status_by_aju, document_submit,
        | manifes_bc11, respon_pdf, dll) di kode baru.
        */
        'manifes_status'     => env('CEISA_MANIFES_STATUS_PATH', '/status/{nomorAju}'),
        'manifes_inward'     => env('CEISA_MANIFES_INWARD_PATH', '/manifes-bc11'),
        'manifes_respon_pdf' => env('CEISA_MANIFES_RESPON_PDF_PATH', '/respon/pdf'),
        'pib_submit'         => env('CEISA_PIB_SUBMIT_PATH', '/document'),
        'pib_status'         => env('CEISA_PIB_STATUS_PATH', '/status/{nomorAju}'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth Header (VERIFIED from OpenAPI Portal)
    |--------------------------------------------------------------------------
    | Gateway BC memakai DUA lapis auth:
    |   1) API Key via header `beacukai-api-key` (APIKEY policy)
    |   2) OAuth2 Bearer token via header `Authorization: Bearer <token>`
    | Dikonfirmasi dari metadata API: policies = beacukai-api-key + oauth2,
    | dan endpoint.apiKeyHeader = "beacukai-api-key".
    */
    'auth' => [
        'api_key_header' => env('CEISA_API_KEY_HEADER', 'beacukai-api-key'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth2 (Client Credentials Grant untuk Bearer token)
    |--------------------------------------------------------------------------
    | Digunakan CeisaOAuthService untuk meminta access_token (Bearer).
    | token_url & client_credentials didapat saat onboarding aplikasi di portal BC.
    | Default mengikuti pola WSO2 API Manager (sandbox/production terpisah).
    */
    'oauth' => [
        'sandbox' => [
            'token_url'     => env('CEISA_OAUTH_SANDBOX_TOKEN_URL', 'https://apisdev-gw.beacukai.go.id/oauth2/token'),
            'client_id'     => env('CEISA_OAUTH_SANDBOX_CLIENT_ID', ''),
            'client_secret' => env('CEISA_OAUTH_SANDBOX_CLIENT_SECRET', ''),
        ],
        'production' => [
            'token_url'     => env('CEISA_OAUTH_PRODUCTION_TOKEN_URL', 'https://apis-gw.beacukai.go.id/oauth2/token'),
            'client_id'     => env('CEISA_OAUTH_PRODUCTION_CLIENT_ID', ''),
            'client_secret' => env('CEISA_OAUTH_PRODUCTION_CLIENT_SECRET', ''),
        ],
        // Grant type & scope (default WSO2 client_credentials; scope kosong = default scope)
        'grant_type' => env('CEISA_OAUTH_GRANT_TYPE', 'client_credentials'),
        'scope'      => env('CEISA_OAUTH_SCOPE', ''),
        // Safety margin (detik) sebelum token expire untuk trigger refresh
        'token_refresh_margin' => (int) env('CEISA_OAUTH_TOKEN_REFRESH_MARGIN', 60),
        // Cache TTL untuk token valid (detik). Default ikut expires_in dari response BC.
        'token_cache_ttl' => (int) env('CEISA_OAUTH_TOKEN_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Credentials (fallback if DB ceisa_credentials empty)
    |--------------------------------------------------------------------------
    | In production these are stored encrypted in the `ceisa_credentials` table.
    */
    'application_id' => env('CEISA_APPLICATION_ID', ''),
    'api_key' => env('CEISA_API_KEY', ''),
    // OAuth2 client credentials (fallback jika tidak ada di DB)
    'client_id' => env('CEISA_OAUTH_CLIENT_ID', ''),
    'client_secret' => env('CEISA_OAUTH_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */
    'webhook_url' => env('CEISA_WEBHOOK_URL', 'https://api-ceisa.sagansa.id/v1/ceisa-webhook'),
    'webhook_secret' => env('CEISA_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP client & retry tuning (NFR: retry every 5 minutes, max 3x)
    |--------------------------------------------------------------------------
    */
    'http_timeout' => (int) env('CEISA_HTTP_TIMEOUT', 30),
    'connect_timeout' => (int) env('CEISA_CONNECT_TIMEOUT', 10),
    'submit_tries' => (int) env('CEISA_SUBMIT_TRIES', 3),
    'submit_backoff' => (int) env('CEISA_SUBMIT_BACKOFF', 300),

    /*
    |--------------------------------------------------------------------------
    | Test/health path untuk cek koneksi gateway (Fase 2)
    |--------------------------------------------------------------------------
    | Path yang di-hit oleh CeisaClient::ping(). WAJIB berupa path API nyata
    | (bukan '/') karena gateway menutup koneksi tanpa respons HTTP untuk root.
    | Setiap HTTP response code (termasuk 401/403/404) dianggap "reachable".
    |
    | Sejak v2, default menggunakan endpoint status unified. Akan return
    | 401/404 tetapi membuktikan gateway reachable + IP whitelist.
    */
    'test_path' => env('CEISA_TEST_PATH', '/v2/openapi/status/ping-test'),

    /*
    |--------------------------------------------------------------------------
    | Status → urgency classification (PRD 2.2)
    |--------------------------------------------------------------------------
    */
    'urgency' => [
        'normal' => ['AJU', 'HIJAU', 'SPPB'],
        'urgent' => ['MERAH', 'NOTUL', 'SPTNP', 'DENDA', 'SSP', 'SPP'],
    ],
];