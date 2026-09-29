# Progress Implementasi Alur CKPN Multi-Level Segmentation

**Status Keseluruhan**: Audit selesai → Siap Phase 1 implementasi  
**Tanggal Update**: 2026-09-29  
**Ref Dokumen Utama**: [docs/AUDIT_ALUR_CKPN.md](docs/AUDIT_ALUR_CKPN.md)

---

## Timeline Implementasi

| Phase | Deskripsi | Status | Target | Progress |
|-------|-----------|--------|--------|----------|
| Pre-0 | Audit sistem saat ini | ✅ DONE | — | 100% |
| **1** | Schema & Master Data | ⏳ PENDING | Week 1 | 0% |
| **2** | Klasifikasi & Staging | ⏳ PENDING | Week 1–2 | 0% |
| **3** | PD Netflow Fix (akad rules) | ⏳ PENDING | Week 2–3 | 0% |
| **4** | UI & Calculation | ⏳ PENDING | Week 3–4 | 0% |
| **5** | Test & Verify | ⏳ PENDING | Week 4–5 | 0% |

---

## Audit Summary (Pre-Phase 0)

### ✅ Audit Selesai — 2026-09-29

**Key Findings**:
- Sistem saat ini hanya support filter `usage_type` → perlu tambah `office_code` + `akad_code`
- Tabel `ckpn_period_classifications` belum punya kolom `office_code` + `akad_code`
- PD Netflow query **hardcoded ke `outstanding_balance`** → belum support tunggakan pokok per akad
- Bucket 14 WO logic sudah benar (per-periode, bukan >360 hari)
- ClassifyPeriodDataJob belum populate segmentasi 3-level ke staging

**Gap Count**: 10 items (3 HIGH PRIORITY, 7 MEDIUM–HIGH)

**Full Audit Report**: [docs/AUDIT_ALUR_CKPN.md](docs/AUDIT_ALUR_CKPN.md)

---

## Phase-by-Phase Status

### Phase 1: Schema & Master Data (0% → target 100%)

**Tasks**:
- [ ] Create migration: add `office_code`, `akad_code` ke `ckpn_period_classifications`
- [ ] Create migration: create `akad_calculation_rules` table
- [ ] Seed `akad_calculation_rules` dengan default config
- [ ] Create `AkadCalculationRulesRepository` service

**Milestone**: Schema siap, master data seeded  
**Started**: —  
**Completed**: —

---

### Phase 2: Klasifikasi & Staging (0% → target 100%)

**Tasks**:
- [ ] Update `ClassifyPeriodDataJob` untuk populate `office_code`, `akad_code`
- [ ] Test ClassifyPeriodDataJob dengan data sample
- [ ] Update `CkpnClassificationIndex` UI filter

**Milestone**: Klasifikasi support segmentasi 3-level  
**Started**: —  
**Completed**: —

---

### Phase 3: PD Netflow Fix (0% → target 100%)

**Tasks**:
- [ ] Update `OutstandingMapLoader.load()` untuk dynamic field selection
- [ ] Update `PdNetflowDetailService` query
- [ ] Update `BucketMovementValidator`
- [ ] Test PD Netflow per akad

**Milestone**: PD Netflow support outstanding + tunggakan pokok per akad  
**Started**: —  
**Completed**: —

---

### Phase 4: UI & Calculation (0% → target 100%)

**Tasks**:
- [ ] Update `PdNetflowPivotIndex` filter UI
- [ ] Update `PdNetflowResultIndex` filter UI
- [ ] Update `PdNetflowCalculationJob` dispatcher
- [ ] Update PD Migration UI
- [ ] Update CKPN Individual/Collective UI

**Milestone**: Semua UI support filter 3-level  
**Started**: —  
**Completed**: —

---

### Phase 5: Test & Verify (0% → target 100%)

**Tasks**:
- [ ] End-to-end test (klasifikasi → PD → CKPN)
- [ ] Verify akad rules selection
- [ ] Verify bucket 14 WO logic
- [ ] Performance test dataset besar
- [ ] Update PROGRESS.md

**Milestone**: Sistem production-ready  
**Started**: —  
**Completed**: —

---

## Known Issues

| ID | Issue | Impact | Status | Solution |
|----|-------|--------|--------|----------|
| I-1 | Segmentasi hanya `usage_type` | Blocker | ⏳ PENDING | Phase 1–2 |
| I-2 | Outstanding hardcoded | Blocker | ⏳ PENDING | Phase 3 |
| I-3 | UI filter minimal | Medium | ⏳ PENDING | Phase 4 |

---

## Questions for User

1. **Akad Rules**: Akad mana yg pakai `tgkmdl`? Default = `outstanding_balance`?
2. **Dispatch Strategy**: 1 job per (period, usage, office, akad) atau 1 job per (period, usage) + loop?
3. **Segment Priority**: Jika data kosong → skip, warning, atau fallback ke level sebelumnya?

---

## Next Step

👉 **Lanjut ke Phase 1**: Schema & Master Data  
Tunggu user confirm 3 questions di atas sebelum start implementasi.

---

*Dokumen ini di-update otomatis setiap phase selesai. Lihat [docs/AUDIT_ALUR_CKPN.md](docs/AUDIT_ALUR_CKPN.md) untuk detail lengkap.*
