<?php

declare(strict_types=1);

namespace App\Livewire\Ckpn;

use App\Enums\CalculationMethodKey;
use App\Enums\ParameterMethod;
use App\Enums\SegmentType;
use App\Enums\UsageType;
use App\Models\CalculationColumnConfig;
use App\Models\CalculationDataRange;
use App\Models\CalculationGeneralSetting;
use App\Models\CalculationSegmentationLevel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Pengelolaan Parameter Kalkulasi Terstruktur (Kolom Tabel, Rentang Data PD/LGD, Segmentasi, Parameter Umum).
 * Ref: PRD Bab 5, 6.1, 7, 8, 9, 10, 15 + Permintaan User.
 */
#[Layout('layouts.app', ['title' => 'Parameter Kalkulasi'])]
class CalculationParameterIndex extends Component
{
    use WithPagination;

    #[Url(as: 'subtab')]
    public string $activeSubTab = 'column_config'; // column_config | data_range | segmentation | general

    #[Url(as: 'search')]
    public string $search = '';

    // --- Notifikasi ---
    public string $flashMessage = '';

    public string $flashType = 'success'; // 'success' | 'error'

    // --- Form State: Column Config ---
    public bool $showColumnConfigModal = false;

    public ?int $editingColumnConfigId = null;

    public string $colMethod = 'ead';

    public string $colPokpbyCode = '';

    public string $colPokpbyLabel = '';

    public string $colSourceTable = 'financing_account_periods';

    public string $colColumnName = 'outstanding_balance';

    public bool $colRequireMaturity = false;

    public bool $colIsActive = true;

    public string $colNotes = '';

    // --- Form State: Data Range ---
    public bool $showDataRangeModal = false;

    public ?int $editingDataRangeId = null;

    public string $rangeMethod = 'pd_netflow';

    public string $rangeKey = 'pd_netflow_rolling_window_months';

    public string $rangeValue = '';

    public string $rangeUnit = 'months';

    public string $rangeOfficeCode = '';

    public string $rangeUsageType = '';

    public string $rangeAkadCode = '';

    public bool $rangeIsActive = true;

    public string $rangeNotes = '';

    // --- Form State: Segmentation Level ---
    public bool $showSegmentationModal = false;

    public ?int $editingSegmentationId = null;

    public int $segLevelOrder = 1;

    public string $segType = 'office_code';

    public string $segLabel = '';

    public bool $segIsActive = true;

    public bool $segAllowGlobalFallback = true;

    public string $segNotes = '';

    // --- Form State: General Setting ---
    public bool $showSettingModal = false;

    public ?int $editingSettingId = null;

    public string $settingKey = '';

    public string $settingValue = '';

    public string $settingCategory = 'ckpn';

    public string $settingLabel = '';

    public string $settingDescription = '';

    // --- Modal Hapus ---
    public ?int $deletingId = null;

    public string $deletingType = ''; // 'column_config' | 'data_range' | 'segmentation' | 'setting'

    public function updatingSearch(): void
    {
        $this->resetPage('col_page');
        $this->resetPage('range_page');
    }

    public function updatingActiveSubTab(): void
    {
        $this->updatingSearch();
        $this->search = '';
    }

    /** Unit mengikuti katalog range_key terpilih. */
    public function updatingRangeKey(): void
    {
        $this->rangeUnit = CalculationDataRange::rangeCatalog()[$this->rangeKey]['unit'] ?? $this->rangeUnit;
    }

    /** Sinkronkan range_key & unit saat metode diganti agar tetap valid. */
    public function updatingRangeMethod(): void
    {
        $catalog = $this->rangeCatalogForMethod($this->rangeMethod);

        if (! array_key_exists($this->rangeKey, $catalog)) {
            $this->rangeKey = (string) array_key_first($catalog);
        }

        $this->updatingRangeKey();
    }

    /** Katalog range_key yang valid untuk metode terpilih (mapping UI, bukan aturan bisnis). */
    public function rangeCatalogForMethod(string $method): array
    {
        $keys = match ($method) {
            'pd_netflow' => ['pd_netflow_rolling_window_months', 'pd_netflow_forward_projection_months', 'pd_netflow_projection_lookback_months', 'pd_netflow_projection_method'],
            'pd_migration' => ['pd_migration_matrix_count'],
            'lgd_expected_recoveries' => ['lgd_er_rolling_window_years', 'lgd_er_use_all_account'],
            'lgd_collateral_shortfall' => ['lgd_cs_selling_cost_rate'],
            default => [],
        };

        return array_intersect_key(CalculationDataRange::rangeCatalog(), array_flip($keys));
    }

    // ==========================================
    // ACTIONS: COLUMN CONFIG
    // ==========================================

    public function tambahColumnConfig(): void
    {
        $this->resetColumnConfigForm();
        $this->showColumnConfigModal = true;
    }

    public function editColumnConfig(int $id): void
    {
        $cfg = CalculationColumnConfig::findOrFail($id);
        $this->editingColumnConfigId = $id;
        $this->colMethod = $cfg->method->value;
        $this->colPokpbyCode = $cfg->pokpby_code;
        $this->colPokpbyLabel = $cfg->pokpby_label ?? '';
        $this->colSourceTable = $cfg->source_table;
        $this->colColumnName = $cfg->column_name;
        $this->colRequireMaturity = (bool) $cfg->require_maturity;
        $this->colIsActive = (bool) $cfg->is_active;
        $this->colNotes = $cfg->notes ?? '';
        $this->showColumnConfigModal = true;
    }

    public function simpanColumnConfig(): void
    {
        $this->validate([
            'colMethod' => ['required', 'in:ead,pd,ckpn,lgd'],
            'colPokpbyCode' => ['required', 'string', 'max:10'],
            'colPokpbyLabel' => ['nullable', 'string', 'max:100'],
            'colSourceTable' => ['required', 'string', 'max:60'],
            'colColumnName' => ['required', 'string', 'max:60'],
            'colNotes' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'method' => $this->colMethod,
            'pokpby_code' => trim($this->colPokpbyCode),
            'pokpby_label' => $this->colPokpbyLabel ?: null,
            'source_table' => $this->colSourceTable,
            'column_name' => $this->colColumnName,
            'require_maturity' => $this->colRequireMaturity,
            'is_active' => $this->colIsActive,
            'notes' => $this->colNotes ?: null,
        ];

        // Ref: PRD Bab 15 — (method, pokpby_code) unik per baris konfigurasi.
        // updateOrCreate mencegah UniqueConstraintViolation 'uq_column_config_method_pokpby'
        // saat user menambahkan kombinasi method+pokpby yang sudah ada.
        try {
            CalculationColumnConfig::updateOrCreate(
                ['method' => $data['method'], 'pokpby_code' => $data['pokpby_code']],
                collect($data)->except(['method', 'pokpby_code'])->all(),
            );
            $this->flashMessage = $this->editingColumnConfigId !== null
                ? 'Konfigurasi kolom berhasil diperbarui.'
                : 'Konfigurasi kolom berhasil disimpan (kombinasi method + POKPBY sudah ada sebelumnya, diperbarui).';
        } catch (Throwable $e) {
            $this->flashMessage = 'Gagal menyimpan konfigurasi kolom: '.$e->getMessage();
            $this->flashType = 'error';

            return;
        }

        $this->flashType = 'success';
        $this->showColumnConfigModal = false;
        $this->resetColumnConfigForm();
    }

    public function toggleColumnConfigStatus(int $id): void
    {
        $cfg = CalculationColumnConfig::findOrFail($id);
        $cfg->update(['is_active' => ! $cfg->is_active]);
        $this->flashMessage = 'Status konfigurasi kolom berhasil diubah.';
        $this->flashType = 'success';
    }

    private function resetColumnConfigForm(): void
    {
        $this->editingColumnConfigId = null;
        $this->colMethod = 'ead';
        $this->colPokpbyCode = '';
        $this->colPokpbyLabel = '';
        $this->colSourceTable = 'financing_account_periods';
        $this->colColumnName = 'outstanding_balance';
        $this->colRequireMaturity = false;
        $this->colIsActive = true;
        $this->colNotes = '';
        $this->resetValidation();
    }

    // ==========================================
    // ACTIONS: DATA RANGE
    // ==========================================

    public function tambahDataRange(): void
    {
        $this->resetDataRangeForm();
        $this->showDataRangeModal = true;
    }

    public function editDataRange(int $id): void
    {
        $range = CalculationDataRange::findOrFail($id);
        $this->editingDataRangeId = $id;
        $this->rangeMethod = $range->method->value;
        $this->rangeKey = $range->range_key;
        $this->rangeValue = $range->range_value;
        $this->rangeUnit = $range->range_unit;
        $this->rangeOfficeCode = $range->office_code ?? '';
        $this->rangeUsageType = $range->usage_type !== null ? (string) $range->usage_type->value : '';
        $this->rangeAkadCode = $range->akad_code ?? '';
        $this->rangeIsActive = (bool) $range->is_active;
        $this->rangeNotes = $range->notes ?? '';
        $this->showDataRangeModal = true;
    }

    public function simpanDataRange(): void
    {
        $this->validate([
            'rangeMethod' => ['required', 'in:pd_netflow,pd_migration,lgd_expected_recoveries,lgd_collateral_shortfall'],
            'rangeKey' => ['required', 'string', 'max:60'],
            'rangeValue' => ['required', 'string', 'max:100'],
            'rangeUnit' => ['required', 'string', 'max:20'],
            'rangeOfficeCode' => ['nullable', 'string', 'max:10'],
            'rangeUsageType' => ['nullable', 'in:1,2,3'],
            'rangeAkadCode' => ['nullable', 'string', 'max:10'],
            'rangeNotes' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'method' => $this->rangeMethod,
            'range_key' => trim($this->rangeKey),
            'range_value' => trim($this->rangeValue),
            'range_unit' => trim($this->rangeUnit),
            'office_code' => $this->rangeOfficeCode !== '' ? trim($this->rangeOfficeCode) : null,
            'usage_type' => $this->rangeUsageType !== '' ? (int) $this->rangeUsageType : null,
            'akad_code' => $this->rangeAkadCode !== '' ? trim($this->rangeAkadCode) : null,
            'is_active' => $this->rangeIsActive,
            'notes' => $this->rangeNotes ?: null,
        ];

        if ($this->editingDataRangeId !== null) {
            CalculationDataRange::findOrFail($this->editingDataRangeId)->update($data);
            $this->flashMessage = 'Rentang data berhasil diperbarui.';
        } else {
            CalculationDataRange::create($data);
            $this->flashMessage = 'Rentang data baru berhasil ditambahkan.';
        }

        $this->flashType = 'success';
        $this->showDataRangeModal = false;
        $this->resetDataRangeForm();
    }

    public function toggleDataRangeStatus(int $id): void
    {
        $range = CalculationDataRange::findOrFail($id);
        $range->update(['is_active' => ! $range->is_active]);
        $this->flashMessage = 'Status rentang data berhasil diubah.';
        $this->flashType = 'success';
    }

    private function resetDataRangeForm(): void
    {
        $this->editingDataRangeId = null;
        $this->rangeMethod = 'pd_netflow';
        $this->rangeKey = 'pd_netflow_rolling_window_months';
        $this->rangeValue = '';
        $this->rangeUnit = 'months';
        $this->rangeOfficeCode = '';
        $this->rangeUsageType = '';
        $this->rangeAkadCode = '';
        $this->rangeIsActive = true;
        $this->rangeNotes = '';
        $this->resetValidation();
    }

    // ==========================================
    // ACTIONS: SEGMENTATION LEVEL
    // ==========================================

    public function tambahSegmentation(): void
    {
        $this->resetSegmentationForm();
        $this->segLevelOrder = (int) (CalculationSegmentationLevel::max('level_order') ?? 0) + 1;
        $this->showSegmentationModal = true;
    }

    public function editSegmentation(int $id): void
    {
        $seg = CalculationSegmentationLevel::findOrFail($id);
        $this->editingSegmentationId = $id;
        $this->segLevelOrder = $seg->level_order;
        $this->segType = $seg->segment_type->value;
        $this->segLabel = $seg->label;
        $this->segIsActive = (bool) $seg->is_active;
        $this->segAllowGlobalFallback = (bool) $seg->allow_global_fallback;
        $this->segNotes = $seg->notes ?? '';
        $this->showSegmentationModal = true;
    }

    public function simpanSegmentation(): void
    {
        $this->validate([
            'segLevelOrder' => ['required', 'integer', 'min:1', 'max:10'],
            'segType' => ['required', 'in:office_code,usage_type,akad_code'],
            'segLabel' => ['required', 'string', 'max:60'],
            'segNotes' => ['nullable', 'string', 'max:255'],
        ]);

        $data = [
            'level_order' => $this->segLevelOrder,
            'segment_type' => $this->segType,
            'label' => trim($this->segLabel),
            'is_active' => $this->segIsActive,
            'allow_global_fallback' => $this->segAllowGlobalFallback,
            'notes' => $this->segNotes ?: null,
        ];

        if ($this->editingSegmentationId !== null) {
            CalculationSegmentationLevel::findOrFail($this->editingSegmentationId)->update($data);
            $this->flashMessage = 'Level segmentasi berhasil diperbarui.';
        } else {
            CalculationSegmentationLevel::create($data);
            $this->flashMessage = 'Level segmentasi baru berhasil ditambahkan.';
        }

        $this->flashType = 'success';
        $this->showSegmentationModal = false;
        $this->resetSegmentationForm();
    }

    public function toggleSegmentationStatus(int $id): void
    {
        $seg = CalculationSegmentationLevel::findOrFail($id);
        $seg->update(['is_active' => ! $seg->is_active]);
        $this->flashMessage = 'Status level segmentasi berhasil diubah.';
        $this->flashType = 'success';
    }

    private function resetSegmentationForm(): void
    {
        $this->editingSegmentationId = null;
        $this->segLevelOrder = 1;
        $this->segType = 'office_code';
        $this->segLabel = '';
        $this->segIsActive = true;
        $this->segAllowGlobalFallback = true;
        $this->segNotes = '';
        $this->resetValidation();
    }

    // ==========================================
    // ACTIONS: GENERAL SETTING
    // ==========================================

    public function editSetting(int $id): void
    {
        $set = CalculationGeneralSetting::findOrFail($id);
        $this->editingSettingId = $id;
        $this->settingKey = $set->setting_key;
        $this->settingValue = $set->setting_value;
        $this->settingCategory = $set->category;
        $this->settingLabel = $set->label;
        $this->settingDescription = $set->description ?? '';
        $this->showSettingModal = true;
    }

    public function simpanSetting(): void
    {
        $this->validate([
            'settingValue' => ['required', 'string', 'max:255'],
            'settingLabel' => ['required', 'string', 'max:100'],
            'settingDescription' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->editingSettingId !== null) {
            $set = CalculationGeneralSetting::findOrFail($this->editingSettingId);
            $set->update([
                'setting_value' => trim($this->settingValue),
                'label' => trim($this->settingLabel),
                'description' => $this->settingDescription ?: null,
            ]);
            $this->flashMessage = 'Parameter umum berhasil diperbarui.';
            $this->flashType = 'success';
        }

        $this->showSettingModal = false;
        $this->editingSettingId = null;
    }

    // ==========================================
    // ACTIONS: GENERIC DELETE
    // ==========================================

    public function konfirmasiHapus(int $id, string $type): void
    {
        $this->deletingId = $id;
        $this->deletingType = $type;
    }

    public function hapus(): void
    {
        if ($this->deletingId === null || $this->deletingType === '') {
            return;
        }

        match ($this->deletingType) {
            'column_config' => CalculationColumnConfig::destroy($this->deletingId),
            'data_range' => CalculationDataRange::destroy($this->deletingId),
            'segmentation' => CalculationSegmentationLevel::destroy($this->deletingId),
            default => null,
        };

        $this->flashMessage = 'Data parameter berhasil dihapus.';
        $this->flashType = 'success';
        $this->deletingId = null;
        $this->deletingType = '';
    }

    // ==========================================
    // RENDER
    // ==========================================

    public function render(): View
    {
        $availableColumns = CalculationColumnConfig::availableColumns();
        $rangeCatalog = CalculationDataRange::rangeCatalog();
        $parameterMethods = ParameterMethod::cases();
        $calculationMethods = CalculationMethodKey::cases();
        $segmentTypes = SegmentType::cases();
        $usageTypes = UsageType::cases();

        // Data queries per sub-tab
        $columnConfigs = CalculationColumnConfig::query()
            ->when($this->search, fn ($q) => $q->where(
                fn ($w) => $w->where('pokpby_code', 'like', "%{$this->search}%")
                    ->orWhere('pokpby_label', 'like', "%{$this->search}%")
                    ->orWhere('column_name', 'like', "%{$this->search}%")
                    ->orWhere('notes', 'like', "%{$this->search}%")
            ))
            ->orderBy('method')
            ->orderBy('pokpby_code')
            ->paginate(15, ['*'], 'col_page');

        $dataRanges = CalculationDataRange::query()
            ->when($this->search, fn ($q) => $q->where(
                fn ($w) => $w->where('range_key', 'like', "%{$this->search}%")
                    ->orWhere('range_value', 'like', "%{$this->search}%")
                    ->orWhere('office_code', 'like', "%{$this->search}%")
                    ->orWhere('notes', 'like', "%{$this->search}%")
            ))
            ->orderBy('method')
            ->orderBy('range_key')
            ->paginate(15, ['*'], 'range_page');

        $segmentationLevels = CalculationSegmentationLevel::query()
            ->with('values')
            ->orderBy('level_order')
            ->get();

        $generalSettings = CalculationGeneralSetting::query()
            ->when($this->search, fn ($q) => $q->where(
                fn ($w) => $w->where('setting_key', 'like', "%{$this->search}%")
                    ->orWhere('label', 'like', "%{$this->search}%")
                    ->orWhere('setting_value', 'like', "%{$this->search}%")
            ))
            ->orderBy('category')
            ->orderBy('setting_key')
            ->get();

        return view('livewire.ckpn.calculation-parameter-index', [
            'columnConfigs' => $columnConfigs,
            'dataRanges' => $dataRanges,
            'segmentationLevels' => $segmentationLevels,
            'generalSettings' => $generalSettings,
            'availableColumns' => $availableColumns,
            'rangeCatalog' => $rangeCatalog,
            'parameterMethods' => $parameterMethods,
            'calculationMethods' => $calculationMethods,
            'segmentTypes' => $segmentTypes,
            'usageTypes' => $usageTypes,
        ]);
    }
}
