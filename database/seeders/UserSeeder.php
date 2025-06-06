<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Visiteur',
                'email' => 'visiteur@example.com',
                'role' => 'Citoyen non connecté',
            ],
            [
                'name' => 'Jean Citoyen',
                'email' => 'citoyen@example.com',
                'role' => 'Citoyen connecté',
            ],
            [
                'name' => 'Martine Modératrice',
                'email' => 'moderateur@example.com',
                'role' => 'Modérateur',
            ],
            [
                'name' => 'Paul Admin',
                'email' => 'admin@example.com',
                'role' => 'Administrateur',
            ],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'role' => 'Super-Administrateur',
            ],
        ];

        foreach ($users as $data) {
            $role = Role::where('name', $data['role'])->first();

            if ($role) {
                User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'email_verified_at' => now(),
                        'password' => Hash::make('password'),
                        'role_id' => $role->id,
                    ]
                );

                User::factory(3)->create([
                    'role_id' => $role->id,
                ]);
            }
        }
    }
}
