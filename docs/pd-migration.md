**Dokumentasi Logika Sistem Kalkulasi PD Migration (PSAK 414)**

Parameter utama yang dibutuhkan oleh sistem adalah **Periode Target** dari posisi Baki Debet / EAD yang ingin dihitung, menggunakan format `YYYYMM` (contoh: `202608`). Sistem di _layer backend_ atau _Stored Procedure_ akan secara otomatis menentukan Kuartal Acuan (_Anchor Quarter_) dan merangkai 4 blok waktu matriks mundur ke belakang.

**1. Logika Penentuan Kuartal Acuan (Anchor Quarter)**

Sistem mengekstrak digit bulan (MM) dari parameter `YYYYMM` target. Karena data pembentuk _Probability of Default_ (PD) harus menggunakan kuartal yang sudah berstatus tutup buku, gunakan algoritma pemetaan bulan target ke bulan acuan (T) berikut:

- Jika Bulan Target = **01, 02, 03** ➔ Bulan Acuan (T) = **12** (Tahun sebelumnya: `YYYY - 1`)
- Jika Bulan Target = **04, 05, 06** ➔ Bulan Acuan (T) = **03** (Tahun berjalan: `YYYY`)
- Jika Bulan Target = **07, 08, 09** ➔ Bulan Acuan (T) = **06** (Tahun berjalan: `YYYY`)
- Jika Bulan Target = **10, 11, 12** ➔ Bulan Acuan (T) = **09** (Tahun berjalan: `YYYY`)

**2. Generator Parameter Rentang Waktu (Data Setting)**

Setelah `Bulan Acuan` dan `Tahun Acuan` didapatkan dalam format `YYYYMM`, sistem membangun _array_ 4 periode waktu untuk matriks evaluasi tahunan (jarak 12 bulan per matriks, mundur setiap 3 bulan).

_Contoh simulasi jika input Target Periode = `202608` (Maka Acuan T = `202606`):_

| ID Matriks   | Parameter Periode Awal (T-12 bulan) | Parameter Periode Akhir (Evaluasi Posisi) |
| :----------- | :---------------------------------- | :---------------------------------------- |
| **M1** (T)   | `202506` (Acuan - 1 Tahun)          | `202606` (Acuan)                          |
| **M2** (T-3) | `202503`                            | `202603`                                  |
| **M3** (T-6) | `202412`                            | `202512`                                  |
| **M4** (T-9) | `202409`                            | `202509`                                  |

**3. Algoritma Kalkulasi Sistem**

- **Iterasi Ekstraksi (Looping 4x):** _Query database_ dieksekusi 4 kali berdasarkan _array_ rentang waktu `YYYYMM` di atas. Pada setiap iterasi, sistem melakukan _Left Join_ antara baki debet rekening pada "Periode Awal" dengan posisinya di "Periode Akhir".
- **Klasifikasi Status Akhir:** Kelompokkan baki debet akhir ke dalam _bucket_:
    - Kualitas 1 s.d. 5.
    - Hapus Buku (jika rekening sudah di-_write-off_ dalam rentang 1 tahun tersebut).
    - Pembayaran (selisih penurunan baki debet atau pelunasan).
- **Kalkulasi Total Persentase Default (Per Matriks):** Hitung bobot kerugian. Persentase "TOTAL" didapat dari menjumlahkan probabilitas nasabah yang turun menjadi Kualitas 5, Hapus Buku, dan persentase gagal bayar lainnya sesuai ketetapan parameter entitas.
- **Agregasi Rata-rata:** Simpan hasil dari 4 iterasi ke dalam _temporary table_ atau _collection_. Jumlahkan persentase _default_ dari keempat matriks untuk masing-masing Kualitas awal, lalu bagi 4. Nilai konstan ini menjadi `PD_Rate`.
- **Eksekusi CKPN Target:** Tarik data nominatif (_Outstanding_) berjalan berdasarkan Periode Target asli (contoh: `202608`). Kalikan nominal tiap rekening dengan `PD_Rate` (sesuai kualitas berjalan) dan persentase `LGD`.

---

**Perencanaan & To-Do List Progress**

- [ ] **Parsing Parameter:** Buat fungsi/method untuk mengekstrak string `YYYYMM` menjadi komponen Tahun dan Bulan untuk kalkulasi Anchor Quarter.
- [ ] **Konstruktor Array Matriks:** Tulis _logic loop_ yang otomatis mengenerate 4 pasang _array_ `YYYYMM` dengan interval minus 3 bulan dari kuartal acuan.
- [ ] **Penyesuaian Skema Database:** Pastikan _index_ tabel penyimpanan data riwayat pembiayaan mendukung _query_ agregasi menggunakan string/integer format `YYYYMM`.
- [ ] **Testing Validasi Periode:** Jalankan skenario _Unit Testing_ menggunakan input `202601` untuk memastikan sistem mundur dengan benar ke matriks `202512`, `202412`, dst.
