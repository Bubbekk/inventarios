<?php

use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
});

test('el catálogo crea los treinta y un permisos del sistema', function () {
    expect(RolesYPermisosSeeder::catalogo())->toHaveCount(31)
        ->and(Permission::count())->toBe(31);
});

test('existen los tres roles definidos', function () {
    expect(Role::pluck('name')->all())
        ->toEqualCanonicalizing(['administrador', 'verificador', 'consulta']);
});

test('el administrador recibe el catálogo completo de permisos', function () {
    $administrador = Role::findByName('administrador');

    expect($administrador->permissions)->toHaveCount(31);
});

test('el verificador puede registrar verificaciones y crear ítems', function () {
    $usuario = User::factory()->create();
    $usuario->assignRole('verificador');

    expect($usuario->can('verificaciones.registrar'))->toBeTrue()
        ->and($usuario->can('items.crear'))->toBeTrue()
        ->and($usuario->can('items.editar'))->toBeTrue()
        ->and($usuario->can('reportes.exportar'))->toBeTrue();
});

test('el verificador no puede administrar catálogos ni conciliar', function () {
    $usuario = User::factory()->create();
    $usuario->assignRole('verificador');

    expect($usuario->can('tipos.crear'))->toBeFalse()
        ->and($usuario->can('items.eliminar'))->toBeFalse()
        ->and($usuario->can('procesos.cerrar'))->toBeFalse()
        ->and($usuario->can('conciliacion.aplicar'))->toBeFalse()
        ->and($usuario->can('auditoria.ver'))->toBeFalse()
        ->and($usuario->can('usuarios.ver'))->toBeFalse();
});

test('el rol consulta solo puede ver y exportar', function () {
    $usuario = User::factory()->create();
    $usuario->assignRole('consulta');

    expect($usuario->can('items.ver'))->toBeTrue()
        ->and($usuario->can('reportes.exportar'))->toBeTrue()
        ->and($usuario->can('items.crear'))->toBeFalse()
        ->and($usuario->can('verificaciones.registrar'))->toBeFalse();
});

test('los funcionarios no tienen permiso de eliminación', function () {
    expect(RolesYPermisosSeeder::catalogo())->not->toContain('funcionarios.eliminar');
});

test('el seeder es idempotente', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(Permission::count())->toBe(31)
        ->and(Role::count())->toBe(3);
});
