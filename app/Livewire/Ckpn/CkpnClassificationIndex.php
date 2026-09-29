<?php

declare(strict_types=1);

namespace App\Livewire\Ckpn;

use App\Enums\ClassificationType;
use App\Enums\UsageType;
use App\Exports\CkpnClassificationExport;
use App\Domain\Ckpn\Services\SyncCalculationService;
use App\Models\CkpnPeriod;
use App\Models\CkpnPeriodClassification;
use App\Models\FinancingOffice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tabel read-only klasifikasi pembiayaan per periode CKPN.
 * Ref: PRD Bab 6.1, 12a Step 3
 * Support segmentasi 3-level: kantor (office_code), akad (akad_code), usage type
 */
#[Layout('layouts.app', ['title' => 'Klasifikasi Pembiayaan CKPN'])]
class CkpnClassificationIndex extends Component
{
    use WithPagination;

    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url(as: 'usage_type')]
    public string $filterUsageType = '';

    #[Url(as: 'office_code')]
    public string $filterOfficeCode = '';

    #[Url(as: 'akad_code')]
    public string $filterAkadCode = '';

    #[Url(as: 'classification')]
    public string $filterClassification = '';

    #[Url(as: 'search')]
    public string $search = '';

    public string $classificationPeriod = '';

    /** Apakah tabel hasil klasifikasi sedang ditampilkan (setelah klik "Tampil"). */
    public bool $showTable = false;

    /** Aksi yang menunggu konfirmasi: 'klasifikasi' | 'hapus' | '' */
    public string $confirmingAction = '';

    public function tampilkan(): void
    {
        if ($this->classificationPeriod === '') {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode penetapan CKPN terlebih dahulu.');

            return;
        }

        $this->filterPeriode = $this->classificationPeriod;
        $this->showTable = true;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClassificationPeriod(): void
    {
        $this->showTable = false;
        $this->filterPeriode = '';
        $this->resetPage();
    }

    public function confirmKlasifikasi(): void
    {
        if ($this->classificationPeriod === '') {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode penetapan CKPN terlebih dahulu.');

            return;
        }

        $this->confirmingAction = 'klasifikasi';
    }

    public function confirmHapusKlasifikasi(): void
    {
        if ($this->classificationPeriod === '') {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode penetapan CKPN terlebih dahulu.');

            return;
        }

        $this->confirmingAction = 'hapus';
    }

    /** Export data klasifikasi ke file Excel sesuai filter aktif. */
    public function exportExcel(): BinaryFileResponse
    {
        $periodSuffix = $this->filterPeriode !== '' ? '-'.$this->filterPeriode : ($this->classificationPeriod !== '' ? '-'.$this->classificationPeriod : '');
        $filename = 'klasifikasi-ckpn'.$periodSuffix.'-'.now()->format('YmdHis').'.xlsx';

        return Excel::download(
            new CkpnClassificationExport(
                $this->filterPeriode !== '' ? $this->filterPeriode : $this->classificationPeriod,
                $this->filterUsageType,
                $this->filterClassification,
                $this->search
            ),
            $filename
        );
    }

    public function hapusKlasifikasi(): void
    {
        $this->confirmingAction = '';
        $period = CkpnPeriod::where('period', $this->classificationPeriod)->first();

        if (! $period) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode penetapan CKPN terlebih dahulu.');

            return;
        }

        $this->authorize('update', $period);

        if ($period->isApproved()) {
            $this->dispatch('notify', type: 'error', message: 'Periode yang sudah Approved tidak dapat dihapus klasifikasinya.');

            return;
        }

        // Hapus data staging klasifikasi periode ini, lalu reset flag is_classified
        CkpnPeriodClassification::where('period', $this->classificationPeriod)->delete();
        $period->update(['is_classified' => false]);

        $this->showTable = false;
        $this->filterPeriode = '';
        $this->resetPage();

        $this->dispatch('notify', type: 'success', message: "Klasifikasi periode {$this->classificationPeriod} berhasil dihapus.");
        $this->dispatch('$refresh');
    }

    public function klasifikasikan(): void
    {
        $this->confirmingAction = '';
        $period = CkpnPeriod::where('period', $this->classificationPeriod)->first();

        if (! $period) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode penetapan CKPN terlebih dahulu.');

            return;
        }

        $this->authorize('update', $period);

        if ($period->isApproved()) {
            $this->dispatch('notify', type: 'error', message: 'Periode yang sudah Approved tidak dapat diklasifikasikan ulang.');

            return;
        }

        // Cek apakah data staging sudah ada untuk periode ini
        $hasStagingData = CkpnPeriodClassification::where('period', $period->period)->exists();

        $runner = new SyncCalculationService;

        if ($hasStagingData) {
            $runner->runClassifyPeriodData($period, (int) auth()->id());
        } else {
            $runner->runPopulatePeriodDebtors($period, (int) auth()->id());
            $runner->runClassifyPeriodData($period, (int) auth()->id());
        }

        $this->dispatch('notify', type: 'success', message: "Klasifikasi periode {$period->period} selesai dijalankan.");
    }

    public function updatedFilterPeriode(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUsageType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterOfficeCode(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAkadCode(): void
    {
        $this->resetPage();
    }

    public function updatedFilterClassification(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $selectedPeriod = $this->classificationPeriod !== ''
            ? CkpnPeriod::where('period', $this->classificationPeriod)->first()
            : null;

        $periodClassified = (bool) ($selectedPeriod?->is_classified ?? false);
        $periodApproved = (bool) ($selectedPeriod?->isApproved() ?? false);

        $classifications = $this->showTable
            ? CkpnPeriodClassification::query()
                ->with(['financingAccount', 'ckpnPeriod'])
                ->when($this->filterPeriode !== '', fn ($q) => $q->where('period', $this->filterPeriode))
                ->when($this->filterUsageType !== '', fn ($q) => $q->where('usage_type', (int) $this->filterUsageType))
                ->when($this->filterOfficeCode !== '', fn ($q) => $q->where('office_code', $this->filterOfficeCode))
                ->when($this->filterAkadCode !== '', fn ($q) => $q->where('akad_code', $this->filterAkadCode))
                ->when($this->filterClassification !== '', fn ($q) => $q->where('classification', $this->filterClassification))
                ->when($this->search, fn ($q) => $q->where(
                    fn ($w) => $w
                        ->where('period', 'like', "%{$this->search}%")
                        ->orWhereHas('financingAccount', fn ($fa) => $fa->where('customer_name', 'like', "%{$this->search}%"))
                ))
                ->orderByDesc('period')
                ->orderBy('usage_type')
                ->paginate(25)
            : (new LengthAwarePaginator([], 0, 25));

        $periods = CkpnPeriod::select('period')->orderByDesc('period')->pluck('period')->unique();
        $usageTypes = UsageType::cases();
        $classificationTypes = ClassificationType::cases();
        $offices = FinancingOffice::select('code as office_code', 'name as office_name')->orderBy('code')->get();
        $akadCodes = CkpnPeriodClassification::select('akad_code')->distinct()->whereNotNull('akad_code')->pluck('akad_code')->sort()->values();

        $totalOutstanding = $this->showTable
            ? CkpnPeriodClassification::query()
                ->when($this->filterPeriode !== '', fn ($q) => $q->where('period', $this->filterPeriode))
                ->when($this->filterUsageType !== '', fn ($q) => $q->where('usage_type', (int) $this->filterUsageType))
                ->when($this->filterOfficeCode !== '', fn ($q) => $q->where('office_code', $this->filterOfficeCode))
                ->when($this->filterAkadCode !== '', fn ($q) => $q->where('akad_code', $this->filterAkadCode))
                ->when($this->filterClassification !== '', fn ($q) => $q->where('classification', $this->filterClassification))
                ->sum('outstanding_balance')
            : 0.0;

        return view('livewire.ckpn.ckpn-classification-index', [
            'classifications' => $classifications,
            'periods' => $periods,
            'usageTypes' => $usageTypes,
            'offices' => $offices,
            'akadCodes' => $akadCodes,
            'classificationTypes' => $classificationTypes,
            'totalOutstanding' => $totalOutstanding,
            'periodClassified' => $periodClassified,
            'periodApproved' => $periodApproved,
        ]);
    }
}
