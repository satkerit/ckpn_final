<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed roles + assign permission per role CKPN.
 * Ref: PRD FR-14
 *
 * super_admin  — akses penuh (semua permission)
 * risk_analyst — baca + upload data + jalankan kalkulasi, tidak bisa approve/kelola user/role
 * approver     — baca + approve CkpnPeriod, tidak bisa input data atau kelola user
 * viewer       — hanya ViewAny + View semua resource, tanpa aksi apapun
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** Resource yang bersifat read-only (hasil snapshot kalkulasi). */
    private const RESULT_RESOURCES = [
        'CkpnCollectiveResult',
        'CkpnIndividualResult',
        'PdNetflowResult',
        'PdMigrationResult',
        'LgdExpectedRecoveriesResult',
        'LgdCollateralShortfallResult',
        'CalculationRunLog',
    ];

    /** Resource master data yang bisa dikelola risk_analyst. */
    private const MASTER_RESOURCES = [
        'Bucket',
        'CollateralType',
        'FinancingOffice',
        'QualityGrade',
        'RiskSegment',
    ];

    /** Resource data input (upload / transaksi). */
    private const INPUT_RESOURCES = [
        'Collateral',
        'FinancingAccount',
        'FinancingAccountPeriod',
        'FinancingUploadBatch',
        'QueryTemplate',
    ];

    /** Resource CKPN period lifecycle. */
    private const PERIOD_RESOURCES = [
        'CkpnPeriod',
    ];

    /** Resource reporting (read-only + anomaly resolve action). */
    private const REPORTING_RESOURCES = [
        'DataQualityAnomaly',
        'LgdSummary',
        'CkpnSummary',
    ];

    /** Resource manajemen user & role (hanya super_admin). */
    private const ADMIN_RESOURCES = [
        'User',
        'Role',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Pastikan semua role ada
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin',  'guard_name' => 'web']);
        $riskAnalyst = Role::firstOrCreate(['name' => 'risk_analyst', 'guard_name' => 'web']);
        $approver = Role::firstOrCreate(['name' => 'approver',     'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer',       'guard_name' => 'web']);

        // ── super_admin: semua permission ────────────────────────────────────
        $allPermissions = Permission::all();
        $superAdmin->syncPermissions($allPermissions);

        // ── viewer: hanya ViewAny + View semua resource ───────────────────────
        $allResources = array_merge(
            self::RESULT_RESOURCES,
            self::MASTER_RESOURCES,
            self::INPUT_RESOURCES,
            self::PERIOD_RESOURCES,
            self::REPORTING_RESOURCES,
        );

        $viewerPerms = [];
        foreach ($allResources as $resource) {
            $viewerPerms[] = "ViewAny:{$resource}";
            $viewerPerms[] = "View:{$resource}";
        }
        $viewer->syncPermissions(
            Permission::whereIn('name', $viewerPerms)->get()
        );

        // ── risk_analyst: viewer + CRUD master + CRUD input + Create/Delete result + trigger kalkulasi ─
        $riskAnalystPerms = $viewerPerms; // inherit viewer

        $crudActions = ['Create', 'Update', 'Delete', 'DeleteAny', 'ViewAny', 'View'];
        foreach (array_merge(self::MASTER_RESOURCES, self::INPUT_RESOURCES) as $resource) {
            foreach ($crudActions as $action) {
                $riskAnalystPerms[] = "{$action}:{$resource}";
            }
            $riskAnalystPerms[] = "Replicate:{$resource}";
        }

        // Bisa lihat & buat CkpnPeriod (trigger kalkulasi), tapi tidak bisa approve
        $riskAnalystPerms[] = 'ViewAny:CkpnPeriod';
        $riskAnalystPerms[] = 'View:CkpnPeriod';
        $riskAnalystPerms[] = 'Create:CkpnPeriod';

        // Bisa lihat semua hasil + jalankan/hapus kalkulasi (create/delete snapshot)
        foreach (self::RESULT_RESOURCES as $resource) {
            $riskAnalystPerms[] = "ViewAny:{$resource}";
            $riskAnalystPerms[] = "View:{$resource}";
            $riskAnalystPerms[] = "Create:{$resource}";
            $riskAnalystPerms[] = "Delete:{$resource}";
            $riskAnalystPerms[] = "DeleteAny:{$resource}";
        }

        // Bisa lihat + resolve anomali data quality di reporting
        foreach (self::REPORTING_RESOURCES as $resource) {
            $riskAnalystPerms[] = "ViewAny:{$resource}";
            $riskAnalystPerms[] = "View:{$resource}";
        }
        $riskAnalystPerms[] = 'Update:DataQualityAnomaly'; // resolve/unresolve action

        $riskAnalyst->syncPermissions(
            Permission::whereIn('name', array_unique($riskAnalystPerms))->get()
        );

        // ── approver: viewer + update/approve CkpnPeriod + lihat semua ────────
        $approverPerms = $viewerPerms;

        // Bisa update CkpnPeriod (approve/reject)
        $approverPerms[] = 'ViewAny:CkpnPeriod';
        $approverPerms[] = 'View:CkpnPeriod';
        $approverPerms[] = 'Update:CkpnPeriod';

        // Bisa lihat reporting + resolve anomali
        foreach (self::REPORTING_RESOURCES as $resource) {
            $approverPerms[] = "ViewAny:{$resource}";
            $approverPerms[] = "View:{$resource}";
        }
        $approverPerms[] = 'Update:DataQualityAnomaly';

        $approver->syncPermissions(
            Permission::whereIn('name', array_unique($approverPerms))->get()
        );

        $this->command->info('Roles & permissions seeded successfully.');
        $this->command->table(
            ['Role', 'Total Permissions'],
            [
                ['super_admin',  $superAdmin->permissions()->count()],
                ['risk_analyst', $riskAnalyst->permissions()->count()],
                ['approver',     $approver->permissions()->count()],
                ['viewer',       $viewer->permissions()->count()],
            ]
        );
    }
}
