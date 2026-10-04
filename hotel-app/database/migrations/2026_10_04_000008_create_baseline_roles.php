<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now('UTC');
        foreach ([
            'owner' => 'Owner',
            'manager' => 'Manager',
            'cashier' => 'Cashier',
            'waiter' => 'Waiter',
            'kitchen_lead' => 'Kitchen lead',
            'kitchen_staff' => 'Kitchen staff',
            'menu_editor' => 'Menu editor',
            'auditor' => 'Auditor',
        ] as $key => $name) {
            DB::table('roles')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'key' => $key,
                'name' => $name,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('key', [
            'owner', 'manager', 'cashier', 'waiter', 'kitchen_lead', 'kitchen_staff', 'menu_editor', 'auditor',
        ])->delete();
    }
};
