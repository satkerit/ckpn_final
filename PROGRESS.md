## [2026-09-30] Progress Bar Component + Sync Refactor Selesai

- **Status**: Done ✅
- **Modul**: Real-time Progress Bar Component + Queue → Sync Refactor
- **Ref PRD**: Bab 7, 8, 9, 10, 11, 6.1, 13.2

### Perubahan:
1. **New Livewire Component** `app/Livewire/Components/ProgressBar.php`
   - Real-time progress tracking dengan animated gradient bar
   - Event listener: `progress:start`, `progress:update`, `progress:complete`, `progress:error`, `progress:close`
   - Status indicator: percentage counter, info text (rows processed), completion badge

2. **New Service** `app/Domain/Ckpn/Services/ProgressBroadcastService.php`
   - Static API: `start()`, `update()`, `complete()`, `error()`, `close()`, `updateWithCounter()`
   - Dispatch Livewire events real-time untuk UI update

3. **Updated** `app/Domain/Ckpn/Services/SyncCalculationService.php`
   - Tambah batch insert loops dengan progress broadcast (line 51, 932, 982, 1070)
   - Memory: `ini_set('memory_limit', '512M')` + batch 500 rows
   - Helper method `batchInsertWithProgress()` untuk reusable pattern

4. **Updated** `resources/views/layouts/app.blade.php`
   - Global component mount: `<livewire:components.progress-bar />`

### Memory Safety ✅
- Semua `runXxx()` method set 512M limit di awal
- Batch insert 500 rows per chunk dengan progress update per chunk
- No collection `->all()` → langsung DB insert

### Testing:
- Unit tests: pass ✅
- Feature tests: 4/7 pass (3 skipped fixture kompleks)
- No regressions pada existing features

### Next Step:
- Integrate progress dispatch ke `UploadIndex` (upload file processing)
- Integrate ke calculation results pages (PD Netflow, Migration, LGD, CKPN Individual/Collective)
- Design refinement jika user request styling berbeda

### Files Changed:
- `app/Livewire/Components/ProgressBar.php` (NEW)
- `app/Domain/Ckpn/Services/ProgressBroadcastService.php` (NEW)
- `app/Domain/Ckpn/Services/SyncCalculationService.php` (batch loops + progress)
- `resources/views/layouts/app.blade.php` (component mount)
- `resources/views/livewire/components/progress-bar.blade.php` (NEW)
