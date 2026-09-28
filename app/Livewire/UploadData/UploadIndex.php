<?php

declare(strict_types=1);

namespace App\Livewire\UploadData;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use App\Services\UploadProcessorService;
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

    /** Ekstensi file yang diizinkan untuk upload (.xlsx dan .csv didukung oleh OpenSpout). */
    private const ALLOWED_EXTENSIONS = 'xlsx,csv';

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

    /**
     * Tahap 1 (cepat): validasi file, simpan ke disk, buat record batch,
     * lalu kirim event ke frontend untuk membuka dialog progress bar.
     *
     * Proses impor berat dijalankan terpisah lewat executeUpload() agar UI
     * dapat menampilkan progress tanpa menunggu request selesai.
     */
    public function processUpload(string $type): void
    {
        $this->authorize('create', FinancingUploadBatch::class);

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
            "{$field}.mimes" => 'Jenis file tidak didukung. Gunakan format .xlsx atau .csv.',
            "{$field}.max" => 'Ukuran file melebihi batas maksimal '.self::MAX_FILE_SIZE_MB.' MB.',
        ]);

        $file = $this->{$field};
        $filename = $file->getClientOriginalName();
        $storedPath = $file->store('uploads/financing', 'local');

        $batch = FinancingUploadBatch::create([
            'upload_type' => $types[$type]['upload_type'],
            'filename' => $filename,
            'file_path' => $storedPath,
            'uploaded_by_user_id' => Auth::id(),
            'uploaded_at' => now(),
            'status' => UploadBatchStatus::Pending,
            'total_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'processed_rows' => 0,
        ]);

        // Reset input file agar tidak ter-upload ulang
        $this->{$field} = null;

        $this->dispatch(
            'start-upload-progress',
            batchId: $batch->id,
            filename: $filename,
            typeKey: $type,
            label: $types[$type]['label'],
        );
    }

    /**
     * Tahap 2 (berat): jalankan impor sinkron via UploadProcessorService.
     * Dipanggil frontend tanpa await, sehingga dialog progress tetap responsif.
     */
    public function executeUpload(int $batchId): void
    {
        $batch = FinancingUploadBatch::findOrFail($batchId);

        // Cegah path/file milik user lain diproses
        abort_unless(
            $batch->uploaded_by_user_id === Auth::id() || Auth::user()?->hasRole('super_admin'),
            403,
        );

        // Guard idempotency: jangan proses ulang batch yang sudah selesai/diproses
        if (in_array($batch->status, [UploadBatchStatus::Done, UploadBatchStatus::Processing], true)) {
            return;
        }

        $typeKey = $this->resolveTypeKeyByUploadType($batch->upload_type);

        if ($typeKey === null || $batch->file_path === null) {
            $this->dispatch('upload-finished', success: false, message: 'Data batch tidak valid untuk diproses.');

            return;
        }

        $fullPath = Storage::disk('local')->path($batch->file_path);

        $exceptionMessage = '';

        try {
            app(UploadProcessorService::class)->process($batchId, $fullPath, $batch->upload_type);
        } catch (\Throwable $e) {
            report($e);
            $exceptionMessage = $e->getMessage();
        }

        $batch->refresh();

        if ($batch->status === UploadBatchStatus::Done) {
            $errorCount = count($batch->error_summary ?? []);
            $message = "File \"{$batch->filename}\" berhasil diproses. {$batch->imported_rows} baris diimpor";

            if ($batch->skipped_rows > 0) {
                $message .= ", {$batch->skipped_rows} baris dilewati";
            }

            if ($errorCount > 0) {
                $message .= ", {$errorCount} error ditemukan";
            }

            $this->messages[$typeKey] = [
                'type' => $errorCount > 0 ? 'warning' : 'success',
                'text' => $message.'.',
                'errors' => $errorCount > 0 ? $this->formatErrorList($batch->error_summary ?? []) : [],
            ];

            $this->dispatch('upload-finished', success: true, message: $message, hasErrors: $errorCount > 0);
        } else {
            $mainError = $this->extractMainError($batch->error_summary ?? []);

            if ($mainError === '' && $exceptionMessage !== '') {
                $mainError = $exceptionMessage;
            }

            $this->messages[$typeKey] = [
                'type' => 'error',
                'text' => "File \"{$batch->filename}\" gagal diproses.".($mainError !== '' ? ' '.$mainError : ''),
                'errors' => $this->formatErrorList($batch->error_summary ?? []),
            ];

            $this->dispatch(
                'upload-finished',
                success: false,
                message: $mainError !== '' ? $mainError : 'Upload gagal diproses. Cek detail error pada kartu upload.',
                hasErrors: false,
            );
        }
    }

    /** Cari key tipe upload dari nilai upload_type batch. */
    private function resolveTypeKeyByUploadType(string $uploadType): ?string
    {
        foreach ($this->getUploadTypesProperty() as $key => $type) {
            if ($type['upload_type'] === $uploadType) {
                return $key;
            }
        }

        return null;
    }

    /** Ambil pesan error utama dari error_summary batch. */
    private function extractMainError(array $errorSummary): string
    {
        if ($errorSummary === []) {
            return '';
        }

        $first = $errorSummary[0];

        if (is_string($first)) {
            return $first;
        }

        if (! is_array($first)) {
            return '';
        }

        $row = $first['row'] ?? null;
        $field = $first['field'] ?? null;
        $error = (string) ($first['error'] ?? '');

        $prefix = '';
        if (is_numeric($row)) {
            $prefix = "Baris {$row}: ";
        } elseif ($field !== null && $field !== '' && $field !== 'general') {
            $prefix = "Kolom {$field}: ";
        }

        return $prefix.$error;
    }

    /**
     * Format daftar error_summary menjadi array string ringkas (maks 5 entri)
     * untuk ditampilkan langsung di alert card.
     *
     * @return array<int, string>
     */
    private function formatErrorList(array $errorSummary): array
    {
        if ($errorSummary === []) {
            return [];
        }

        $formatted = [];
        $maxDisplay = 5;
        $count = 0;

        foreach ($errorSummary as $item) {
            if ($count >= $maxDisplay) {
                $remaining = count($errorSummary) - $maxDisplay;
                $formatted[] = "...dan {$remaining} error lainnya (lihat di halaman Riwayat Upload).";

                break;
            }

            if (is_string($item)) {
                $formatted[] = $item;
                $count++;

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $row = $item['row'] ?? null;
            $field = $item['field'] ?? null;
            $error = (string) ($item['error'] ?? 'Terjadi kesalahan');

            $prefix = '';
            if (is_numeric($row)) {
                $prefix = "Baris {$row}: ";
            } elseif ($field !== null && $field !== '' && $field !== 'general') {
                $prefix = "Kolom {$field}: ";
            }

            $formatted[] = $prefix.$error;
            $count++;
        }

        return $formatted;
    }

    /** Clear pesan status untuk tipe upload tertentu */
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
