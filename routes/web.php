<?php

declare(strict_types=1);

use App\Exports\CollateralTypeTemplateExport;
use App\Exports\CollateralUploadTemplateExport;
use App\Exports\FinancingMasterTemplateExport;
use App\Exports\FinancingOfficeTemplateExport;
use App\Exports\FinancingPeriodTemplateExport;
use App\Http\Controllers\Api\UploadProgressController;
use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Admin\CalculationRunLogIndex;
use App\Livewire\Admin\UserIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Ckpn\CkpnCollectiveResultIndex;
use App\Livewire\Ckpn\CkpnIndividualResultIndex;
use App\Livewire\Ckpn\PeriodeKlasifikasiIndex;
use App\Livewire\Dashboard\Index as Dashboard;
use App\Livewire\DataPembiayaan\CollateralIndex;
use App\Livewire\DataPembiayaan\FinancingAccountIndex;
use App\Livewire\DataPembiayaan\FinancingPeriodIndex;
use App\Livewire\Export\ExportDataIndex;
use App\Livewire\Kalkulasi\HasilCkpnIndex;
use App\Livewire\Kalkulasi\LossGivenDefaultIndex;
use App\Livewire\Kalkulasi\ProbabilitasDefaultIndex;
use App\Livewire\MasterData\BucketIndex;
use App\Livewire\MasterData\CalculationParameterIndex;
use App\Livewire\MasterData\CollateralTypeIndex;
use App\Livewire\MasterData\QualityGradeIndex;
use App\Livewire\PdNetflow\PdNetflowPivotIndex;
use App\Livewire\Reporting\CkpnFinalReportIndex;
use App\Livewire\Reporting\CkpnSummaryIndex;
use App\Livewire\Reporting\DataQualityAnomalyIndex;
use App\Livewire\Reporting\LgdSummaryIndex;
use App\Livewire\UploadData\UploadBatchIndex;
use App\Livewire\UploadData\UploadIndex;
use App\Models\ExportJob;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/*
|--------------------------------------------------------------------------
| Auth routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
});

// Ref: PRD FR-14 — logout POST-only (CSRF-safe), dipindah ke LogoutController.
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// TODO(PRD Bab 12): fallback untuk stale link/bookmark GET /logout agar tidak 405.
// NOTE: Tanpa middleware auth agar tetap berfungsi walau sesi sudah kadaluarsa.
Route::get('/logout', fn () => redirect()->route('login'))->name('logout.get');

/*
|--------------------------------------------------------------------------
| Authenticated routes — semua role bisa akses (dashboard + read-only)
| Ref: PRD FR-14
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {

    // Dashboard — semua role
    Route::get('/', Dashboard::class)->name('dashboard');

    // Data Pembiayaan — semua role (read-only view)
    Route::get('/data-pembiayaan/accounts', FinancingAccountIndex::class)->name('data-pembiayaan.accounts.index');
    Route::get('/data-pembiayaan/periods', FinancingPeriodIndex::class)->name('data-pembiayaan.periods.index');
    Route::get('/data-pembiayaan/collaterals', CollateralIndex::class)->name('data-pembiayaan.collaterals.index');

    // Reporting — semua role
    Route::get('/reporting/ckpn-summary', CkpnSummaryIndex::class)->name('reporting.ckpn-summary');
    Route::get('/reporting/ckpn-final', CkpnFinalReportIndex::class)->name('reporting.ckpn-final');
    Route::get('/reporting/lgd-summary', LgdSummaryIndex::class)->name('reporting.lgd-summary');
    Route::get('/reporting/anomalies', DataQualityAnomalyIndex::class)->name('reporting.anomalies');

    /*
    |----------------------------------------------------------------------
    | Risk Analyst + Super Admin — upload, kalkulasi, master data, CKPN
    | Ref: PRD FR-14
    |----------------------------------------------------------------------
    */
    Route::middleware('role:super_admin,risk_analyst')->group(function (): void {

        // Upload data
        Route::get('/upload', UploadIndex::class)->name('upload.index');
        Route::get('/upload/batches', UploadBatchIndex::class)->name('upload.batches.index');

        // Template upload per jenis data (agar format & urutan kolom sesuai importer)
        Route::get('/upload/template/{type}', function (string $type) {
            $templates = [
                'active_financing' => FinancingMasterTemplateExport::class,
                'historical_financing' => FinancingPeriodTemplateExport::class,
                'collateral' => CollateralUploadTemplateExport::class,
                'financing_office' => FinancingOfficeTemplateExport::class,
                'collateral_type' => CollateralTypeTemplateExport::class,
            ];

            $class = $templates[$type] ?? null;
            abort_if($class === null, 404);

            return Excel::download(new $class, 'template-'.$type.'.xlsx');
        })->name('upload.template.download');

        // Master data
        Route::get('/master-data/parameters', CalculationParameterIndex::class)->name('master-data.parameters.index');
        Route::get('/master-data/buckets', BucketIndex::class)->name('master-data.buckets.index');
        Route::get('/master-data/quality-grades', QualityGradeIndex::class)->name('master-data.quality-grades.index');
        Route::get('/master-data/collateral-types', CollateralTypeIndex::class)->name('master-data.collateral-types.index');

        Route::get('/exports/download/{exportJob}', function (ExportJob $exportJob) {
            abort_if($exportJob->status !== 'done' || ! $exportJob->file_path, 404);

            $filePath = Storage::disk('local')->path($exportJob->file_path);
            abort_unless(Storage::disk('local')->exists($exportJob->file_path), 404);

            return response()->download($filePath, $exportJob->filename);
        })->name('exports.download');

        // Export data terpusat
        Route::get('/export/data', ExportDataIndex::class)->name('export.data.index');

        // Kalkulasi — tab-based pages (Ref: PRD Bab 7, 8, 9, 10, 11)
        Route::get('/kalkulasi/periode-klasifikasi', PeriodeKlasifikasiIndex::class)->name('kalkulasi.periode-klasifikasi.index');
        Route::get('/kalkulasi/pd', ProbabilitasDefaultIndex::class)->name('kalkulasi.pd.index');
        Route::get('/kalkulasi/pd/pivot', PdNetflowPivotIndex::class)->name('kalkulasi.pd.pivot');
        Route::get('/kalkulasi/lgd', LossGivenDefaultIndex::class)->name('kalkulasi.lgd.index');
        Route::get('/kalkulasi/ckpn', HasilCkpnIndex::class)->name('kalkulasi.ckpn.index');
        Route::get('/kalkulasi/ckpn/individual', CkpnIndividualResultIndex::class)->name('kalkulasi.ckpn.individual');
        Route::get('/kalkulasi/ckpn/kolektif', CkpnCollectiveResultIndex::class)->name('kalkulasi.ckpn.kolektif');
    });

    /*
    |----------------------------------------------------------------------
    | Super Admin only — manajemen user & run log
    | Ref: PRD FR-14
    |----------------------------------------------------------------------
    */
    Route::middleware('role:super_admin')->group(function (): void {
        Route::get('/admin/users', UserIndex::class)->name('admin.users.index');
        Route::get('/admin/run-logs', CalculationRunLogIndex::class)->name('admin.run-logs.index');
    });
});

/*
|--------------------------------------------------------------------------
| Upload Progress API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('api/upload-progress')->group(function (): void {
    Route::get('/{uploadId}', [UploadProgressController::class, 'show'])->name('api.upload-progress.show');
});
