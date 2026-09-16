<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    /**
     * Catálogo cerrado de permisos del sistema, agrupado por módulo.
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISOS = [
        'usuarios' => ['ver', 'crear', 'editar', 'eliminar'],
        'tipos' => ['ver', 'crear', 'editar', 'eliminar'],
        'ubicaciones' => ['ver', 'crear', 'editar', 'eliminar'],
        'funcionarios' => ['ver', 'crear', 'editar'],
        'items' => ['ver', 'crear', 'editar', 'eliminar', 'restaurar'],
        'procesos' => ['ver', 'crear', 'cerrar', 'reabrir'],
        'verificaciones' => ['ver', 'registrar'],
        'conciliacion' => ['ver', 'aplicar'],
        'reportes' => ['ver', 'exportar'],
        'auditoria' => ['ver'],
    ];

    /**
     * Permisos de cada rol. El administrador recibe el catálogo completo.
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISOS_POR_ROL = [
        'verificador' => [
            'tipos.ver',
            'ubicaciones.ver',
            'funcionarios.ver',
            'items.ver',
            'items.crear',
            'items.editar',
            'procesos.ver',
            'verificaciones.ver',
            'verificaciones.registrar',
            'reportes.ver',
            'reportes.exportar',
        ],
        'consulta' => [
            'tipos.ver',
            'ubicaciones.ver',
            'funcionarios.ver',
            'items.ver',
            'procesos.ver',
            'verificaciones.ver',
            'reportes.ver',
            'reportes.exportar',
        ],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        foreach ($this->catalogo() as $permiso) {
            Permission::findOrCreate($permiso, $guard);
        }

        $administrador = Role::findOrCreate('administrador', $guard);
        $administrador->syncPermissions($this->catalogo());

        foreach (self::PERMISOS_POR_ROL as $nombre => $permisos) {
            Role::findOrCreate($nombre, $guard)->syncPermissions($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Devuelve el catálogo completo de permisos en formato modulo.accion.
     *
     * @return array<int, string>
     */
    public static function catalogo(): array
    {
        $permisos = [];

        foreach (self::PERMISOS as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $permisos[] = $modulo.'.'.$accion;
            }
        }

        return $permisos;
    }
}
