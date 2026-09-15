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

## [2026-09-15] Fitur Hapus Data Jaminan (Single & Bulk Delete)

- Status: Done
- Modul: Data Pembiayaan — Data Jaminan (`CollateralIndex`)
- Ref PRD: Bab 10
- Perubahan: 
  - Menambahkan method `konfirmasiHapus($id)` dan `hapus()` pada Livewire `CollateralIndex` untuk menghapus per baris data jaminan dengan modal konfirmasi interaktif.
  - Menambahkan method `konfirmasiHapusSemua()` dan `hapusSemuaData()` untuk pembersihan bulk seluruh data jaminan jika diperlukan re-upload.
  - Menambahkan tombol aksi hapus single di setiap baris tabel serta tombol "Hapus Semua Jaminan" pada header halaman berdesain kontras tinggi (WCAG 2.1 AA).
- File utama: `app/Livewire/DataPembiayaan/CollateralIndex.php`, `resources/views/livewire/data-pembiayaan/collateral-index.blade.php`
- Verifikasi: Formatted via Laravel Pint (`./vendor/bin/pint`) & `php artisan view:clear`.

## [2026-09-15] Penyelarasan Total EAD pada Tampilan Penetapan Periode CKPN

- Status: Done
- Modul: CKPN Engine — Penetapan Periode CKPN (`CkpnPeriodIndex`)
- Ref PRD: Bab 6.1
- Perubahan: 
  - Menyelaraskan query kolom "Total Outstanding / EAD" di menu Penetapan Periode (`CkpnPeriodIndex`) agar mengecualikan pembiayaan dengan `product_code = '72'`, sehingga angka EAD di tabel Penetapan Periode identik 100% dengan total EAD pada Klasifikasi Pembiayaan (`PopulatePeriodDebtorsJob` & `CkpnClassificationIndex`).
- File utama: `app/Livewire/Ckpn/CkpnPeriodIndex.php`
- Verifikasi: Formatted via Laravel Pint (`./vendor/bin/pint`).

## [2026-09-15] Fitur Export Data Excel Klasifikasi CKPN

- Status: Done
- Modul: CKPN Engine — Klasifikasi Pembiayaan (Individual vs Kolektif)
- Ref PRD: Bab 6.1 & FR-12
- Perubahan: 
  - Membuat class export `CkpnClassificationExport` berbasis `FromQuery`, `WithChunkReading` (chunk: 1000 baris), dan auto-styling untuk mengekspor data nominatif klasifikasi pembiayaan periode CKPN ke berkas Excel (`.xlsx`).
  - Menambahkan method `exportExcel()` pada Livewire `CkpnClassificationIndex` yang merespons filter aktif (periode, jenis penggunaan, klasifikasi, serta kata kunci pencarian debitur).
  - Menambahkan tombol "Export Excel" berdesain kontras tinggi (WCAG 2.1 AA) di sebelah tombol aksi pada view `ckpn-classification-index.blade.php`.
- File utama: `app/Exports/CkpnClassificationExport.php`, `app/Livewire/Ckpn/CkpnClassificationIndex.php`, `resources/views/livewire/ckpn/ckpn-classification-index.blade.php`
- Verifikasi: Formatted via Laravel Pint (`./vendor/bin/pint`) & `php artisan view:clear`.

## [2026-09-15] Penyesuaian Filter Exclude Kode Produk 72 pada PD Netflow & Penetapan Debitur EAD

- Status: Done
- Modul: CKPN Engine — PD Netflow Baseline & Penetapan Debitur EAD
- Ref PRD: Bab 6.1 & Bab 7
- Perubahan: 
  - Menambahkan filter pengecualian kode produk 72 (`product_code != '72'`) pada `PdNetflowBaseline` sehingga seluruh perhitungan, historis, dan pivot pergerakan bucket PD Netflow mengecualikan pembiayaan dengan produk 72 secara terpusat.
  - Menambahkan filter pengecualian kode produk 72 pada `PopulatePeriodDebtorsJob` dan `CkpnPreviewService` sehingga penetapan populasi debitur EAD dan preview simulasi CKPN konsisten tidak memuat pembiayaan produk 72.
- File utama: `app/Domain/Ckpn/Pd/Netflow/PdNetflowBaseline.php`, `app/Jobs/PopulatePeriodDebtorsJob.php`, `app/Domain/Ckpn/Preview/CkpnPreviewService.php`
- Verifikasi: Formatted via Laravel Pint (`./vendor/bin/pint`).

## [2026-09-15] Fix MethodNotAllowedHttpException pada GET /logout

- Status: Done
- Modul: Auth — Logout
- Ref PRD: FR-14
- Perubahan: Route logout dipindah ke `LogoutController` (reuse, hapus duplikasi closure) + tambah fallback `GET /logout` redirect ke login agar stale link/bookmark tidak melempar 405 (POST-only).
- File utama: `routes/web.php`
- Verifikasi: `php -l` & Pint bersih.

## [2026-09-14] Audit & Standardisasi Kontras UI/UX WCAG 2.1 AA Seluruh View & Form

- Status: Done
- Modul: UI/UX Sistem CKPN — Seluruh View Form, Filter, Input, Modal & Badge Status
- Ref PRD: Bab 14 & AGENTS.md §5
- Perubahan: 
  - Standardisasi seluruh field input, select filter, search bar ke kontras WCAG 2.1 AA (`bg-zinc-950`, `border-zinc-700`, `text-zinc-200`, `placeholder-zinc-500`, `focus:border-primary-500 focus:ring-primary-500`) yang menghasilkan rasio kontras > 11:1.
  - Perbaikan warna indikator error dan elemen danger dari merah pekat gelap (`text-red-600`) menjadi `text-rose-400` / `border-rose-800/80 bg-rose-950/40 text-rose-300` agar terbaca jelas di atas dark background (rasio > 5.5:1).
  - Standardisasi seluruh modal konfirmasi (backdrop `bg-black/60 backdrop-blur-sm`, modal box `border border-zinc-800 bg-zinc-900 shadow-2xl`, tombol aksi `bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white` & high-contrast action buttons).
  - Standardisasi navigasi tab kalkulasi dan form login (`auth/login.blade.php`).
- File utama:
  - Master Data: `collateral-type-index.blade.php`, `quality-grade-index.blade.php`
  - Data Pembiayaan & Upload: `financing-account-index.blade.php`, `collateral-index.blade.php`, `financing-period-index.blade.php`, `upload-index.blade.php`, `upload-batch-index.blade.php`, `export-data-index.blade.php`
  - PD Engine: `pd-netflow-result-index.blade.php`, `pd-netflow-pivot-index.blade.php`, `pd-migration-result-index.blade.php`, `probabilitas-default-index.blade.php`
  - LGD Engine: `loss-given-default-index.blade.php`, `lgd-er-result-index.blade.php`, `lgd-cs-result-index.blade.php`, `lgd-final-result-index.blade.php`
  - CKPN Engine & Reporting: `ckpn-period-index.blade.php`, `ckpn-classification-index.blade.php`, `ckpn-preview-index.blade.php`, `ckpn-individual-result-index.blade.php`, `ckpn-collective-result-index.blade.php`, `hasil-ckpn-index.blade.php`, `ckpn-final-report-index.blade.php`, `lgd-summary-index.blade.php`, `data-quality-anomaly-index.blade.php`
  - Admin & Auth: `user-index.blade.php`, `calculation-run-log-index.blade.php`, `login.blade.php`
- Verifikasi: `php artisan view:clear` sukses, tidak ada sintaks blade rusak.

## [2026-09-14] Redesign UI/UX Manajemen Pengguna & Konsolidasi Ringkasan CKPN

- Status: Done
- Modul: Manajemen Pengguna & Ringkasan Laporan CKPN
- Ref PRD: Bab 11, Bab 14 (User Management & Role)
- Perubahan: 
  - Redesign UI/UX halaman Manajemen Pengguna (`UserIndex`) dengan kontras tinggi (dark theme), penambahan 4 kartu statistik pengguna, toolbar pencarian & filter role yang presisi, list tabel ber-avatar gradien dan badge peran berkarakter kontras, form modal create/edit dengan pilihan kartu peran interaktif, serta modal konfirmasi hapus.
  - Penambahan konsolidasi total CKPN pada halaman Ringkasan CKPN (`CkpnSummaryIndex`) yang menggabungkan CKPN Individual dan Kolektif secara menyeluruh dan per periode perhitungan.
- File utama: `resources/views/livewire/admin/user-index.blade.php`, `app/Livewire/Admin/UserIndex.php`, `resources/views/livewire/reporting/ckpn-summary-index.blade.php`, `app/Livewire/Reporting/CkpnSummaryIndex.php`
- Verifikasi: Kode terformat rapi sesuai PSR-12 via Laravel Pint, tidak ada diagnostik error.

## [2026-09-14] Optimasi Memory Limit Job Upload Data Jaminan

- Status: Done
- Modul: ProcessCollateralUploadJob & Queue Worker Config
- Ref PRD: Bab 10 (LGD-CS) & Bab 15
- Perubahan: Refactor `ProcessCollateralUploadJob` menjadi streaming reader hemat memori dengan PhpSpreadsheet ReadDataOnly & batch upsert (`collaterals`). Menambahkan `--memory=512` pada `queue-worker.bat` & `scripts/queue-worker.bat` untuk mencegah restart tak terduga saat pemrosesan batch besar.
- File utama: `app/Jobs/ProcessCollateralUploadJob.php`, `queue-worker.bat`, `scripts/queue-worker.bat`, `tests/Feature/CollateralUploadJobTest.php`
- Verifikasi: Automated test Pest berhasil.

## [2026-08-31] Export Excel PD Netflow 4 Segmen

- Status: Done
- Modul: PD Netflow Pivot Export
- Ref: PRD Bab 7
- Perubahan: Export kini selalu menghasilkan tepat empat sheet (Konsolidasi, Modal Kerja, Investasi, Konsumtif), masing-masing dihitung melalui PdNetflowDetailService; outstanding ditampilkan dalam rupiah penuh.
- File utama: `app/Exports/PdNetflowPivotExport.php`, `app/Jobs/PdNetflowPivotExportJob.php`
- Verifikasi: PHP lint berhasil; validasi Excel runtime belum dijalankan.

- Status: Done
- Modul: PD Netflow Result
- Perubahan: Menambahkan padding horizontal responsif pada blok heading agar judul dan deskripsi tidak menempel ke tepi container.
- File utama: `resources/views/livewire/pd-netflow/pd-netflow-result-index.blade.php`
- Verifikasi: `php artisan view:cache` berhasil.

## [2026-08-31] Audit Spacing Seluruh Blade

- Status: Done
- Modul: UI — margin, padding, gap, container, tabel, form, modal, responsive spacing
- Ref: AGENTS.md §5
- Perubahan: Audit seluruh `resources/views/**/*.blade.php`; perbaiki lebar filter form agar penuh di mobile dan kembali ke lebar tetap mulai breakpoint `sm`, tanpa mengubah logic.
- File utama: `resources/views/livewire/data-pembiayaan/financing-period-index.blade.php`, `resources/views/livewire/upload-data/upload-batch-index.blade.php`
- Verifikasi: `php artisan view:cache` berhasil.
- Next step: validasi visual browser bila diperlukan.

## [2026-08-31] Audit Spacing Dashboard Dark

- Status: Done
- Modul: UI — konsistensi spacing Blade
- Ref: AGENTS.md §5
- Perubahan: Audit seluruh `resources/views/**/*.blade.php`; menormalkan wrapper dashboard dari `space-y-0` menjadi `space-y-6` dan menyamakan padding tab kalkulasi menjadi `px-4 py-4 sm:px-6` agar responsif dan konsisten dengan dark dashboard.
- File utama: `resources/views/livewire/dashboard/index.blade.php`, `resources/views/livewire/kalkulasi/probabilitas-default-index.blade.php`, `resources/views/livewire/kalkulasi/loss-given-default-index.blade.php`
- Verifikasi: `php artisan view:cache` berhasil.
- Next step: validasi visual browser; perhatian tersisa pada beberapa view legacy yang masih memakai `px-6`/`py-4` tetap, tetapi tidak diubah karena konteks tabel dan panelnya sudah konsisten.

## [2026-08-31] Penyelarasan Detail PD Netflow

- Status: Done
- Modul: PD Netflow Detail / Pivot
- Ref: PRD Bab 7
- Perubahan: Jarak antar tiga tabel dibuat konsisten dengan margin bawah 2rem dan jarak judul 0.75rem. Header transition dipisahkan secara jelas dari header compound menggunakan zinc sebagai warna dasar dan amber hanya untuk kolom proyeksi. Nominal outstanding ditampilkan sebagai rupiah penuh dengan pemisah ribuan, bukan jutaan.
- File utama: `resources/views/livewire/pd-netflow/pd-netflow-pivot-index.blade.php`
- Verifikasi: Pint dan `php artisan view:cache` berhasil.

- Status: Done
- Modul: PD Netflow Detail
- Ref: PRD Bab 7, AGENTS.md §4
- Perubahan: Tombol mengikuti state periode: Hitung jika belum ada hasil, Tampilkan Data jika hasil tersedia namun belum dibuka, dan Rekalkulasi/Hapus setelah data ditampilkan. Dispatch perhitungan normal kini memakai transaksi dan lockForUpdate per periode+segmen agar request bersamaan tidak membuat run log ganda; run ulang tetap membuat histori baru hanya melalui aksi Rekalkulasi.
- File utama: `app/Livewire/PdNetflow/PdNetflowResultIndex.php`
- Verifikasi: PHP lint, Pint, dan `php artisan view:cache` berhasil.
- Next step: uji dua klik/request paralel pada periode yang sama dan verifikasi queue worker.

- Status: Done
- Modul: UI — seluruh Blade views
- Ref: AGENTS.md §5
- Perubahan: Normalisasi palette dark pada dashboard, reporting, PD Migration, LGD, master data, data pembiayaan, kalkulasi, upload, dan layout; perbaiki badge/status, alert, panel, tabel, serta malformed opacity class.
- File utama: `resources/views/livewire/**/*.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/filament/tables/financing-upload-errors.blade.php`
- Verifikasi: `php artisan view:cache` dan `php artisan optimize:clear` berhasil.
- Next step: validasi visual browser per modul.
