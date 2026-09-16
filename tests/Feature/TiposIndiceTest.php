<?php

use App\DTOs\Tipos\GuardarTipoDTO;
use App\Models\User;
use App\Services\Tipos\TipoService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
});

function cuentaConRol(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function crearTipo(string $nombre = 'Computador'): int
{
    return app(TipoService::class)->crear(GuardarTipoDTO::desdeArreglo([
        'nombre' => $nombre,
        'requiere_serie' => true,
    ]));
}

test('la ruta exige autenticación', function () {
    $this->get(route('tipos.indice'))->assertRedirect(route('login'));
});

test('el administrador accede al catálogo', function () {
    $this->actingAs(cuentaConRol('administrador'))
        ->get(route('tipos.indice'))
        ->assertOk();
});

test('el verificador accede al catálogo en modo lectura', function () {
    $this->actingAs(cuentaConRol('verificador'))
        ->get(route('tipos.indice'))
        ->assertOk();

    // El listado es diferido: el contenido llega en la segunda petición.
    Livewire::withoutLazyLoading()->test('pages::tipos.indice')->assertDontSee('Nuevo tipo');
});

test('crea un tipo desde el componente', function () {
    $this->actingAs(cuentaConRol('administrador'));

    Livewire::test('pages::tipos.indice')
        ->call('crear')
        ->set('nombre', 'Extintor')
        ->set('controlaVencimiento', true)
        ->call('guardar')
        ->assertHasNoErrors();

    $tipo = DB::table('tipos')->where('nombre', 'Extintor')->first();

    expect($tipo)->not->toBeNull()
        ->and((bool) $tipo->controla_vencimiento)->toBeTrue();
});

test('informa el nombre repetido como error del formulario', function () {
    $this->actingAs(cuentaConRol('administrador'));
    crearTipo();

    Livewire::test('pages::tipos.indice')
        ->call('crear')
        ->set('nombre', 'Computador')
        ->call('guardar')
        ->assertHasErrors('nombre');
});

test('exige el nombre', function () {
    $this->actingAs(cuentaConRol('administrador'));

    Livewire::test('pages::tipos.indice')
        ->call('crear')
        ->call('guardar')
        ->assertHasErrors(['nombre' => 'required']);
});

test('edita un tipo y sus indicadores', function () {
    $this->actingAs(cuentaConRol('administrador'));
    $id = crearTipo();

    Livewire::test('pages::tipos.indice')
        ->call('editar', $id)
        ->assertSet('nombre', 'Computador')
        ->assertSet('requiereSerie', true)
        ->set('requiereSerie', false)
        ->set('controlaVencimiento', true)
        ->call('guardar')
        ->assertHasNoErrors();

    $tipo = DB::table('tipos')->find($id);

    expect((bool) $tipo->requiere_serie)->toBeFalse()
        ->and((bool) $tipo->controla_vencimiento)->toBeTrue();
});

test('elimina un tipo sin ítems asociados', function () {
    $this->actingAs(cuentaConRol('administrador'));
    $id = crearTipo();

    Livewire::test('pages::tipos.indice')
        ->call('confirmarEliminacion', $id)
        ->assertSet('tipoPorEliminar', $id)
        ->call('eliminar');

    expect(DB::table('tipos')->where('id', $id)->exists())->toBeFalse();
});

test('no elimina un tipo con ítems asociados', function () {
    $this->actingAs(cuentaConRol('administrador'));
    $id = crearTipo();

    $ubicacion = DB::table('ubicaciones')->insertGetId([
        'nombre' => 'Bodega central',
        'tipo' => 'bodega',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('items')->insert([
        'numero_inventario' => '10601003.1',
        'tipo_id' => $id,
        'ubicacion_id' => $ubicacion,
        'estado_conservacion' => 'bueno',
        'situacion' => 'registrado_daf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test('pages::tipos.indice')
        ->call('confirmarEliminacion', $id)
        ->call('eliminar');

    expect(DB::table('tipos')->where('id', $id)->exists())->toBeTrue();
});

test('el listado filtra por texto y por indicadores', function () {
    $this->actingAs(cuentaConRol('administrador'));
    crearTipo();
    app(TipoService::class)->crear(GuardarTipoDTO::desdeArreglo([
        'nombre' => 'Extintor',
        'controla_vencimiento' => true,
    ]));

    $componente = Livewire::test('pages::tipos.indice');

    expect($componente->set('busqueda', 'ext')->instance()->tipos->total())->toBe(1);
    expect($componente->set('busqueda', '')->set('filtroSerie', 'si')->instance()->tipos->total())->toBe(1);
    expect($componente->set('filtroSerie', '')->set('filtroVencimiento', 'si')->instance()->tipos->total())->toBe(1);
});

test('ordena alternando el sentido sobre la misma columna', function () {
    $this->actingAs(cuentaConRol('administrador'));

    Livewire::test('pages::tipos.indice')
        ->assertSet('ordenarPor', 'nombre')
        ->assertSet('direccion', 'asc')
        ->call('ordenar', 'nombre')
        ->assertSet('direccion', 'desc')
        ->call('ordenar', 'created_at')
        ->assertSet('ordenarPor', 'created_at')
        ->assertSet('direccion', 'asc');
});

test('la sección Inventario aparece en el menú al existir la ruta de tipos', function () {
    $this->actingAs(cuentaConRol('administrador'))
        ->get(route('inicio'))
        ->assertSee('Inventario');
});
