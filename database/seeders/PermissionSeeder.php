<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyage du cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage products',
            'manage orders',
            'manage clients',
            'adjust balances',
            'manage tickets',
            'manage settings',
            'view audit logs',
        ];

        foreach (['admin', 'web'] as $guard) {
            foreach ($permissions as $perm) {
                Permission::firstOrCreate([
                    'name' => $perm,
                    'guard_name' => $guard,
                ]);
            }
        }
    }
}
