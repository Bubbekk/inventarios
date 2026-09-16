<?php

use App\DTOs\Ubicaciones\GuardarUbicacionDTO;
use App\Models\User;
use App\Services\Ubicaciones\UbicacionService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
});

function cuentaConRolUbicaciones(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function crearUbicacion(string $nombre = 'Central', string $tipo = 'oficina'): int
{
    return app(UbicacionService::class)->crear(GuardarUbicacionDTO::desdeArreglo([
        'nombre' => $nombre,
        'tipo' => $tipo,
        'direccion' => 'Av. Principal 123',
    ]));
}

test('la ruta exige autenticación', function () {
    $this->get(route('ubicaciones.indice'))->assertRedirect(route('login'));
});

test('el administrador accede al catálogo', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'))
        ->get(route('ubicaciones.indice'))
        ->assertOk();
});

test('el verificador accede al catálogo en modo lectura', function () {
    $this->actingAs(cuentaConRolUbicaciones('verificador'))
        ->get(route('ubicaciones.indice'))
        ->assertOk();

    // El listado es diferido: el contenido llega en la segunda petición.
    Livewire::withoutLazyLoading()->test('pages::ubicaciones.indice')->assertDontSee('Nueva ubicación');
});

test('crea una ubicación desde el componente', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));

    Livewire::test('pages::ubicaciones.indice')
        ->call('crear')
        ->set('nombre', 'Bodega norte')
        ->set('tipo', 'bodega')
        ->set('direccionUbicacion', 'Calle Sur 45')
        ->call('guardar')
        ->assertHasNoErrors();

    $ubicacion = DB::table('ubicaciones')->where('nombre', 'Bodega norte')->first();

    expect($ubicacion)->not->toBeNull()
        ->and($ubicacion->tipo)->toBe('bodega')
        ->and($ubicacion->direccion)->toBe('Calle Sur 45');
});

test('crea una ubicación sin dirección', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));

    Livewire::test('pages::ubicaciones.indice')
        ->call('crear')
        ->set('nombre', 'Móvil 1')
        ->set('tipo', 'vehiculo')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(DB::table('ubicaciones')->where('nombre', 'Móvil 1')->first()->direccion)->toBeNull();
});

test('exige el nombre y el tipo', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));

    Livewire::test('pages::ubicaciones.indice')
        ->call('crear')
        ->call('guardar')
        ->assertHasErrors(['nombre' => 'required', 'tipo' => 'required']);
});

test('rechaza un tipo fuera del enum', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));

    Livewire::test('pages::ubicaciones.indice')
        ->call('crear')
        ->set('nombre', 'Galpón')
        ->set('tipo', 'galpon')
        ->call('guardar')
        ->assertHasErrors(['tipo' => 'in']);
});

test('informa el nombre repetido como error del formulario', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));
    crearUbicacion();

    Livewire::test('pages::ubicaciones.indice')
        ->call('crear')
        ->set('nombre', 'Central')
        ->set('tipo', 'bodega')
        ->call('guardar')
        ->assertHasErrors('nombre');
});

test('edita una ubicación', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));
    $id = crearUbicacion();

    Livewire::test('pages::ubicaciones.indice')
        ->call('editar', $id)
        ->assertSet('nombre', 'Central')
        ->assertSet('tipo', 'oficina')
        ->assertSet('direccionUbicacion', 'Av. Principal 123')
        ->set('tipo', 'bodega')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(DB::table('ubicaciones')->find($id)->tipo)->toBe('bodega');
});

test('elimina una ubicación sin ítems asociados', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));
    $id = crearUbicacion();

    Livewire::test('pages::ubicaciones.indice')
        ->call('confirmarEliminacion', $id)
        ->assertSet('ubicacionPorEliminar', $id)
        ->call('eliminar');

    expect(DB::table('ubicaciones')->where('id', $id)->exists())->toBeFalse();
});

test('no elimina una ubicación con ítems asociados', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));
    $id = crearUbicacion();

    $tipo = DB::table('tipos')->insertGetId([
        'nombre' => 'Computador',
        'requiere_serie' => true,
        'controla_vencimiento' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('items')->insert([
        'numero_inventario' => '10601003.1',
        'tipo_id' => $tipo,
        'ubicacion_id' => $id,
        'estado_conservacion' => 'bueno',
        'situacion' => 'registrado_daf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test('pages::ubicaciones.indice')
        ->call('confirmarEliminacion', $id)
        ->call('eliminar');

    expect(DB::table('ubicaciones')->where('id', $id)->exists())->toBeTrue();
});

test('el listado filtra por tipo y por texto', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));
    crearUbicacion();
    crearUbicacion('Bodega norte', 'bodega');
    crearUbicacion('Cámara plaza', 'via_publica');

    $componente = Livewire::test('pages::ubicaciones.indice');

    expect($componente->set('filtroTipo', 'bodega')->instance()->ubicaciones->total())->toBe(1);
    expect($componente->set('filtroTipo', '')->set('busqueda', 'plaza')->instance()->ubicaciones->total())->toBe(1);
    expect($componente->set('busqueda', '')->instance()->ubicaciones->total())->toBe(3);
});

test('ordena alternando el sentido sobre la misma columna', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'));

    Livewire::test('pages::ubicaciones.indice')
        ->assertSet('ordenarPor', 'nombre')
        ->assertSet('direccion', 'asc')
        ->call('ordenar', 'nombre')
        ->assertSet('direccion', 'desc')
        ->call('ordenar', 'tipo')
        ->assertSet('ordenarPor', 'tipo')
        ->assertSet('direccion', 'asc');
});

test('el menú incluye ubicaciones dentro de la sección Inventario', function () {
    $this->actingAs(cuentaConRolUbicaciones('administrador'))
        ->get(route('ubicaciones.indice'))
        ->assertSee('Inventario')
        ->assertSee('Ubicaciones');
});
