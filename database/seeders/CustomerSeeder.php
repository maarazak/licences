<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Environnement local et testing UNIQUEMENT : données de démonstration.
     */
    public function run(): void
    {
        // Sécurité absolue : ne jamais exécuter en production
        if (!app()->environment('local', 'testing')) {
            $this->command?->warn('CustomerSeeder : ignoré en production.');
            return;
        }

        $clientRoleWeb  = Role::where(['name' => 'client', 'guard_name' => 'web'])->first();
        $adminRoleAdmin = Role::where(['name' => 'admin', 'guard_name' => 'admin'])->first();
        $adminRoleWeb   = Role::where(['name' => 'admin', 'guard_name' => 'web'])->first();

        // 1. Client de démonstration avec solde crédité pour tests
        $client = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Test User',
                'first_name' => 'Test',
                'last_name' => 'User',
                'role' => 'client',
                'password' => Hash::make('password'),
                'balance_xof' => 25000,
                'country' => 'BF',
                'phone' => '+22670112233',
                'lang' => 'fr',
                'is_blocked' => false,
                'mail_reminders' => true,
            ]
        );

        if ($clientRoleWeb) {
            $client->syncRoles([$clientRoleWeb]);
        }

        // 2. Compte administrateur local de secours
        $fallbackAdmin = User::firstOrCreate(
            ['email' => 'admin@licences.local'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Administrateur Local',
                'first_name' => 'Admin',
                'last_name' => 'Local',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'balance_xof' => 0,
                'country' => 'BF',
                'phone' => '+22670000000',
                'lang' => 'fr',
                'is_blocked' => false,
                'mail_reminders' => false,
            ]
        );

        $roles = array_filter([$adminRoleAdmin, $adminRoleWeb]);
        $fallbackAdmin->syncRoles($roles);
    }
}
