<?php

namespace App\Services;

/**
 * Gate Service — CEISA 4.0 OpenAPI v2 (gate-controller).
 *
 * Manajemen gate-in/gate-out kemasan & kontainer di Tempat Penyimpanan
 * Sementara (TPB) Mandiri.
 *
 *   GET  /gate/dokumen?nomorAju=          → data dokumen pabean gate-in
 *   POST /gate/kemasan/gate-in            → gate-in kemasan
 *   POST /gate/kemasan/gate-out           → gate-out kemasan
 *   POST /gate/kontainer/gate-in          → gate-in kontainer
 *   POST /gate/rekam-hasil-bongkar        → rekam hasil bongkar
 *   POST /gate/rekam-hasil-stuffing       → rekam hasil stuffing
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: gate-controller.
 */
class GateService extends CeisaBaseService
{
    /** GET /gate/dokumen — Data dokumen pabean untuk gate-in. */
    public function dokumen(string $nomorAju): array
    {
        $path = $this->endpoint('gate_dokumen');
        $response = $this->client->get($path, ['nomorAju' => $nomorAju], 'manifes');

        return $this->wrapProxy($response, 'dokumen');
    }

    /** POST /gate/kemasan/gate-in — Gate-in kemasan. */
    public function kemasanIn(array $payload): array
    {
        $path = $this->endpoint('gate_kemasan_in');
        $response = $this->client->post($path, $payload, 'manifes');

        return $this->wrapProxy($response, 'kemasanIn');
    }

    /** POST /gate/kemasan/gate-out — Gate-out kemasan. */
    public function kemasanOut(array $payload): array
    {
        $path = $this->endpoint('gate_kemasan_out');
        $response = $this->client->post($path, $payload, 'manifes');

        return $this->wrapProxy($response, 'kemasanOut');
    }

    /** POST /gate/kontainer/gate-in — Gate-in kontainer. */
    public function kontainerIn(array $payload): array
    {
        $path = $this->endpoint('gate_kontainer_in');
        $response = $this->client->post($path, $payload, 'manifes');

        return $this->wrapProxy($response, 'kontainerIn');
    }

    /** POST /gate/rekam-hasil-bongkar — Rekam hasil bongkar. */
    public function rekamBongkar(array $payload): array
    {
        $path = $this->endpoint('gate_rekam_bongkar');
        $response = $this->client->post($path, $payload, 'manifes');

        return $this->wrapProxy($response, 'rekamBongkar');
    }

    /** POST /gate/rekam-hasil-stuffing — Rekam hasil stuffing. */
    public function rekamStuffing(array $payload): array
    {
        $path = $this->endpoint('gate_rekam_stuffing');
        $response = $this->client->post($path, $payload, 'manifes');

        return $this->wrapProxy($response, 'rekamStuffing');
    }
}
