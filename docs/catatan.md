# Pedoman Teknis Perhitungan CKPN (PSAK 414) dan PPKA (POJK 24/2024)

Dokumen ini berisi standar operasional dan logika metodologi perhitungan **CKPN (Cair/Penyisihan Kerugian Penurunan Nilai)** berbasis **PSAK 414 / SAK Entitas Privat** serta penyelarasan terhadap **POJK No. 24 Tahun 2024 (PPKA)** untuk instrumen pembiayaan syariah.

---

## 1. Parameter Utama Perhitungan CKPN

Perhitungan CKPN menggunakan formula dasar statistik kerugian ekspektasian (*Expected Loss*):

$$
\text{CKPN} = \text{EAD} \times \text{PD Netflow} \times \text{LGD}
$$

---

## 2. Penentuan Exposure at Default (EAD) Berdasarkan Akad

Exposure at Default (EAD) ditentukan berdasarkan klasifikasi akuntansi neraca di mana instrumen keuangan berbentuk piutang (*Dain*) masuk dalam cakupan PSAK 414.

| Jenis Akad           | Status Pembiayaan          | Klasifikasi Neraca                 | Komponen EAD (PSAK 414)                                                               |
| :------------------- | :------------------------- | :--------------------------------- | :------------------------------------------------------------------------------------ |
| **Murabahah**  | Semua*Bucket*            | Piutang Murabahah                  | $\text{Sisa Pokok} + \text{Tunggakan Margin Jatuh Tagih}$                           |
| **Multijasa**  | Semua*Bucket*            | Piutang Multijasa                  | $\text{Sisa Pokok} + \text{Tunggakan Ujrah Jatuh Tagih}$                            |
| **Musyarakah** | Lancar / Belum Jatuh Tempo | Investasi Musyarakah (*Syirkah*) | *Out of Scope* PSAK 414 $\rightarrow$ Dihitung via PPKA OJK / Impairment PSAK 459 |
| **Musyarakah** | Menunggak / Jatuh Tempo    | Piutang Musyarakah                 | $\text{Sisa Modal Syirkah} + \text{Tunggakan Bagi Hasil Jatuh Tagih}$               |
| **IMBT**       | Lancar / Belum Jatuh Tagih | Aset Perolehan IMBT (Aset Fisik)   | *Out of Scope* PSAK 414 $\rightarrow$ Impairment Aset Tetap PSAK 407              |
| **IMBT**       | Menunggak / Jatuh Tagih    | Piutang Ujrah / Sewa               | $\text{Tunggakan Pokok / Piutang Sewa Jatuh Tagih}$ *(Sesuai POJK 24/2024)*       |

---

## 3. Penetapan Populasi Historis & Fleksibilitas PD Netflow Dinamis

Perhitungan Probability of Default (PD) menggunakan metode **Roll Rate / Migration Matrix** bulanan ($T_{t-1} \to T_t$).

### A. Catatan Perubahan: Periode Observasi Dinamis (Dynamic Lookback Window)

Modul perhitungan PD Netflow dirancang secara **dinamis** untuk mendukung konfigurasi rentang data historis sesuai kebutuhan analisis portofolio atau kebijakan manajemen risiko internal:

* **Pilihan Periode Observasi:** **12 Bulan**, **24 Bulan**, **36 Bulan**, hingga **60 Bulan**.
* **Contoh Penentuan Periode Observasi (Posisi Laporan: September 2026 / `202609`):**
  * **Opsi 12 Bulan:** Data dimulainya transisi dari **202509** s.d. **202609** (12 matriks transisi).
  * **Opsi 24 Bulan:** Data dimulainya transisi dari **202409** s.d. **202609** (24 matriks transisi).
  * **Opsi 36 Bulan (Standar):** Data dimulainya transisi dari **202309** s.d. **202609** (36 matriks transisi).
  * **Opsi 60 Bulan:** Data dimulainya transisi dari **202109** s.d. **202609** (60 matriks transisi).
* **Formula PD Final:** Rata-rata aritmatika (*Average Roll Rate*) dari seluruh matriks transisi bulanan dalam rentang yang dipilih ($N$ bulan).

$$
\text{PD Netflow Average} = \frac{\sum_{i=1}^{N} \text{Matriks Transisi}_i}{N}
$$

### B. Ketentuan Populasi Debitur PD Netflow

1. **Dynamic Cohort Matching:** Setiap transisi bulanan ($T_{t-1} \to T_t$) menghubungkan seluruh debitur aktif pada posisi $T_{t-1}$ ke posisi $T_t$.
2. **Debitur Baru & Lunas:** Debitur baru otomatis masuk pada snapshot bulan cair. Debitur lunas (*Paid Off*) dicatat di kolom pelunasan lalu keluar dari snapshot bulan berikutnya.
3. **Perlakuan Hapus Buku (Write-Off / WO):**
   * **WO Berjalan:** Eksekusi WO yang terjadi dalam rentang masa observasi wajib terekam sebagai migrasi menuju *Loss State/WO*.
   * **WO Lama:** Debitur yang sudah berstatus WO sebelum titik awal observasi ($T_0$) **wajib dikeluarkan** dari populasi penyebut (*denominator*).

---

## 4. Loss Given Default (LGD) Collateral Shortfall

Parameter LGD mengukur estimasi persentase kerugian yang tidak dapat terpulihkan dari eksekusi jaminan saat debitur mengalami *default*.

### A. Populasi Debitur LGD

Daftar debitur yang digunakan dalam perhitungan parameter LGD **bukanlah populasi luar**, melainkan **subset (bagian) dari populasi PD Netflow**, yakni debitur-debitur yang berpindah/masuk ke status *Default/Macet/WO* selama rentang data historis observasi.

### B. Formula LGD Shortfall

$$
\text{LGD Shortfall \%} = \frac{\sum \text{EAD Debitur Default} - \sum \text{Nilai Agunan Diperhitungkan/Terrealisasi}}{\sum \text{EAD Debitur Default}}
$$

---

## 5. Matrix Perbandingan Komprehensif: CKPN vs PPKA

| Parameter                           | Murabahah & Multijasa                                     | Musyarakah                                                | IMBT                                                      |
| :---------------------------------- | :-------------------------------------------------------- | :-------------------------------------------------------- | :-------------------------------------------------------- |
| **Cakupan PSAK 414**          | Semua*Bucket* (Lancar s.d. Macet)                       | Hanya yang Menunggak / Jatuh Tempo                        | Hanya yang Menunggak (Tunggakan Pokok)                    |
| **Metode PSAK 414**           | $\text{PD Netflow} \times \text{LGD} \times \text{EAD}$ | $\text{PD Netflow} \times \text{LGD} \times \text{EAD}$ | $\text{PD Netflow} \times \text{LGD} \times \text{EAD}$ |
| **Dasar PPKA (POJK 24/2024)** | $\text{Sisa Pokok} + \text{Tunggakan Pokok}$            | Saldo Modal Syirkah                                       | Tunggakan Pokok IMBT                                      |
| **Rumus PPKA OJK**            | Persentase Wajib Min. OJK$\times$ Base PPKA             | Persentase Wajib Min. OJK$\times$ Base PPKA             | Persentase Wajib Min. OJK$\times$ Base PPKA             |

---

## 6. Algoritma & Urutan Eksekusi Sistem

1. **Inisialisasi Parameter Filter:**
   * Tentukan `Tanggal Pelaporan` (contoh: `202609`).
   * Tentukan `Lookback Window PD` (pilihan: `12`, `24`, `36`, atau `60` bulan).
2. **Penarikan Snapshot Transisi Bulanan:**
   * Ambil data snapshot `N` bulan ke belakang mulai dari $T_0 = \text{Tanggal Pelaporan} - N \text{ bulan}$.
3. **Kalkulasi Matriks Roll Rate Bulanan:**
   * Hubungkan ID Rekening dari $T_{t-1}$ ke $T_t$.
   * Hitung perpindahan saldo/rekening antar *bucket* (termasuk *Paid Off* dan *Write-off*).
4. **Kalkulasi Average PD Netflow:**
   * Hitung nilai rata-rata perpindahan dari `N` matriks transisi.
5. **Kalkulasi LGD Shortfall:**
   * Tarik daftar debitur default dari populasi histori transisi, hitung rasio *collateral shortfall*.
6. **Kalkulasi Akhir CKPN & Ekualisasi PPKA:**
   * Kalikan $\text{EAD}_{202609} \times \text{PD Netflow Avg} \times \text{LGD}$ untuk setiap rekening piutang aktif.
   * Bandingkan Total CKPN (PSAK 414) dengan Total PPKA Wajib (POJK 24/2024). Jika CKPN < PPKA, bentuk Cadangan PPKA Tambahan.
