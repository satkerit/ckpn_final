<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\FinancingHistoryExport;
use App\Exports\FinancingPeriodExport;
use App\Exports\LgdCsSourceExport;
use App\Exports\LgdErSourceExport;
use App\Exports\PdNetflowSourceExport;
use App\Models\ExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CsvExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        private readonly int $exportJobId,
        private readonly string $type,
        private readonly array $filters,
    ) {}

    public function handle(): void
    {
        $job = ExportJob::findOrFail($this->exportJobId);
        $job->update(['status' => 'processing', 'error_message' => null]);

        try {
            [$export, $prefix] = $this->exportDefinition();
            $filename = $this->safeFilename($prefix.'-'.($this->filters['periode'] ?: 'all').'-'.($this->filters['usage_type'] ?: 'all').'.csv');
            $path = 'exports/'.$filename;
            $temporaryPath = $path.'.part';
            Storage::disk('local')->makeDirectory('exports');
            $stream = fopen(Storage::disk('local')->path($temporaryPath), 'wb');
            fputcsv($stream, $export->headings());
            $query = $export->query();
            $key = $query->getModel()->getTable().'.id';
            $query->chunkById(500, function ($rows) use ($stream, $export): void {
                foreach ($rows as $row) {
                    fputcsv($stream, $export->map($row));
                }
            }, $key, 'id');
            fclose($stream);
            Storage::disk('local')->move($temporaryPath, $path);

            if (! Storage::disk('local')->exists($path)) {
                throw new \RuntimeException('File export tidak tersedia setelah proses selesai.');
            }

            $job->update(['status' => 'done', 'filename' => $filename, 'file_path' => $path]);
        } catch (\Throwable $exception) {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
            $job->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }

    private function exportDefinition(): array
    {
        $period = (string) ($this->filters['periode'] ?? '');
        $usage = (string) ($this->filters['usage_type'] ?? '');

        return match ($this->type) {
            'financing' => [new FinancingPeriodExport($period), 'daftar-pembiayaan'],
            'financing_history' => [new FinancingHistoryExport($period, $usage), 'history-pembiayaan'],
            'pd_netflow' => [new PdNetflowSourceExport($period, $usage), 'nasabah-pd-netflow'],
            'lgd_er' => [new LgdErSourceExport($period, $usage), 'nasabah-lgd-er'],
            'lgd_cs' => [new LgdCsSourceExport($period, $usage), 'nasabah-lgd-cs'],
            default => throw new \InvalidArgumentException('Tipe export tidak valid.'),
        };
    }

    private function safeFilename(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'export.csv';
    }
}
