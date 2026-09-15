<?php

declare(strict_types=1);

namespace App\Livewire\UploadData;

use App\Enums\UploadBatchStatus;
use App\Jobs\ProcessCollateralTypeUploadJob;
use App\Jobs\ProcessCollateralUploadJob;
use App\Jobs\ProcessFinancingMasterUploadJob;
use App\Jobs\ProcessFinancingOfficeUploadJob;
use App\Jobs\ProcessFinancingPeriodUploadJob;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Ref: PRD Bab 3 - Upload Data Pembiayaan */
#[Layout('layouts.app', ['title' => 'Upload Data'])]
class UploadIndex extends Component
{
    use WithFileUploads;

    /** Batas maksimal ukuran file upload (MB). */
    private const MAX_FILE_SIZE_MB = 20;

    /** Ekstensi file yang diizinkan untuk upload. */
    private const ALLOWED_EXTENSIONS = 'xlsx,xls,csv';

    // Upload type yang sedang aktif di-upload
    public string $activeType = '';

    // File uploads per tipe
    public $fileAktif = null;

    public $fileHistoris = null;

    public $fileJaminan = null;

    public $fileKantor = null;

    public $fileJenisJaminan = null;

    // Status pesan per tipe
    public array $messages = [];

    /** Definisi 7 jenis upload */
    public function getUploadTypesProperty(): array
    {
        return [
            'aktif' => [
                'label' => 'Pembiayaan Aktif',
                'description' => 'Data akun pembiayaan aktif periode berjalan',
                'field' => 'fileAktif',
                'upload_type' => 'active_financing',
                'icon_color' => 'bg-blue-100 text-blue-600',
                'template' => true,
            ],
            'historis' => [
                'label' => 'Historis Pembiayaan',
                'description' => 'Data historis periode sebelumnya (account periods)',
                'field' => 'fileHistoris',
                'upload_type' => 'historical_financing',
                'icon_color' => 'bg-violet-100 text-violet-600',
                'template' => true,
            ],
            'jaminan' => [
                'label' => 'Data Jaminan',
                'description' => 'Data agunan/collateral per akun pembiayaan',
                'field' => 'fileJaminan',
                'upload_type' => 'collateral',
                'icon_color' => 'bg-amber-100 text-amber-600',
                'template' => true,
            ],
            'kantor' => [
                'label' => 'Master Kantor Pembiayaan',
                'description' => 'Data master kantor cabang pembiayaan',
                'field' => 'fileKantor',
                'upload_type' => 'financing_office',
                'icon_color' => 'bg-sky-100 text-sky-600',
                'template' => true,
            ],
            'jenis_jaminan' => [
                'label' => 'Master Jenis Jaminan',
                'description' => 'Data master jenis/tipe agunan',
                'field' => 'fileJenisJaminan',
                'upload_type' => 'collateral_type',
                'icon_color' => 'bg-orange-100 text-orange-600',
                'template' => true,
            ],
        ];
    }

    /** Validasi & simpan batch upload untuk satu tipe. Nama method dihindarkan dari 'upload' untuk menghindari konflik dengan WithFileUploads::$wire.upload(). */
    public function processUpload(string $type): void
    {
        $types = $this->getUploadTypesProperty();

        if (! isset($types[$type])) {
            return;
        }

        $field = $types[$type]['field'];

        $this->validateOnly($field, [
            $field => ['required', 'file', 'mimes:'.self::ALLOWED_EXTENSIONS, 'max:'.(self::MAX_FILE_SIZE_MB * 1024)],
        ], [
            "{$field}.required" => 'Pilih file terlebih dahulu sebelum upload.',
            "{$field}.file" => 'File yang dipilih tidak valid.',
            "{$field}.mimes" => 'Jenis file tidak didukung. Gunakan format .xlsx, .xls, atau .csv.',
            "{$field}.max" => 'Ukuran file melebihi batas maksimal '.self::MAX_FILE_SIZE_MB.' MB.',
        ]);

        $file = $this->{$field};
        $filename = $file->getClientOriginalName();
        $storedPath = $file->store('uploads/financing', 'local');

        $batch = FinancingUploadBatch::create([
            'upload_type' => $types[$type]['upload_type'],
            'filename' => $filename,
            'uploaded_by_user_id' => Auth::id(),
            'uploaded_at' => now(),
            'status' => UploadBatchStatus::Pending,
            'total_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'processed_rows' => 0,
        ]);

        $this->dispatchUploadJob($batch->id, $storedPath, $types[$type]['upload_type']);

        $this->messages[$type] = [
            'type' => 'success',
            'text' => "File \"{$filename}\" berhasil diunggah dan dijadwalkan untuk diproses.",
        ];

        // Reset field file setelah upload berhasil
        $this->{$field} = null;
    }

    /** Dispatch job yang sesuai berdasarkan upload_type. Ref: PRD Bab 15 */
    private function dispatchUploadJob(int $batchId, string $filePath, string $uploadType): void
    {
        $fullPath = Storage::disk('local')->path($filePath);

        match ($uploadType) {
            'active_financing' => ProcessFinancingMasterUploadJob::dispatch($batchId, $fullPath),
            'historical_financing' => ProcessFinancingPeriodUploadJob::dispatch($batchId, $fullPath),
            'collateral' => ProcessCollateralUploadJob::dispatch($batchId, $fullPath),
            'financing_office' => ProcessFinancingOfficeUploadJob::dispatch($batchId, $fullPath),
            'collateral_type' => ProcessCollateralTypeUploadJob::dispatch($batchId, $fullPath),
            // TODO: ProcessRecoveryUploadJob belum dibuat
            default => null,
        };
    }

    public function clearMessage(string $type): void
    {
        unset($this->messages[$type]);
    }

    public function render(): View
    {
        return view('livewire.upload-data.upload-index', [
            'uploadTypes' => $this->getUploadTypesProperty(),
            'maxFileSizeMb' => self::MAX_FILE_SIZE_MB,
            'allowedExtensions' => self::ALLOWED_EXTENSIONS,
        ]);
    }
}
