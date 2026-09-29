# AGENTS.md — Panduan Kerja Agent untuk Sistem CKPN (Laravel 13 + Filament 4)

Dokumen ini adalah instruksi kerja untuk AI coding agent (Claude Code, Cursor, dsb.) yang mengerjakan repo ini.
Tujuan: **konsisten dengan PRD, efisien token, dan mengikuti standar pengembangan Laravel**.
Spesifikasi bisnis lengkap ada di `PRD.md` — **jangan duplikasikan isi PRD di sini atau di komentar kode**, cukup rujuk nomor bab-nya.

---

## 1. Prinsip Kerja Agent (Efisiensi Token)

1. **Jangan baca ulang file yang sudah dibaca** dalam sesi yang sama kecuali file tsb baru saja diubah. Simpan konteks path & isi penting yang sudah diketahui.
2. **Baca sebagian, bukan seluruh file**, saat file besar (>200 baris). Gunakan `grep`/pencarian simbol untuk lompat langsung ke fungsi/class yang relevan, baru buka range baris tsb.
3. **Jangan cetak ulang seluruh isi file setelah edit.** Cukup laporkan ringkas: file mana yang diubah, bagian apa, kenapa. Diff singkat > dump penuh.
4. **Kelompokkan pekerjaan per fase PRD** (lihat Bab 17 PRD). Kerjakan satu fase/satu modul sampai selesai (migration → model → policy → resource → test) sebelum pindah modul lain, agar tidak bolak-balik membuka file yang sama berkali-kali.
5. **Gunakan referensi nomor bab PRD** di commit message / PR description / komentar kode singkat (mis. `// Ref: PRD Bab 7.3 - validasi data quality`), **bukan menyalin isi paragraf PRD**.
6. **Jangan generate boilerplate yang bisa di-generate `artisan make:*`.** Jalankan Artisan generator dulu, baru edit hasilnya — lebih murah daripada menulis seluruh file dari nol lewat token model.
   5b. **Sebelum menulis kode migrasi/model baru, cek dulu apakah tabel/model serupa sudah ada** (lihat Bab 15 PRD untuk daftar tabel) agar tidak membuat struktur duplikat.
7. **Jangan jalankan test suite penuh setiap kali** kalau hanya mengubah 1 file kecil — jalankan test yang scope-nya relevan dulu (`--filter`), full suite hanya sebelum commit/PR akhir fase.
8. **Tidak perlu menjelaskan ulang arsitektur** yang sudah dijelaskan di PRD/AGENTS.md ini di setiap respons — asumsikan sudah dibaca, langsung eksekusi.
9. **Wajib konfirmasi user** sebelum menjalankan/menyusun operasi apa pun yang berisiko kehilangan data — lihat Bab 13. Tidak ada pengecualian.

---

## 2. Tech Stack & Versi

| Komponen        | Versi/Ketentuan                                                        |
| --------------- | ---------------------------------------------------------------------- |
| PHP             | ^8.3 (mengikuti requirement Laravel 13)                                |
| Framework       | Laravel 13.x                                                           |
| Admin Panel     | Filament 4.x                                                           |
| Database        | MySQL 8.x                                                              |
| CSS             | Tailwind CSS (bawaan Filament, jangan tambah framework CSS lain)       |
| Testing         | Pest (preferred) atau PHPUnit — ikuti yang sudah terpasang di repo     |
| Queue           | Database/Redis driver untuk job perhitungan batch (lihat PRD Bab 13.2) |
| Package manager | Composer + npm (jangan campur yarn/pnpm dalam repo yang sama)          |

Jangan downgrade/upgrade versi major package di atas tanpa instruksi eksplisit dari user.

---

## 3. Struktur Direktori (Domain CKPN)

Gunakan struktur modular berbasis domain di dalam `app/`, bukan menumpuk semua di folder default Laravel:

```
app/
  Models/                     -- Eloquent models (1 file = 1 model, sesuai tabel di PRD Bab 15)
  Filament/
    Resources/                -- Filament Resource per master data & hasil (read-only resources utk snapshot)
    Clusters/                 -- Kelompokkan resource per domain (Master Data, PD Netflow, PD Migration, LGD, CKPN, Reporting)
  Domain/
    Ckpn/
      Pd/
        Netflow/              -- PdNetflowCalculator, validators, DTO
        Migration/             -- PdMigrationCalculator, DTO
        Contracts/             -- PdCalculationMethodInterface
      Lgd/
        ExpectedRecoveries/
        CollateralShortfall/
        Contracts/             -- LgdCalculationMethodInterface
      Individual/              -- CkpnIndividualCalculator
      Collective/              -- CkpnCollectiveCalculator (kombinasi PD x LGD x EAD, lihat PRD Bab 11)
  Jobs/                        -- Job batch per periode/segmen (queue-able, idempotent — lihat PRD Bab 13.2 & 16)
  Services/                    -- Service pendukung lintas domain (mis. RollingWindowResolver, BucketingService)
  Enums/                       -- Bucket, QualityGrade, RunStatus, PdMethod, LgdMethod, dst. (PHP native enum)
database/
  migrations/
  factories/                   -- WAJIB dibuat untuk setiap model utama (untuk kebutuhan test & seeding)
  seeders/
tests/
  Feature/                     -- test end-to-end per engine (Netflow, Migration, LGD, Individual, Collective)
  Unit/                        -- test formula murni (assert angka hasil kalkulasi terhadap contoh manual)
```

**Aturan:** satu class = satu tanggung jawab (Single Responsibility). Perhitungan Netflow, Migration, LGD-ER, LGD-CS **tidak boleh** ditulis dalam satu class besar — masing-masing implementasi interface terpisah sesuai PRD Bab 13.2 (Strategy Pattern).

---

## 4. Standar Koding Laravel

1. **PSR-12** untuk seluruh kode PHP. Jalankan `./vendor/bin/pint` sebelum commit (bukan format manual).
2. **Strict types**: `declare(strict_types=1);` di setiap file PHP baru di `app/`.
3. **Type-hint semua parameter & return type**, termasuk untuk method perhitungan (`float`, `int`, `Collection`, DTO/Value Object — jangan `array` polos untuk struktur data hasil kalkulasi, gunakan DTO/Data class agar aman secara tipe).
4. **Eloquent, bukan query builder mentah**, kecuali untuk agregasi berat (bucket movement, compound flow, migration matrix) — di situ boleh pakai query builder/raw SQL demi performa, **beri komentar singkat alasan performanya**.
5. **Jangan taruh logika bisnis di Controller atau Filament Resource.** Resource/Controller hanya orchestration; logika kalkulasi ada di `app/Domain/Ckpn/*`.
6. **Job harus idempotent** (sesuai PRD Bab 16) — gunakan `unique()` job atau cek status run sebelum eksekusi ulang, agar retry tidak duplikasi snapshot data.
7. **Snapshot immutability**: tabel hasil (`pd_netflow_result`, `pd_migration_result`, `lgd_*_result`, `ckpn_*_result`) tidak boleh punya method `update()` dipakai bebas — gunakan pola insert-only per periode, proteksi lewat model event/policy jika periode berstatus `Completed`/`Approved` (PRD Bab 13.2 & FR-13).
8. **Semua angka finansial pakai tipe `decimal` di migration** (bukan `float`) dan cast model ke `decimal:2`/`decimal:6` sesuai kebutuhan presisi rate/persentase.
9. **Naming database**: snake_case, tabel jamak, foreign key `{singular_table}_id`, nama tabel mengikuti daftar di PRD Bab 15 — jangan ubah nama tabel tanpa alasan kuat.
10. **Migration granular**: 1 migration = 1 perubahan logis. Jangan gabung pembuatan banyak tabel tak terkait dalam 1 file migration.

---

## 5. Konvensi Filament 4

1. Gunakan **Cluster** untuk mengelompokkan resource sesuai domain (Master Data, PD Netflow, PD Migration, LGD, CKPN Individual, CKPN Kolektif, Reporting) — jangan taruh semua resource flat di satu menu.
2. Resource untuk **tabel hasil/snapshot bersifat read-only** (disable create/edit/delete di halaman list, kecuali kolom catatan tindak lanjut anomali data quality — PRD Bab 7.3/FR-4.3).
3. Gunakan **Filament Actions** untuk trigger job perhitungan (mis. tombol "Jalankan Perhitungan PD Netflow Periode X"), bukan route/controller custom terpisah kalau tidak perlu.
4. Gunakan **Filament Notifications** untuk memberi tahu user saat job batch selesai/gagal (job berjalan async via queue, bukan blocking request).
5. Terapkan **Filament Shield** (atau setara) untuk role: Admin, Risk Analyst, Appraisal/Approval Agunan, Approver CKPN, Viewer (PRD FR-14) — definisikan permission per Resource/Cluster, jangan hardcode role check di dalam kode Resource.
6. Styling murni via Tailwind utility class bawaan Filament — **jangan menulis custom CSS file baru** kecuali benar-benar tidak bisa dicapai lewat Filament theming.

---

## 6. Referensi Domain CKPN (Ringkas — Detail di PRD.md)

Jangan salin ulang rumus/penjelasan panjang dari PRD ke kode/komentar. Cukup gunakan tabel ringkas ini sebagai index cepat saat butuh tahu "ini masuk domain mana":

| Domain                                 | Kelas Utama                                       | Ref PRD                                                                                   |
| -------------------------------------- | ------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Segmentasi                             | `RiskSegment`, `FinancingAccountSegmentMap`       | Bab 5                                                                                     |
| CKPN Individual                        | `CkpnIndividualCalculator`                        | Bab 6.1                                                                                   |
| PD Netflow                             | `PdNetflowCalculator`, `BucketMovementValidator`  | Bab 7                                                                                     |
| PD Migration                           | `PdMigrationCalculator`, `MigrationMatrixBuilder` | Bab 8                                                                                     |
| LGD Expected Recoveries                | `LgdExpectedRecoveriesCalculator`                 | Bab 9                                                                                     |
| LGD Collateral Shortfall               | `LgdCollateralShortfallCalculator`                | Bab 10                                                                                    |
| Kombinasi PD/LGD final → CKPN Kolektif | `CkpnCollectiveCalculator`                        | Bab 11 (⚠️ kebijakan kombinasi belum final, cek Bab 12 open items sebelum hardcode logic) |
| Data model                             | —                                                 | Bab 15                                                                                    |

**Jika ada open item PRD Bab 12 yang belum dikonfirmasi user** (mis. rentang bucket 2–13, kebijakan kombinasi PD/LGD) dan implementasi butuh nilai tsb: **buat parameter configurable dengan nilai default yang jelas ditandai TODO**, jangan hardcode angka tebakan tanpa penanda. Contoh:

```php
// TODO(PRD Bab 12.1): rentang hari bucket 2-13 belum dikonfirmasi user, nilai di bawah SEMENTARA.
```

---

## 7. Testing

1. **Unit test wajib** untuk setiap formula (Netflow rate, compound flow, migration matrix, LGD-ER, LGD-CS, CKPN Individual) dengan **angka contoh manual** (buat fixture kecil yang hasilnya bisa dihitung tangan, assert exact match).
2. **Feature test** untuk alur end-to-end per engine: import data → jalankan job → cek snapshot hasil tersimpan benar.
3. Gunakan **factory** untuk seluruh model utama, jangan seed data lewat SQL manual di test.
4. Jalankan `php artisan test --filter=NamaTest` selama development; jalankan full suite (`php artisan test`) sebelum menandai fase selesai.
5. Test untuk **validasi data quality** (PRD Bab 7.3) wajib mencakup kasus anomali (bucket kosong, bucket tujuan > bucket asal) untuk memastikan flag anomali terbentuk, bukan silent pass.

---

## 8. Git & Commit

1. Commit kecil & fokus per langkah logis (1 migration/1 fitur kecil per commit), bukan 1 commit besar per fase.
2. Format commit: `[Modul] Ringkas perubahan` — contoh: `[PD Netflow] Tambah validasi bucket movement anomaly`.
3. Jangan commit file hasil build (`node_modules`, `vendor`, `.env`, storage cache).
4. Jalankan `pint` dan test relevan sebelum commit.

---

## 9. Larangan (Do Not)

- ❌ Jangan hardcode angka bisnis (rentang bucket, panjang rolling window, threshold) langsung di kode kalkulasi — semua harus ambil dari `calculation_parameters` (PRD Bab 15) via Service/Repository, bukan konstanta di class kalkulator.
- ❌ Jangan mengubah struktur tabel snapshot hasil yang sudah dipakai fase sebelumnya tanpa migration baru (jangan `Schema::table()->change()` sembarangan pada tabel snapshot yang sudah berjalan production).
- ❌ Jangan menggabungkan logic PD Netflow dan PD Migration dalam satu class/method.
- ❌ Jangan expose proses perhitungan berat sebagai synchronous HTTP request — selalu lewat Queue Job.
- ❌ Jangan menulis ulang seluruh PRD.md ke dalam README/komentar — cukup link/referensi bab.
- ❌ Jangan menjalankan/menyusun operasi penghapusan atau penimpaan data (DELETE massal, TRUNCATE, DROP/dropColumn, migrate:fresh/refresh destruktif, reset seeder, git reset --hard, dll.) tanpa konfirmasi eksplisit user — lihat Bab 13.

---

## 10. Perintah Cepat (Cheat Sheet)

```bash
# Setup awal
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed

# Generate resource baru (jangan tulis manual dari nol)
php artisan make:model Domain/Ckpn/... -mf
php artisan make:filament-resource NamaResource --generate

# Format & test sebelum commit
./vendor/bin/pint
php artisan test --filter=NamaTestYangRelevan

# Jalankan job perhitungan (contoh, sesuaikan nama job sebenarnya)
php artisan queue:work --queue=ckpn-calculation
```

---

## 11. Clean Code & Reusability

1. **Fungsi/method kecil & fokus satu tugas.** Jika sebuah method sudah melebihi ±25–30 baris atau melakukan >1 tanggung jawab (mis. sekaligus validasi + hitung + simpan), pecah jadi beberapa method/class kecil.
2. **Ekstrak logic yang dipakai >1 tempat menjadi Service/Trait/Helper**, jangan copy-paste. Contoh yang wajib reusable karena dipakai lintas metode:
   - `RollingWindowResolver` — resolusi rentang periode bergerak (dipakai PD Netflow, PD Migration, LGD Expected Recoveries — semuanya "rolling window mengikuti posisi yang dihitung").
   - `BucketingService` — penentuan bucket dari hari tunggakan (dipakai PD Netflow & CKPN Individual).
   - `PeriodHelper` — parsing/format periode `yyyymm`, geser periode mundur/maju N bulan/tahun.
   - `SegmentAwareCalculationTrait` atau setara — pola "hitung per segmen, fallback ke all-account" (dipakai LGD-ER & LGD-CS).
   - `SnapshotWriter`/`SnapshotRepository` — pola simpan hasil sebagai insert-only per periode+segmen (dipakai semua engine hasil).
3. **Prinsip SOLID**, khususnya:
   - **Single Responsibility** — 1 class = 1 alasan untuk berubah (lihat Bab 3 & 9).
   - **Open/Closed** — menambah metode PD/LGD baru = menambah class baru yang implement interface, **tanpa mengubah** class kalkulator lain yang sudah ada.
   - **Dependency Inversion** — kalkulator bergantung pada interface (`PdCalculationMethodInterface`, `LgdCalculationMethodInterface`), bukan pada implementasi konkret lain secara langsung.
4. **Hindari magic number/string.** Gunakan Enum (`Bucket`, `QualityGrade`, `PdMethod`, `LgdMethod`, `RunStatus`) dan konstanta bernama, bukan literal angka/string tersebar di banyak file.
5. **Penamaan jelas & konsisten** (Bahasa Inggris untuk nama class/method/variable, mengikuti istilah domain di PRD Bab 4 — mis. `calculateNetflowRate()`, bukan singkatan ambigu seperti `calcNfRt()`).
6. **DRY tapi jangan over-abstract.** Sebelum membuat abstraksi/interface baru, pastikan minimal ada 2 pemakaian nyata (bukan spekulatif) — abstraksi prematur juga melanggar clean code.
7. **Self-review sebelum commit**: jalankan `pint`, pastikan tidak ada dead code/`dd()`/`var_dump()`/commented-out code tertinggal, dan setiap method publik non-trivial punya PHPDoc singkat (1–3 baris, deskripsi + referensi bab PRD bila relevan — bukan menyalin isi PRD).

## 12. Update Progress & Dokumentasi (Wajib Setiap Selesai Perubahan)

Setiap kali menyelesaikan satu unit pekerjaan (1 fitur kecil, 1 fase, 1 perbaikan bug, 1 penambahan modul), agent **wajib** melakukan hal berikut sebelum dianggap selesai:

1. **Update `PROGRESS.md`** di root repo (buat file ini jika belum ada) dengan format ringkas per entri:
   ```
   ## [yyyy-mm-dd] Ringkasan Perubahan
   - Status: Done / In Progress / Blocked
   - Modul: (mis. PD Netflow - Bucket Movement Validator)
   - Ref PRD: Bab X.Y
   - Perubahan: ringkas 1-3 baris, bukan narasi panjang
   - File utama yang berubah: path saja (tidak perlu isi lengkap)
   - Next step / blocker (jika ada)
   ```
   Tulis **ringkas dan padat** (hemat token) — ini log kerja, bukan laporan naratif.
2. **Update dokumentasi teknis terkait** setiap ada perubahan yang memengaruhi:
   - Struktur database → update ringkasan skema (mis. `docs/DATABASE.md` bila sudah dibuat pada tahap Technical Design).
   - Aturan/parameter bisnis baru yang disepakati (mis. open item PRD Bab 12 sudah dikonfirmasi user) → update `PRD.md` bagian terkait, tandai versi & tanggal update, **jangan buat dokumen PRD baru terpisah**.
   - Cara kerja/standar teknis baru → update `AGENTS.md` ini pada bab yang relevan.
3. **Tandai open item yang sudah terjawab.** Jika suatu keputusan dari PRD Bab 12 (Open Items) sudah dikonfirmasi user selama development, pindahkan/hapus dari daftar open item dan catat keputusan final di bab terkait — jangan biarkan dua sumber kebenaran (PRD lama vs keputusan baru di chat/commit) berbeda.
4. **Jangan tunda update dokumentasi ke akhir proyek.** Update dilakukan **di commit yang sama** dengan perubahan kode, bukan sebagai commit terpisah "nanti dirapikan".
5. **Ringkasan ke user**: setiap merespons setelah menyelesaikan pekerjaan, sertakan ringkasan singkat (3-5 poin: apa yang selesai, file yang berubah, status test, next step) — bukan menampilkan ulang seluruh kode/file yang sudah dibuat.

---

## 13. Kebijakan WAJIB: Konfirmasi Sebelum Operasi Berisiko Kehilangan Data

Agent **wajib meminta konfirmasi eksplisit dari user dan menunggu persetujuan** sebelum menjalankan ATAU menyusun perubahan apa pun yang berpotensi menghapus/menimpa data — di database, file, maupun repository. Aturan ini tidak memiliki pengecualian, termasuk saat perubahan terlihat kecil atau user meminta cepat.

### 13.1 Cakupan Operasi yang WAJIB Dikonfirmasi

| Kategori | Contoh operasi |
| --- | --- |
| DDL destruktif | `DROP TABLE`, `DROP DATABASE`, `DROP COLUMN`, `DROP INDEX` pada data terpakai, `TRUNCATE`, ubah tipe kolom yang berisiko kehilangan nilai |
| DML penghapusan | `DELETE` massal/tanpa key spesifik, `Model::truncate()`, `->delete()` pada koleksi luas, hard delete yang menggantikan soft delete |
| Artisan destruktif | `migrate:fresh`, `migrate:refresh`, `migrate:rollback` yang menghapus data/kolom, `db:wipe`, reset seeder yang menimpa/menghapus data existing |
| Data snapshot CKPN | Hapus/timpa baris `pd_netflow_result`, `pd_migration_result`, `lgd_*_result`, `ckpn_*_result`, atau tabel master (`risk_segments`, `akad_calculation_rules`, dsb. — PRD Bab 15). Snapshot bersifat immutable; penghapusan = hilangnya histori audit |
| Queue/Job | `queue:flush`, `queue:clear`, `queue:restart`, atau membatalkan batch saat perhitungan berjalan (PRD Bab 13.2) |
| File data | Hapus/overwrite file import/ekspor di `storage/app`, `rm -rf` di luar target yang eksplisit diminta user |
| Repository | `git reset --hard`, `git clean -fd`, `git checkout/restore .`, `git push --force`, `commit --amend` pada commit yang sudah di-push |

Operasi di luar daftar tapi berpotensi serupa (mis. `updateOrCreate`/`upsert` massal yang menimpa banyak nilai existing) diperlakukan sama: **konfirmasi dulu**.

### 13.2 Format Konfirmasi (Sebelum Eksekusi)

Nyatakan ringkas 4 poin berikut, lalu **berhenti sampai user menjawab setuju**:

1. **Aksi** — perintah/kode persis yang akan dijalankan.
2. **Dampak** — tabel/file/rentang data yang terdampak; bila bisa dihitung, tampilkan jumlah barisnya dulu (mis. `SELECT COUNT(*) ...`) sebelum meminta persetujuan.
3. **Reversibility** — apakah bisa di-rollback (backup, soft delete, `migration down`) atau permanen.
4. **Pertanyaan eksplisit** — "Lanjutkan? (ya/tidak)".

### 13.3 Mitigasi Wajib Setelah User Setuju

1. Untuk penghapusan massal di environment non-dev, usulkan **backup atau soft delete** dulu (dump tabel terkait / tambah `deleted_at`) sebelum hard delete.
2. Migration destruktif (`dropColumn`, `dropTable`) dibuat **di file migration terpisah**, tidak digabung dengan migration lain, dan diberi catatan destruktif di deskripsinya.
3. Jangan merantai beberapa operasi destruktif dalam satu perintah — eksekusi per langkah.
4. Setelah selesai, catat operasi destruktif yang dijalankan (beserta persetujuan user) di `PROGRESS.md` (Bab 12).

---

_Dokumen ini adalah panduan kerja teknis, pelengkap `PRD.md`. Jika ada konflik antara AGENTS.md dan PRD.md soal aturan bisnis/formula, PRD.md yang menjadi acuan utama — AGENTS.md hanya mengatur cara kerja & standar teknis._
