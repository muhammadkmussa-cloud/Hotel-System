<?php

declare(strict_types=1);

// Copied into an isolated prefixed MySQL fixture by ReportLedgerTest.
require __DIR__.'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$prefix = getenv('REPORT_TEST_PREFIX');
if (getenv('APP_ENV') !== 'testing' || ! is_string($prefix) || ! preg_match('/\Arl_[a-f0-9]{24}_\z/', $prefix)) {
    throw new RuntimeException('Invalid isolated report fixture.');
}
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$app['config']->set('database.connections.mysql.prefix', $prefix);
$action = $argv[1] ?? '';

if ($action === 'migrate') {
    $status = $kernel->call('app:migrate', ['--no-interaction' => true]);
    echo json_encode(['status' => $status], JSON_THROW_ON_ERROR);
    exit;
}
if ($action === 'drop') {
    $connection = DB::connection('mysql');
    $quotedPrefix = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix);
    $tables = $connection->select('SHOW TABLES LIKE ?', [$quotedPrefix.'%']);
    $connection->statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $row) {
        $table = (string) array_values((array) $row)[0];
        if (! str_starts_with($table, $prefix)) throw new RuntimeException('Unsafe fixture table.');
        $connection->statement('DROP TABLE `'.str_replace('`', '``', $table).'`');
    }
    $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    echo json_encode(['dropped' => count($tables)], JSON_THROW_ON_ERROR);
    exit;
}
if ($action !== 'exercise') throw new RuntimeException('Unknown action.');

$date = '2026-10-07';
$now = '2026-10-06 10:00:00';
$ids = [
    'hotel' => '01990000-0000-7000-8000-000000000001',
    'actor' => '01990000-0000-7000-8000-000000000002',
    'submission' => '01990000-0000-7000-8000-000000000003',
    'checkout' => '01990000-0000-7000-8000-000000000004',
    'payment' => '01990000-0000-7000-8000-000000000005',
    'custodian' => '01990000-0000-7000-8000-000000000006',
    'drawer' => '01990000-0000-7000-8000-000000000007',
    'drawerPayment' => '01990000-0000-7000-8000-000000000008',
    'custodyPayment' => '01990000-0000-7000-8000-000000000009',
];
$chargeIds = [
    'sale' => '01990000-0000-7000-8000-000000000011',
    'cancelled' => '01990000-0000-7000-8000-000000000012',
    'neverPosted' => '01990000-0000-7000-8000-000000000013',
    'proposed' => '01990000-0000-7000-8000-000000000014',
    'postedCancellation' => '01990000-0000-7000-8000-000000000015',
];
$itemIds = [
    'sale' => '01990000-0000-7000-8000-000000000021',
    'cancelled' => '01990000-0000-7000-8000-000000000022',
    'neverPosted' => '01990000-0000-7000-8000-000000000023',
    'proposed' => '01990000-0000-7000-8000-000000000024',
    'postedCancellation' => '01990000-0000-7000-8000-000000000025',
];

DB::statement('SET FOREIGN_KEY_CHECKS=0');
try {
    DB::table('hotel_settings')->insert(['id' => $ids['hotel'], 'name' => 'Report fixture', 'timezone' => 'Africa/Nairobi', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('staff_users')->insert([
        ['id' => $ids['actor'], 'email' => 'owner-report@example.test', 'name' => 'Fixture owner', 'password_hash' => 'not-used', 'created_at' => $now, 'updated_at' => $now],
        ['id' => $ids['custodian'], 'email' => 'waiter-report@example.test', 'name' => 'Fixture custodian', 'password_hash' => 'not-used', 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::table('staff_role_grants')->insert(['id' => '01990000-0000-7000-8000-000000000010', 'staff_user_id' => $ids['actor'],
        'role_id' => DB::table('roles')->where('key', 'owner')->value('id'), 'granted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('order_submissions')->insert([
        'id' => $ids['submission'], 'channel' => 'table', 'reference' => 'ORD-REPORT', 'state' => 'released',
        'idempotency_hash' => str_repeat('a', 64), 'quote_digest' => str_repeat('b', 64), 'total_minor' => 190000,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    foreach ($itemIds as $name => $itemId) {
        DB::table('order_items')->insert([
            'id' => $itemId, 'submission_id' => $ids['submission'], 'meal_id' => '01990000-0000-7000-8000-000000000099',
            'meal_version' => 1, 'meal_name' => ucfirst($name), 'unit_price_minor' => 10000, 'quantity' => 1,
            'line_total_minor' => 10000, 'removed' => '[]', 'extras' => '[]', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $charges = [
        ['name' => 'sale', 'gross' => 10000, 'state' => 'posted'],
        // Simulate a row written by the old cancellation behavior. Migration 000029 must repair it.
        ['name' => 'cancelled', 'gross' => 20000, 'state' => 'voided'],
        ['name' => 'neverPosted', 'gross' => 70000, 'state' => 'voided'],
        ['name' => 'proposed', 'gross' => 90000, 'state' => 'proposed'],
        ['name' => 'postedCancellation', 'gross' => 5000, 'state' => 'posted'],
    ];
    foreach ($charges as $charge) {
        DB::table('charges')->insert([
            'id' => $chargeIds[$charge['name']], 'order_item_id' => $itemIds[$charge['name']], 'submission_id' => $ids['submission'],
            'gross_minor' => $charge['gross'], 'state' => $charge['state'], 'posted_at' => $charge['state'] === 'proposed' ? null : $now,
            'business_date' => $date, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    DB::table('adjustments')->insert([
        ['id' => '01990000-0000-7000-8000-000000000031', 'charge_id' => $chargeIds['sale'], 'allocation_id' => '01990000-0000-7000-8000-000000000041', 'kind' => 'discount', 'amount_minor' => 1000, 'reason' => 'Fixture discount', 'approved_by' => $ids['actor'], 'business_date' => $date, 'created_at' => $now],
        ['id' => '01990000-0000-7000-8000-000000000032', 'charge_id' => $chargeIds['cancelled'], 'kind' => 'cancellation', 'amount_minor' => 20000, 'reason' => 'Fixture cancellation', 'approved_by' => $ids['actor'], 'business_date' => $date, 'created_at' => $now],
    ]);
    DB::table('charge_allocations')->insert([
        ['id' => '01990000-0000-7000-8000-000000000041', 'charge_id' => $chargeIds['sale'], 'guest_id' => '01990000-0000-7000-8000-000000000051', 'amount_minor' => 4000, 'state' => 'open', 'reason' => 'shared', 'created_at' => $now, 'updated_at' => $now],
        ['id' => '01990000-0000-7000-8000-000000000042', 'charge_id' => $chargeIds['sale'], 'guest_id' => '01990000-0000-7000-8000-000000000052', 'amount_minor' => 6000, 'state' => 'open', 'reason' => 'shared', 'created_at' => $now, 'updated_at' => $now],
        ['id' => '01990000-0000-7000-8000-000000000043', 'charge_id' => $chargeIds['cancelled'], 'guest_id' => '01990000-0000-7000-8000-000000000051', 'amount_minor' => 20000, 'state' => 'voided', 'reason' => 'ordered', 'created_at' => $now, 'updated_at' => $now],
        ['id' => '01990000-0000-7000-8000-000000000044', 'charge_id' => $chargeIds['proposed'], 'guest_id' => '01990000-0000-7000-8000-000000000051', 'amount_minor' => 90000, 'state' => 'open', 'reason' => 'ordered', 'created_at' => $now, 'updated_at' => $now],
        ['id' => '01990000-0000-7000-8000-000000000045', 'charge_id' => $chargeIds['postedCancellation'], 'guest_id' => '01990000-0000-7000-8000-000000000051', 'amount_minor' => 5000, 'state' => 'open', 'reason' => 'ordered', 'created_at' => $now, 'updated_at' => $now],
    ]);
    DB::table('checkouts')->insert(['id' => $ids['checkout'], 'amount_minor' => 9000, 'paid_minor' => 9000, 'state' => 'paid', 'receipt_number' => 'R-REPORT', 'paid_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('payments')->insert(['id' => $ids['payment'], 'checkout_id' => $ids['checkout'], 'method' => 'cash', 'amount_minor' => 9000, 'reference' => 'PAY-REPORT', 'state' => 'applied', 'refunded_minor' => 500, 'business_date' => $date, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('refunds')->insert(['id' => '01990000-0000-7000-8000-000000000061', 'payment_id' => $ids['payment'], 'amount_minor' => 500, 'reason' => 'Fixture refund', 'state' => 'completed', 'cash_source_type' => 'unattributed', 'requested_by' => $ids['actor'], 'completed_by' => $ids['actor'], 'business_date' => $date, 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
} finally {
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

$repair = require __DIR__.'/database/migrations/2026_10_07_000029_repair_cancelled_charge_ledger_state.php';
$repair->up();
$reviews = $app->make(App\Domain\Ordering\ReviewService::class);
$reviews->cancelItem($itemIds['proposed'], 'Never released', false, $ids['actor']);
$reviews->cancelItem($itemIds['postedCancellation'], 'Posted cancellation', false, $ids['actor']);
$report = new App\Domain\Operations\ReportService;
$summary = $report->summary($date, $date);
$sales = $report->salesRows($date, $date);
$payments = $report->paymentRows($date, $date);
$bill = $app->make(App\Domain\Billing\BillService::class)->bill('guest', '01990000-0000-7000-8000-000000000051');

// Cash-source accounting is exercised after report snapshots so these extra
// fixture movements cannot change the known-day report assertions above.
DB::table('drawer_sessions')->insert(['id' => $ids['drawer'], 'opened_by' => $ids['actor'], 'opening_float_minor' => 10000,
    'state' => 'open', 'opened_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
DB::table('payments')->insert([
    ['id' => $ids['drawerPayment'], 'checkout_id' => $ids['checkout'], 'method' => 'cash', 'amount_minor' => 5000, 'reference' => 'DRAWER-CASH',
        'state' => 'applied', 'drawer_session_id' => $ids['drawer'], 'business_date' => $date, 'created_at' => $now, 'updated_at' => $now],
    ['id' => $ids['custodyPayment'], 'checkout_id' => $ids['checkout'], 'method' => 'cash', 'amount_minor' => 5000, 'reference' => 'CUSTODY-CASH',
        'state' => 'applied', 'custody_staff_user_id' => $ids['custodian'], 'business_date' => $date, 'created_at' => $now, 'updated_at' => $now],
]);
DB::table('refunds')->insert([
    ['id' => '01990000-0000-7000-8000-000000000062', 'payment_id' => $ids['payment'], 'amount_minor' => 1000, 'reason' => 'Drawer payout',
        'state' => 'approved', 'requested_by' => $ids['actor'], 'approved_by' => $ids['actor'], 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now],
    ['id' => '01990000-0000-7000-8000-000000000063', 'payment_id' => $ids['payment'], 'amount_minor' => 1000, 'reason' => 'Custody payout',
        'state' => 'completed', 'cash_source_type' => 'custodian', 'cash_custody_staff_user_id' => $ids['custodian'],
        'requested_by' => $ids['actor'], 'approved_by' => $ids['actor'], 'completed_by' => $ids['actor'], 'business_date' => $date,
        'approved_at' => $now, 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now],
    ['id' => '01990000-0000-7000-8000-000000000064', 'payment_id' => $ids['payment'], 'amount_minor' => 6000, 'reason' => 'Too much custody cash',
        'state' => 'approved', 'requested_by' => $ids['actor'], 'approved_by' => $ids['actor'], 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now],
]);
$refundService = $app->make(App\Domain\Billing\RefundService::class);
$refundService->complete('01990000-0000-7000-8000-000000000062', null, 'drawer:'.$ids['drawer'], $ids['actor']);
$insufficientBlocked = false;
try {
    $refundService->complete('01990000-0000-7000-8000-000000000064', null, 'custodian:'.$ids['custodian'], $ids['actor']);
} catch (App\Domain\DomainError $error) {
    $insufficientBlocked = $error->errorCode === 'INSUFFICIENT_SOURCE_CASH';
}
$cash = $app->make(App\Domain\Billing\CashService::class);
$custodyBefore = $cash->custody($ids['custodian']);
$handoverId = $cash->proposeHandover($ids['custodian'], 'Fixture handover');
$cash->decideHandover($handoverId, true, 4000, null, $ids['actor']);

$drawerRefund = DB::table('refunds')->where('id', '01990000-0000-7000-8000-000000000062')->first();
$custodyRefund = DB::table('refunds')->where('id', '01990000-0000-7000-8000-000000000063')->first();
echo json_encode([
    'states' => DB::table('charges')->orderBy('id')->pluck('state', 'id')->all(),
    'summary' => $summary,
    'sales' => $sales,
    'payments' => $payments,
    'bill' => $bill,
    'cash' => [
        'insufficientBlocked' => $insufficientBlocked,
        'drawerRefundSource' => [$drawerRefund->cash_source_type, $drawerRefund->cash_drawer_session_id],
        'custodyBefore' => $custodyBefore,
        'custodyAfter' => $cash->custody($ids['custodian']),
        'drawerAfter' => $cash->expected($ids['drawer']),
        'custodyRefundHandover' => $custodyRefund->cash_handover_id,
        'handoverId' => $handoverId,
        'unattributed' => DB::table('refunds')->where('cash_source_type', 'unattributed')->count(),
    ],
], JSON_THROW_ON_ERROR);
