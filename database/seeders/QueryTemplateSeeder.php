<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\QueryTemplate;
use Illuminate\Database\Seeder;

/**
 * Seed query SQL Server default untuk setiap tipe upload.
 * Query ini digunakan sebagai referensi untuk menarik data dari SQL Server ke MySQL.
 */
class QueryTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ─── Master Pembiayaan ────────────────────────────────────────────
            [
                'name' => 'SELECT Master Pembiayaan (Aktif)',
                'upload_type' => 'master',
                'source_database' => 'sqlserver',
                'description' => 'Ambil seluruh data master rekening pembiayaan yang masih aktif dari core banking SQL Server.',
                'target_table' => 'financing_accounts',
                'sql_query' => <<<'SQL'
SELECT
    fa.ACCOUNT_NO        AS account_no,
    fa.CUSTOMER_NO       AS customer_no,
    fa.CUSTOMER_NAME     AS customer_name,
    fa.PRODUCT_CODE      AS product_code,
    fa.PRODUCT_NAME      AS product_name,
    fa.BRANCH_CODE       AS branch_code,
    fa.AKAD_TYPE         AS akad_type,
    fa.OPEN_DATE         AS open_date,
    fa.MATURITY_DATE     AS maturity_date,
    fa.PLAFOND           AS plafond,
    fa.OUTSTANDING       AS outstanding,
    fa.CURRENCY          AS currency,
    fa.SEGMENT_CODE      AS segment_code,
    fa.STATUS            AS account_status,
    fa.CREATED_AT        AS created_at
FROM dbo.FINANCING_ACCOUNT fa
WHERE fa.STATUS IN ('A', 'active')
  AND fa.BRANCH_CODE = :branch_code   -- ganti 'all' untuk semua cabang
ORDER BY fa.ACCOUNT_NO
SQL,
                'parameters' => ['branch_code' => 'all'],
                'is_active' => true,
            ],

            [
                'name' => 'SELECT Master Pembiayaan + Segmentasi',
                'upload_type' => 'master',
                'source_database' => 'sqlserver',
                'description' => 'Ambil data master pembiayaan beserta mapping segmen risiko (mikro/kecil/konsumer/KPR).',
                'target_table' => 'financing_accounts',
                'sql_query' => <<<'SQL'
SELECT
    fa.ACCOUNT_NO        AS account_no,
    fa.CUSTOMER_NO       AS customer_no,
    fa.CUSTOMER_NAME     AS customer_name,
    fa.PRODUCT_CODE      AS product_code,
    fa.BRANCH_CODE       AS branch_code,
    fa.PLAFOND           AS plafond,
    fa.OUTSTANDING       AS outstanding,
    fa.AKAD_TYPE         AS akad_type,
    fa.OPEN_DATE         AS open_date,
    fa.MATURITY_DATE     AS maturity_date,
    rs.SEGMENT_CODE      AS segment_code,
    rs.SEGMENT_NAME      AS segment_name,
    fa.STATUS            AS account_status
FROM dbo.FINANCING_ACCOUNT fa
LEFT JOIN dbo.RISK_SEGMENT_MAPPING rs
    ON rs.PRODUCT_CODE = fa.PRODUCT_CODE
WHERE fa.STATUS IN ('A', 'active')
  AND fa.PERIOD_CODE = :period         -- format: YYYYMM
ORDER BY fa.ACCOUNT_NO
SQL,
                'parameters' => ['period' => '202506'],
                'is_active' => true,
            ],

            // ─── Historis Pembiayaan ──────────────────────────────────────────
            [
                'name' => 'SELECT Historis Pembiayaan Per Periode',
                'upload_type' => 'period',
                'source_database' => 'sqlserver',
                'description' => 'Ambil data kolektibilitas, tunggakan, dan saldo per rekening untuk satu periode bulan tertentu.',
                'target_table' => 'financing_account_periods',
                'sql_query' => <<<'SQL'
SELECT
    fp.ACCOUNT_NO           AS account_no,
    fp.PERIOD_CODE          AS period,
    fp.KOLEKTIBILITAS       AS collectibility,
    fp.OUTSTANDING          AS outstanding,
    fp.TUNGGAKAN_POKOK      AS overdue_principal,
    fp.TUNGGAKAN_MARGIN     AS overdue_margin,
    fp.TOTAL_TUNGGAKAN      AS total_overdue,
    fp.HARI_TUNGGAKAN       AS overdue_days,
    fp.FLAG_RESTRUCTURE     AS is_restructured,
    fp.FLAG_WRITEOFF        AS is_written_off,
    fp.TGL_MACET            AS default_date,
    fp.EAD                  AS ead,
    fp.BRANCH_CODE          AS branch_code
FROM dbo.FINANCING_PERIOD fp
WHERE fp.PERIOD_CODE = :period          -- format: YYYYMM
  AND fp.BRANCH_CODE = :branch_code     -- ganti 'all' untuk semua cabang
ORDER BY fp.ACCOUNT_NO
SQL,
                'parameters' => ['period' => '202506', 'branch_code' => 'all'],
                'is_active' => true,
            ],

            [
                'name' => 'SELECT Historis Pembiayaan Rolling 24 Bulan',
                'upload_type' => 'period',
                'source_database' => 'sqlserver',
                'description' => 'Ambil data historis 24 bulan terakhir untuk kebutuhan perhitungan PD Netflow dan PD Migration.',
                'target_table' => 'financing_account_periods',
                'sql_query' => <<<'SQL'
SELECT
    fp.ACCOUNT_NO           AS account_no,
    fp.PERIOD_CODE          AS period,
    fp.KOLEKTIBILITAS       AS collectibility,
    fp.OUTSTANDING          AS outstanding,
    fp.TOTAL_TUNGGAKAN      AS total_overdue,
    fp.HARI_TUNGGAKAN       AS overdue_days,
    fp.FLAG_RESTRUCTURE     AS is_restructured,
    fp.FLAG_WRITEOFF        AS is_written_off,
    fp.EAD                  AS ead
FROM dbo.FINANCING_PERIOD fp
WHERE fp.PERIOD_CODE BETWEEN :period_from AND :period_to  -- format: YYYYMM
ORDER BY fp.ACCOUNT_NO, fp.PERIOD_CODE
SQL,
                'parameters' => ['period_from' => '202306', 'period_to' => '202506'],
                'is_active' => true,
            ],

            // ─── Data Jaminan ─────────────────────────────────────────────────
            [
                'name' => 'SELECT Data Jaminan (Agunan)',
                'upload_type' => 'collateral',
                'source_database' => 'sqlserver',
                'description' => 'Ambil data agunan/jaminan per rekening pembiayaan dari sistem appraisal.',
                'target_table' => 'collaterals',
                'sql_query' => <<<'SQL'
SELECT
    c.COLLATERAL_ID         AS collateral_id,
    c.ACCOUNT_NO            AS account_no,
    c.COLLATERAL_TYPE_CODE  AS collateral_type_code,
    c.COLLATERAL_DESC       AS description,
    c.OWNER_NAME            AS owner_name,
    c.APPRAISED_VALUE       AS appraised_value,
    c.MARKET_VALUE          AS market_value,
    c.LIQUIDATION_VALUE     AS liquidation_value,
    c.APPRAISAL_DATE        AS appraisal_date,
    c.LOCATION              AS location,
    c.STATUS                AS collateral_status,
    c.CERTIFICATE_NO        AS certificate_no
FROM dbo.COLLATERAL c
INNER JOIN dbo.FINANCING_ACCOUNT fa
    ON fa.ACCOUNT_NO = c.ACCOUNT_NO
WHERE fa.STATUS IN ('A', 'active')
  AND c.STATUS = 'A'
ORDER BY c.ACCOUNT_NO, c.COLLATERAL_ID
SQL,
                'parameters' => [],
                'is_active' => true,
            ],

            [
                'name' => 'SELECT Data Penjualan Agunan (Write-off)',
                'upload_type' => 'collateral',
                'source_database' => 'sqlserver',
                'description' => 'Ambil data penjualan agunan untuk rekening write-off, digunakan dalam perhitungan LGD Expected Recoveries.',
                'target_table' => 'collateral_sales_data',
                'sql_query' => <<<'SQL'
SELECT
    cs.ACCOUNT_NO           AS account_no,
    cs.COLLATERAL_ID        AS collateral_id,
    cs.SALE_DATE            AS sale_date,
    cs.SALE_PRICE           AS sale_price,
    cs.BOOK_VALUE_AT_SALE   AS book_value_at_sale,
    cs.RECOVERY_AMOUNT      AS recovery_amount,
    cs.SALE_COST            AS sale_cost,
    cs.PERIOD_CODE          AS period
FROM dbo.COLLATERAL_SALE cs
WHERE cs.PERIOD_CODE BETWEEN :period_from AND :period_to
ORDER BY cs.ACCOUNT_NO, cs.SALE_DATE
SQL,
                'parameters' => ['period_from' => '202306', 'period_to' => '202506'],
                'is_active' => true,
            ],

            // ─── Master Kantor ────────────────────────────────────────────────
            [
                'name' => 'SELECT Master Kantor/Cabang',
                'upload_type' => 'office',
                'source_database' => 'sqlserver',
                'description' => 'Ambil data master kantor cabang beserta hierarki region dan wilayah.',
                'target_table' => 'financing_offices',
                'sql_query' => <<<'SQL'
SELECT
    o.BRANCH_CODE           AS branch_code,
    o.BRANCH_NAME           AS branch_name,
    o.BRANCH_TYPE           AS branch_type,
    o.REGION_CODE           AS region_code,
    o.REGION_NAME           AS region_name,
    o.AREA_CODE             AS area_code,
    o.AREA_NAME             AS area_name,
    o.ADDRESS               AS address,
    o.CITY                  AS city,
    o.PROVINCE              AS province,
    o.IS_ACTIVE             AS is_active
FROM dbo.BRANCH_MASTER o
WHERE o.IS_ACTIVE = 1
ORDER BY o.REGION_CODE, o.BRANCH_CODE
SQL,
                'parameters' => [],
                'is_active' => true,
            ],

            // ─── Master Jenis Jaminan ─────────────────────────────────────────
            [
                'name' => 'SELECT Master Jenis Jaminan',
                'upload_type' => 'collateral_type',
                'source_database' => 'sqlserver',
                'description' => 'Ambil data master tipe/jenis jaminan beserta nilai haircut untuk perhitungan LGD Collateral Shortfall.',
                'target_table' => 'collateral_types',
                'sql_query' => <<<'SQL'
SELECT
    ct.TYPE_CODE            AS type_code,
    ct.TYPE_NAME            AS type_name,
    ct.CATEGORY             AS category,
    ct.HAIRCUT_RATE         AS haircut_rate,
    ct.IS_LIQUID            AS is_liquid,
    ct.DESCRIPTION          AS description,
    ct.IS_ACTIVE            AS is_active
FROM dbo.COLLATERAL_TYPE ct
WHERE ct.IS_ACTIVE = 1
ORDER BY ct.CATEGORY, ct.TYPE_CODE
SQL,
                'parameters' => [],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            QueryTemplate::updateOrCreate(
                ['name' => $template['name'], 'upload_type' => $template['upload_type']],
                $template
            );
        }

        $this->command->info('Query templates seeded: '.count($templates).' records.');
    }
}
