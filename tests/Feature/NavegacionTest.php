<?php

use App\Models\User;
use App\Services\Navegacion\NavegacionService;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    $this->servicio = app(NavegacionService::class);
});

function cuentaCon(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

/**
 * @return array<int, string>
 */
function clavesVisibles(NavegacionService $servicio): array
{
    return array_map(
        static fn ($seccion): string => $seccion->clave,
        $servicio->seccionesVisibles(),
    );
}

test('oculta las secciones cuyas rutas todavía no existen', function () {
    $this->actingAs(cuentaCon('administrador'));

    expect(clavesVisibles($this->servicio))->toBe(['inicio', 'inventario', 'administracion']);
});

test('el administrador ve el módulo de usuarios en administración', function () {
    $this->actingAs(cuentaCon('administrador'));

    $administracion = collect($this->servicio->seccionesVisibles())
        ->firstWhere('clave', 'administracion');

    expect(array_map(static fn ($modulo): string => $modulo->ruta, $administracion->modulos))
        ->toBe(['usuarios.indice']);
});

test('el verificador no ve la sección de administración', function () {
    $this->actingAs(cuentaCon('verificador'));

    expect(clavesVisibles($this->servicio))->toBe(['inicio', 'inventario']);
});

test('el rol consulta tampoco ve la sección de administración', function () {
    $this->actingAs(cuentaCon('consulta'));

    expect(clavesVisibles($this->servicio))->toBe(['inicio', 'inventario']);
});

test('reconoce la sección a la que pertenece la ruta actual', function () {
    $this->actingAs(cuentaCon('administrador'));

    $this->get(route('usuarios.indice'));

    expect(app(NavegacionService::class)->seccionActual()?->clave)->toBe('administracion');
});

test('el encabezado muestra solo las secciones permitidas', function () {
    $this->actingAs(cuentaCon('administrador'))
        ->get(route('inicio'))
        ->assertSee('Administración')
        ->assertDontSee('Verificación');

    $this->actingAs(cuentaCon('verificador'))
        ->get(route('inicio'))
        ->assertDontSee('Administración');
});

test('la apariencia no queda forzada a oscuro en el marcado', function () {
    $this->actingAs(cuentaCon('administrador'))
        ->get(route('inicio'))
        ->assertDontSee('<html lang="es" class="dark">', false);
});
