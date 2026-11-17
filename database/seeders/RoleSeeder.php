<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nettoyage du cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Rôle Administrateur pour les deux guards (admin et web)
        $adminRoleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
        $adminRoleWeb   = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Assigner toutes les permissions du guard admin au rôle admin
        $adminPermissions = Permission::where('guard_name', 'admin')->get();
        $adminRoleAdmin->syncPermissions($adminPermissions);

        $webPermissions = Permission::where('guard_name', 'web')->get();
        $adminRoleWeb->syncPermissions($webPermissions);

        // 2. Rôle Client pour le guard web
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    }
}
