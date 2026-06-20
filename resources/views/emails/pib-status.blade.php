@component('mail::message')
# PIB (BC 2.0) — Update Status

## Detail PIB

| Field | Nilai |
|:---|:---|
| **Nomor AJU** | `{{ $doc->aju_number }}` |
| **Nomor Pendaftaran** | `{{ $doc->registration_number ?? '-' }}` |
| **Importir** | {{ $doc->importir_name ?? '-' }} |
| **Status Terbaru** | {{ strtoupper($doc->status) }} |

@component('mail::button', ['url' => config('app.url')])
Lihat Detail
@endcomponent

---
*Email ini dikirim otomatis oleh sistem integrasi CEISA 4.0 SAGANSA.*
@endcomponent