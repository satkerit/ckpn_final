# PRD (Product Requirements Document)

# Sistem Perhitungan CKPN (Cadangan Kerugian Penurunan Nilai)

**Versi Dokumen:** 2.0
**Tanggal:** 18 Agustus 2026
**Status:** Draft — metodologi PD Netflow, PD Migration, LGD Expected Recoveries, dan LGD Collateral Shortfall sudah lengkap. Beberapa parameter teknis masih perlu dikonfirmasi (lihat Bab 12 — Open Items).

---

## 1. Latar Belakang

Perusahaan (BPRS) membutuhkan sistem internal untuk menghitung CKPN (Cadangan Kerugian Penurunan Nilai) atas portofolio pembiayaan, mengacu pada standar akuntansi keuangan terkait instrumen keuangan (referensi PSAK yang disebutkan: **PSAK 71**). Sistem akan dibangun sebagai aplikasi web berbasis **Laravel 13** dan **Filament 4** dengan database **MySQL** dan styling **Tailwind CSS**, agar proses perhitungan CKPN dapat dilakukan secara terstruktur, auditable, dan berulang setiap periode (bulanan), meliputi CKPN Individual dan CKPN Kolektif (dengan PD dari metode Netflow dan/atau Migration, serta LGD dari metode Expected Recoveries dan/atau Collateral Shortfall).

> **Catatan:** Dokumen ini menyebut standar sebagai "PSAK 71". Mohon dikonfirmasi apakah referensi PSAK yang dimaksud ("PSAK 414") sudah sesuai atau perlu dikoreksi.

---

## 2. Tujuan Produk

1. Mengotomatisasi perhitungan CKPN bulanan (CKPN Individual + CKPN Kolektif) berbasis data pembiayaan.
2. Menyediakan mesin perhitungan **PD** dengan dua metode: **Netflow** dan **Migration**, dihitung per **segmen/kelompok risiko kredit**.
3. Menyediakan mesin perhitungan **LGD** dengan dua metode: **Expected Recoveries** dan **Collateral Shortfall**.
4. Menghitung **EAD** dari baki debet outstanding per akun/segmen.
5. Menyediakan perhitungan **CKPN Individual** untuk akun NPF kriteria tertentu.
6. Menyimpan histori parameter, rate, matriks, dan hasil perhitungan tiap periode agar dapat diaudit (audit trail, immutable snapshot).
7. Menyediakan dashboard/report hasil perhitungan CKPN per periode, per segmen, per metode.

---

## 3. Ruang Lingkup

### 3.1 Termasuk dalam scope (sudah detail penuh)

- Segmentasi portofolio berdasarkan risiko kredit serupa.
- Struktur bucket hari tunggakan (Bucket 1–14).
- CKPN Individual.
- PD Netflow (per segmen, rolling window, validasi kualitas data, proyeksi forward, compound flow to loss).
- PD Migration (per segmen, matriks migrasi kualitas/kolektibilitas triwulanan).
- LGD Expected Recoveries (recovery rate dari penerimaan atas WO).
- LGD Collateral Shortfall (shortfall dari eksekusi/penjualan agunan).
- Konsolidasi CKPN Kolektif = PD x LGD x EAD.

### 3.2 Di luar scope pada versi ini

- Integrasi otomatis real-time dengan core banking system (asumsi: input via import/upload atau API batch).
- Perhitungan jurnal akuntansi otomatis ke General Ledger.
- Model statistik lanjutan (mis. regresi makroekonomi/forward looking macroeconomic overlay) — dapat menjadi fase lanjutan jika dibutuhkan.

---

## 4. Definisi & Istilah

| Istilah                 | Definisi                                                                                                                            |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| CKPN                    | Cadangan Kerugian Penurunan Nilai                                                                                                   |
| PD                      | Probability of Default                                                                                                              |
| LGD                     | Loss Given Default                                                                                                                  |
| EAD                     | Exposure at Default                                                                                                                 |
| NPF                     | Non Performing Financing                                                                                                            |
| Bucket                  | Segmentasi umur tunggakan (hari tunggakan pokok/bunga, mana yang lebih tinggi), 1–14                                                |
| Kualitas/Kolektibilitas | Klasifikasi kualitas pembiayaan (umumnya 1–5: Lancar, DPK, Kurang Lancar, Diragukan, Macet) — dipakai pada metode PD Migration      |
| Segmen Risiko           | Kelompok pembiayaan dengan karakteristik risiko kredit serupa (jenis penggunaan, sektor ekonomi, skala UMKM, skema pembiayaan, dll) |
| Netflow                 | Metode PD berbasis pergerakan outstanding antar bucket hari tunggakan per periode (bulanan)                                         |
| Migration               | Metode PD berbasis matriks transisi kualitas/kolektibilitas per triwulan, ditelusuri 1 tahun                                        |
| Hapus Buku (WO)         | Write-off pembiayaan                                                                                                                |
| Recoveries              | Penerimaan pembayaran kembali atas pembiayaan yang telah dihapusbuku                                                                |
| Collateral Shortfall    | Selisih kurang antara baki debet dengan nilai penjualan/eksekusi agunan                                                             |
| Rolling Window          | Rentang data historis yang bergerak mengikuti posisi periode yang dihitung                                                          |
| Periode                 | Format `yyyymm`, mis. `202312` untuk Desember 2023                                                                                  |

---

## 5. Segmentasi Portofolio (Berlaku untuk PD Netflow, PD Migration, dan LGD)

Sebelum perhitungan penurunan nilai kolektif dilakukan, seluruh pembiayaan **wajib dikelompokkan terlebih dahulu berdasarkan risiko kredit serupa**, misalnya:

- Jenis penggunaan (Modal Kerja, Konsumtif, Investasi)
- Sektor ekonomi
- Skala usaha (UMKM/Non-UMKM)
- Skema pembiayaan (murabahah, musyarakah, dll)

**Ketentuan sistem:**

- Sistem harus mendukung **multi-segmen** — setiap segmen memiliki PD sendiri (untuk Netflow maupun Migration) dan LGD sendiri (Expected Recoveries maupun Collateral Shortfall), dihitung secara terpisah/independen (bukan satu PD/LGD tunggal untuk seluruh portofolio).
- Contoh: jika BPRS menetapkan 3 kelompok (Modal Kerja, Konsumtif, Investasi), maka sistem menghasilkan 3 set PD Netflow, 3 set PD Migration, dan LGD masing-masing per kelompok.
- **Konfigurasi segmen bersifat dinamis** (Master Data) — dapat ditambah/diubah oleh user berwenang tanpa mengubah kode program (bukan hardcode).
- Jika data pada suatu segmen tidak mencukupi untuk menghasilkan LGD yang andal, sistem harus mendukung opsi **"LGD All Account"** (gabungan seluruh segmen menjadi satu perhitungan LGD).
- Setiap akun pembiayaan (`financing_accounts`) dipetakan ke tepat satu segmen aktif per periode (mapping dapat berubah antar periode, disimpan dengan histori).

---

## 6. Formula Perhitungan Utama

```
CKPN Total     = CKPN Individual + CKPN Kolektif
CKPN Kolektif  = PD x LGD x EAD          (dihitung per akun/segmen, lalu diagregasi)
```

### 6.1 CKPN Individual

Diterapkan pada kriteria pembiayaan NPF dengan bucket/kolektibilitas **N terbesar**.

```
CKPN Individual = Baki Debet
                   - Total Nilai Likuidasi Jaminan
                   - (Nilai Likuidasi Jaminan x Persentase Biaya Penjualan)
```

> Nilai N final, serta apakah "Persentase Biaya Penjualan" di sini sama dengan komponen biaya (mis. biaya lelang) yang dipakai pada LGD Collateral Shortfall, perlu dikonfirmasi agar parameter dapat digunakan bersama (shared parameter) — lihat Bab 12.

---

## 7. PD Netflow (Metode Detail)

### 7.1 Prinsip Umum

- Dihitung **per segmen risiko kredit** (Bab 5).
- Definisi default: **tunggakan > 360 hari (Bucket 14) DAN/ATAU dihapusbukukan**.
- Menggunakan **rolling window** historis yang **bergerak mengikuti posisi PD yang dihitung**, bukan window tetap.
- Panjang window bersifat **configurable per parameter** (disarankan 36/48/60 bulan; semakin panjang semakin stabil secara statistik).

**Pemisahan rentang data (dikonfirmasi user, 2026-08-18):**

Dengan contoh posisi perhitungan = **Desember 2026** dan window = **36 bulan**:

| Jenis data                  | Rentang                                                     | Keterangan                                                                     |
| --------------------------- | ----------------------------------------------------------- | ------------------------------------------------------------------------------ |
| Data outstanding per bucket | `202212` s/d `202512`                                       | Titik awal (N+1 titik) untuk menghitung perpindahan                            |
| Rate perpindahan per bucket | `202301` s/d `202612`                                       | Rate dihitung mulai periode pertama setelah data awal outstanding              |
| Rate `202601` s/d `202612`  | Proyeksi (rata-rata 6 bulan ke belakang: `202507`–`202512`) | Karena data aktual periode ini belum tersedia saat dihitung                    |
| Compound flow to loss       | `202301` s/d `202512`                                       | Dimulai `202301` (bukan `202212`), berakhir 1 periode sebelum proyeksi dimulai |
| PD Netflow final            | rata-rata compound flow `202301`–`202512`                   | Per bucket, per segmen                                                         |

> Ringkasan: data outstanding punya 1 titik awal ekstra (`N bulan + 1` titik) dibanding jumlah rate perpindahan, karena setiap rate membutuhkan dua titik outstanding (periode t-1 dan t). Compound flow dimulai dari periode rate pertama (`202301`) dan dirata-ratakan — **bukan** dimulai dari titik data outstanding pertama (`202212`).

### 7.2 Data yang Dibutuhkan

1. Data outstanding pembiayaan **bulanan**, per segmen, dikelompokkan ke bucket berdasarkan **hari tunggakan maksimum** (pokok **atau** bunga, mana yang lebih tinggi).
2. Data pembiayaan yang **dihapusbukukan** per bulan (termasuk dalam definisi default/Bucket 14).

### 7.3 Langkah Pengerjaan

**Langkah 1 — Pengumpulan Data**
Kumpulkan data pembiayaan berdasarkan hari tunggakan, data hapus buku, dan (jika relevan) data penerimaan pembayaran, minimal untuk rentang window yang ditetapkan (≥12 bulan, disarankan lebih panjang).

**Langkah 2 — Bucketing**
Kelompokkan outstanding ke bucket 1–14 per segmen per periode, termasuk kelompok hapus buku pada Bucket 14.

**Langkah 3 — Validasi Kualitas Data (Data Quality Check)**
Sistem **wajib** melakukan validasi otomatis berikut sebelum rate dihitung, dan menandai (flag) data yang tidak wajar untuk ditinjau ulang:

- **Bucket tidak boleh bernilai kosong/nol secara tidak wajar** pada rentang data yang digunakan (indikasi data hilang).
- **Outstanding bucket setelah perpindahan tidak boleh melebihi outstanding bucket sebelumnya pada bucket asal periode sebelumnya.**
  Contoh: outstanding **Bucket 2 posisi Januari 2021** tidak boleh lebih besar dari outstanding **Bucket 1 posisi Desember 2020** (karena Bucket 2 bulan berjalan hanya bisa berasal dari, paling banyak, seluruh outstanding Bucket 1 bulan sebelumnya).
  Jika kondisi ini terjadi → sistem menandai sebagai **anomali/exception** yang harus diteliti/dikonfirmasi user (bukan otomatis diblok, namun perlu approval/catatan sebelum lanjut ke perhitungan rate) — lihat FR-4.3.

**Langkah 4 — Hitung % Perpindahan Bucket (Transition Rate) + Proyeksi Forward**
Untuk setiap posisi (periode) dan setiap segmen:

```
Rate_perpindahan(bucket_i -> bucket_i+1, periode_t)
   = Outstanding bucket_i+1 pada periode_t
     / Outstanding bucket_i pada periode_t-1
```

- Rate ini menunjukkan **peningkatan risiko kredit** (perpindahan ke bucket lebih tinggi).
- **Proyeksi 1 bulan ke depan**: ditambahkan rate proyeksi yang dihitung dari **rata-rata rate perpindahan 6 bulan ke belakang** pada masing-masing posisi/bucket (rolling 6-month average), untuk melengkapi rangkaian data hingga posisi bulan yang dihitung.

**Langkah 5 — Hitung Compound Flow to Loss & PD**

```
Compound_Flow_to_Loss(bucket_i, periode_t)
   = Rate(bucket_i -> bucket_i+1, periode_t)
     x Rate(bucket_i+1 -> bucket_i+2, periode_t+1... dst mengikuti diagonal)
     x ... x Rate(bucket_13 -> bucket_14)

PD Netflow (bucket_i, segmen)
   = rata-rata seluruh nilai Compound_Flow_to_Loss(bucket_i, periode_t)
     untuk seluruh periode t dalam rolling window
```

Hasil akhir: **PD per bucket, per segmen**, disimpan sebagai snapshot per periode perhitungan.

### 7.4 Ringkasan Parameter yang Perlu Disetel (Configurable)

| Parameter                          | Nilai Dikonfirmasi / Default                                 | Keterangan                                                                        |
| ---------------------------------- | ------------------------------------------------------------ | --------------------------------------------------------------------------------- |
| Panjang rolling window             | **36 bulan** (default; configurable 24/48/60)                | Jumlah bulan data outstanding yang dipakai; data outstanding = window+1 titik     |
| Jumlah bulan proyeksi forward rate | **6 bulan** (rolling average ke belakang)                    | Proyeksi rate untuk periode yang belum ada data aktual                            |
| Rentang rate perpindahan aktual    | `periode_awal+1` s/d `posisi_perhitungan`                    | Otomatis dihitung dari window; contoh 36 bln di Desember 2026 → `202301`–`202612` |
| Rentang proyeksi forward rate      | 6 bulan terakhir window+1 s/d posisi perhitungan             | Contoh: `202601`–`202612` menggunakan rata-rata `202507`–`202512`                 |
| Rentang compound flow to loss      | Sama dengan rentang rate aktual, **tidak termasuk proyeksi** | Contoh: `202301`–`202512`                                                         |
| Ambang toleransi validasi data     | TBD                                                          | Lihat Bab 12 open item no. 11                                                     |

---

## 8. PD Migration (Metode Detail)

### 8.1 Prinsip Umum

- Dihitung **per segmen risiko kredit** (Bab 5), terpisah dari PD Netflow.
- Menggunakan **kualitas/kolektibilitas pembiayaan** (bukan bucket hari tunggakan) sebagai state migrasi.
- Definisi default untuk metode ini: **Hapus Buku (WO)** — berbeda dengan PD Netflow yang memakai >360 hari dan/atau WO.
- Data disusun secara **triwulanan**: posisi awal di 4 titik waktu per tahun (31 Maret, 30 Juni, 30 September, 31 Desember), masing-masing ditelusuri **1 tahun ke depan** ke posisi triwulan yang sama tahun berikutnya (mis. 31 Maret 2020 → 31 Maret 2021; 30 Juni 2020 → 30 Juni 2021; dst).
- **Rolling window**: mengikuti posisi PD yang dihitung — PD posisi Desember 2021 memakai data Desember 2020–Desember 2021 (contoh); PD posisi Januari 2022 bergeser ke Januari 2021–Januari 2022. Contoh hanya memakai 1 tahun data, namun disarankan menggunakan lebih banyak cohort/tahun bila data historis tersedia agar hasil lebih robust.

### 8.2 Data yang Dibutuhkan

1. Data outstanding pembiayaan per **kualitas/kolektibilitas**, pada 4 posisi triwulanan per tahun serta posisi yang sama 1 tahun berikutnya, untuk rekening yang sama (per segmen).
2. Data rekening yang **dihapusbukukan** selama 1 tahun periode tracing tsb.
3. Data **penerimaan pembayaran** (pelunasan/angsuran) dari rekening yang sama selama 1 tahun periode tracing.

### 8.3 Langkah Pengerjaan

**Langkah 1 — Pengumpulan Data**
Kumpulkan data pembiayaan (per segmen) untuk masing-masing dari 4 posisi triwulanan.

**Langkah 2 — Tracing / Cohort Tracking**
Untuk setiap rekening pada posisi awal, telusuri kondisinya 1 tahun kemudian, dan klasifikasikan ke salah satu outcome:

- **Tetap** pada kualitas yang sama,
- **Pindah** ke kualitas lain (naik/turun),
- **Dihapusbukukan (WO)** — dianggap default,
- **Lunas/dibayar** selama periode 1 tahun (exit dari populasi, dianggap "success"/bukan default).

**Langkah 3 — Hitung Persentase Migrasi (Migration Matrix)**

```
Rate_migrasi(kualitas_A -> kualitas_B, cohort)
   = Outstanding rekening kualitas_A awal yang menjadi kualitas_B akhir tahun
     / Total outstanding kualitas_A pada posisi awal
```

Matriks migrasi mencakup seluruh kombinasi kualitas asal → kualitas tujuan, termasuk 2 **state penyerap (absorbing state)**: **Hapus Buku** dan **Lunas**.

**Langkah 4 — Hitung PD per Kualitas**

```
PD Migration (kualitas_X, segmen)
   = Probabilitas outstanding kualitas_X pada posisi awal
     berakhir pada state "Hapus Buku" (default)
     dalam horizon 1 tahun, berdasarkan rata-rata matriks migrasi antar cohort/posisi
```

Jika tersedia >1 cohort dalam rolling window, PD final = rata-rata rate migrasi menuju state default dari seluruh cohort yang tersedia.

### 8.4 Ringkasan Parameter yang Perlu Disetel

| Parameter                          | Keterangan                                                    |
| ---------------------------------- | ------------------------------------------------------------- |
| Frekuensi posisi data              | Triwulanan (Maret, Juni, September, Desember)                 |
| Horizon tracing                    | 1 tahun ke depan per posisi                                   |
| Jumlah cohort/tahun yang digunakan | Default contoh 1 tahun; disarankan lebih banyak jika tersedia |
| Daftar kualitas/kolektibilitas     | Master data (default: 1–5)                                    |

---

## 9. LGD Expected Recoveries (Metode Detail)

### 9.1 Prinsip Umum

- Dihitung **per segmen** (atau **"LGD All Account"** jika data per segmen tidak mencukupi — opsi ini harus tersedia di sistem, dipilih per segmen).
- Rolling window historis: **5 tahun**, bergerak mengikuti posisi LGD yang dihitung (LGD posisi Januari tahun berjalan → data Januari 5 tahun sebelumnya s.d. posisi tsb).
- Mengandalkan **hasil penerimaan (recoveries)** atas pembiayaan yang telah dihapusbukukan.

### 9.2 Data yang Dibutuhkan

1. Data pembiayaan (per kategori/segmen) yang **dihapusbukukan per tahun**, minimal 5 tahun ke belakang.
2. Data **pembayaran kembali (recoveries)** secara **cash** atas pembiayaan yang telah dihapusbukukan tsb, per tahun.

### 9.3 Langkah Pengerjaan

1. Kumpulkan data pembiayaan yang dihapusbukukan per tahun, per kategori/segmen.
2. Kumpulkan data jumlah pembayaran kembali (recoveries) atas pembiayaan yang dihapusbukukan tsb (saat/sejak dihapusbuku).
3. Hitung **persentase recovery** untuk masing-masing posisi/tahun:

```
Recovery_Rate(tahun_t, segmen) = Total Recoveries(tahun_t) / Total Pembiayaan Dihapusbuku(tahun_t)
```

4. Hitung **rata-rata recovery (expected recoveries)** dari seluruh tahun dalam window 5 tahun:

```
Expected_Recovery_Rate(segmen) = rata-rata Recovery_Rate(tahun_t) untuk t dalam window
```

5. Hitung LGD:

```
LGD Expected Recoveries (segmen) = 1 - Expected_Recovery_Rate(segmen)
```

---

## 10. LGD Collateral Shortfall (Metode Detail)

### 10.1 Prinsip Umum

- Dihitung **per segmen** (atau **"LGD All Account"** bila diperlukan).
- Mengandalkan penerimaan dari **hasil penjualan/eksekusi agunan** yang telah/akan dikuasai oleh BPRS.
- Berlaku untuk akun dengan **kualitas 5 (macet)** yang agunannya **telah dieksekusi** atau **akan diselesaikan melalui penjualan agunan**, serta akun **hapus buku (WO)** yang penyelesaiannya melalui penjualan agunan namun **belum dieksekusi**.

### 10.2 Data yang Dibutuhkan

1. Data rekening pembiayaan posisi perhitungan dengan **kualitas 5** (agunan telah dieksekusi ATAU akan diselesaikan lewat penjualan agunan), ditambah data **WO** yang penyelesaiannya melalui penjualan agunan namun belum dieksekusi.
2. Data **nilai penjualan agunan**, sudah memperhitungkan biaya-biaya terkait (mis. biaya lelang) — untuk agunan yang belum dieksekusi, digunakan **nilai estimasi agunan**.

> **Penting (ditegaskan oleh kebutuhan awal):** estimasi nilai agunan yang belum dieksekusi secara riil **harus dilakukan secara memadai** — tidak boleh _overvalue_ maupun _undervalue_. Sistem sebaiknya menyediakan mekanisme approval/validasi atas nilai estimasi agunan ini (bukan hanya input bebas), misal role approval khusus untuk penilai/appraisal.

### 10.3 Langkah Pengerjaan

1. Kumpulkan data rekening pembiayaan sesuai kriteria (kualitas 5 dan/atau WO menunggu penjualan agunan) per posisi penilaian.
2. Kumpulkan data nilai penjualan agunan (aktual jika sudah dieksekusi, estimasi jika belum), net setelah biaya-biaya penjualan/lelang.
3. Hitung **shortfall**:

```
Shortfall = Baki Debet (belum lunas) - Nilai Penjualan Agunan (net biaya)
```

4. Hitung LGD:

```
LGD Collateral Shortfall = Shortfall / Baki Debet (belum lunas)
```

---

## 11. Kombinasi PD & LGD dalam CKPN Kolektif — Perlu Kebijakan/Konfirmasi

Karena sistem mendukung **2 metode PD** (Netflow, Migration) dan **2 metode LGD** (Expected Recoveries, Collateral Shortfall), diperlukan **kebijakan final** tentang bagaimana nilai-nilai ini dikombinasikan menjadi satu angka **CKPN Kolektif = PD x LGD x EAD** per akun/segmen. Sistem akan dibangun agar **fleksibel** menampung skema berikut (final skema mengikuti kebijakan BPRS, dikonfirmasi di Bab 12):

**Opsi PD:**

- (a) Gunakan salah satu metode sebagai _primary_ (mis. Netflow untuk seluruh portofolio kolektif, Migration sebagai pembanding/validasi), atau
- (b) PD final = kombinasi/rata-rata tertimbang dari kedua metode.

**Opsi LGD (usulan default, mengikuti karakteristik masing-masing metode):**

- Gunakan **LGD Collateral Shortfall** untuk akun dengan agunan yang sedang/akan dieksekusi (kualitas 5 atau WO menunggu penjualan agunan).
- Gunakan **LGD Expected Recoveries** untuk akun WO lain (tanpa proses eksekusi agunan spesifik) atau sebagai default umum per segmen.
- Perlu dikonfirmasi apakah pendekatan ini yang dimaksud, atau ada aturan lain (mis. LGD final = kombinasi tertimbang keduanya per segmen).

Desain data model (Bab 13) menyimpan **PD per metode** dan **LGD per metode** secara terpisah per akun/segmen/periode, dengan kolom tambahan `pd_method_used` dan `lgd_method_used` pada tabel hasil final, sehingga kebijakan kombinasi dapat diterapkan/diubah tanpa mengubah struktur data.

---

## 12. Open Items / Perlu Dikonfirmasi Sebelum Development

**Item yang sudah dikonfirmasi (2026-08-18):**
- ✅ Mekanisme rolling window PD Netflow: pemisahan rentang data outstanding, rate perpindahan, dan compound flow → lihat Bab 7.1 & 7.4
- ✅ Panjang rolling window default: **36 bulan**
- ✅ Proyeksi forward rate: rata-rata **6 bulan** ke belakang
- ✅ Compound flow to loss: dimulai dari periode rate pertama, **tidak termasuk** periode proyeksi
- ✅ Alur sistem end-to-end (7 langkah, beserta dependensi) → lihat Bab 12a
- ✅ Sumber data pembiayaan: **upload Excel per periode** (`financing_period_uploads`)
- ✅ Informasi akun tambahan: `kdprd`, `pokpby`, `kdloc`, `sektor_ekonomi`, `jenis_penggunaan` (1=Modal Kerja, 2=Investasi, 3=Konsumsi), `tgleff`, `tglexp`
- ✅ **Rentang bucket 2–13**: bertahap 30 hari — B2:1-30, B3:31-60, B4:61-90, B5:91-120, B6:121-150, B7:151-180, B8:181-210, B9:211-240, B10:241-270, B11:271-300, B12:301-330, B13:331-360, B14:>360
- ✅ **Kriteria CKPN Individual**: kolektibilitas NPL 3, 4, 5 (kurang lancar, diragukan, macet) **DAN** akun dengan outstanding terbesar (N akun teratas per segmen) — parameter N dikonfigurasi di `calculation_parameters`
- ✅ **Kebijakan kombinasi PD**: keduanya (Netflow & Migration) selalu dihitung; user/approver memilih metode yang dipakai sebagai PD final saat approval per periode (kolom `pd_method_used` di `ckpn_collective_result`)

**Item yang masih terbuka:**

1. Nilai **N** (jumlah akun outstanding terbesar) untuk kriteria CKPN Individual — berapa jumlah akun per segmen yang diambil?
2. Apakah biaya penjualan pada rumus CKPN Individual = parameter yang sama dengan biaya penjualan pada LGD Collateral Shortfall (shared parameter)?
4. Daftar segmen risiko final (jenis penggunaan / sektor ekonomi / skala usaha / skema pembiayaan) — akan jadi Master Data, minta contoh konkret dari BPRS.
5. Daftar kualitas/kolektibilitas final untuk PD Migration (asumsi 1–5, perlu dikonfirmasi label & definisi tiap kualitas).
6. Mekanisme/role approval untuk nilai **estimasi agunan** pada LGD Collateral Shortfall (agar tidak overvalue/undervalue).
7. Apakah EAD memerlukan adjustment selain baki debet outstanding (mis. undisbursed commitment).
8. Struktur role & workflow approval hasil perhitungan (approval berjenjang?).
9. Ambang toleransi/aturan tindak lanjut saat validasi data PD Netflow menghasilkan anomali (Bab 7.3) — apakah proses dihentikan otomatis atau hanya diberi warning untuk ditinjau manual.

---

## 12a. Alur Sistem End-to-End (Dikonfirmasi user, 2026-08-18)

Urutan proses per periode penilaian CKPN:

```
1. Upload Data
   └─ Upload file Excel data pembiayaan per periode (financing_period_uploads)
   └─ Sistem validasi & simpan ke DB; akun baru otomatis dibuat di financing_accounts

2. Penetapan Periode Penilaian CKPN
   └─ User menetapkan periode (yyyymm) sebagai posisi perhitungan
   └─ Sistem mengidentifikasi daftar pembiayaan:
       ├─ CKPN Individual → akun NPF dengan bucket/kolektibilitas N terbesar
       └─ CKPN Kolektif   → semua akun lain (sisanya)

3. Hitung PD (opsional — user memilih metode)
   ├─ PD Netflow  → PdNetflowCalculator (Bab 7)
   │   └─ Data outstanding bucket: posisi_perhitungan-window s/d posisi_perhitungan-1
   │   └─ Rate perpindahan: periode+1 s/d posisi_perhitungan (aktual + proyeksi 6bln)
   │   └─ Compound flow: periode+1 s/d posisi_perhitungan-1 (tidak termasuk proyeksi)
   │   └─ PD = rata-rata compound flow per bucket per segmen
   └─ PD Migration → PdMigrationCalculator (Bab 8)
       └─ Matriks migrasi kualitas triwulanan dalam rolling window

4. Hitung LGD (keduanya dihitung, tidak opsional)
   ├─ LGD Expected Recoveries  → LgdExpectedRecoveriesCalculator (Bab 9)
   └─ LGD Collateral Shortfall → LgdCollateralShortfallCalculator (Bab 10)
       └─ Nilai estimasi agunan yang belum dieksekusi wajib melalui approval Appraisal

5. Hitung CKPN Individual
   └─ CkpnIndividualCalculator (Bab 6.1)
   └─ Dapat dihitung setelah penetapan periode (step 2), tidak bergantung pada PD/LGD

6. Hitung CKPN Kolektif
   └─ CkpnCollectiveCalculator (Bab 11): CKPN Kolektif = PD × LGD × EAD
   └─ Baru dapat dihitung setelah PD periode tersebut selesai (step 3)
   └─ Gunakan PD Netflow ATAU PD Migration (sesuai kebijakan per segmen — Bab 12 open item)
   └─ LGD dipilih per akun: CS untuk kol-5/WO agunan, ER untuk WO lainnya (default)

7. Konsolidasi & Approval
   └─ CKPN Total = CKPN Individual + CKPN Kolektif
   └─ Workflow: Draft → In Progress → Completed → Approved (FR-11)
```

**Dependensi antar langkah:**

- Step 5 (Individual) tidak bergantung step 3 (PD) — bisa paralel.
- Step 6 (Kolektif) **wajib** menunggu step 3 (PD) selesai untuk periode yang sama.
- Step 4 (LGD) tidak bergantung step 3 (PD) — bisa paralel dengan step 3 & 5.

---

## 13. Arsitektur Teknis

### 13.1 Tech Stack

| Layer             | Teknologi                                                                                                |
| ----------------- | -------------------------------------------------------------------------------------------------------- |
| Backend Framework | Laravel 13                                                                                               |
| Admin Panel / UI  | Filament 4.x                                                                                             |
| Database          | MySQL 8.x                                                                                                |
| CSS Framework     | Tailwind CSS (via Filament)                                                                              |
| Queue/Job         | Laravel Queue (untuk proses batch bulanan yang berat: bucketing, transition matrix, compound flow, dsb.) |
| Scheduler         | Laravel Scheduler (opsional, untuk trigger otomatis)                                                     |

### 13.2 Prinsip Arsitektur

- **Batch processing per periode & per segmen**: setiap "Run" perhitungan mengacu ke 1 periode (`yyyymm`), dan diproses per segmen secara independen (dapat diparalelkan via queue jobs).
- **Snapshot-based & immutable**: setiap hasil (outstanding per bucket/kualitas, rate perpindahan, matriks migrasi, PD, LGD, EAD, CKPN) disimpan sebagai snapshot per periode+segmen+metode, tidak berubah walau data master berubah kemudian (audit trail).
- **Versioning parameter**: seluruh parameter (rentang bucket, panjang rolling window, daftar segmen, daftar kualitas, persentase biaya penjualan, dll.) disimpan dengan efektif periode.
- **Modular formula engine (Strategy Pattern)**: PD Netflow, PD Migration, LGD Expected Recoveries, LGD Collateral Shortfall masing-masing diimplementasikan sebagai kelas terpisah yang mengikuti interface (`PdCalculationMethodInterface`, `LgdCalculationMethodInterface`) sehingga dapat dijalankan berdampingan, diaktifkan/nonaktifkan per segmen, dan mudah ditambah metode baru di masa depan.
- **Data quality gate**: proses perhitungan PD Netflow menyertakan tahap validasi (Bab 7.3) sebagai _pipeline step_ terpisah sebelum kalkulasi rate, dengan hasil validasi (anomali) disimpan & dapat ditinjau/diapprove user sebelum lanjut ke tahap berikut.

---

## 14. Modul Fungsional (Functional Requirements)

### FR-1: Master Data Management

- Master **Segmen Risiko Kredit** (kode, nama, deskripsi, status aktif, efektif periode).
- Master **Bucket** (kode, rentang hari tunggakan, flag `is_default_bucket`/Bucket 14).
- Master **Kualitas/Kolektibilitas** (kode 1–5, nama, definisi, flag `is_absorbing_state` untuk WO/Lunas).
- Master **Parameter Perhitungan** per metode (versioned per periode): panjang rolling window PD Netflow, bulan proyeksi forward (default 6), panjang window LGD (default 5 tahun), dsb.
- Master **Persentase Biaya Penjualan/Lelang Agunan** (per jenis jaminan atau global).

### FR-2: Mapping Segmen ke Akun

- Pemetaan setiap akun pembiayaan ke 1 segmen aktif per periode, dengan histori perubahan segmen antar periode.

### FR-3: Data Input / Import

- Import outstanding pembiayaan bulanan (baki debet, hari tunggakan pokok, hari tunggakan bunga, status WO) per akun.
- Import data outstanding per kualitas/kolektibilitas untuk kebutuhan PD Migration (posisi triwulanan).
- Import data hapus buku (per bulan untuk Netflow; per tahun untuk LGD Expected Recoveries; per posisi untuk Migration).
- Import data recoveries (penerimaan cash atas WO).
- Import data nilai penjualan/estimasi agunan.
- Import data jaminan per akun (untuk CKPN Individual & LGD Collateral Shortfall).
- Validasi format saat import (periode `yyyymm`, kelengkapan kolom wajib, duplikasi).

### FR-4: PD Netflow Engine

- FR-4.1: Bucketing outstanding per segmen per periode (Bucket 1–14, termasuk WO ke Bucket 14).
- FR-4.2: Perhitungan rolling window otomatis mengikuti posisi periode yang dihitung.
- FR-4.3: **Validasi kualitas data** — deteksi bucket kosong tidak wajar & deteksi outstanding bucket tujuan melebihi outstanding bucket asal periode sebelumnya; hasil validasi disimpan sebagai daftar anomali yang dapat ditinjau/diberi catatan oleh user sebelum lanjut ke perhitungan rate.
- FR-4.4: Perhitungan % perpindahan bucket per periode, termasuk proyeksi forward dari rata-rata 6 bulan ke belakang.
- FR-4.5: Perhitungan compound flow to loss & PD Netflow final per bucket per segmen (rata-rata rolling window).
- FR-4.6: Simpan seluruh rate, matriks pergerakan, dan hasil sebagai snapshot per periode+segmen (audit trail).

### FR-5: PD Migration Engine

- FR-5.1: Susun data outstanding per kualitas untuk 4 posisi triwulanan per segmen.
- FR-5.2: Tracing/cohort tracking rekening selama 1 tahun ke depan per posisi (klasifikasi: tetap/pindah kualitas/WO/lunas).
- FR-5.3: Bangun matriks migrasi kualitas → kualitas (termasuk state WO & Lunas) per cohort.
- FR-5.4: Hitung PD Migration per kualitas per segmen (rata-rata seluruh cohort dalam rolling window).
- FR-5.5: Simpan matriks migrasi & PD hasil sebagai snapshot per periode+segmen.

### FR-6: LGD Expected Recoveries Engine

- FR-6.1: Kumpulkan data WO per tahun & recoveries per tahun, per segmen (atau all-account).
- FR-6.2: Hitung recovery rate per tahun, expected recovery rate (rata-rata 5 tahun rolling), dan LGD = 1 - recovery rate.
- FR-6.3: Simpan hasil sebagai snapshot per periode+segmen.

### FR-7: LGD Collateral Shortfall Engine

- FR-7.1: Identifikasi akun kualitas 5 (agunan dieksekusi/akan dieksekusi) dan akun WO menunggu penjualan agunan.
- FR-7.2: Ambil/hitung nilai penjualan agunan (aktual atau estimasi net biaya).
- FR-7.3: Hitung shortfall & LGD Collateral Shortfall per akun/segmen.
- FR-7.4: Mekanisme approval untuk nilai estimasi agunan yang belum dieksekusi (mencegah overvalue/undervalue).

### FR-8: EAD Calculation

- EAD dari baki debet outstanding per akun per periode perhitungan (perlu dikonfirmasi bila ada adjustment tambahan).

### FR-9: CKPN Individual Engine

- Identifikasi akun NPF kriteria bucket/kolektibilitas N terbesar, hitung sesuai formula Bab 6.1, agregasi per periode.

### FR-10: Kombinasi PD & LGD → CKPN Kolektif

- Terapkan kebijakan kombinasi PD (Netflow/Migration) dan LGD (Expected Recoveries/Collateral Shortfall) sesuai Bab 11 (konfigurasi kebijakan, bukan hardcode) untuk menghasilkan PD final dan LGD final per akun/segmen, lalu hitung CKPN Kolektif = PD x LGD x EAD.

### FR-11: Konsolidasi & Perhitungan Final

- CKPN Total = CKPN Individual + CKPN Kolektif per periode.
- Workflow status Run: Draft → In Progress → Completed → Approved.

### FR-12: Reporting & Dashboard

- Ringkasan CKPN per periode (Total, Individual, Kolektif) dan per segmen.
- Detail per bucket (Netflow): outstanding, rate perpindahan, compound flow to loss, PD.
- Detail per kualitas (Migration): matriks migrasi, PD.
- Detail LGD: recovery rate historis (Expected Recoveries), shortfall detail (Collateral Shortfall).
- Daftar anomali data quality (dari FR-4.3) beserta status tindak lanjut.
- Export ke Excel/PDF.

### FR-13: Audit Trail & Log

- Log user/waktu setiap Run perhitungan, snapshot parameter yang dipakai, dan status approval — data tidak dapat diubah setelah periode berstatus Completed/Approved.

### FR-14: User & Role Management

- Role minimal: Admin/Superadmin, Risk Analyst, Appraisal/Approval Nilai Agunan (khusus FR-7.4), Approver Hasil CKPN, Viewer.

---

## 15. Data Model (Draft ERD — Level Konsep, Diperluas)

```
-- Master & Konfigurasi
risk_segments                    -- master segmen risiko kredit
financing_account_segment_map    -- histori pemetaan akun ke segmen per periode
buckets                          -- master bucket 1-14
quality_grades                   -- master kualitas/kolektibilitas 1-5 (+ absorbing state flag)
calculation_parameters           -- parameter versioned per metode per periode
collateral_types                 -- jenis jaminan + persentase biaya penjualan/lelang

-- Data Transaksional
financing_accounts               -- master akun pembiayaan
financing_outstanding_monthly    -- outstanding bulanan per akun (hari tunggakan pokok/bunga, bucket, flag WO)
financing_outstanding_quarterly  -- outstanding per akun per kualitas, posisi triwulanan (utk Migration)
writeoff_data                    -- data hapus buku (bulanan utk Netflow, tahunan utk LGD-ER, per posisi utk Migration)
recoveries_data                  -- penerimaan cash atas pembiayaan WO (utk LGD Expected Recoveries)
collateral_sales_data            -- nilai penjualan/estimasi agunan (utk LGD Collateral Shortfall & CKPN Individual)
collaterals                      -- master jaminan per akun (nilai likuidasi)

-- Hasil Perhitungan PD Netflow
pd_netflow_bucket_movement       -- rate perpindahan bucket per segmen per periode
pd_netflow_compound_rate         -- compound flow to loss per bucket per segmen per periode
pd_netflow_result                -- PD final per bucket per segmen per periode
data_quality_anomalies           -- daftar anomali validasi data (FR-4.3) & status tindak lanjut

-- Hasil Perhitungan PD Migration
pd_migration_matrix              -- matriks migrasi kualitas->kualitas per segmen per cohort
pd_migration_result               -- PD final per kualitas per segmen per periode

-- Hasil Perhitungan LGD
lgd_expected_recoveries_result     -- recovery rate per tahun & LGD final per segmen per periode
lgd_collateral_shortfall_result    -- shortfall & LGD final per akun/segmen per periode

-- Hasil Konsolidasi CKPN
ckpn_individual_result            -- hasil CKPN Individual per akun per periode
ckpn_collective_result            -- PD final, LGD final, EAD, CKPN Kolektif per akun/segmen per periode (+ pd_method_used, lgd_method_used)
ckpn_summary                      -- ringkasan CKPN Total per periode
calculation_run_log               -- audit trail proses perhitungan & approval
```

> Detail kolom, tipe data, index, dan relasi akan dibuatkan pada dokumen **Technical Design / Database Schema** terpisah setelah PRD ini disepakati.

---

## 16. Non-Functional Requirements

- **Performansi:** proses batch bulanan (seluruh segmen, seluruh metode PD & LGD) harus selesai dalam waktu wajar; direkomendasikan diparalelkan per segmen via queue jobs.
- **Auditability:** seluruh hasil & parameter yang dipakai harus dapat ditelusuri ulang (reproducible), termasuk anomali data quality dan siapa yang menindaklanjuti.
- **Skalabilitas:** database & job harus mampu menangani rolling window hingga 60 bulan (Netflow) atau 5 tahun (LGD Expected Recoveries) x jumlah akun tanpa degradasi performa signifikan.
- **Keamanan:** akses ke modul perhitungan, parameter, dan approval nilai agunan dibatasi berdasarkan role (Filament Shield atau setara).
- **Reliability:** proses perhitungan sebagai job idempotent, dapat di-retry tanpa duplikasi data.

---

## 17. Rencana Tahapan Pengembangan (Usulan)

| Fase   | Cakupan                                                                                      |
| ------ | -------------------------------------------------------------------------------------------- |
| Fase 1 | Master data (segmen, bucket, kualitas, parameter), mapping akun ke segmen, import data dasar |
| Fase 2 | Bucketing engine + PD Netflow engine (termasuk validasi data quality)                        |
| Fase 3 | CKPN Individual engine                                                                       |
| Fase 4 | PD Migration engine (matriks migrasi & tracing cohort)                                       |
| Fase 5 | LGD Expected Recoveries engine                                                               |
| Fase 6 | LGD Collateral Shortfall engine (+ workflow approval nilai agunan)                           |
| Fase 7 | Kombinasi PD & LGD → CKPN Kolektif, konsolidasi CKPN Total                                   |
| Fase 8 | Reporting/dashboard, audit trail, role & approval workflow                                   |
| Fase 9 | UAT, perbaikan, go-live                                                                      |

---

_Dokumen ini akan diperbarui lebih lanjut setelah open items pada Bab 12 dikonfirmasi, khususnya kebijakan kombinasi PD & LGD final dan detail rentang bucket 2–13._
