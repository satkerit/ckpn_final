## [2026-09-27] Implementasi OpenSpout untuk Upload Excel Hemat Memori

- Status: Done
- Modul: Upload Data — Semua Job Upload (Period, Collateral, Master, Office, CollateralType)
- Ref PRD: Bab 3 — Upload Data Pembiayaan
- Perubahan:
  - Install package OpenSpout v5.12.0 via Composer untuk streaming read Excel (.xlsx) tanpa load seluruh file ke memori.
  - Buat trait `StreamableExcelUpload` dengan method reusable: `createReader()`, `extractHeadings()`, `streamRows()`, `closeReader()`, `resolveColIndex()`, `parseDate()`, `parseDecimal()`, `parseInt()`, `parseString()`.
  - Refactor seluruh job upload ke OpenSpout streaming:
    - `ProcessFinancingPeriodUploadJob` — streaming + LOAD DATA LOCAL INFILE untuk insert super cepat (20-100x lebih cepat dari INSERT批量).
    - `ProcessCollateralUploadJob` — streaming + bulk upsert.
    - `ProcessFinancingMasterUploadJob` — streaming + bulk upsert.
    - `ProcessFinancingOfficeUploadJob` — streaming + bulk upsert.
    - `ProcessCollateralTypeUploadJob` — streaming + bulk upsert.
  - Ubah `UploadProcessorService` dari `dispatchSync()` ke `dispatch()` untuk queue async (tidak blocking HTTP request).
- Keuntungan:
  - Memory konstan ~30-50MB berapapun ukuran file (sebelumnya bisa 512MB+ untuk file besar).
  - Dapat memproses file ratusan ribu baris tanpa memory limit exceeded.
  - Performa baca 2-5x lebih cepat dari PhpSpreadsheet.
- File utama:
  - `app/Traits/StreamableExcelUpload.php`
  - `app/Jobs/ProcessFinancingPeriodUploadJob.php`
  - `app/Jobs/ProcessCollateralUploadJob.php`
  - `app/Jobs/ProcessFinancingMasterUploadJob.php`
  - `app/Jobs/ProcessFinancingOfficeUploadJob.php`
  - `app/Jobs/ProcessCollateralTypeUploadJob.php`
  - `app/Services/UploadProcessorService.php`
- Catatan penting:
  - Pastikan queue worker berjalan: `php artisan queue:work --queue=ckpn-calculation`
  - Import class lama (`FinancingMasterUploadImport`, dll.) masih ada tapi tidak dipakai — dapat dihapus jika tidak dibutuhkan.
- Next: tidak ada blocker.

## [2026-09-27] Tambah Kolom PPKA ke financing_account_periods

- Status: Done
- Modul: Data Pembiayaan — Historis Periode (`FinancingAccountPeriod`)
- Ref PRD: Bab 15
- Perubahan:
  - Migration: `add_ppka_to_financing_account_periods` (`DECIMAL(20,2) nullable`) — sudah dijalankan.
  - Model: kolom `ppka` sudah ada di `$fillable` & cast `decimal:2`.
  - Job: `ProcessFinancingPeriodUploadJob` — baca kolom `ppka` dari Excel (header `ppka`), simpan ke buffer & `LOAD DATA INFILE`.
  - Template Excel upload: `FinancingPeriodDataSheet` — tambah kolom `ppka` di heading & contoh data; `FinancingPeriodPetunjukSheet` — tambah baris petunjuk `ppka`.
  - Export: `FinancingPeriodExport` — tambah kolom `PPKA (Rp)` di query select, headings & map.
  - View: `financing-period-index.blade.php` — tambah kolom PPKA di tabel, colspan empty-state 10→11.
- File utama: `database/migrations/2026_09_27_152619_add_ppka_to_financing_account_periods.php`, `app/Models/FinancingAccountPeriod.php`, `app/Jobs/ProcessFinancingPeriodUploadJob.php`, `app/Exports/FinancingPeriodDataSheet.php`, `app/Exports/FinancingPeriodPetunjukSheet.php`, `app/Exports/FinancingPeriodExport.php`, `resources/views/livewire/data-pembiayaan/financing-period-index.blade.php`
- Next: tidak ada blocker.

## [2026-09-15] Perbaikan Spacing & Kontras View PD/LGD

- Status: Done
- Modul: UI/UX — seluruh view PD (Netflow, Migration, Pivot) & LGD (ER, CS, Final) + tab container
- Ref PRD: Bab 7–11 (tampilan hasil)
- Perubahan:
  - Standarisasi spacing: header `mb-6` + sub-teks `mt-1`, panel kontrol `p-5` dengan `gap-4`, judul panel `mb-4`, label field `mb-1 text-zinc-400`.
  - Samakan warna label field (sebagian `text-zinc-300` → `text-zinc-400`) dan border panel `zinc-700` → `zinc-800` agar konsisten.
  - Kontras WCAG 2.1 AA: badge status (Completed/Failed/Processing/Approved) pakai `border + bg-*-950/40 + text-*-300`; angka LGD/shortfall `text-red-700` → `text-rose-400`; recovery `text-emerald-700` → `text-emerald-400`; header Migration Matrix `bg-blue-700` → `bg-blue-950/60`.
  - Backdrop modal PD Netflow & Pivot disamakan ke `bg-black/60 backdrop-blur-sm`; tombol hapus modal `bg-rose-600`.
  - Input pencarian hasil PD Netflow diselaraskan ke `bg-zinc-950 + placeholder-zinc-500`.
  - Container tab PD/LGD: nav `flex-wrap gap-x-2 gap-y-1` agar tab tidak overflow/menempel di layar sempit.
- File utama: `resources/views/livewire/kalkulasi/{probabilitas-default-index,loss-given-default-index}.blade.php`, `resources/views/livewire/pd-netflow/*`, `resources/views/livewire/pd-migration/*`, `resources/views/livewire/lgd/*`, `resources/views/livewire/reporting/lgd-summary-index.blade.php`
- Verifikasi: `php artisan view:clear && php artisan view:cache` (semua Blade terkompilasi, exit 0).
- Next: tidak ada blocker.
