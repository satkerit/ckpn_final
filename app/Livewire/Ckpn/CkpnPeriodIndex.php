<?php

declare(strict_types=1);

namespace App\Livewire\Ckpn;

use App\Enums\PdMethod;
use App\Models\CkpnPeriod;
use App\Models\FinancingAccountPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD periode penilaian CKPN + manajemen status.
 * Ref: PRD Bab 12a Step 2, FR-11
 */
#[Layout('layouts.app', ['title' => 'Periode CKPN'])]
class CkpnPeriodIndex extends Component
{
    use WithPagination;

    // --- State form ---
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formPeriod = '';

    public string $formStatus = 'draft';

    public string $formPdMethod = '';

    public string $formNotes = '';

    #[Url(as: 'search')]
    public string $search = '';

    // --- State konfirmasi hapus ---
    public ?int $deletingId = null;

    // --- Notifikasi ---
    public string $flashMessage = '';

    public string $flashType = 'success'; // 'success' | 'error'

    protected function rules(): array
    {
        return [
            'formPeriod' => ['required', 'regex:/^\d{6}$/'],
            'formStatus' => ['required', 'in:draft,in_progress,completed,approved'],
            'formPdMethod' => ['nullable', 'in:netflow,migration'],
            'formNotes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'formPeriod.required' => 'Periode wajib diisi.',
            'formPeriod.regex' => 'Format periode harus yyyymm (contoh: 202412).',
            'formStatus.in' => 'Status tidak valid.',
            'formPdMethod.in' => 'Metode PD tidak valid.',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function buatBaru(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->editingId = null;
    }

    public function edit(int $id): void
    {
        $period = CkpnPeriod::findOrFail($id);
        $this->editingId = $id;
        $this->formPeriod = $period->period;
        $this->formStatus = $period->status;
        $this->formPdMethod = $period->pd_method?->value ?? '';
        $this->formNotes = $period->notes ?? '';
        $this->showForm = true;
    }

    public function simpan(): void
    {
        $this->authorize($this->editingId !== null ? 'update' : 'create', CkpnPeriod::class);

        $this->validate();

        $data = [
            'period' => $this->formPeriod,
            'status' => $this->formStatus,
            'pd_method' => $this->formPdMethod !== '' ? PdMethod::from($this->formPdMethod) : null,
            'notes' => $this->formNotes ?: null,
        ];

        if ($this->editingId !== null) {
            $period = CkpnPeriod::findOrFail($this->editingId);

            // Cegah edit periode yang sudah approved — Ref: AGENTS.md §4
            if ($period->isApproved()) {
                $this->flashMessage = 'Periode yang sudah Approved tidak dapat diubah.';
                $this->flashType = 'error';

                return;
            }

            $period->update($data);
            $this->flashMessage = 'Periode berhasil diperbarui.';
        } else {
            $data['created_by_user_id'] = auth()->id();
            CkpnPeriod::create($data);
            $this->flashMessage = 'Periode baru berhasil dibuat.';
        }

        $this->flashType = 'success';
        $this->resetForm();
        $this->showForm = false;
        $this->resetPage();
        $this->dispatch('$refresh');
    }

    public function konfirmasiHapus(int $id): void
    {
        $this->deletingId = $id;
    }

    public function hapus(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $period = CkpnPeriod::findOrFail($this->deletingId);
        $this->authorize('delete', $period);

        if ($period->isApproved()) {
            $this->flashMessage = 'Periode yang sudah Approved tidak dapat dihapus.';
            $this->flashType = 'error';
            $this->deletingId = null;

            return;
        }

        DB::transaction(function () use ($period): void {
            $p = $period->period;

            // Hapus semua data hasil langsung by calculation_period
            // calculation_run_log hanya dihapus sebagai cleanup riwayat, bukan sebagai verifikasi

            // Klasifikasi
            DB::table('ckpn_period_classifications')->where('ckpn_period_id', $period->id)->delete();

            // PD Netflow — Ref: PRD Bab 7
            DB::table('pd_netflow_calculation_histories')->where('calculation_period', $p)->delete();
            DB::table('pd_netflow_compound_rate')->where('start_period', $p)->delete();
            DB::table('pd_netflow_bucket_movement')->where('period', $p)->delete();
            DB::table('pd_netflow_result')->where('calculation_period', $p)->delete();

            // PD Migration — Ref: PRD Bab 8
            // pd_migration_matrix tidak punya kolom calculation_period, hapus via run_log_ids
            $migrationRunLogIds = DB::table('calculation_run_log')->where('period', $p)->pluck('id');
            if ($migrationRunLogIds->isNotEmpty()) {
                DB::table('pd_migration_matrix')->whereIn('calculation_run_log_id', $migrationRunLogIds)->delete();
            }
            DB::table('pd_migration_result')->where('calculation_period', $p)->delete();

            // LGD ER — Ref: PRD Bab 9
            DB::table('lgd_expected_recoveries_result')->where('calculation_period', $p)->delete();

            // LGD CS — Ref: PRD Bab 10
            DB::table('lgd_collateral_shortfall_by_segment_result')->where('calculation_period', $p)->delete();
            DB::table('lgd_collateral_shortfall_result')->where('calculation_period', $p)->delete();

            // LGD Final — Ref: PRD Bab 11
            DB::table('lgd_final_result')->where('calculation_period', $p)->delete();

            // CKPN Individual & Kolektif — Ref: PRD Bab 6 & 11
            DB::table('ckpn_individual_result')->where('calculation_period', $p)->delete();
            DB::table('ckpn_collective_result')->where('calculation_period', $p)->delete();

            // Cleanup riwayat run log (bukan verifikasi)
            DB::table('calculation_run_log')->where('period', $p)->delete();

            $period->delete();
        });

        $this->flashMessage = 'Periode beserta seluruh data kalkulasi (PD Netflow/Migration, LGD ER/CS/Final, CKPN Individual/Kolektif) berhasil dihapus.';
        $this->flashType = 'success';
        $this->deletingId = null;
        $this->resetPage();
        $this->dispatch('$refresh');
    }

    /**
     * Ubah status periode (transisi maju).
     * Ref: PRD FR-11
     */
    public function ubahStatus(int $id, string $statusBaru): void
    {
        $period = CkpnPeriod::findOrFail($id);
        $this->authorize('update', $period);

        $allowed = ['draft', 'in_progress', 'completed', 'approved'];
        if (! in_array($statusBaru, $allowed, true)) {
            return;
        }

        $period = CkpnPeriod::findOrFail($id);

        $updateData = ['status' => $statusBaru];
        if ($statusBaru === 'approved') {
            $updateData['approved_by_user_id'] = auth()->id();
            $updateData['approved_at'] = now();
        }

        $period->update($updateData);
        $this->flashMessage = 'Status periode diubah ke '.$statusBaru.'.';
        $this->flashType = 'success';
        $this->dispatch('$refresh');
    }

    public function batalForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formPeriod = '';
        $this->formStatus = 'draft';
        $this->formPdMethod = '';
        $this->formNotes = '';
        $this->resetValidation();
    }

    public function render(): View
    {
        $periods = CkpnPeriod::with(['createdBy', 'approvedBy'])
            ->when($this->search, fn ($q) => $q->where(
                fn ($w) => $w
                    ->where('period', 'like', "%{$this->search}%")
                    ->orWhere('status', 'like', "%{$this->search}%")
                    ->orWhere('pd_method', 'like', "%{$this->search}%")
                    ->orWhere('notes', 'like', "%{$this->search}%")
            ))
            ->orderByDesc('period')
            ->paginate(20);

        $pdMethods = PdMethod::cases();

        // Total outstanding/EAD memakai filter baseline yang sama dengan PopulatePeriodDebtorsJob
        // (aktif A, bukan write-off W, produk != 72, akad 03 hanya jika sudah jatuh tempo) — Ref: PRD Bab 6.1
        // Tanggal asesmen = akhir bulan dari periode masing-masing baris (yyyymm).
        $outstandingByPeriod = DB::table('financing_account_periods as fap')
            ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
            ->where('fap.financing_status', 'A')
            ->where(fn ($q) => $q->whereNull('fap.writeoff_status')->orWhere('fap.writeoff_status', '!=', 'W'))
            ->where(function ($q): void {
                $q->whereNull('fa.product_code')
                    ->orWhere('fa.product_code', '!=', '72');
            })
            ->where(function ($query): void {
                $query->where('fa.akad_code', '!=', '03')
                    ->orWhere(function ($m): void {
                        $m->where('fa.akad_code', '03')
                            ->whereNotNull('fap.maturity_date')
                            ->whereRaw("fap.maturity_date <= LAST_DAY(STR_TO_DATE(CONCAT(fap.period, '01'), '%Y%m%d'))");
                    });
            })
            ->select('fap.period', DB::raw('SUM(fap.outstanding_balance) as total_outstanding'))
            ->groupBy('fap.period')
            ->pluck('total_outstanding', 'period');

        $availablePeriods = FinancingAccountPeriod::query()
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period', 'period');

        return view('livewire.ckpn.ckpn-period-index', [
            'periods' => $periods,
            'pdMethods' => $pdMethods,
            'outstandingByPeriod' => $outstandingByPeriod,
            'availablePeriods' => $availablePeriods,
        ]);
    }
}
