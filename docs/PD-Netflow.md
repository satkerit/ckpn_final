# Panduan Dinamis Perhitungan Probability of Default (PD) Net Flow

### Dukungan Range Data Historis Multi-Tahun (2, 3, hingga 5 Tahun)

Dokumen ini merinci metodologi, alur data, formulasi Excel, serta logika proyeksi rolling 6-bulan untuk menghitung _Probability of Default_ (PD) berbasis **Net Flow Rate** secara fleksibel menggunakan range data historis **$N$ Tahun** ($N \in \{2, 3, 4, 5\}$).

---

## 1. Konsep Dasar & Kerangka Parameter Dinamis

Misalkan ditetapkan **Periode Penilaian / Cut-off ($T_{end}$)** = `YYYYMM` (Contoh: `202512`) dan **Rentang Historis ($N$ Tahun)**.

| Parameter                                 | Formula Periode                                           | Contoh ($N = 3$ Tahun, $T_{end} = 202512$) | Contoh ($N = 5$ Tahun, $T_{end} = 202512$) |
| :---------------------------------------- | :-------------------------------------------------------- | :----------------------------------------: | :----------------------------------------: |
| **Awal Outstanding (Tabel 1)**            | $T_{end} - (N 	imes 12 	ext{ bulan})$                       |                 **202212**                 |                 **202012**                 |
| **Akhir Outstanding (Tabel 1)**           | $T_{end}$                                                 |                 **202512**                 |                 **202512**                 |
| **Jumlah Kolom Outstanding**              | $(N 	imes 12) + 1 	ext{ bulan}$                             |                **37 Bulan**                |                **61 Bulan**                |
| **Awal Flow Rate Historis (Tabel 2)**     | $T_{start\_flow} = T_{end} - (N 	imes 12) + 1 	ext{ bulan}$ |                 **202301**                 |                 **202101**                 |
| **Akhir Flow Rate Historis (Tabel 2)**    | $T_{end}$                                                 |                 **202512**                 |                 **202512**                 |
| **Proyeksi Flow Rate (Proyeksi 6 Bulan)** | $T_{end} + 1 	ext{ s.d. } T_{end} + 12 	ext{ bulan}$        |           **202601 s.d. 202612**           |           **202601 s.d. 202612**           |
| **Periode Compound Flow Loss (Tabel 3)**  | $T_{start\_flow} 	ext{ s.d. } T_{end}$                     |     **202301 s.d. 202512** (36 Bulan)      |     **202101 s.d. 202512** (60 Bulan)      |

---

## 2. Detail Logika Perhitungan 3 Tabel Utama

### 📊 Tabel 1: Pengelompokan Outstanding per Bucket per Periode

- **Rentang Periode**: Dari $T_{start\_out}$ hingga $T_{end}$.
- **Struktur Rows**: Bucket 1 (0 hari) s.d. Bucket 13 (331-360 hari), dan Bucket 14 (`>360+WO`).
- **Formulasi Bucket 14**:
  $$ ext{Outstanding B14}_{t} = ext{Outstanding (>360 hari)}_{t} + ext{Outstanding (WO)}\_{t}$$

---

### 🔄 Tabel 2: Perpindahan per Bucket per Periode (_Net Flow Rate_)

Tabel ini dibagi menjadi 2 Bagian Utama:

#### Bagian A: Flow Rate Historis Real ($T_{start\_flow}$ s.d. $T_{end}$)

Membandingkan saldo Bucket $i+1$ pada bulan $t$ dengan saldo Bucket $i$ pada bulan $t-1$:
$$ ext{Flow Rate}_{i o i+1, t} = rac{ ext{Outstanding Bucket }(i+1)_{t}}{ ext{Outstanding Bucket }i\_{t-1}}$$
_Formula Excel_:

```excel
=IF(ISBLANK(Bulan_Lalu_Bucket_i), 1, IFERROR(Bulan_Ini_Bucket_i_plus_1 / Bulan_Lalu_Bucket_i, 0))
```

#### Bagian B: Proyeksi Flow Rate Prospektif ($T_{end} + 1$ s.d. $T_{end} + 12$)

Untuk mendukung perkalian diagonal Compound Flow hingga Bucket 14 pada posisi periode $T_{end}$, diperlukan proyeksi perpindahan selama 12 bulan ke depan ($202601 	ext{ s.d. } 202612$). Proyeksi ini menggunakan **Rata-Rata Bergerak 6 Bulan Historis (_Rolling 6-Month Average_)**.

$$ ext{Flow Rate}_{i o i+1, t} = rac{1}{6} \sum_{m=1}^{6} ext{Flow Rate}\_{i o i+1, t-m}$$

- **Contoh Periode Proyeksi**:
    - **Periode `202601`**: Rata-rata dari periode `202507` s/d `202512` (6 bulan ke belakang).
        ```excel
        =AVERAGE(Kolom_202507:Kolom_202512)
        ```
    - **Periode `202602`**: Rata-rata dari periode `202508` s/d `202601`.
        ```excel
        =AVERAGE(Kolom_202508:Kolom_202601)
        ```
    - **... Periode `202612`**: Rata-rata dari periode `202606` s/d `202611`.

---

### 📈 Tabel 3: Compound Flow Loss per Bucket per Periode

- **Rentang Periode Observasi**: Dari $T_{start\_flow}$ hingga $T_{end}$ (Total $N 	imes 12$ kolom observasi).
- **Aturan Perkalian Diagonal (_Diagonal Multiplication Chain_)**:
  Untuk mendapatkan _Compound Flow Loss_ pada Bucket $i$ di periode $t$, dilakukan perkalian berantai menyilang secara diagonal dari Bucket $i$ hingga Bucket 14.

$$ ext{Compound Flow}_{i, t} = \prod_{k=i}^{13} ext{Flow Rate}\_{k o k+1, \, t + (k - i)}$$

#### Ilustrasi Perkalian Diagonal (Contoh $T_{end} = 202512$):

1. **Bucket 13 to 14**:
    - Periode `202301`: $ ext{Flow Rate}\_{13 o 14, 202301}$
    - Periode `202512`: $ ext{Flow Rate}\_{13 o 14, 202512}$

2. **Bucket 12 to 13**:
    - Periode `202301`: $ ext{Flow Rate}_{12 o 13, 202301} imes ext{Flow Rate}_{13 o 14, 202302}$
    - Periode `202512`: $ ext{Flow Rate}_{12 o 13, 202512} imes ext{Flow Rate}_{13 o 14, 202601}$ _(menggunakan data proyeksi 202601)_

3. **Bucket 1 to 2** (Perkalian 13 tahap):
    - Periode `202301`:
      $$ ext{Flow Rate}_{1 o 2, 202301} imes ext{Flow Rate}_{2 o 3, 202302} imes \dots imes ext{Flow Rate}\_{13 o 14, 202401}$$
    - Periode `202512` (Titik Akhir Penilaian):
      $$ ext{Flow Rate}_{1 o 2, 202512} imes ext{Flow Rate}_{2 o 3, 202601} imes \dots imes ext{Flow Rate}\_{13 o 14, 202612}$$ _(menggunakan data proyeksi 202601 s.d. 202612)_

---

## 3. Rata-Rata Persentase per Bucket (Nilai PD Akhir)

Nilai PD statistik dihitung dengan merata-ratakan seluruh nilai _Compound Flow Loss_ pada Tabel 3 sepanjang periode observasi $T_{start\_flow}$ s.d. $T_{end}$ (sebayak $N 	imes 12$ bulan):

$$
	ext{PD}_i = \min\left(100\%, \, rac{1}{N 	imes 12} \sum_{t=T_{start\_flow}}^{T_{end}} 	ext{Compound Flow}_{i, t}
ight)
$$

- **Formulasi Excel**:
    ```excel
    =MIN(100%, AVERAGE(Rentang_Compound_Flow_Tstart_hingga_Tend))
    ```

---

## 4. Perbandingan Struktur Berdasarkan Range Historis ($T_{end} = 202512$)

| Konfigurasi Range              | 2 Tahun (24 Bln) | 3 Tahun (36 Bln) | 4 Tahun (48 Bln) | 5 Tahun (60 Bln) |
| :----------------------------- | :--------------: | :--------------: | :--------------: | :--------------: |
| **Awal Data Outstanding**      |      202312      |      202212      |      202112      |      202012      |
| **Rentang Flow Rate Real**     | 202401 – 202512  | 202301 – 202512  | 202201 – 202512  | 202101 – 202512  |
| **Rentang Flow Rate Proyeksi** | 202601 – 202612  | 202601 – 202612  | 202601 – 202612  | 202601 – 202612  |
| **Rentang Observasi Compound** | 202401 – 202512  | 202301 – 202512  | 202201 – 202512  | 202101 – 202512  |
| **Jumlah Bulan Rata-Rata PD**  |   **24 Bulan**   |   **36 Bulan**   |   **48 Bulan**   |   **60 Bulan**   |

---

### Keunggulan Metode Proyeksi Rolling 6-Bulan ke Depan:

1. **Menghindari Bias Data Kosong (_No Missing Data_)**: Tanpa proyeksi 12 bulan ke depan, perhitungan _Compound Flow_ untuk posisi $T_{end}$ pada _bucket-bucket_ awal (Bucket 1 s.d. 12) tidak akan lengkap karena membutuhkan data bulan $T_{end}+1$ s.d. $T_{end}+12$.
2. **Relevansi Tren Terkini**: Menggunakan rata-rata bergerak 6 bulan (_rolling 6-month average_) menjaga dinamika tren kualitas kredit terbaru tanpa terpengaruh anomali jangka sangat panjang.
3. **Skalabilitas Mudah**: Logika ini berlaku seragam baik untuk analisis 2, 3, 4, maupun 5 tahun.
