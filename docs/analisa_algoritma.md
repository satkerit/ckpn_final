# Analisa dan Algoritma Perhitungan PD Netflow

Berdasarkan peninjauan pada file perhitungan PD Netflow (`pd_netflow_rumus_2.xlsx`), berikut adalah rangkuman analisis beserta rumusan baku yang dapat diimplementasikan ke dalam sistem (backend/database).

## 1. Konsep Dasar

Perhitungan Probability of Default (PD) dengan pendekatan Netflow dilakukan dalam dua tahapan utama:

1. **Transition Rate (Persentase Pergerakan):** Menghitung probabilitas pergerakan saldo/fasilitas dari satu _Bucket_ ke _Bucket_ berikutnya yang lebih buruk pada periode bulan berikutnya.
2. **Compound Flow to Loss:** Mengakumulasikan/mengalikan probabilitas pergerakan secara diagonal (cohort) melintasi waktu hingga mencapai titik _Loss_ (misal: Bucket 14 / >360 hari + WO).

## 2. Rumus Matematis Baku

### A. Transition Rate (TR)

Untuk mencegah nilai rasio lebih dari 100% (1.0), digunakan batas atas (_cap_) sebesar 1.

**Rumus:**
`TR(b, t) = MIN( 1, OS(b+1, t) / OS(b, t-1) )`

**Keterangan:**

- `TR(b, t)` = _Transition Rate_ dari _Bucket_ `b` ke `b+1` pada bulan `t`.
- `OS(b+1, t)` = _Outstanding_ di _Bucket_ tujuan (`b+1`) pada bulan ini (`t`).
- `OS(b, t-1)` = _Outstanding_ di _Bucket_ asal (`b`) pada bulan lalu (`t-1`).

### B. Compound Flow to Loss (CFL)

Metode yang digunakan adalah perkalian probabilitas secara beruntun (_compound_) melintasi waktu dan bucket secara diagonal.

**Rumus:**
`CFL(b, t) = Π (k=b hingga 13) TR(k, t + (k - b))`

**Keterangan:**

- `CFL(b, t)` = _Compound Flow to Loss_ untuk fasilitas di _Bucket_ `b` pada perhitungan bulan `t`.
- `Π` = Perkalian beruntun.
- `k` = Indeks _bucket_ yang bergerak dari `b` hingga 13 (asumsi _Bucket_ 14 adalah _Loss_).
- `t + (k - b)` = Pergeseran bulan secara maju (diagonal) seiring dengan pergerakan _bucket_.

## 3. Algoritma (Pseudocode) untuk Sistem

Algoritma berikut dirancang agar dinamis terhadap berapapun jumlah periode (bulan) yang tersedia di dalam database.

```python
# PARAMETER INPUT DARI SISTEM:
# OS[bucket][periode] -> Array/Matriks Outstanding
# total_bucket = 14 (Dimana bucket 14 adalah status Loss/WO)
# total_periode = N (Jumlah bulan histori data yang dianalisis)

# ==========================================
# TAHAP 1: Hitung Matriks Transition Rate (TR)
# ==========================================
TR = matrix_kosong(baris=13, kolom=total_periode)

Untuk b dari 1 sampai 13:
    Untuk t dari 2 sampai total_periode: # Mulai dari bulan ke-2 (butuh data t-1)

        # Validasi pembagian dengan nol (Division by Zero)
        Jika OS[b][t-1] == 0 atau OS[b][t-1] == NULL:
            TR[b][t] = 0
        Lainnya:
            rate = OS[b+1][t] / OS[b][t-1]
            TR[b][t] = MIN(1.0, rate) # Batasi maksimal 1 (100%)


# ==========================================
# TAHAP 2: Hitung Matriks Compound Flow (CFL)
# ==========================================
CFL = matrix_kosong(baris=13, kolom=total_periode)

Untuk b dari 1 sampai 13:
    Untuk t dari 2 sampai total_periode:
        compound_rate = 1.0

        # Lakukan perkalian diagonal maju mengikuti pergerakan waktu
        Untuk k dari b sampai 13:
            periode_proyeksi = t + (k - b)

            # Cek apakah periode proyeksi belum melewati batas data historis
            Jika periode_proyeksi <= total_periode:
                compound_rate = compound_rate * TR[k][periode_proyeksi]
            Lainnya:
                # Out of bounds: Data bulan depannya belum terjadi
                # Sistem menghentikan kalkulasi untuk titik ini
                compound_rate = NULL
                Hentikan_Loop_Ini (Break)

        CFL[b][t] = compound_rate
```

## 4. Poin Validasi Penting dalam Pengembangan Sistem

1. **Division by Zero Handling:** Sistem wajib memiliki _error handling_ apabila _Outstanding_ di bulan sebelumnya bernilai 0 untuk mencegah aplikasi _crash_.
2. **Pembatasan Maksimal (Capping):** Beberapa data _Outstanding_ bisa bertambah karena anomali atau koreksi data. Oleh karena itu, _Transition Rate_ harus selalu di-_cap_ maksimal 1 (100%) menggunakan fungsi `MIN()`.
3. **Data Out of Bounds (Pergeseran Bulan Belum Terjadi):** Pada metode cohort/diagonal, bulan-bulan terbaru tidak akan memiliki perhitungan _Compound Flow_ yang penuh hingga _Bucket Loss_ (karena data bulan-bulan ke depannya belum ada). Sistem harus diatur untuk mereturn `Null` atau mengabaikan perhitungan (menjadi area kosong) pada matriks ujung kanan bawah, atau menggunakan pendekatan proyeksi rata-rata (jika disyaratkan oleh tim bisnis).
