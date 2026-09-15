# DATABASE_DESIGN.md — Technical Design & Skema Database Sistem CKPN

**Versi:** 1.0 | **Tanggal:** 18 Agustus 2026
**Rujukan:** `PRD.md` v2.0 Bab 15 (daftar tabel konsep), `AGENTS.md` Bab 4 (standar koding & naming)
**Status:** Draft teknis — siap dipakai sebagai acuan pembuatan migration Laravel. Kolom bertanda `⚠️ TBD` menunggu konfirmasi open item PRD Bab 12.

> Dokumen ini **tidak mengulang** narasi bisnis/formula dari PRD — hanya struktur teknis (tabel, kolom, tipe, index, relasi). Untuk arti bisnis suatu field, rujuk nomor bab PRD yang tertera di tiap tabel.

---

## 1. Konvensi Umum

- Semua tabel: `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `created_at`, `updated_at` (Laravel default timestamps).
- Tabel **snapshot hasil perhitungan** (namanya berakhiran `_result`, `_movement`, `_rate`, `_matrix`) **tidak punya** `deleted_at` (tidak pakai soft delete) dan **tidak boleh di-update setelah run berstatus Completed/Approved** — proteksi di level aplikasi (model event/policy), bukan hanya di DB.
- Kolom periode: `period` CHAR(6) format `yyyymm` (bukan DATE), supaya pencarian/agregasi per bulan konsisten dengan penyebutan di PRD. Index selalu disertakan pada kolom `period`.
- Kolom uang/baki debet: `DECIMAL(20,2)`.
- Kolom rate/persentase/PD/LGD: `DECIMAL(10,8)` (agar presisi rate kecil <1% tetap akurat).
- Foreign key: `{singular_table}_id`, `onDelete('restrict')` untuk data transaksional/master (jangan cascade delete data historis), kecuali eksplisit disebutkan lain.
- Enum sebisa mungkin di level aplikasi (PHP Enum + kolom `VARCHAR`/`TINYINT` di DB dengan `CHECK`/cast), bukan `ENUM` MySQL native (memudahkan migrasi/alter di kemudian hari).

---

## 2. Master & Konfigurasi

### 2.1 `risk_segments`

_(PRD Bab 5)_
| Kolom | Tipe | Ket |
|---|---|---|
| code | VARCHAR(20) UNIQUE | kode segmen, mis. `MODAL_KERJA` |
| name | VARCHAR(100) | nama segmen |
| description | TEXT NULLABLE | |
| is_active | BOOLEAN DEFAULT true | |
| effective_period_start | CHAR(6) | mulai berlaku |
| effective_period_end | CHAR(6) NULLABLE | null = masih berlaku |

### 2.2 `financing_account_segment_map`

_(PRD Bab 5 — histori mapping akun ke segmen per periode)_
| Kolom | Tipe | Ket |
|---|---|---|
| financing_account_id | FK → financing_accounts | |
| risk_segment_id | FK → risk_segments | |
| period | CHAR(6) | periode berlakunya mapping ini |
| UNIQUE (financing_account_id, period) | | 1 akun hanya 1 segmen per periode |

### 2.3 `buckets`

_(PRD Bab 5.5, Bab 7 — Bucket 1–14)_
| Kolom | Tipe | Ket |
|---|---|---|
| bucket_number | TINYINT UNIQUE | 1–14 |
| name | VARCHAR(50) | |
| min_days_past_due | INT NULLABLE | ⚠️ TBD untuk bucket 2–13 (PRD Bab 12.1) |
| max_days_past_due | INT NULLABLE | ⚠️ TBD untuk bucket 2–13 |
| is_default_bucket | BOOLEAN DEFAULT false | true khusus Bucket 14 (>360 hari dan/atau WO) |

### 2.4 `quality_grades`

_(PRD Bab 8 — kualitas/kolektibilitas untuk PD Migration)_
| Kolom | Tipe | Ket |
|---|---|---|
| grade_number | TINYINT UNIQUE | 1–5 (default) |
| name | VARCHAR(50) | Lancar/DPK/Kurang Lancar/Diragukan/Macet |
| is_absorbing_state | BOOLEAN DEFAULT false | true untuk state "Hapus Buku"/"Lunas" tambahan (lihat 2.4.1) |

**2.4.1** Dua baris tambahan non-numerik direkomendasikan sebagai absorbing state di tabel yang sama (`grade_number` null, `code` VARCHAR `WO`/`LUNAS`) — **atau** dipisah ke kolom `outcome_type` ENUM aplikasi (`NORMAL`, `WRITE_OFF`, `PAID_OFF`) pada tabel `pd_migration_matrix` (lihat 4.1). **Rekomendasi: opsi kedua** (lebih bersih, `quality_grades` murni untuk 1–5).

### 2.5 `calculation_parameters`

_(PRD Bab 7.4, 8.4, 15 — parameter versioned per metode per periode)_
| Kolom | Tipe | Ket |
|---|---|---|
| method | VARCHAR(30) | `pd_netflow` \| `pd_migration` \| `lgd_expected_recoveries` \| `lgd_collateral_shortfall` |
| risk_segment_id | FK → risk_segments NULLABLE | null = berlaku semua segmen |
| param_key | VARCHAR(50) | mis. `rolling_window_months`, `forward_projection_months`, `lgd_window_years` |
| param_value | VARCHAR(100) | disimpan sebagai string, cast sesuai key di aplikasi |
| effective_period_start | CHAR(6) | |
| effective_period_end | CHAR(6) NULLABLE | |
| UNIQUE (method, risk_segment_id, param_key, effective_period_start) | | |

### 2.6 `collateral_types`

_(PRD Bab 6.1, Bab 10 — persentase biaya penjualan/lelang)_
| Kolom | Tipe | Ket |
|---|---|---|
| code | VARCHAR(30) UNIQUE | |
| name | VARCHAR(100) | |
| selling_cost_percentage | DECIMAL(10,8) | dipakai formula CKPN Individual (Bab 6.1) & LGD Collateral Shortfall (Bab 10) — **shared parameter**, lihat PRD Bab 12.2 |

---

## 3. Data Transaksional

### 3.0 `financing_offices`

_(Master kantor/cabang — sumber kolom `kdloc` pada data upload)_
| Kolom | Tipe | Ket |
|---|---|---|
| code | VARCHAR(20) UNIQUE | kode kantor (`kdloc` di sistem core) |
| name | VARCHAR(150) | nama kantor/cabang |
| is_active | BOOLEAN DEFAULT true | |

### 3.1 `financing_accounts`

_(Data statik per akun pembiayaan — diisi/diupdate saat upload pertama atau perubahan data master)_
| Kolom | Tipe | Ket |
|---|---|---|
| account_number | VARCHAR(50) UNIQUE | `nokontrak` — nomor kontrak/rekening |
| product_code | VARCHAR(20) NULLABLE | `kdprd` — kode produk |
| akad_code | VARCHAR(20) NULLABLE | `pokpby` — kode akad pembiayaan |
| office_code | VARCHAR(20) NULLABLE | `kdloc` — kode kantor (FK logis ke `financing_offices.code`) |
| economic_sector | VARCHAR(100) NULLABLE | `lb_sektor ekonomi` — label sektor ekonomi |
| usage_type | TINYINT NULLABLE | `jenis_penggunaan`: 1=Modal Kerja, 2=Investasi, 3=Konsumsi |
| origination_date | DATE NULLABLE | `tgleff` — tanggal awal/efektif pembiayaan |
| maturity_date | DATE NULLABLE | `tglexp` — tanggal jatuh tempo |
| customer_name | VARCHAR(150) NULLABLE | nama debitur (opsional, diisi jika tersedia) |
| is_active | BOOLEAN DEFAULT true | |

> `usage_type` dipakai sebagai dasar segmentasi jika `risk_segment` belum dikonfigurasi manual. Nilai: 1=Modal Kerja, 2=Investasi, 3=Konsumsi (sesuai kode jenis penggunaan dari core).

### 3.1a `financing_period_uploads`

_(Data per periode yang diupload via Excel — satu baris per nokontrak per periode)_
| Kolom | Tipe | Ket |
|---|---|---|
| financing_account_id | FK → financing_accounts | |
| period | CHAR(6) | periode upload format `yyyymm` |
| outstanding_balance | DECIMAL(20,2) | `osmdlc` — baki debet |
| collectibility | TINYINT | `colbaru` — kolektibilitas (1–5) |
| writeoff_date | DATE NULLABLE | `tglwo` — tanggal hapus buku (format `yyyymmdd` di Excel → DATE di DB) |
| financing_status | VARCHAR(5) | `stsrec` — status pembiayaan: `A`=Aktif, dst. |
| writeoff_status | VARCHAR(5) NULLABLE | `stsacc` — status writeoff: `W`=Writeoff |
| upload_batch_id | FK → financing_upload_batches | referensi batch upload (audit trail) |
| UNIQUE (financing_account_id, period) | | 1 akun hanya 1 record per periode |
| INDEX (period, collectibility) | | untuk agregasi cepat per periode |
| INDEX (period, writeoff_status) | | untuk filter WO per periode |

### 3.1b `financing_upload_batches`

_(Audit trail setiap batch upload Excel)_
| Kolom | Tipe | Ket |
|---|---|---|
| period | CHAR(6) | periode yang diupload |
| filename | VARCHAR(255) | nama file Excel original |
| uploaded_by_user_id | FK → users | |
| uploaded_at | TIMESTAMP | |
| total_rows | INT | jumlah baris di file |
| imported_rows | INT | jumlah baris berhasil diimport |
| failed_rows | INT DEFAULT 0 | jumlah baris gagal validasi |
| status | VARCHAR(20) DEFAULT 'pending' | `pending` \| `processing` \| `done` \| `failed` |
| error_summary | JSON NULLABLE | ringkasan error per baris (jika ada) |

### 3.2 `financing_outstanding_monthly`

_(PRD Bab 7.2 — dasar bucketing PD Netflow)_
| Kolom | Tipe | Ket |
|---|---|---|
| financing_account_id | FK | |
| period | CHAR(6) | |
| outstanding_balance | DECIMAL(20,2) | baki debet |
| days_past_due_principal | INT DEFAULT 0 | hari tunggakan pokok |
| days_past_due_interest | INT DEFAULT 0 | hari tunggakan bunga/margin |
| bucket_id | FK → buckets | hasil bucketing (max dari principal/interest DPD) |
| is_written_off | BOOLEAN DEFAULT false | |
| UNIQUE (financing_account_id, period) | | |
| INDEX (period, bucket_id) | | untuk agregasi cepat per bucket per periode |

### 3.3 `financing_outstanding_quarterly`

_(PRD Bab 8.2 — dasar PD Migration, posisi triwulanan per kualitas)_
| Kolom | Tipe | Ket |
|---|---|---|
| financing_account_id | FK | |
| period | CHAR(6) | hanya posisi Maret/Juni/September/Desember |
| outstanding_balance | DECIMAL(20,2) | |
| quality_grade_id | FK → quality_grades | |
| UNIQUE (financing_account_id, period) | | |
| INDEX (period, quality_grade_id) | | |

### 3.4 `writeoff_data`

_(dipakai lintas metode — PD Netflow bulanan, PD Migration per posisi, LGD-ER tahunan; dibedakan via kolom `context`)_
| Kolom | Tipe | Ket |
|---|---|---|
| financing_account_id | FK | |
| context | VARCHAR(20) | `netflow_monthly` \| `migration_cohort` \| `lgd_er_yearly` |
| period | CHAR(6) | bulan/posisi/tahun (tahun disimpan `yyyy01` atau kolom terpisah `fiscal_year`, lihat catatan) |
| writeoff_amount | DECIMAL(20,2) | |
| writeoff_date | DATE | |
| INDEX (context, period) | | |

> Catatan: jika tim dev lebih suka pemisahan tabel per konteks demi kejelasan query (`writeoff_monthly`, `writeoff_cohort`, `writeoff_yearly`), itu valid juga — pilih salah satu pendekatan secara konsisten saat implementasi, tidak dicampur.

### 3.5 `recoveries_data`

_(PRD Bab 9.2 — LGD Expected Recoveries)_
| Kolom | Tipe | Ket |
|---|---|---|
| financing_account_id | FK | |
| fiscal_year | SMALLINT | tahun penerimaan |
| recovery_amount | DECIMAL(20,2) | penerimaan cash |
| recovery_date | DATE NULLABLE | |
| INDEX (fiscal_year) | | |

### 3.6 `collateral_sales_data`

_(PRD Bab 10.2 — LGD Collateral Shortfall)_
| Kolom | Tipe | Ket |
|---|---|---|
| financing_account_id | FK | |
| period | CHAR(6) | posisi penilaian |
| valuation_type | VARCHAR(20) | `actual_sale` \| `estimated` |
| gross_sale_value | DECIMAL(20,2) | nilai jual/estimasi sebelum biaya |
| selling_cost_amount | DECIMAL(20,2) | biaya (lelang, dsb.) — bisa dihitung dari `collateral_types.selling_cost_percentage` atau input manual |
| net_sale_value | DECIMAL(20,2) | gross − cost (disimpan tersimpan, bukan hanya dihitung on-the-fly, demi audit trail) |
| appraised_by | VARCHAR(150) NULLABLE | penilai, khusus `estimated` |
| approved_by_user_id | FK → users NULLABLE | approval nilai estimasi (PRD FR-7.4) |
| approved_at | TIMESTAMP NULLABLE | |

### 3.7 `collaterals`

_(PRD Bab 6.1 — untuk CKPN Individual & referensi umum jaminan)_
| Kolom | Tipe | Ket |
|---|---|---|
| financing_account_id | FK | |
| collateral_type_id | FK → collateral_types | |
| liquidation_value | DECIMAL(20,2) | nilai likuidasi jaminan |
| valuation_date | DATE NULLABLE | |

---

## 4. Hasil Perhitungan — PD Netflow

### 4.1 `data_quality_anomalies`

_(PRD Bab 7.3, FR-4.3 — dijalankan sebelum kalkulasi rate)_
| Kolom | Tipe | Ket |
|---|---|---|
| risk_segment_id | FK | |
| period | CHAR(6) | |
| bucket_id | FK → buckets NULLABLE | |
| anomaly_type | VARCHAR(30) | `empty_bucket` \| `bucket_exceeds_source` |
| detail | JSON | angka yang memicu anomali (utk audit) |
| status | VARCHAR(20) DEFAULT 'open' | `open` \| `reviewed` \| `accepted` \| `data_corrected` |
| reviewed_by_user_id | FK → users NULLABLE | |
| reviewed_at | TIMESTAMP NULLABLE | |
| note | TEXT NULLABLE | |

### 4.2 `pd_netflow_bucket_movement`

_(PRD Bab 7.3 langkah 4 — rate perpindahan + proyeksi forward)_
| Kolom | Tipe | Ket |
|---|---|---|
| risk_segment_id | FK | |
| period | CHAR(6) | |
| from_bucket_id | FK → buckets | |
| to_bucket_id | FK → buckets | |
| movement_rate | DECIMAL(10,8) | |
| is_projected | BOOLEAN DEFAULT false | true jika hasil rata-rata 6 bulan (forward projection) |
| UNIQUE (risk_segment_id, period, from_bucket_id, to_bucket_id) | | |

### 4.3 `pd_netflow_compound_rate`

_(PRD Bab 7.3 langkah 5 — compound flow to loss)_
| Kolom | Tipe | Ket |
|---|---|---|
| risk_segment_id | FK | |
| period | CHAR(6) | |
| bucket_id | FK → buckets | bucket awal rangkaian |
| compound_rate | DECIMAL(10,8) | hasil perkalian berantai menuju Bucket 14 |
| UNIQUE (risk_segment_id, period, bucket_id) | | |

### 4.4 `pd_netflow_result`

_(PRD Bab 7.3 langkah 5 — PD final per bucket per segmen per periode)_
| Kolom | Tipe | Ket |
|---|---|---|
| calculation_run_id | FK → calculation_run_log | |
| risk_segment_id | FK | |
| period | CHAR(6) | periode perhitungan (posisi PD) |
| bucket_id | FK → buckets | |
| rolling_window_months | SMALLINT | window yang dipakai (audit trail) |
| pd_value | DECIMAL(10,8) | |
| UNIQUE (risk_segment_id, period, bucket_id) | | |

---

## 5. Hasil Perhitungan — PD Migration

### 5.1 `pd_migration_matrix`

_(PRD Bab 8.3 — matriks migrasi per cohort)_
| Kolom | Tipe | Ket |
|---|---|---|
| calculation_run_id | FK | |
| risk_segment_id | FK | |
| cohort_start_period | CHAR(6) | posisi awal triwulan |
| cohort_end_period | CHAR(6) | posisi 1 tahun kemudian |
| from_quality_grade_id | FK → quality_grades | |
| to_outcome | VARCHAR(20) | kode kualitas tujuan **atau** `WRITE_OFF` \| `PAID_OFF` |
| migration_rate | DECIMAL(10,8) | |
| UNIQUE (risk_segment_id, cohort_start_period, from_quality_grade_id, to_outcome) | | |

### 5.2 `pd_migration_result`

_(PRD Bab 8.3 langkah 4 — PD final per kualitas)_
| Kolom | Tipe | Ket |
|---|---|---|
| calculation_run_id | FK | |
| risk_segment_id | FK | |
| period | CHAR(6) | periode perhitungan (posisi PD) |
| quality_grade_id | FK → quality_grades | |
| cohorts_used_count | TINYINT | jumlah cohort yang dirata-rata (audit trail) |
| pd_value | DECIMAL(10,8) | |
| UNIQUE (risk_segment_id, period, quality_grade_id) | | |

---

## 6. Hasil Perhitungan — LGD

### 6.1 `lgd_expected_recoveries_result`

_(PRD Bab 9.3)_
| Kolom | Tipe | Ket |
|---|---|---|
| calculation_run_id | FK | |
| risk_segment_id | FK NULLABLE | null = "All Account" (PRD Bab 5) |
| period | CHAR(6) | periode perhitungan LGD |
| window_years | TINYINT DEFAULT 5 | |
| yearly_recovery_rates | JSON | detail rate per tahun dalam window (audit trail) |
| expected_recovery_rate | DECIMAL(10,8) | rata-rata |
| lgd_value | DECIMAL(10,8) | 1 − expected_recovery_rate |
| UNIQUE (risk_segment_id, period) | | |

### 6.2 `lgd_collateral_shortfall_result`

_(PRD Bab 10.3 — dihitung per akun, diagregasi ke segmen bila perlu)_
| Kolom | Tipe | Ket |
|---|---|---|
| calculation_run_id | FK | |
| financing_account_id | FK | |
| risk_segment_id | FK | |
| period | CHAR(6) | |
| outstanding_balance | DECIMAL(20,2) | baki debet belum lunas |
| collateral_net_sale_value | DECIMAL(20,2) | dari `collateral_sales_data.net_sale_value` |
| shortfall_amount | DECIMAL(20,2) | outstanding − net sale value |
| lgd_value | DECIMAL(10,8) | shortfall / outstanding |
| UNIQUE (financing_account_id, period) | | |

---

## 7. Hasil Konsolidasi CKPN

### 7.1 `ckpn_individual_result`

_(PRD Bab 6.1)_
| Kolom | Tipe | Ket |
|---|---|---|
| calculation_run_id | FK | |
| financing_account_id | FK | |
| period | CHAR(6) | |
| outstanding_balance | DECIMAL(20,2) | Baki Debet |
| total_collateral_liquidation_value | DECIMAL(20,2) | Total Nilai Likuidasi Jaminan |
| selling_cost_percentage | DECIMAL(10,8) | snapshot parameter yang dipakai (audit trail) |
| ckpn_individual_amount | DECIMAL(20,2) | hasil formula Bab 6.1 |
| UNIQUE (financing_account_id, period) | | |

### 7.2 `ckpn_collective_result`

_(PRD Bab 11 — kombinasi PD & LGD final, ⚠️ kebijakan kombinasi final menunggu konfirmasi)_
| Kolom | Tipe | Ket |
|---|---|---|
| calculation_run_id | FK | |
| financing_account_id | FK | |
| risk_segment_id | FK | |
| period | CHAR(6) | |
| pd_method_used | VARCHAR(20) | `netflow` \| `migration` \| `blended` |
| pd_value_final | DECIMAL(10,8) | |
| lgd_method_used | VARCHAR(20) | `expected_recoveries` \| `collateral_shortfall` \| `blended` |
| lgd_value_final | DECIMAL(10,8) | |
| ead_value | DECIMAL(20,2) | dari outstanding balance (PRD Bab 15/FR-8) |
| ckpn_collective_amount | DECIMAL(20,2) | pd_value_final × lgd_value_final × ead_value |
| UNIQUE (financing_account_id, period) | | |

### 7.3 `ckpn_summary`

_(PRD Bab 6, ringkasan per periode)_
| Kolom | Tipe | Ket |
|---|---|---|
| calculation_run_id | FK | |
| period | CHAR(6) UNIQUE | |
| total_ckpn_individual | DECIMAL(20,2) | |
| total_ckpn_collective | DECIMAL(20,2) | |
| total_ckpn | DECIMAL(20,2) | |

### 7.4 `calculation_run_log`

_(PRD FR-13 — audit trail & workflow status)_
| Kolom | Tipe | Ket |
|---|---|---|
| period | CHAR(6) | |
| run_type | VARCHAR(30) | `pd_netflow` \| `pd_migration` \| `lgd_er` \| `lgd_cs` \| `ckpn_individual` \| `ckpn_collective` \| `full_run` |
| status | VARCHAR(20) DEFAULT 'draft' | `draft` \| `in_progress` \| `completed` \| `approved` \| `failed` |
| triggered_by_user_id | FK → users | |
| approved_by_user_id | FK → users NULLABLE | |
| started_at | TIMESTAMP NULLABLE | |
| completed_at | TIMESTAMP NULLABLE | |
| error_message | TEXT NULLABLE | |

---

## 8. Ringkasan Relasi Kunci (untuk generate migration berurutan)

Urutan pembuatan migration disarankan (agar foreign key valid):

```
1. users (bawaan Laravel) → roles/permissions (Filament Shield)
2. risk_segments, buckets, quality_grades, collateral_types
3. financing_offices
4. calculation_parameters (FK ke risk_segments)
5. financing_accounts (FK logis ke financing_offices via office_code)
6. financing_upload_batches (FK ke users)
7. financing_period_uploads (FK: financing_accounts, financing_upload_batches)
8. financing_account_segment_map (FK: financing_accounts, risk_segments)
9. financing_outstanding_monthly (FK: financing_accounts, buckets)
10. financing_outstanding_quarterly (FK: financing_accounts, quality_grades)
11. writeoff_data, recoveries_data (FK: financing_accounts)
12. collaterals, collateral_sales_data (FK: financing_accounts, collateral_types, users)
13. calculation_run_log (FK: users)
14. data_quality_anomalies (FK: risk_segments, buckets)
15. pd_netflow_bucket_movement, pd_netflow_compound_rate, pd_netflow_result (FK: risk_segments, buckets, calculation_run_log)
16. pd_migration_matrix, pd_migration_result (FK: risk_segments, quality_grades, calculation_run_log)
17. lgd_expected_recoveries_result, lgd_collateral_shortfall_result (FK: risk_segments, financing_accounts, calculation_run_log)
18. ckpn_individual_result, ckpn_collective_result, ckpn_summary (FK: financing_accounts, risk_segments, calculation_run_log)
```

---

## 9. Open Items Teknis (Tambahan dari Technical Design)

1. Pemisahan `writeoff_data` per konteks (satu tabel dengan kolom `context` vs 3 tabel terpisah) — **keputusan tim dev**, pilih satu, catat di sini setelah diputuskan (ikuti AGENTS.md Bab 12 — tandai keputusan final, jangan biarkan dua pendekatan hidup berdampingan).
2. Apakah `pd_migration_result` dan `lgd_expected_recoveries_result` perlu breakdown ke level akun individual atau cukup level segmen — saat ini didesain **level segmen** (sesuai PRD: PD/LGD dihitung per segmen, diterapkan merata ke seluruh akun dalam segmen tsb saat kombinasi CKPN Kolektif). Perlu dikonfirmasi ke user bersamaan dengan PRD Bab 12.
3. Semua item ⚠️ TBD mengikuti PRD Bab 12 (rentang bucket 2–13, kebijakan kombinasi PD/LGD final).

---

_Dokumen ini acuan teknis untuk pembuatan migration Laravel. Update dokumen ini (bukan buat file baru) setiap ada perubahan struktur tabel, sesuai AGENTS.md Bab 12._
