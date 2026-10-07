<?php

declare(strict_types=1);

/**
 * P10 fixture probe for the meal/recipe/publication catalogue.
 * Driven by tests/Database/MealCatalogueTest.php against a disposable schema.
 */

require __DIR__.'/vendor/autoload.php';

try {
    $app = require __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $app['config']->set('database.migrations.table', 'probe_migrations');
    $db = $app['db']->connection('mysql');
    $schema = $db->getSchemaBuilder();
    $action = $argv[1] ?? 'inspect';

    $dropAll = static function () use ($db, $schema): void {
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($db->select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $row) {
            $schema->dropIfExists((string) $row->name);
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');
    };

    if ($action === 'migrate') {
        $dropAll();
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $status = $kernel->call('app:migrate', ['--no-interaction' => true], $output);
        echo json_encode(['status' => $status]);
        exit(0);
    }
    if ($action === 'cleanup') {
        $dropAll();
        echo json_encode(['cleaned' => true]);
        exit(0);
    }

    $catalogue = $app->make(App\Domain\Catalogue\MealCatalogue::class);
    $actor = '01990000-0000-7000-8000-0000000000aa';

    $guard = static function (callable $work): array {
        try {
            return ['ok' => $work()];
        } catch (App\Domain\DomainError $error) {
            return ['error' => $error->errorCode, 'status' => $error->status];
        } catch (Throwable $error) {
            return ['error' => 'throwable', 'message' => $error->getMessage()];
        }
    };

    if ($action === 'category-save') {
        $result = $guard(fn () => $catalogue->saveCategory(
            getenv('ID') ?: null,
            (string) (getenv('NAME') ?: ''),
            (int) (getenv('ORDER') ?: 0),
            true,
            $actor,
        ));
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'meal-create') {
        $result = $guard(fn () => $catalogue->create([
            'name' => (string) (getenv('NAME') ?: ''),
            'description' => 'Fixture meal',
            'price_minor' => App\Domain\Money::parse((string) (getenv('PRICE') ?: '0.00')) ?? 0,
            'category_id' => getenv('CATEGORY_ID') ?: null,
            'station_id' => null,
            'media_id' => null,
            'display_order' => 0,
        ], $actor));
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'meal-update') {
        $result = $guard(function () use ($catalogue, $actor) {
            $catalogue->updateDraft((string) (getenv('ID') ?: ''), (int) (getenv('VERSION') ?: 1), [
                'name' => (string) (getenv('NAME') ?: ''),
                'description' => 'Fixture meal',
                'price_minor' => App\Domain\Money::parse((string) (getenv('PRICE') ?: '0.00')) ?? 0,
                'category_id' => getenv('CATEGORY_ID') ?: null,
                'station_id' => null,
                'media_id' => null,
                'display_order' => 0,
            ], $actor);

            return true;
        });
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'meal-ingredients') {
        $rows = $db->table('meal_ingredients')->where('meal_id', (string) (getenv('ID') ?: ''))
            ->get(['ingredient_id', 'rule'])->map(static fn ($r) => ['ingredient_id' => (string) $r->ingredient_id, 'rule' => (string) $r->rule])->all();
        echo json_encode(['rules' => $rows]);
        exit(0);
    }

    if ($action === 'kiosk-setup') {
        $deviceId = (string) Illuminate\Support\Str::uuid7();
        $sessionId = (string) Illuminate\Support\Str::uuid7();
        $orderId = (string) Illuminate\Support\Str::uuid7();
        $now = now('UTC');
        $db->table('devices')->insert(['id' => $deviceId, 'name' => 'Fixture kiosk', 'mode' => 'kiosk', 'credential_digest' => hash('sha256', 'fixture-credential-'.bin2hex(random_bytes(8))), 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $db->table('device_sessions')->insert(['id' => $sessionId, 'device_id' => $deviceId, 'token_digest' => hash('sha256', 'fixture-token-'.bin2hex(random_bytes(8))), 'issued_at' => $now, 'expires_at' => $now->copy()->addHour(), 'created_at' => $now, 'updated_at' => $now]);
        $db->table('kiosk_orders')->insert(['id' => $orderId, 'device_session_id' => $sessionId, 'dining' => 'eat_in', 'state' => 'draft', 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        echo json_encode(['kioskOrderId' => $orderId, 'deviceSessionId' => $sessionId]);
        exit(0);
    }

    if ($action === 'kiosk-cart-add') {
        $cart = $app->make(App\Domain\Ordering\CartService::class);
        $result = $guard(function () use ($cart) {
            $cart->add('kiosk', (string) (getenv('KIOSK_ORDER_ID') ?: ''), (string) (getenv('MEAL_ID') ?: ''), [], [], (int) (getenv('QTY') ?: 1), null);

            return true;
        });
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'kiosk-quote') {
        $cart = $app->make(App\Domain\Ordering\CartService::class);
        echo json_encode(['quote' => $cart->quote('kiosk', (string) (getenv('KIOSK_ORDER_ID') ?: ''))]);
        exit(0);
    }

    if ($action === 'order-kiosk') {
        $orders = $app->make(App\Domain\Ordering\OrderService::class);
        $result = $guard(fn () => $orders->submitKiosk(
            (string) (getenv('KIOSK_ORDER_ID') ?: ''),
            (string) (getenv('DEVICE_SESSION_ID') ?: ''),
            (string) (getenv('KEY') ?: ''),
            (string) (getenv('QUOTE_DIGEST') ?: ''),
            null, 'mpesa', 'eat_in', 'Fixture',
        ));
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'submission-count') {
        echo json_encode([
            'submissions' => $db->table('order_submissions')->count(),
            'charges' => $db->table('charges')->count(),
            'order_items' => $db->table('order_items')->count(),
            'snapshot' => (array) ($db->table('order_items')->first(['removed_ids', 'extras_snapshot']) ?? []),
        ]);
        exit(0);
    }

    if ($action === 'cart-quote') {
        $cart = $app->make(App\Domain\Ordering\CartService::class);
        echo json_encode(['quote' => $cart->quote('guest', (string) (getenv('OWNER_ID') ?: 'owner'))]);
        exit(0);
    }

    if ($action === 'cart-expire') {
        $db->table('cart_lines')->where('owner_type', 'guest')->where('owner_id', (string) (getenv('OWNER_ID') ?: 'owner'))
            ->update(['expires_at' => now('UTC')->subMinute()]);
        echo json_encode(['expired' => true]);
        exit(0);
    }

    if ($action === 'cart-counts') {
        echo json_encode([
            'cart_lines' => $db->table('cart_lines')->count(),
            'charges' => $db->table('charges')->count(),
            'order_items' => $db->table('order_items')->count(),
            'kitchen_tickets' => $db->table('kitchen_tickets')->count(),
        ]);
        exit(0);
    }

    if ($action === 'cart-add') {
        $cart = $app->make(App\Domain\Ordering\CartService::class);
        $removed = array_values(array_filter(explode(',', (string) (getenv('REMOVED') ?: '')), static fn ($v) => $v !== ''));
        $extras = array_values(array_filter(explode(',', (string) (getenv('EXTRAS') ?: '')), static fn ($v) => $v !== ''));
        $result = $guard(function () use ($cart, $removed, $extras) {
            $cart->add('guest', (string) (getenv('OWNER_ID') ?: 'owner'), (string) (getenv('MEAL_ID') ?: ''), $removed, $extras, (int) (getenv('QTY') ?: 1), null);

            return true;
        });
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'meal-add-ingredient') {
        $result = $guard(function () use ($app, $catalogue, $actor) {
            $mealId = (string) (getenv('ID') ?: '');
            $ingredients = $app->make(App\Domain\Catalogue\IngredientCatalogue::class);
            $ingredientId = $ingredients->create(['name' => 'Fixture Salt', 'description' => null], $actor);
            $version = (int) DB::table('meals')->where('id', $mealId)->value('version');
            $catalogue->setIngredients($mealId, $version, [
                ['ingredient_id' => $ingredientId, 'rule' => 'fixed', 'extra_price_minor' => 0],
            ], $actor);

            return true;
        });
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'meal-approve') {
        $result = $guard(function () use ($catalogue, $actor) {
            $id = (string) (getenv('ID') ?: '');
            $digest = $catalogue->refreshDigest($id);
            $catalogue->approveRecipe($id, $digest, $actor, 'Fixture approval');

            return true;
        });
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'meal-publish') {
        $result = $guard(fn () => $catalogue->publish((string) (getenv('ID') ?: ''), $actor));
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'meal-state') {
        $id = (string) (getenv('ID') ?: '');
        $row = $db->table('meals')->where('id', $id)->first(['id', 'name', 'price_minor', 'version', 'archived', 'draft_digest', 'recipe_approved_digest', 'published_version']);
        if ($row === null) {
            echo json_encode(['found' => false]);
            exit(0);
        }
        echo json_encode([
            'found' => true, 'name' => $row->name, 'price_minor' => (int) $row->price_minor,
            'version' => (int) $row->version, 'draft_digest' => $row->draft_digest,
            'recipe_approved_digest' => $row->recipe_approved_digest, 'published_version' => $row->published_version,
            'status' => $catalogue->status($row),
        ]);
        exit(0);
    }

    if ($action === 'meal-snapshot') {
        $version = getenv('VERSION') ? (int) getenv('VERSION') : null;
        echo json_encode(['snapshot' => $catalogue->publishedSnapshot((string) (getenv('ID') ?: ''), $version)]);
        exit(0);
    }

    if ($action === 'menu') {
        $menu = $catalogue->menu();
        echo json_encode(['meals' => array_map(static fn (array $m): string => (string) $m['name'], $menu['meals'])]);
        exit(0);
    }

    fwrite(STDERR, "Unknown meal fixture action.\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage()."\n");
    exit(1);
}
