<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Domain\Ckpn\Collective\CkpnCollectiveCalculator;
use App\Domain\Ckpn\Individual\CkpnIndividualCalculator;
use App\Domain\Ckpn\Lgd\CollateralShortfall\LgdCollateralShortfallCalculator;
use App\Domain\Ckpn\Lgd\ExpectedRecoveries\LgdExpectedRecoveriesCalculator;
use App\Domain\Ckpn\Lgd\LgdFinalCalculator;
use App\Domain\Ckpn\Pd\Migration\MigrationMatrixBuilder;
use App\Domain\Ckpn\Pd\Migration\PdMigrationCalculator;
use App\Domain\Ckpn\Pd\Netflow\BucketMovementValidator;
use App\Domain\Ckpn\Pd\Netflow\PdNetflowCalculator;
use App\Enums\AnomalySeverity;
use App\Enums\CalculationMethodKey;
use App\Enums\ClassificationType;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Models\CalculationDataRange;
use App\Models\CalculationGeneralSetting;
use App\Models\CalculationRunLog;
use App\Models\CkpnPeriod;
use App\Models\CkpnPeriodClassification;
use App\Models\DataQualityAnomaly;
use App\Models\FinancingAccountPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Synchronous runner menggantikan semua Queue Job kalkulasi.
 * Setiap method = verbatim logic dari handle() job yang dihapus.
 * Memory strategy: ini_set 512M + array_chunk batch insert 500 baris.
 * Ref: PRD Bab 7, 8, 9, 10, 11, 6.1
 */
final class SyncCalculationService
{
    // -------------------------------------------------------------------------
    // PD Netflow — Ref: PRD Bab 7
    // -------------------------------------------------------------------------

    /**
     * @param  array<string>  $segmentDimensions
     */
    public function runPdNetflow(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        ?string $officeCode = null,
        ?string $akadCode = null,
        array $segmentDimensions = [],
    ): void {
        ini_set('memory_limit', '512M');

        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $windowMonths = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::PdNetflow,
                'pd_netflow_rolling_window_months',
                officeCode: $officeCode,
                usageType: (int) $usageType->value,
                akadCode: $akadCode,
                default: 36,
            );

            $forwardMonths = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::PdNetflow,
                'pd_netflow_forward_projection_months',
                officeCode: $officeCode,
                usageType: (int) $usageType->value,
                akadCode: $akadCode,
                default: 6,
            );

            $resolver = new RollingWindowResolver($windowMonths, $forwardMonths);
            $calculator = new PdNetflowCalculator($resolver, new BucketMovementValidator);
            $writer = new SnapshotWriter;

            if (! empty($segmentDimensions)) {
                $dynamicResult = $calculator->calculateDynamic($usageType, $calculationPeriod, $segmentDimensions);
                foreach ($dynamicResult['segment_results'] as $segmentData) {
                    $this->writePdNetflowSegment(
                        $writer, $runLog, $usageType, $calculationPeriod,
                        $resolver, $windowMonths, $forwardMonths,
                        $segmentData['result'], $segmentData['segment'],
                    );
                }
                $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);

                return;
            }

            $result = $calculator->calculate($usageType, $calculationPeriod, $officeCode, $akadCode);

            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $usageType);
            $notes = sprintf(
                "Dasar data PD Netflow [%s]:\n"
                    ."- Sumber: financing_account_periods via PdNetflowBaseline\n"
                    ."- Filter: financing_status='A' ATAU (writeoff_status='W' DAN writeoff_date=periode yyyymm); akad eligible: %s; akad 03 hanya jika jatuh tempo (maturity<=LAST_DAY(periode))\n"
                    ."- Window: rolling_window=%d bln, forward_projection=%d bln\n"
                    ."- Periode data rate: %s s.d. %s (aktual) + proyeksi s.d. %s\n"
                    ."- Bucketing: tgkhari -> bucket B1..B14\n"
                    .'- Jumlah baris historis dipakai: %d',
                $usageType->label(),
                AkadEligibilityService::formatCodes($akadCodes),
                $windowMonths,
                $forwardMonths,
                $resolver->rateStartPeriod($calculationPeriod),
                $calculationPeriod,
                $result['compound_end'],
                $result['history']['row_count'],
            );

            $writer->writePdNetflowResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                dataStart: $resolver->rateStartPeriod($calculationPeriod),
                dataEnd: $calculationPeriod,
                windowMonths: $windowMonths,
                pdRates: $result['pd_rates'],
                pdRatesPerAkad: $result['pd_rates_per_akad'] ?? null,
                notes: $notes,
                officeCode: $officeCode,
            );

            $writer->writePdNetflowDetail(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                transitionRates: $result['transition_rates'],
                compoundRates: $result['compound_rates'],
                outstandingMap: $result['outstanding_map'],
                projPeriods: $result['proj_periods'],
                rateStart: $result['rate_start'],
                compoundEnd: $result['compound_end'],
                officeCode: $officeCode,
                akadCode: $akadCode,
            );

            $writer->writePdNetflowHistory(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                history: $result['history'],
                officeCode: $officeCode,
                akadCode: $akadCode,
            );

            $lastPeriod = $calculationPeriod;
            $prevPeriod = PeriodHelper::shiftBack($lastPeriod, 1);
            $flatTransition = [];
            foreach ($result['transition_rates'] as $bucketId => $periods) {
                $flatTransition[$bucketId] = (float) ($periods[$lastPeriod] ?? end($periods) ?: 0.0);
            }
            $flatCompound = [];
            foreach ($result['compound_rates'] as $bucketId => $periods) {
                $flatCompound[$bucketId] = (float) ($periods[$lastPeriod] ?? end($periods) ?: 0.0);
            }
            $sourceOs = [];
            $destOs = [];
            foreach ($result['pd_rates'] as $bucketId => $_) {
                $sourceOs[$bucketId] = (float) ($result['outstanding_map'][$prevPeriod][$bucketId] ?? 0.0);
                $destOs[$bucketId] = (float) ($result['outstanding_map'][$lastPeriod][$bucketId] ?? 0.0);
            }

            $writer->writePdNetflowSegmented(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                dataStart: $resolver->rateStartPeriod($calculationPeriod),
                dataEnd: $calculationPeriod,
                windowMonths: $windowMonths,
                pdRates: $result['pd_rates'],
                transitionRates: $flatTransition,
                compoundRates: $flatCompound,
                sourceOs: $sourceOs,
                destOs: $destOs,
                officeCode: $officeCode,
                akadCode: $akadCode,
            );

            $writer->writePdNetflowDetailBreakdown(
                runLog: $runLog,
                usageType: $usageType,
                detailData: $result['detail_breakdown'] ?? null,
            );

            $criticalCount = DataQualityAnomaly::where('period', $calculationPeriod)
                ->where('usage_type', $usageType->value)
                ->where('severity', AnomalySeverity::Critical->value)
                ->count();

            $finalStatus = $criticalCount > 0 ? RunStatus::CompletedWithWarning : RunStatus::Completed;
            $runLog->update(['status' => $finalStatus, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    private function writePdNetflowSegment(
        SnapshotWriter $writer,
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        RollingWindowResolver $resolver,
        int $windowMonths,
        int $forwardMonths,
        array $result,
        array $segment,
    ): void {
        $segOffice = $segment['office_code'] ?? null;
        $segAkad = $segment['akad_code'] ?? null;

        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $usageType);
        $notes = sprintf(
            "Segmentasi PD Netflow [%s] — office: %s, akad: %s\n"
                ."- Sumber: financing_account_periods via PdNetflowBaseline\n"
                ."- Window: rolling_window=%d bln, forward_projection=%d bln\n"
                .'- Jumlah baris historis dipakai: %d',
            $usageType->label(),
            $segOffice ?? 'ALL',
            $segAkad ?? 'ALL',
            $windowMonths,
            $forwardMonths,
            $result['history']['row_count'],
        );

        $writer->writePdNetflowResult(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $calculationPeriod,
            dataStart: $resolver->rateStartPeriod($calculationPeriod),
            dataEnd: $calculationPeriod,
            windowMonths: $windowMonths,
            pdRates: $result['pd_rates'],
            pdRatesPerAkad: $result['pd_rates_per_akad'] ?? null,
            notes: $notes,
            officeCode: $segOffice,
        );

        $writer->writePdNetflowDetail(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $calculationPeriod,
            transitionRates: $result['transition_rates'],
            compoundRates: $result['compound_rates'],
            outstandingMap: $result['outstanding_map'],
            projPeriods: $result['proj_periods'],
            rateStart: $result['rate_start'],
            compoundEnd: $result['compound_end'],
            officeCode: $segOffice,
            akadCode: $segAkad,
        );

        $writer->writePdNetflowHistory(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $calculationPeriod,
            history: $result['history'],
            officeCode: $segOffice,
            akadCode: $segAkad,
        );
    }

    // -------------------------------------------------------------------------
    // PD Migration — Ref: PRD Bab 8
    // -------------------------------------------------------------------------

    /**
     * @param  array<string>  $segmentDimensions
     */
    public function runPdMigration(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        ?string $officeCode = null,
        ?string $akadCode = null,
        array $segmentDimensions = [],
    ): void {
        ini_set('memory_limit', '512M');

        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $builder = new MigrationMatrixBuilder;
            $calculator = new PdMigrationCalculator($builder);
            $writer = new SnapshotWriter;

            if (! empty($segmentDimensions)) {
                $dynamicResult = $calculator->calculateDynamic($usageType, $calculationPeriod, $segmentDimensions);
                foreach ($dynamicResult['segment_results'] as $segmentData) {
                    $this->writePdMigrationSegment(
                        $writer, $runLog, $usageType, $calculationPeriod,
                        $builder, $segmentData['segment'],
                    );
                }
                $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);

                return;
            }

            $lookbackMonths = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::PdMigration,
                'pd_migration_lookback_months',
                officeCode: $officeCode,
                usageType: (int) $usageType->value,
                akadCode: $akadCode,
                default: 12,
            );

            $matrixCount = (int) (CalculationDataRange::resolveValue(
                CalculationMethodKey::PdMigration,
                'pd_migration_matrix_count',
                officeCode: $officeCode,
                usageType: (int) $usageType->value,
                akadCode: $akadCode,
                default: null,
            ) ?? 12);

            $pdRates = $calculator->calculate($usageType, $calculationPeriod, $officeCode, $akadCode);

            $anchor = PeriodHelper::anchorQuarter($calculationPeriod);
            $earliestEnd = PeriodHelper::shiftBack($anchor, ($matrixCount - 1) * 3);
            $dataStart = PeriodHelper::shiftBack($earliestEnd, 12);
            $dataEnd = $calculationPeriod;

            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $usageType);
            $notes = sprintf(
                "Dasar data PD Migration [%s]:\n"
                    ."- Sumber: financing_outstanding_quarterly (posisi awal & akhir matriks) + financing_account_periods (writeoff)\n"
                    ."- Filter: akad eligible: %s; akad 03 hanya jika JTP saat writeoff (maturity<=writeoff_date)\n"
                    ."- Anchor Quarter (T): %s; jumlah matriks migrasi = %d (M1=T, Mk = T - 3(k-1) bln), masing-masing dilacak 12 bln\n"
                    ."- Data range: %s s.d. %s\n"
                    .'- matrix_count=%d',
                $usageType->label(),
                AkadEligibilityService::formatCodes($akadCodes),
                $anchor,
                $matrixCount,
                $dataStart,
                $dataEnd,
                $matrixCount,
            );

            $writer->writePdMigrationResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                dataStart: $dataStart,
                dataEnd: $dataEnd,
                cohortCount: $matrixCount,
                pdRates: $pdRates,
                notes: $notes,
                officeCode: $officeCode,
                akadCode: $akadCode,
            );

            $cohorts = $calculator->getCohorts($calculationPeriod);
            $matrixRows = [];
            foreach ($cohorts as [$startPeriod, $endPeriod]) {
                foreach ($builder->buildRows($usageType, $startPeriod, $endPeriod, $officeCode, $akadCode) as $row) {
                    $matrixRows[] = [
                        'from_quality_grade_id' => $row['from_quality_grade_id'],
                        'to_quality_grade_id' => $row['to_quality_grade_id'],
                        'cohort_period' => $startPeriod,
                        'migration_rate' => $row['migration_rate'],
                        'source_outstanding' => $row['source_outstanding'],
                        'destination_outstanding' => $row['destination_outstanding'],
                    ];
                }
            }

            $writer->writePdMigrationMatrix(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                rows: $matrixRows,
                officeCode: $officeCode,
                akadCode: $akadCode,
            );

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    private function writePdMigrationSegment(
        SnapshotWriter $writer,
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        MigrationMatrixBuilder $builder,
        array $segment,
    ): void {
        $officeCode = $segment['office_code'] ?? null;
        $akadCode = $segment['akad_code'] ?? null;

        $matrixCount = (int) (CalculationDataRange::resolveValue(
            CalculationMethodKey::PdMigration,
            'pd_migration_matrix_count',
            officeCode: $officeCode,
            usageType: (int) $usageType->value,
            akadCode: $akadCode,
            default: null,
        ) ?? 12);

        $calculator = new PdMigrationCalculator($builder, $matrixCount);
        $pdRates = $calculator->calculate($usageType, $calculationPeriod, $officeCode, $akadCode);

        $anchor = PeriodHelper::anchorQuarter($calculationPeriod);
        $earliestEnd = PeriodHelper::shiftBack($anchor, ($matrixCount - 1) * 3);
        $dataStart = PeriodHelper::shiftBack($earliestEnd, 12);
        $dataEnd = $calculationPeriod;

        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $usageType);
        $notes = sprintf(
            "Dasar data PD Migration [%s]:\n"
                ."- Sumber: financing_outstanding_quarterly (posisi awal & akhir matriks) + financing_account_periods (writeoff)\n"
                ."- Filter: akad eligible: %s; akad 03 hanya jika JTP saat writeoff (maturity<=writeoff_date)\n"
                ."- Anchor Quarter (T): %s; jumlah matriks migrasi = %d (M1=T, Mk = T - 3(k-1) bln), masing-masing dilacak 12 bln\n"
                ."- Data range: %s s.d. %s\n"
                .'- matrix_count=%d',
            $usageType->label(),
            AkadEligibilityService::formatCodes($akadCodes),
            $anchor,
            $matrixCount,
            $dataStart,
            $dataEnd,
            $matrixCount,
        );

        $writer->writePdMigrationResult(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $calculationPeriod,
            dataStart: $dataStart,
            dataEnd: $dataEnd,
            cohortCount: $matrixCount,
            pdRates: $pdRates,
            notes: $notes,
            officeCode: $officeCode,
            akadCode: $akadCode,
        );

        $cohorts = $calculator->getCohorts($calculationPeriod);
        $matrixRows = [];
        foreach ($cohorts as [$startPeriod, $endPeriod]) {
            foreach ($builder->buildRows($usageType, $startPeriod, $endPeriod, $officeCode, $akadCode) as $row) {
                $matrixRows[] = [
                    'from_quality_grade_id' => $row['from_quality_grade_id'],
                    'to_quality_grade_id' => $row['to_quality_grade_id'],
                    'cohort_period' => $startPeriod,
                    'migration_rate' => $row['migration_rate'],
                    'source_outstanding' => $row['source_outstanding'],
                    'destination_outstanding' => $row['destination_outstanding'],
                ];
            }
        }

        $writer->writePdMigrationMatrix(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $calculationPeriod,
            rows: $matrixRows,
            officeCode: $officeCode,
            akadCode: $akadCode,
        );
    }

    // -------------------------------------------------------------------------
    // LGD Expected Recoveries — Ref: PRD Bab 9
    // -------------------------------------------------------------------------

    /**
     * @param  array<string>  $segmentDimensions
     */
    public function runLgdEr(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        ?string $officeCode = null,
        ?string $akadCode = null,
        array $segmentDimensions = [],
    ): void {
        ini_set('memory_limit', '512M');

        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $calculator = app(LgdExpectedRecoveriesCalculator::class);
            $writer = new SnapshotWriter;

            if (! empty($segmentDimensions)) {
                $dynamicResult = $calculator->calculateDynamic($usageType, $calculationPeriod, $segmentDimensions);
                foreach ($dynamicResult['segment_results'] as $segmentData) {
                    $this->writeLgdErSegment($writer, $runLog, $usageType, $calculationPeriod, $segmentData['segment']);
                }
                $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);

                return;
            }

            $windowYears = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::LgdExpectedRecoveries,
                'lgd_er_rolling_window_years',
                officeCode: $officeCode,
                usageType: $usageType->value,
                akadCode: $akadCode,
                default: 5,
            );

            $details = $calculator->calculateWithDetails($usageType, $calculationPeriod, $officeCode, $akadCode);
            $dataStart = PeriodHelper::shiftBack($calculationPeriod, $windowYears * 12);

            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_LGD_RATE, $usageType);
            $notes = sprintf(
                "Dasar data LGD Expected Recoveries [%s]:\n"
                    ."- Sumber: financing_account_periods (writeoff_status NOT NULL, writeoff_date terisi)\n"
                    ."- Filter: writeoff_date dlm window %d thn ke belakang dari periode; akad eligible: %s; akad 03 hanya jika maturity<=writeoff_date\n"
                    ."- Window: %d thn; periode writeoff: [%s, %s]\n"
                    ."- Fallback all-account: %s\n"
                    .'- total_writeoff=%.2f; total_recovery=%.2f',
                $usageType->label(),
                $windowYears,
                AkadEligibilityService::formatCodes($akadCodes),
                $windowYears,
                $dataStart,
                $calculationPeriod,
                $details['is_all_account'] ? 'YA (segmen kosong)' : 'TIDAK',
                $details['total_writeoff'],
                $details['total_recovery'],
            );

            $writer->writeLgdErResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                dataStart: $dataStart,
                dataEnd: $calculationPeriod,
                windowYears: $windowYears,
                lgdRate: $details['lgd_rate'],
                expectedRecoveryRate: $details['expected_recovery_rate'],
                totalWriteoff: $details['total_writeoff'],
                totalRecovery: $details['total_recovery'],
                isAllAccount: $details['is_all_account'],
                notes: $notes,
                officeCode: $officeCode,
                akadCode: $akadCode,
            );

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    private function writeLgdErSegment(
        SnapshotWriter $writer,
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        array $segment,
    ): void {
        $officeCode = $segment['office_code'] ?? null;
        $akadCode = $segment['akad_code'] ?? null;

        $windowYears = (int) CalculationDataRange::resolveValue(
            CalculationMethodKey::LgdExpectedRecoveries,
            'lgd_er_rolling_window_years',
            officeCode: $officeCode,
            usageType: $usageType->value,
            akadCode: $akadCode,
            default: 5,
        );

        $useAllAccount = (bool) CalculationDataRange::resolveValue(
            CalculationMethodKey::LgdExpectedRecoveries,
            'lgd_er_use_all_account',
            officeCode: $officeCode,
            usageType: $usageType->value,
            akadCode: $akadCode,
            default: false,
        );

        $calculator = app(LgdExpectedRecoveriesCalculator::class);
        $calculator->setWindowYears($windowYears);
        $calculator->setUseAllAccount($useAllAccount);
        $details = $calculator->calculateWithDetails($usageType, $calculationPeriod, $officeCode, $akadCode);
        $dataStart = PeriodHelper::shiftBack($calculationPeriod, $windowYears * 12);

        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_LGD_RATE, $usageType);
        $notes = sprintf(
            "Dasar data LGD Expected Recoveries [%s]:\n"
                ."- Sumber: financing_account_periods (writeoff_status NOT NULL, writeoff_date terisi)\n"
                ."- Filter: writeoff_date dlm window %d thn ke belakang dari periode; akad eligible: %s; akad 03 hanya jika maturity<=writeoff_date\n"
                ."- Window: %d thn; periode writeoff: [%s, %s]\n"
                ."- Fallback all-account: %s\n"
                .'- total_writeoff=%.2f; total_recovery=%.2f',
            $usageType->label(),
            $windowYears,
            AkadEligibilityService::formatCodes($akadCodes),
            $windowYears,
            $dataStart,
            $calculationPeriod,
            $details['is_all_account'] ? 'YA (segmen kosong)' : 'TIDAK',
            $details['total_writeoff'],
            $details['total_recovery'],
        );

        $writer->writeLgdErResult(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $calculationPeriod,
            dataStart: $dataStart,
            dataEnd: $calculationPeriod,
            windowYears: $windowYears,
            lgdRate: $details['lgd_rate'],
            expectedRecoveryRate: $details['expected_recovery_rate'],
            totalWriteoff: $details['total_writeoff'],
            totalRecovery: $details['total_recovery'],
            isAllAccount: $details['is_all_account'],
            notes: $notes,
            officeCode: $officeCode,
            akadCode: $akadCode,
        );
    }

    // -------------------------------------------------------------------------
    // LGD Collateral Shortfall — Ref: PRD Bab 10
    // -------------------------------------------------------------------------

    /**
     * @param  array<string>  $segmentDimensions
     */
    public function runLgdCs(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        ?string $officeCode = null,
        ?string $akadCode = null,
        array $segmentDimensions = [],
    ): void {
        ini_set('memory_limit', '512M');

        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $calculator = app(LgdCollateralShortfallCalculator::class);
            $writer = new SnapshotWriter;

            if (! empty($segmentDimensions)) {
                $dynamicResult = $calculator->calculateDynamic($usageType, $calculationPeriod, $segmentDimensions);
                foreach ($dynamicResult['segment_results'] as $segmentData) {
                    $this->writeLgdCsSegment($writer, $runLog, $usageType, $calculationPeriod, $segmentData['segment']);
                }
                $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);

                return;
            }

            $accountResults = $calculator->calculatePerAccount($usageType, $calculationPeriod, $officeCode, $akadCode);
            $aggregate = $calculator->aggregate($accountResults);

            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_LGD_RATE, $usageType);
            $notes = sprintf(
                "Dasar data LGD Collateral Shortfall [%s]:\n"
                    ."- Sumber: financing_account_periods (collectibility=5 ATAU writeoff_status='W') + collaterals (estimated_sale_value / appraisal_value*(1-discount))\n"
                    ."- Filter: akad 03 hanya jika JTP; HARUS punya agunan aktif ber-nilai; outstanding>0\n"
                    ."- Nilai jual bersih = estimated_sale_value (jika ada) ATAU appraisal_value*(1-discount)\n"
                    .'- account_count=%d; total_outstanding=%.2f; total_shortfall=%.2f',
                $usageType->label(),
                $aggregate['account_count'],
                $aggregate['total_outstanding'],
                $aggregate['total_shortfall'],
            );

            $writer->writeLgdCsResults(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                accountResults: $accountResults,
                notes: $notes,
                officeCode: $officeCode,
                akadCode: $akadCode,
            );

            $writer->writeLgdCsBySegmentResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                aggregate: $aggregate,
                notes: $notes,
                officeCode: $officeCode,
                akadCode: $akadCode,
            );

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    private function writeLgdCsSegment(
        SnapshotWriter $writer,
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        array $segment,
    ): void {
        $officeCode = $segment['office_code'] ?? null;
        $akadCode = $segment['akad_code'] ?? null;

        $calculator = app(LgdCollateralShortfallCalculator::class);
        $accountResults = $calculator->calculatePerAccount($usageType, $calculationPeriod, $officeCode, $akadCode);
        $aggregate = $calculator->aggregate($accountResults);

        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_LGD_RATE, $usageType);
        $notes = sprintf(
            "Dasar data LGD Collateral Shortfall [%s]:\n"
                ."- Sumber: financing_account_periods (collectibility=5 ATAU writeoff_status='W') + collaterals (estimated_sale_value / appraisal_value*(1-discount))\n"
                ."- Filter: akad 03 hanya jika JTP; HARUS punya agunan aktif ber-nilai; outstanding>0\n"
                ."- Nilai jual bersih = estimated_sale_value (jika ada) ATAU appraisal_value*(1-discount)\n"
                .'- account_count=%d; total_outstanding=%.2f; total_shortfall=%.2f',
            $usageType->label(),
            $aggregate['account_count'],
            $aggregate['total_outstanding'],
            $aggregate['total_shortfall'],
        );

        $writer->writeLgdCsResults(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $calculationPeriod,
            accountResults: $accountResults,
            notes: $notes,
            officeCode: $officeCode,
            akadCode: $akadCode,
        );

        $writer->writeLgdCsBySegmentResult(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $calculationPeriod,
            aggregate: $aggregate,
            notes: $notes,
            officeCode: $officeCode,
            akadCode: $akadCode,
        );
    }

    // -------------------------------------------------------------------------
    // LGD Final — Ref: PRD Bab 11
    // -------------------------------------------------------------------------

    public function runLgdFinal(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        ?string $officeCode = null,
    ): void {
        ini_set('memory_limit', '256M');

        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $calculator = new LgdFinalCalculator;
            $writer = new SnapshotWriter;

            $result = $calculator->calculateForSegment($usageType, $calculationPeriod, $officeCode);

            $writer->writeLgdFinalResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $calculationPeriod,
                result: $result,
                officeCode: $officeCode,
            );

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // CKPN Collective — Ref: PRD Bab 11
    // Batch insert 500 rows per chunk (memory-safe)
    // -------------------------------------------------------------------------

    /**
     * @param  array<string>  $segmentDimensions
     */
    public function runCkpnCollective(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        array $segmentDimensions = [],
    ): void {
        ini_set('memory_limit', '512M');

        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing]);

        try {
            $calculator = app(CkpnCollectiveCalculator::class);

            if (! empty($segmentDimensions)) {
                $result = $calculator->calculateDynamic($usageType, $calculationPeriod, $segmentDimensions);
                foreach ($result['segment_results'] as $segmentResult) {
                    $this->batchInsertCkpnCollective($runLog, $calculationPeriod, $segmentResult);
                }
            } else {
                $result = $calculator->calculate($usageType, $calculationPeriod);
                $this->batchInsertCkpnCollective($runLog, $calculationPeriod, [
                    'segment' => [
                        'usage_type' => $usageType->value,
                        'calculation_period' => $calculationPeriod,
                    ],
                    'results' => $result['results'],
                    'summary' => $result['summary'],
                ]);
            }

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    /** Batch insert 500 rows — ganti N+1 Eloquent::create() dari job lama */
    private function batchInsertCkpnCollective(
        CalculationRunLog $runLog,
        string $calculationPeriod,
        array $segmentResult,
    ): void {
        $segment = $segmentResult['segment'];
        $officeCode = $segment['office_code'] ?? null;
        $akadCode = $segment['akad_code'] ?? null;

        $rows = array_map(
            fn (array $r): array => [
                'calculation_run_log_id' => $runLog->id,
                'financing_account_id' => $r['financing_account_id'],
                'usage_type' => $r['usage_type'],
                'office_code' => $officeCode,
                'calculation_period' => $calculationPeriod,
                'pd_method_used' => $r['pd_method_used'],
                'pd_rate' => $r['pd_rate'],
                'lgd_method_used' => $r['lgd_method_used'],
                'lgd_rate' => $r['lgd_rate'],
                'ead' => $r['ead'],
                'pd_bucket_id' => $r['pd_bucket_id'],
                'pd_quality_grade_id' => $r['pd_quality_grade_id'],
                'ckpn_amount' => $r['ckpn_amount'],
            ],
            $segmentResult['results'],
        );

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ckpn_collective_results')->insert($chunk);
            unset($chunk);
        }
        unset($rows);
    }

    // -------------------------------------------------------------------------
    // CKPN Individual — Ref: PRD Bab 6
    // Batch insert 500 rows per chunk (memory-safe)
    // -------------------------------------------------------------------------

    public function runCkpnIndividual(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
    ): void {
        ini_set('memory_limit', '512M');

        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $calculator = app(CkpnIndividualCalculator::class);
            $result = $calculator->calculate($usageType, $calculationPeriod);

            $rows = array_map(
                fn (array $a): array => [
                    'calculation_run_log_id' => $runLog->id,
                    'account_number' => $a['account_number'],
                    'usage_type' => $usageType->value,
                    'office_code' => $a['office_code'],
                    'akad_code' => $a['akad_code'],
                    'calculation_period' => $calculationPeriod,
                    'bucket' => $a['bucket'],
                    'days_past_due' => $a['days_past_due'],
                    'pd_rate' => $a['pd_rate'],
                    'lgd_rate' => $a['lgd_rate'],
                    'ckpn_rate' => $a['ckpn_rate'],
                    'outstanding' => $a['outstanding'],
                    'ckpn_amount' => $a['ckpn_amount'],
                ],
                $result['accounts'],
            );

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('ckpn_individual_results')->insert($chunk);
                unset($chunk);
            }
            unset($rows);

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Populate Period Debtors — Ref: PRD Bab 6.1
    // -------------------------------------------------------------------------

    public function runPopulatePeriodDebtors(CkpnPeriod $ckpnPeriod, int $triggeredByUserId): void
    {
        ini_set('memory_limit', '512M');

        $period = $ckpnPeriod->period;

        $runLog = CalculationRunLog::create([
            'period' => $period,
            'run_type' => RunType::Classification,
            'usage_type' => null,
            'status' => RunStatus::Processing,
            'triggered_by_user_id' => $triggeredByUserId,
            'started_at' => now(),
        ]);

        try {
            $accounts = FinancingAccountPeriod::where('financing_account_periods.period', $period)
                ->where('financing_account_periods.financing_status', 'A')
                ->where(fn ($q) => $q->whereNull('financing_account_periods.writeoff_status')
                    ->orWhere('financing_account_periods.writeoff_status', '!=', 'W'))
                ->join('financing_accounts', 'financing_accounts.id', '=', 'financing_account_periods.financing_account_id')
                ->where(function ($q): void {
                    $q->whereNull('financing_accounts.product_code')
                        ->orWhere('financing_accounts.product_code', '!=', '72');
                })
                ->where(function ($query) use ($period): void {
                    $assessmentDate = CarbonImmutable::createFromFormat('Ym', $period)->endOfMonth()->toDateString();
                    $query->where('financing_accounts.akad_code', '!=', '03')
                        ->orWhere(function ($maturityQuery) use ($assessmentDate): void {
                            $maturityQuery->where('financing_accounts.akad_code', '03')
                                ->whereNotNull('financing_account_periods.maturity_date')
                                ->whereDate('financing_account_periods.maturity_date', '<=', $assessmentDate);
                        });
                })
                ->select([
                    'financing_account_periods.financing_account_id',
                    'financing_account_periods.outstanding_balance',
                    'financing_account_periods.collectibility',
                    'financing_account_periods.financing_status',
                    'financing_account_periods.writeoff_status',
                    'financing_accounts.usage_type',
                    'financing_accounts.office_code',
                    'financing_accounts.akad_code',
                ])
                ->get();

            // Idempotency: hapus data lama sebelum insert ulang
            CkpnPeriodClassification::where('period', $period)->delete();

            $rows = $accounts->map(fn (FinancingAccountPeriod $account): array => [
                'period' => $period,
                'financing_account_id' => $account->financing_account_id,
                'classification' => null,
                'is_classified' => false,
                'outstanding_balance' => $account->outstanding_balance,
                'collectibility' => $account->collectibility,
                'financing_status' => $account->financing_status?->value,
                'writeoff_status' => $account->writeoff_status?->value,
                'usage_type' => $account->usage_type,
                'office_code' => $account->office_code,
                'akad_code' => $account->akad_code,
                'classification_reason' => null,
                'ckpn_period_id' => $ckpnPeriod->id,
            ])->values()->toArray();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('ckpn_period_classifications')->insert($chunk);
            }
            unset($rows);

            $runLog->update([
                'status' => RunStatus::Completed,
                'completed_at' => now(),
                'notes' => sprintf('Populate selesai: %d debitur dimuat dari historis periode %s', $accounts->count(), $period),
            ]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Classify Period Data — Ref: PRD Bab 6.1
    // -------------------------------------------------------------------------

    public function runClassifyPeriodData(CkpnPeriod $ckpnPeriod, int $triggeredByUserId): void
    {
        ini_set('memory_limit', '512M');

        $period = $ckpnPeriod->period;

        $runLog = CalculationRunLog::create([
            'period' => $period,
            'run_type' => RunType::Classification,
            'usage_type' => null,
            'status' => RunStatus::Processing,
            'triggered_by_user_id' => $triggeredByUserId,
            'started_at' => now(),
        ]);

        try {
            $nplMinCollectibility = CalculationGeneralSetting::intValue('npl_min_collectibility', 3);
            $topN = CalculationGeneralSetting::intValue('ckpn_individual_top_n_outstanding', 10);

            $stagingData = CkpnPeriodClassification::where('period', $period)
                ->with('financingAccount')
                ->get();

            $topNIds = array_flip(
                array_unique(
                    $stagingData
                        ->filter(fn ($a) => $a->financing_status?->value === 'A'
                            && ($a->writeoff_status === null || $a->writeoff_status?->value !== 'W')
                            && $a->collectibility >= $nplMinCollectibility)
                        ->sortByDesc('outstanding_balance')
                        ->take($topN)
                        ->pluck('financing_account_id')
                        ->toArray()
                )
            );

            $individualCount = 0;
            $collectiveCount = 0;

            foreach ($stagingData as $row) {
                $isAktif = $row->financing_status?->value === 'A';
                $isWriteoff = $row->writeoff_status?->value === 'W';
                $isTopN = isset($topNIds[$row->financing_account_id]);

                if ($isAktif && ! $isWriteoff && $isTopN) {
                    $classification = ClassificationType::Individual;
                    $reason = 'top_n_npl_outstanding';
                    $individualCount++;
                } else {
                    $classification = ClassificationType::Collective;
                    $reason = $isWriteoff ? 'writeoff' : 'aktif_lancar_atau_lainnya';
                    $collectiveCount++;
                }

                $officeCode = $row->financingAccount?->office_code;
                $akadCode = $row->financingAccount?->akad_code;

                DB::table('ckpn_period_classifications')
                    ->where('id', $row->id)
                    ->update([
                        'classification' => $classification->value,
                        'is_classified' => true,
                        'classification_reason' => $reason,
                        'office_code' => $officeCode,
                        'akad_code' => $akadCode,
                    ]);
            }

            $ckpnPeriod->update([
                'is_classified' => true,
                'status' => 'in_progress',
                'notes' => sprintf(
                    "Dasar data Klasifikasi [periode %s]:\n"
                        ."- Sumber staging: ckpn_period_classifications (diisi PopulatePeriodDebtorsJob)\n"
                        ."- Filter staging: financing_status='A'; akad 03 hanya jika jatuh tempo; writeoff_status!='W'\n"
                        ."- Parameter: npl_min_collectibility=%d, top_n=%d\n"
                        ."- Individual = aktif & bukan WO & NPL & masuk top-N outstanding\n"
                        ."- Kolektif = sisa (termasuk lancar & write-off)\n"
                        .'- Hasil: %d individual, %d kolektif',
                    $period,
                    $nplMinCollectibility,
                    $topN,
                    $individualCount,
                    $collectiveCount,
                ),
            ]);

            $runLog->update([
                'status' => RunStatus::Completed,
                'completed_at' => now(),
                'notes' => sprintf(
                    'Klasifikasi selesai: %d akun (%d individual, %d kolektif)',
                    $stagingData->count(),
                    $individualCount,
                    $collectiveCount,
                ),
            ]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }
}
