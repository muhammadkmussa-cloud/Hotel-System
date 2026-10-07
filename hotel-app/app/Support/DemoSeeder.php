<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Catalogue\IngredientCatalogue;
use App\Domain\Catalogue\MealCatalogue;
use App\Domain\Hotel;
use App\Domain\Ids;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds a clearly-labelled demonstration installation: test-mode settings,
 * one account per role, tables, stations, a small published menu and
 * enrolled demo devices. Refuses to run against an installation that
 * already has staff or is not in test mode.
 */
final class DemoSeeder
{
    public const PASSWORD = 'demo-password-2026';

    public function __construct(
        private readonly PasswordHasher $hasher,
        private readonly IngredientCatalogue $ingredients,
        private readonly MealCatalogue $meals,
        private readonly DeviceRegistry $devices,
    ) {}

    /** @return array{staff:list<array{email:string,role:string}>,devices:list<array{name:string,mode:string,code:string}>} */
    public function seed(): array
    {
        if (DB::table('staff_users')->exists()) {
            throw new RuntimeException('Refusing to seed: this installation already has staff accounts.');
        }
        $settings = DB::table('hotel_settings')->first();
        if ($settings === null) {
            DB::table('hotel_settings')->insert(['id' => Ids::new(), 'name' => 'Demo Lakeside Hotel', 'timezone' => 'Africa/Nairobi', 'currency' => 'KES', 'test_mode' => 1,
                'receipt_header' => 'Restaurant & Bar · Demo', 'receipt_footer' => 'Thank you for dining with us', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
        } elseif (! $settings->test_mode) {
            throw new RuntimeException('Refusing to seed: the installation is not in test mode.');
        }
        Hotel::forget();

        $now = now('UTC');
        $hash = $this->hasher->hash(self::PASSWORD);
        $roles = DB::table('roles')->pluck('id', 'key')->all();
        $people = [
            ['owner', 'Olivia Owner'], ['manager', 'Mark Manager'], ['cashier', 'Cate Cashier'], ['waiter', 'Wanjiru Waiter'],
            ['kitchen_lead', 'Kamau Kitchen Lead'], ['kitchen_staff', 'Kip Kitchen'], ['menu_editor', 'Mercy Menu Editor'], ['auditor', 'Aziz Auditor'],
        ];
        $staff = [];
        $ownerId = null;
        foreach ($people as [$role, $name]) {
            $id = Ids::new();
            $email = str_replace('_', '-', $role).'@demo.test';
            DB::table('staff_users')->insert(['id' => $id, 'email' => $email, 'name' => $name, 'password_hash' => $hash, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('staff_role_grants')->insert(['id' => Ids::new(), 'staff_user_id' => $id, 'role_id' => $roles[$role], 'granted_by' => $ownerId, 'granted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            if ($role === 'owner') {
                $ownerId = $id;
                DB::table('installation_bootstrap')->insert(['id' => Ids::new(), 'owner_staff_user_id' => $id, 'bootstrapped_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
            $staff[] = ['email' => $email, 'role' => $role];
        }

        foreach (range(1, 8) as $n) {
            DB::table('tables')->insert(['id' => Ids::new(), 'label' => 'Table '.$n, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $kitchen = Ids::new();
        $bar = Ids::new();
        DB::table('stations')->insert([
            ['id' => $kitchen, 'name' => 'Main kitchen', 'kind' => 'kitchen', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => $bar, 'name' => 'Bar', 'kind' => 'bar', 'active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $cat = [];
        foreach (['Starters', 'Mains', 'Desserts', 'Drinks'] as $i => $name) {
            $cat[$name] = $this->meals->saveCategory(null, $name, $i, true, $ownerId);
        }

        $ing = [];
        $defs = [
            'Beef patty' => [null, 'Grilled 150 g beef patty'], 'Brioche bun' => ['Contains gluten, egg and milk.', null],
            'Cheddar cheese' => ['Contains milk.', null], 'Lettuce' => [null, null], 'Tomato' => [null, null], 'Red onion' => [null, null],
            'Pickles' => [null, null], 'Bacon' => [null, 'Smoked streaky bacon'], 'Fried egg' => ['Contains egg.', null], 'Avocado' => [null, null],
            'Chicken breast' => [null, 'Free-range, marinated'], 'Pilau rice' => [null, 'Spiced Swahili rice'], 'Kachumbari' => [null, 'Fresh tomato & onion salad'],
            'Chapati' => ['Contains gluten.', null], 'Sukuma wiki' => [null, 'Sautéed collard greens'], 'Ugali' => [null, 'Maize meal'],
            'Tilapia' => ['Contains fish.', 'Whole fried lake tilapia'], 'Lemon' => [null, null], 'Chili' => [null, null], 'Coconut cream' => [null, null],
            'Mango' => [null, null], 'Passion fruit' => [null, null], 'Vanilla ice cream' => ['Contains milk.', null], 'Chocolate sauce' => ['Contains milk and soy.', null],
            'Ginger' => [null, null], 'Black tea' => [null, null], 'Milk' => ['Contains milk.', null], 'Samosa pastry' => ['Contains gluten.', null], 'Spiced minced beef' => [null, null],
            'Peanut sauce' => ['Contains peanuts.', null],
        ];
        foreach ($defs as $name => [$allergen, $desc]) {
            $ing[$name] = $this->ingredients->create(['name' => $name, 'allergen_notes' => $allergen, 'description' => $desc], $ownerId);
        }

        $menu = [
            ['Beef samosas (3)', 'Starters', 45000, $kitchen, 'Crisp pastry parcels with spiced minced beef, served with lemon and chili.', [['Samosa pastry', 'fixed'], ['Spiced minced beef', 'fixed'], ['Lemon', 'removable'], ['Chili', 'removable']]],
            ['Kachumbari & avocado salad', 'Starters', 52000, $kitchen, 'Tomato, red onion and chili with ripe avocado.', [['Tomato', 'fixed'], ['Red onion', 'removable'], ['Chili', 'removable'], ['Avocado', 'removable'], ['Lemon', 'removable']]],
            ['Classic cheeseburger', 'Mains', 115000, $kitchen, 'Beef patty, cheddar, lettuce, tomato and pickles in a toasted brioche bun.', [['Beef patty', 'fixed'], ['Brioche bun', 'fixed'], ['Cheddar cheese', 'removable'], ['Lettuce', 'removable'], ['Tomato', 'removable'], ['Red onion', 'removable'], ['Pickles', 'removable'], ['Bacon', 'extra', 15000], ['Fried egg', 'extra', 10000], ['Avocado', 'extra', 12000]]],
            ['Chicken pilau', 'Mains', 98000, $kitchen, 'Spiced pilau rice with marinated chicken and kachumbari.', [['Chicken breast', 'fixed'], ['Pilau rice', 'fixed'], ['Kachumbari', 'removable'], ['Chili', 'extra', 0]]],
            ['Whole fried tilapia', 'Mains', 145000, $kitchen, 'Lake tilapia fried whole, with ugali and sukuma wiki.', [['Tilapia', 'fixed'], ['Ugali', 'removable'], ['Sukuma wiki', 'removable'], ['Kachumbari', 'removable'], ['Lemon', 'removable'], ['Chapati', 'extra', 8000]]],
            ['Chicken in peanut sauce', 'Mains', 125000, $kitchen, 'Tender chicken simmered in a mild peanut and coconut sauce, with chapati.', [['Chicken breast', 'fixed'], ['Peanut sauce', 'fixed'], ['Coconut cream', 'fixed'], ['Chapati', 'removable'], ['Pilau rice', 'extra', 15000]]],
            ['Mango & passion sorbet', 'Desserts', 45000, $kitchen, 'Fresh fruit sorbet.', [['Mango', 'fixed'], ['Passion fruit', 'fixed']]],
            ['Ice cream sundae', 'Desserts', 55000, $kitchen, 'Vanilla ice cream with chocolate sauce.', [['Vanilla ice cream', 'fixed'], ['Chocolate sauce', 'removable'], ['Mango', 'extra', 8000]]],
            ['Fresh passion juice', 'Drinks', 30000, $bar, 'Freshly squeezed.', [['Passion fruit', 'fixed'], ['Ginger', 'extra', 3000]]],
            ['Kenyan chai', 'Drinks', 20000, $bar, 'Black tea brewed with milk and ginger.', [['Black tea', 'fixed'], ['Milk', 'removable'], ['Ginger', 'removable']]],
        ];
        foreach ($menu as $i => [$name, $category, $price, $station, $desc, $rules]) {
            $id = $this->meals->create(['name' => $name, 'description' => $desc, 'price_minor' => $price, 'category_id' => $cat[$category], 'station_id' => $station, 'display_order' => $i], $ownerId);
            $this->meals->setIngredients($id, 1, array_map(static fn ($r) => ['ingredient_id' => $ing[$r[0]], 'rule' => $r[1], 'extra_price_minor' => $r[2] ?? 0], $rules), $ownerId);
            $digest = (string) DB::table('meals')->where('id', $id)->value('draft_digest');
            $this->meals->approveRecipe($id, $digest, $ownerId, 'Demo approval');
            $this->meals->publish($id, $ownerId);
        }
        DB::table('meals')->where('name', 'Whole fried tilapia')->update(['portions_remaining' => 3]);

        $devices = [];
        foreach ([['Demo table tablet', 'tablet'], ['Demo kiosk', 'kiosk'], ['Kitchen screen', 'kitchen'], ['Collection screen', 'collection']] as [$name, $mode]) {
            $r = $this->devices->enroll($name, $mode, bin2hex(random_bytes(24)));
            $devices[] = ['name' => $name, 'mode' => $mode, 'code' => (string) ($r['code'] ?? '')];
        }

        return ['staff' => $staff, 'devices' => $devices];
    }
}
