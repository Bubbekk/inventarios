<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usuario administrador inicial
    |--------------------------------------------------------------------------
    |
    | Datos con los que AdministradorSeeder crea la primera cuenta del sistema.
    | En producción deben definirse en el archivo .env antes de ejecutar los
    | seeders, para no dejar la cuenta con las credenciales por defecto.
    |
    */

    'administrador' => [
        'nombre' => env('ADMIN_NOMBRE', 'Administrador'),
        'correo' => env('ADMIN_CORREO', 'admin@dsp.cl'),
        'clave' => env('ADMIN_CLAVE', 'password'),
    ],

];
