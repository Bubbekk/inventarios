<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdministradorSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $correo = config('inventario.administrador.correo');

        $administrador = User::firstOrCreate(
            ['email' => $correo],
            [
                'name' => config('inventario.administrador.nombre'),
                'password' => config('inventario.administrador.clave'),
                'email_verified_at' => now(),
            ],
        );

        if (! $administrador->hasRole('administrador')) {
            $administrador->assignRole('administrador');
        }
    }
}
