<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
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

        // Identifiants du compte administrateur : définis ici (pas dans le .env). Le mot de passe est stocké sous forme
        // d'empreinte bcrypt : le code ne contient pas le mot de passe en clair.
        $email = 'admin@local.com';
        $passwordHash = '$2y$12$LG0VdL6YOv0e/zjgRYfuL.ALz/rETjsgZgOKI1X1.awDrFoSno526';

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Administrateur',
                'first_name' => 'Administrateur',
                'last_name' => 'Licences',
                'role' => 'admin',
                'password' => $passwordHash,
                'balance_xof' => 0,
                'country' => 'BF',
                'lang' => 'fr',
                'is_blocked' => false,
                'mail_reminders' => false,
            ]
        );

        // Le mot de passe du seeder fait foi à chaque exécution.
        $admin->password = $passwordHash;
        $admin->save();

        $roles = array_filter([$adminRoleAdmin, $adminRoleWeb]);
        $admin->syncRoles($roles);
    }
}
