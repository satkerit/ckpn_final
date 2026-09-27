# Hasil Audit Kode — Upload + Codebase

> Tanggal audit: 2026-09-27
> Status: **Read-only audit** — belum ada perbaikan diterapkan.
> Dokumen ini menjadi **acuan urutan perbaikan**. Tandai item dengan `[x]` setelah diperbaiki, tambahkan referensi file/commit di kolom catatan.

---

## Ringkasan Eksekutif

- Alur upload saat ini: **upload masuk → job di-queue (`dispatch()`) → worker `queue:work` memproses**.
- OpenSpout sudah benar dipakai di jalur utama (streaming per baris via trait), **tetapi alur tetap bergantung pada daemon `queue:work`** — syarat "tidak menggunakan queue:work" belum terpenuhi.
- `app/Imports/*` (Maatwebsite) adalah sisa jalur lama yang **tidak dipakai sama sekali** = dead code (~400 baris).
- 4 temuan tingkat **High**: (1) SQL injection via `LOAD DATA` + retry_after mismatch, (2) otorisasi Livewire hilang, (3) `$guarded=[]` mass assignment, (4) format file `xls/csv` diterima UI tapi gagal saat runtime.
- Duplikasi masif antar 5 upload job (skeleton `handle()`, parser tanggal/decimal/boolean identik copy-paste).

---

## 1. Upload + OpenSpout + `queue:work`

| ID | Temuan | Bukti | Tingkat | Status |
|----|--------|-------|---------|--------|
| U1 | OpenSpout dipakai benar — streaming `Reader` per baris, generator, tidak memuat seluruh worksheet ke RAM | `app/Traits/StreamableExcelUpload.php:45,88` | OK | ✅ |
| U2 | **MASIH pakai `queue:work`** — `dispatch()` men-push ke queue, butuh daemon worker berjalan | `app/Services/UploadProcessorService.php:45-49`; `queue-worker.bat:7` (`queue:work database --queue=ckpn-calculation,default`) | ❌ Target tidak tercapai | [ ] |
| U3 | Kompleksitas palsu — file dibaca **3× pass**: `extractHeadings()` (reader #1), `countTotalRows()` (reader #2), lalu proses data (reader #3). Pemborosan I/O pada file besar | `app/Jobs/ProcessFinancingMasterUploadJob.php:95-110`; `ProcessFinancingOfficeUploadJob.php:78-83` | Medium | [ ] |
| U4 | Deadlock user — setelah dispatch, `$batch->refresh()` + cek `status === Done`; worker async belum jalan → status tetap `Pending` → branch `Processing` di UI tak pernah tercapai | `app/Livewire/UploadData/UploadIndex.php:133-157` | Medium | [ ] |
| U5 | **`retry_after=90` vs `timeout=1800`** — job yang berjalan >90 detik bisa di-claim ulang worker lain → **double-run / duplikasi data** | `config/queue.php:43` vs `app/Jobs/ProcessFinancingPeriodUploadJob.php:56` | **High** | [ ] |
| U6 | OpenSpout reader hardcode XLSX — UI mengizinkan `xlsx,xls,csv`, tapi `createReader()` hardcode `OpenSpout\Reader\XLSX\Reader` → `xls`/`csv` selalu gagal saat runtime | `app/Traits/StreamableExcelUpload.php:47` vs `app/Livewire/UploadData/UploadIndex.php:27,105` | **High** | [ ] |
| U7 | API OpenSpout deprecated (gaya v3) — `$row->getCells()`/`$cell->getValue()`, bukan `$row->getCellIterator()`; rawan rusak saat bump versi minor | `app/Traits/StreamableExcelUpload.php:67-69,99-103` | Medium | [ ] |
| U8 | Progress tracking tak akurat — `countTotalRows()` menghitung seluruh baris file, tapi `importedRows` bisa skip baris invalid → persentase tidak konsisten | `app/Services/UploadProcessorService.php`; `app/Traits/HasProgressTracking.php:57` | Low | [ ] |

### Keputusan desain yang perlu dikonfirmasi (U2)

Dua opsi untuk memenuhi syarat "tidak menggunakan queue:work":

1. **`dispatchSync()`** — proses inline dalam request. Hanya aman untuk file kecil; melanggar AGENTS.md Bab 9 ("jangan expose proses berat sebagai synchronous HTTP") bila dipakai untuk 300k baris.
2. **Tetap queue** — benar untuk file besar, tapi hapus klaim "tanpa queue:work" dari dokumentasi dan pastikan worker terpasang (task scheduler/service).

Rekomendasi: **hybrid** — file ≤ threshold → `dispatchSync()`; file besar → tetap queue. Threshold masuk `calculation_parameters`/config, bukan hardcode (AGENTS.md Bab 9).

---

## 2. Dead Code / Duplicate / Reusability

| ID | Temuan | Bukti | Status |
|----|--------|-------|--------|
| D1 | **4 kelas `app/Imports/*` = dead code total** (`FinancingMasterUploadImport`, `CollateralUploadImport`, `CollateralTypeUploadImport`, `FinancingOfficeUploadImport`) — tidak pernah di-instantiate/`import()` di mana pun. Masih membawa dependensi Maatwebsite | `app/Imports/*` (~400 baris) | [ ] |
| D2 | Duplikat `parseDate()` + `parseDecimal()` — identik antara trait dan import lama | `app/Traits/StreamableExcelUpload.php:163-218` vs `app/Imports/CollateralUploadImport.php:126-161` | [ ] |
| D3 | Skeleton `handle()` 5 job copy-paste — guard `FinancingUploadBatch::find`, idempotency, `file_exists`, set `Processing`, try/catch → `Failed` + rethrow. ~40 baris identik per job | `app/Jobs/Process*UploadJob.php` ×5 | [ ] |
| D4 | Duplikat `parseBoolean()` — identik 2 job | `app/Jobs/ProcessFinancingOfficeUploadJob.php:188-199` vs `ProcessCollateralTypeUploadJob.php:202-213` | [ ] |
| D5 | Dead code di trait — `streamRows()`, `getCellValue()` tidak dipanggil job mana pun | `app/Traits/StreamableExcelUpload.php:88,147` | [ ] |
| D6 | Dead code progress helper — `getProgressPercentage()`, `getETAFormatted()`, `formatDuration()` tak terpakai | `app/Traits/HasProgressTracking.php:216,232,240` | [ ] |
| D7 | Controller API stub — `index()` return `[]`, `cancel()` hanya return sukses tanpa membatalkan apa pun; route-nya aktif | `app/Http/Controllers/Api/UploadProgressController.php:43-70`; `routes/web.php:156-158` | [ ] |
| D8 | Script debug tertinggal di root repo — bootstrap kernel + `echo`/`print_r` | `check_params.php`, `test_pokpby.php` | [ ] |

### Rencana refactor (D2–D4)

Ekstrak **`UploadJobBase`** (abstract job base): guard batch, lifecycle status (`Pending→Processing→Done/Failed`), idempotency check, buffer flush, parser helper (`parseDate`/`parseDecimal`/`parseBoolean`). Estimasi: hilang ~200 baris duplikat. Konsisten AGENTS.md Bab 11.2 (ekstrak logic yang dipakai >1 tempat).

---

## 3. Keamanan

| ID | Temuan | Bukti | Tingkat | Status |
|----|--------|-------|---------|--------|
| S1 | **Path injection / SQL `LOAD DATA`** — `$tempFile` diinterpolasi mentah ke SQL tanpa escaping; `local_infile=ON` di MySQL (didokumentasikan) | `app/Jobs/ProcessFinancingPeriodUploadJob.php:349`; `docs/UPLOAD_OPTIMIZATION.md:30` | **High** | [ ] |
| S2 | **Otorisasi Livewire hilang** — 21 Policy ada (`app/Policies/*`), tapi nol `$this->authorize()` di komponen yang melakukan mutasi. Middleware `role:` di route saja tidak cukup | `routes/web.php:64-145`; komponen terdampak: `Admin/UserIndex` (:112,148), `MasterData/*Index` (save/delete), `Ckpn/CkpnPeriodIndex` (:154,204), semua `*ResultIndex` | **High** | [ ] |
| S3 | **Mass assignment longgar** — model dengan `$guarded = []` | `app/Models/*` | **High** | [ ] |
| S4 | API upload-progress tanpa ownership check — `show($uploadId)`/`cancel()` bisa diakses untuk ID upload milik user lain (Sanctum auth saja) | `app/Http/Controllers/Api/UploadProgressController.php:22,61` | Medium | [ ] |
| S5 | Data nasabah bocor ke log — `Log::error` menyertakan `$record` penuh (account_number, customer_name) | `app/Jobs/ProcessFinancingMasterUploadJob.php:397-401` | Medium | [ ] |
| S6 | Raw DB error message expose ke user — `error_summary` berisi `{$e->getMessage()}` lalu dirender di UI error upload | `app/Jobs/ProcessFinancingMasterUploadJob.php:394`; view `resources/views/tables/financing-upload-errors.blade.php` | Medium | [ ] |
| S7 | `whereRaw` interpolasi variabel | `app/Livewire/Ckpn/CkpnPeriodIndex.php:274` (`{$periodEndSql}`); `app/Domain/Ckpn/Pd/Netflow/PdNetflowBaseline.php:67` (statis, risiko rendah) | Low | [ ] |
| S8 | Upload hygiene — file upload tidak pernah dihapus setelah diproses; `mimes` menerima `csv`; `max:20mb` tidak konsisten dengan target 50MB di dokumen optimasi | `app/Livewire/UploadData/UploadIndex.php:24,105,115`; `docs/UPLOAD_OPTIMIZATION.md` | Low | [ ] |

---

## Urutan Perbaikan Disarankan

1. **U5 + U6** — blocker produksi: double-run queue + format file gagal runtime.
2. **S1, S2, S3** — keamanan kritis.
3. **D1** — hapus `app/Imports/*` + dependensi Maatwebsite (cek dulu tidak ada pemakai lain); **D7** hapus atau implementasi controller API stub; **D8** hapus script debug root.
4. **D2/D3/D4** — ekstrak `UploadJobBase` → hilangkan duplikasi; **U3** satukan 3× pass baca file menjadi 1 pass.
5. **U2** — putuskan arsitektur (sync vs queue vs hybrid) sesuai PRD Bab 13.2 & AGENTS.md Bab 9.
6. Sisanya (S4–S8, U4, U7, U8, D5, D6) — perbaikan lanjutan per modul.

## Aturan Pengerjaan Perbaikan

- Ikuti AGENTS.md: PSR-12 + `pint`, `declare(strict_types=1)`, type-hint penuh, DTO untuk struktur hasil, job idempotent (PRD Bab 16), snapshot insert-only (PRD Bab 13.2).
- Setiap item selesai → commit kecil `[Modul] Ringkas perubahan` + update `PROGRESS.md` (AGENTS.md Bab 8 & 12) + centang tabel di dokumen ini.
- Jangan ubah struktur tabel snapshot yang sudah berjalan tanpa migration baru (AGENTS.md Bab 9).
