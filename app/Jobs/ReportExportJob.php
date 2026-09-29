<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Services\ReportGenerationService;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $period,
        private readonly UsageType $usageType,
        private readonly ?string $recipientEmail = null,
    ) {
        $this->queue = 'ckpn-calculation';
    }

    public function handle(ReportGenerationService $reportService): void
    {
        try {
            $data = $reportService->generateRekapitulasi($this->period, $this->usageType);

            // Generate PDF
            $pdf = Pdf::loadView('reports.rekapitulasi', ['data' => $data]);
            $filename = "rekapitulasi_{$this->period}_{$this->usageType->value}_" . now()->format('Ymd_His') . '.pdf';
            $path = "reports/{$filename}";

            Storage::disk('local')->put($path, $pdf->output());

            // Email if recipient provided
            if ($this->recipientEmail) {
                Mail::send('emails.report_export', ['data' => $data, 'filename' => $filename], function ($message) {
                    $message
                        ->to($this->recipientEmail)
                        ->subject("CKPN Report - Period {$this->period}")
                        ->attach(storage_path("app/{$path}"));
                });
            }
        } catch (\Exception $e) {
            \Log::error("ReportExportJob failed for period {$this->period}", [
                'exception' => $e->getMessage(),
                'usage_type' => $this->usageType->value,
            ]);

            throw $e;
        }
    }
}
