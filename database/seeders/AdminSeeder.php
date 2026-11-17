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

        $email = env('ADMIN_EMAIL', 'abdoulrazakmaali@gmail.com');
        $password = env('ADMIN_PASSWORD', 'Admin123!');

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
