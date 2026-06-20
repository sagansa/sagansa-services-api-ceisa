@component('mail::message')
# ⚠️ NOTUL / SPTNP — PIB (BC 2.0)

@if($doc->is_underprice)
**🔴 UNDERPRICE TERDETEKSI** — Bea Cukai menetapkan nilai pabean lebih tinggi dari deklarasi.
@endif

## Detail PIB

| Field | Nilai |
|:---|:---|
| **Nomor AJU** | `{{ $doc->aju_number }}` |
| **Nomor Pendaftaran** | `{{ $doc->registration_number ?? '-' }}` |
| **Importir** | {{ $doc->importir_name ?? '-' }} |
| **NPWP** | {{ $doc->importir_npwp ?? '-' }} |
| **Status** | {{ strtoupper($doc->status) }} |

@if($notul)
## 📋 Detail NOTUL/SPTNP

| Komponen | Nominal (IDR) |
|:---|---:|
| **Nomor Surat** | {{ $notul->nomor_surat ?? '-' }} |
| **Tanggal Surat** | {{ optional($notul->tanggal_surat)->format('d M Y') ?? '-' }} |
| **HS Code** | {{ $notul->hs_code ?? '-' }} |
| **Uraian Barang** | {{ $notul->uraian_barang ?? '-' }} |
| **Nilai Deklarasi** | Rp {{ number_format($notul->nilai_deklarasi, 0, ',', '.') }} |
| **Nilai Penetapan BC** | Rp {{ number_format($notul->nilai_penetapan_bc, 0, ',', '.') }} |
| **Selisih Bea Masuk** | Rp {{ number_format($notul->selisih_bea_masuk, 0, ',', '.') }} |
| **Denda** | Rp {{ number_format($notul->denda, 0, ',', '.') }} |
| **PPN/PPH** | Rp {{ number_format($notul->ppn_pph, 0, ',', '.') }} |
| **TOTAL KEWAJIBAN** | **Rp {{ number_format($notul->total_kewajiban, 0, ',', '.') }}** |

@if($notul->rekening_ssp || $notul->due_date_ssp)
## 💳 Instruksi Pembayaran (SSP)

- **Rekening SSP:** `{{ $notul->rekening_ssp ?? '-' }}`
- **Jatuh Tempo:** {{ optional($notul->due_date_ssp)->format('d F Y') ?? '-' }}
@endif
@endif

@component('mail::button', ['url' => config('app.url')])
Lihat Detail di Dasbor
@endcomponent

---
*Email ini dikirim otomatis oleh sistem integrasi CEISA 4.0 SAGANSA.*
@endcomponent