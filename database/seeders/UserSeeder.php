<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRoleAdmin = Role::where(['name' => 'admin', 'guard_name' => 'admin'])->first();
        $adminRoleWeb   = Role::where(['name' => 'admin', 'guard_name' => 'web'])->first();
        $clientRoleWeb  = Role::where(['name' => 'client', 'guard_name' => 'web'])->first();

        // 1. Administrateurs Backoffice
        $admins = [
            [
                'email' => 'abdoulrazakmaali@gmail.com',
                'name' => 'Abdoul Razak Maali',
                'first_name' => 'Abdoul Razak',
                'last_name' => 'Maali',
                'password' => 'Admin123!',
            ],
            [
                'email' => 'admin@licences.local',
                'name' => 'Administrateur',
                'first_name' => 'Admin',
                'last_name' => 'Principal',
                'password' => 'password',
            ],
        ];

        foreach ($admins as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $data['name'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'role' => 'admin',
                    'password' => Hash::make($data['password']),
                    'balance_xof' => 0,
                    'country' => 'BF',
                    'phone' => '+22670000000',
                    'lang' => 'fr',
                    'is_blocked' => false,
                    'mail_reminders' => false,
                ]
            );

            // S'assurer que le mot de passe est synchronisé
            $user->password = Hash::make($data['password']);
            $user->save();

            $roles = array_filter([$adminRoleAdmin, $adminRoleWeb]);
            $user->syncRoles($roles);
        }

        // 2. Client Test
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
    }
}
