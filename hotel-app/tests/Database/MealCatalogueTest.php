<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * P10 — meals, recipes, prices and publication on real MySQL: draft isolation
 * from guest reads, digest-bound approval, immutable published versions, exact
 * minor units, version checks, and category-rename invalidation.
 *
 * Requires the explicit disposable-schema opt-in and private HOTEL_TEST_DB_*.
 */
final class MealCatalogueTest extends TestCase
{
    public function testMealPublicationInvariantsOnRealMysql(): void
    {
        self::assertSame('1', getenv('HOTEL_TEST_DB_ALLOW_SCHEMA'), 'Disposable-schema opt-in required.');
        $env = ['PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_'.$key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable DB settings required.');
            $env['DB_'.$key] = $value;
        }
        $env = array_merge(array_fill_keys(array_keys(getenv()), false), $env);
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/hotel-meals-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        try {
            foreach (['app', 'config', 'routes', 'database/migrations'] as $directory) {
                $files->copyDirectory($source.'/'.$directory, $fixture.'/'.$directory);
            }
            foreach (['bootstrap/cache', 'storage/logs'] as $directory) {
                $files->makeDirectory($fixture.'/'.$directory, 0700, true);
            }
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) {
                $files->copy($source.'/'.$file, $fixture.'/'.$file);
            }
            $files->copy($source.'/tests/Fixtures/meal-catalogue-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $run = static function (string $action, array $overrides = []) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($env, $overrides), timeout: 60);
                $process->run();
                self::assertSame('', $process->getErrorOutput(), 'Meal fixture stderr: '.$process->getErrorOutput());
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };

            $owned = true;
            self::assertSame(0, $run('migrate')['status']);

            $category = $run('category-save', ['NAME' => 'Mains', 'ORDER' => 1])['ok'];
            $meal = $run('meal-create', ['NAME' => 'Ugali', 'PRICE' => '250.00', 'CATEGORY_ID' => $category])['ok'];

            // P10.04 — exact minor units.
            $state = $run('meal-state', ['ID' => $meal]);
            self::assertSame(25000, $state['price_minor'], 'Prices are stored as exact minor units.');
            self::assertSame(1, $state['version']);
            self::assertNull($state['published_version']);

            // P10.02/P10.10 — draft data is hidden from guest reads.
            self::assertSame([], $run('menu')['meals'], 'An unpublished draft never appears on the guest menu.');

            // P10.05 — ingredients are required before kitchen review.
            self::assertArrayHasKey('ok', $run('meal-add-ingredient', ['ID' => $meal]));
            self::assertSame(2, $run('meal-state', ['ID' => $meal])['version']);

            // P10.07/P10.08 — publication requires the exact approved digest.
            self::assertSame('REVIEW_REQUIRED', $run('meal-publish', ['ID' => $meal])['error'], 'Unapproved meals cannot publish.');
            self::assertArrayHasKey('ok', $run('meal-approve', ['ID' => $meal]));
            self::assertSame(1, $run('meal-publish', ['ID' => $meal])['ok'], 'An approved meal publishes version 1.');
            self::assertSame(['Ugali'], $run('menu')['meals'], 'The published meal is on the guest menu.');

            // P11.06 — a fixed ingredient cannot be removed, even by a forged request.
            $rules = $run('meal-ingredients', ['ID' => $meal])['rules'];
            self::assertSame('fixed', $rules[0]['rule']);
            $fixed = $rules[0]['ingredient_id'];
            self::assertSame('VALIDATION_FAILED', $run('cart-add', ['OWNER_ID' => 'guest-a', 'MEAL_ID' => $meal, 'REMOVED' => $fixed])['error'], 'A fixed ingredient cannot be removed through the cart.');
            self::assertArrayHasKey('ok', $run('cart-add', ['OWNER_ID' => 'guest-b', 'MEAL_ID' => $meal, 'QTY' => 2]), 'A valid cart line is accepted.');

            // P12 — server quote, owner isolation, no side effects, expiry, digest stability.
            $quote = $run('cart-quote', ['OWNER_ID' => 'guest-b'])['quote'];
            self::assertSame(2, $quote['count']);
            self::assertSame(50000, $quote['totalMinor'], 'The quote is derived from the published price (2 × 25000).');
            self::assertCount(1, $quote['lines']);
            self::assertSame($quote['digest'], $run('cart-quote', ['OWNER_ID' => 'guest-b'])['quote']['digest'], 'The quote digest is stable.');
            self::assertSame(0, $run('cart-quote', ['OWNER_ID' => 'guest-c'])['quote']['count'], 'A different guest cannot see another guest\'s draft.');
            $counts = $run('cart-counts');
            self::assertSame(0, $counts['charges'], 'A draft cart creates no charges.');
            self::assertSame(0, $counts['order_items'], 'A draft cart creates no order items.');
            self::assertSame(0, $counts['kitchen_tickets'], 'A draft cart creates no kitchen work.');
            self::assertArrayHasKey('ok', $run('cart-add', ['OWNER_ID' => 'guest-c', 'MEAL_ID' => $meal, 'QTY' => 1]));
            self::assertSame(1, $run('cart-quote', ['OWNER_ID' => 'guest-c'])['quote']['count']);
            self::assertArrayHasKey('expired', $run('cart-expire', ['OWNER_ID' => 'guest-c']));
            self::assertSame(0, $run('cart-quote', ['OWNER_ID' => 'guest-c'])['quote']['count'], 'Expired drafts are dropped.');

            // P13 — idempotent submission, ingredient snapshot, later-order support.
            $setup = $run('kiosk-setup');
            self::assertArrayHasKey('ok', $run('kiosk-cart-add', ['KIOSK_ORDER_ID' => $setup['kioskOrderId'], 'MEAL_ID' => $meal, 'QTY' => 1]));
            $digest = $run('kiosk-quote', ['KIOSK_ORDER_ID' => $setup['kioskOrderId']])['quote']['digest'];
            $first = $run('order-kiosk', ['KIOSK_ORDER_ID' => $setup['kioskOrderId'], 'DEVICE_SESSION_ID' => $setup['deviceSessionId'], 'KEY' => 'key-0001', 'QUOTE_DIGEST' => $digest]);
            self::assertArrayHasKey('ok', $first, 'first submission: '.json_encode($first));
            self::assertFalse($first['ok']['replayed'], 'A first submission is not a replay.');
            $firstId = $first['ok']['submission']['id'];
            $replay = $run('order-kiosk', ['KIOSK_ORDER_ID' => $setup['kioskOrderId'], 'DEVICE_SESSION_ID' => $setup['deviceSessionId'], 'KEY' => 'key-0001', 'QUOTE_DIGEST' => $digest]);
            self::assertTrue($replay['ok']['replayed'], 'A same-key retry replays the original result.');
            self::assertSame($firstId, $replay['ok']['submission']['id']);
            $counts = $run('submission-count');
            self::assertSame(1, $counts['submissions'], 'Same-key retries create exactly one submission.');
            self::assertSame(1, $counts['charges'], 'One submission creates one charge.');
            self::assertNotNull($counts['snapshot']['removed_ids'] ?? null, 'The order item snapshots ingredient IDs (P13.01).');

            // A later order creates its own submission and charges.
            $setup2 = $run('kiosk-setup');
            self::assertArrayHasKey('ok', $run('kiosk-cart-add', ['KIOSK_ORDER_ID' => $setup2['kioskOrderId'], 'MEAL_ID' => $meal, 'QTY' => 1]));
            $digest2 = $run('kiosk-quote', ['KIOSK_ORDER_ID' => $setup2['kioskOrderId']])['quote']['digest'];
            $second = $run('order-kiosk', ['KIOSK_ORDER_ID' => $setup2['kioskOrderId'], 'DEVICE_SESSION_ID' => $setup2['deviceSessionId'], 'KEY' => 'key-0002', 'QUOTE_DIGEST' => $digest2]);
            self::assertArrayHasKey('ok', $second);
            self::assertSame(2, $run('submission-count')['submissions'], 'A later order creates its own submission.');

            // P10.02/P10.09 — published versions are immutable.
            $v1 = $run('meal-snapshot', ['ID' => $meal, 'VERSION' => 1])['snapshot'];
            self::assertSame('Ugali', $v1['name']);
            self::assertArrayHasKey('ok', $run('meal-update', ['ID' => $meal, 'VERSION' => 2, 'NAME' => 'Ugali Special', 'PRICE' => '260.00', 'CATEGORY_ID' => $category]));
            self::assertSame('published_with_changes', $run('meal-state', ['ID' => $meal])['status']);
            self::assertSame('Ugali', $run('meal-snapshot', ['ID' => $meal, 'VERSION' => 1])['snapshot']['name'], 'Republishing a draft never rewrites an old published snapshot.');

            // P10.03 — stale draft edits are refused.
            self::assertSame('VERSION_CONFLICT', $run('meal-update', ['ID' => $meal, 'VERSION' => 1, 'NAME' => 'Stale', 'PRICE' => '1.00', 'CATEGORY_ID' => $category])['error']);

            // P10.07 — a category rename invalidates the meal's approval.
            self::assertArrayHasKey('ok', $run('meal-approve', ['ID' => $meal]));
            $approved = $run('meal-state', ['ID' => $meal]);
            self::assertSame($approved['draft_digest'], $approved['recipe_approved_digest']);
            self::assertArrayHasKey('ok', $run('category-save', ['ID' => $category, 'NAME' => 'Main Dishes', 'ORDER' => 1]));
            $afterRename = $run('meal-state', ['ID' => $meal]);
            self::assertNotSame($afterRename['recipe_approved_digest'], $afterRename['draft_digest'], 'A category rename invalidates the recipe approval.');
        } finally {
            try {
                if ($owned) {
                    $run('cleanup');
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }
}
