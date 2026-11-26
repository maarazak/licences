<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * En tout environnement : initialise le compte administrateur principal.
     */
    public function run(): void
    {
        $adminRoleAdmin = Role::where(['name' => 'admin', 'guard_name' => 'admin'])->first();
        $adminRoleWeb   = Role::where(['name' => 'admin', 'guard_name' => 'web'])->first();

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (app()->environment('production')) {
            // Aucun identifiant par défaut en production : ils sont dans le code, donc connus de quiconque le lit.
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password) < 12) {
                throw new \RuntimeException('Production : définissez ADMIN_EMAIL et ADMIN_PASSWORD (12 caractères minimum) avant de lancer ce seeder, par exemple : ADMIN_EMAIL=… ADMIN_PASSWORD=… php artisan db:seed --class=AdminSeeder --force');
            }
        } else {
            // Développement et tests uniquement.
            $email = $email ?: 'admin@example.test';
            $password = $password ?: 'Admin@2026';
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Abdoul Razak Maali',
                'first_name' => 'Abdoul Razak',
                'last_name' => 'Maali',
                'role' => 'admin',
                'password' => Hash::make($password),
                'balance_xof' => 0,
                'country' => 'BF',
                'phone' => '+22670000000',
                'lang' => 'fr',
                'is_blocked' => false,
                'mail_reminders' => false,
            ]
        );

        // S'assurer que le mot de passe est toujours à jour
        $admin->password = Hash::make($password);
        $admin->save();

        $roles = array_filter([$adminRoleAdmin, $adminRoleWeb]);
        $admin->syncRoles($roles);
    }
}
