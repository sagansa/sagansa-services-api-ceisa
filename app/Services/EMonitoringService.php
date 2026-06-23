<?php

namespace App\Services;

/**
 * E-Monitoring Service — CEISA 4.0 OpenAPI v2 (e-monitoring-controller).
 *
 * Laporan inventori & mutasi barang di gudang TPB (H@H Service).
 * Enterprise tier only.
 *
 *   GET /e-monitoring/laporan/get-status-Monitoring-laporan → status laporan
 *   GET /e-monitoring/laporan/inventori                     → laporan inventori
 *   GET /e-monitoring/laporan/mutasi                        → laporan mutasi
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: e-monitoring-controller.
 */
class EMonitoringService extends CeisaBaseService
{
    /** GET /e-monitoring/laporan/get-status-Monitoring-laporan — Status laporan. */
    public function statusLaporan(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('e_monitoring_status'), [], 'manifes'),
            'statusLaporan',
        );
    }

    /** GET /e-monitoring/laporan/inventori — Laporan inventori TPB. */
    public function inventori(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('e_monitoring_inventori'), [], 'manifes'),
            'inventori',
        );
    }

    /** GET /e-monitoring/laporan/mutasi — Laporan mutasi barang TPB. */
    public function mutasi(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('e_monitoring_mutasi'), [], 'manifes'),
            'mutasi',
        );
    }
}
