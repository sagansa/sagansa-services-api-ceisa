<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom Entitas untuk PIB step 2.
 *
 * 5 role entitas (sesuai CEISA 4.0):
 *   1. Importir     — NITKU, nama, alamat, negara
 *   2. Pemilik Barang — NIB (lookup OSS), nama, alamat
 *   3. NPWP Pemusatan — NIB (lookup OSS), nama, alamat
 *   4. Pengirim     — nama, alamat, negara
 *   5. Penjual      — nama, alamat, negara
 *
 * Disimpan sebagai JSON column agar fleksibel (bisa multiple entitas
 * per role di masa depan, tanpa schema change).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pib_documents', function (Blueprint $table) {
            // Importir (role 1) — NITKU didapat dari BC user profile saat login.
            $table->string('importir_nitku', 32)->nullable()->after('importir_npwp');
            $table->string('importir_alamat', 500)->nullable()->after('importir_name');
            $table->string('importir_negara', 4)->nullable()->after('importir_alamat');

            // Pemilik Barang (role 3) — NIB lookup OSS.
            $table->string('pemilik_nib', 32)->nullable()->after('importir_negara');
            $table->string('pemilik_nama', 255)->nullable()->after('pemilik_nib');
            $table->string('pemilik_alamat', 500)->nullable()->after('pemilik_nama');

            // NPWP Pemusatan (role 4) — NIB lookup OSS.
            $table->string('pemusatan_nib', 32)->nullable()->after('pemilik_alamat');
            $table->string('pemusatan_nama', 255)->nullable()->after('pemusatan_nib');
            $table->string('pemusatan_alamat', 500)->nullable()->after('pemusatan_nama');

            // Pengirim / Consignor (role 5).
            $table->string('pengirim_nama', 255)->nullable()->after('pemusatan_alamat');
            $table->string('pengirim_alamat', 500)->nullable()->after('pengirim_nama');
            $table->string('pengirim_negara', 4)->nullable()->after('pengirim_alamat');

            // Penjual / Seller (role 6).
            $table->string('penjual_nama', 255)->nullable()->after('pengirim_negara');
            $table->string('penjual_alamat', 500)->nullable()->after('penjual_nama');
            $table->string('penjual_negara', 4)->nullable()->after('penjual_alamat');
        });
    }

    public function down(): void
    {
        Schema::table('pib_documents', function (Blueprint $table) {
            $table->dropColumn([
                'importir_nitku', 'importir_alamat', 'importir_negara',
                'pemilik_nib', 'pemilik_nama', 'pemilik_alamat',
                'pemusatan_nib', 'pemusatan_nama', 'pemusatan_alamat',
                'pengirim_nama', 'pengirim_alamat', 'pengirim_negara',
                'penjual_nama', 'penjual_alamat', 'penjual_negara',
            ]);
        });
    }
};
