<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UsageType;
use App\Jobs\ReportExportJob;
use Illuminate\Console\Command;

class GenerateDailyReportsCommand extends Command
{
    protected $signature = 'reports:generate-daily';

    protected $description = 'Generate daily CKPN reports for all usage types (scheduled via cron)';

    public function handle(): int
    {
        $usageTypes = UsageType::cases();
        $period = now()->subMonth()->format('Ym'); // Previous month

        foreach ($usageTypes as $usageType) {
            ReportExportJob::dispatch(
                $period,
                $usageType,
                config('app.report_recipient_email'),
            );

            $this->line("Queued report for period {$period}, usage type: {$usageType->label()}");
        }

        $this->info('All daily reports queued successfully.');

        return self::SUCCESS;
    }
}
