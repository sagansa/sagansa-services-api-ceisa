<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom untuk PIB 9-step wizard (step 1: Data Umum).
 *
 * Field-field ini adalah enum tetap dari BC yang tidak disediakan via
 * endpoint referensi (lihat lib/enums.ts di frontend untuk nilainya):
 *   - jenis_pib        → 1=Baru, 2=Tambahan, 3=Pembatalan, 4=Penggantian
 *   - jenis_impor      → 1-8 (IKDK impor/ekspor)
 *   - cara_pembayaran  → 1-8 (tunai, transfer, kredit, bebas bea, dll)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pib_documents', function (Blueprint $table) {
            $table->string('jenis_pib', 4)->nullable()->after('kantor_pabean');
            $table->string('jenis_impor', 4)->nullable()->after('jenis_pib');
            $table->string('cara_pembayaran', 4)->nullable()->after('jenis_impor');
        });
    }

    public function down(): void
    {
        Schema::table('pib_documents', function (Blueprint $table) {
            $table->dropColumn(['jenis_pib', 'jenis_impor', 'cara_pembayaran']);
        });
    }
};
