<?php

use App\DTOs\Items\GuardarItemDTO;
use App\Models\User;
use App\Services\Items\ItemService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);

    $this->tipoSimple = DB::table('tipos')->insertGetId([
        'nombre' => 'Escritorio',
        'requiere_serie' => false,
        'controla_vencimiento' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->tipoConSerie = DB::table('tipos')->insertGetId([
        'nombre' => 'Computador',
        'requiere_serie' => true,
        'controla_vencimiento' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function cuentaConRolItems(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function crearItemDePrueba(int $tipoId, string $numero = '11001001.76'): int
{
    return app(ItemService::class)->crear(GuardarItemDTO::desdeArreglo([
        'tipo_id' => $tipoId,
        'estado_conservacion' => 'bueno',
        'numero_inventario' => $numero,
    ]));
}

test('la ruta exige autenticación', function () {
    $this->get(route('items.indice'))->assertRedirect(route('login'));
});

test('el administrador accede al registro maestro', function () {
    $this->actingAs(cuentaConRolItems('administrador'))
        ->get(route('items.indice'))
        ->assertOk();
});

test('el verificador accede y puede crear ítems', function () {
    $this->actingAs(cuentaConRolItems('verificador'))
        ->get(route('items.indice'))
        ->assertOk();

    // El listado es diferido: el contenido llega en la segunda petición.
    Livewire::withoutLazyLoading()->test('pages::items.indice')->assertSee('Nuevo ítem');
});

test('el rol consulta accede en modo lectura', function () {
    $this->actingAs(cuentaConRolItems('consulta'))
        ->get(route('items.indice'))
        ->assertOk();

    Livewire::withoutLazyLoading()->test('pages::items.indice')->assertDontSee('Nuevo ítem');
});

test('crea un ítem desde el componente', function () {
    $this->actingAs(cuentaConRolItems('administrador'));

    Livewire::test('pages::items.indice')
        ->call('crear')
        ->set('tipoId', (string) $this->tipoSimple)
        ->set('numeroInventario', '11001001.79')
        ->set('marca', 'Genérica')
        ->call('guardar')
        ->assertHasNoErrors();

    $item = DB::table('items')->where('numero_inventario', '11001001.79')->first();

    expect($item)->not->toBeNull()
        ->and($item->situacion)->toBe('registrado_daf');
});

test('informa en el campo el número de serie que exige el tipo', function () {
    $this->actingAs(cuentaConRolItems('administrador'));

    Livewire::test('pages::items.indice')
        ->call('crear')
        ->set('tipoId', (string) $this->tipoConSerie)
        ->set('numeroInventario', '10601003.192')
        ->call('guardar')
        ->assertHasErrors('numeroSerie');
});

test('informa el número de inventario repetido en su campo', function () {
    $this->actingAs(cuentaConRolItems('administrador'));
    crearItemDePrueba($this->tipoSimple);

    Livewire::test('pages::items.indice')
        ->call('crear')
        ->set('tipoId', (string) $this->tipoSimple)
        ->set('numeroInventario', '11001001.76')
        ->call('guardar')
        ->assertHasErrors('numeroInventario');
});

test('exige el tipo y el estado de conservación', function () {
    $this->actingAs(cuentaConRolItems('administrador'));

    Livewire::test('pages::items.indice')
        ->call('crear')
        ->set('estadoConservacion', '')
        ->call('guardar')
        ->assertHasErrors(['tipoId' => 'required', 'estadoConservacion' => 'required']);
});

test('edita un ítem', function () {
    $this->actingAs(cuentaConRolItems('administrador'));
    $id = crearItemDePrueba($this->tipoSimple);

    Livewire::test('pages::items.indice')
        ->call('editar', $id)
        ->assertSet('numeroInventario', '11001001.76')
        ->assertSet('tipoId', (string) $this->tipoSimple)
        ->set('estadoConservacion', 'malo')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(DB::table('items')->find($id)->estado_conservacion)->toBe('malo');
});

test('elimina y restaura un ítem sin borrarlo de la base', function () {
    $this->actingAs(cuentaConRolItems('administrador'));
    $id = crearItemDePrueba($this->tipoSimple);

    $componente = Livewire::test('pages::items.indice')
        ->call('confirmarEliminacion', $id)
        ->assertSet('itemPorEliminar', $id)
        ->call('eliminar');

    expect(DB::table('items')->find($id)->deleted_at)->not->toBeNull()
        ->and($componente->instance()->items->total())->toBe(0);

    $componente->set('verEliminados', true);

    expect($componente->instance()->items->total())->toBe(1);

    $componente->call('restaurar', $id);

    expect(DB::table('items')->find($id)->deleted_at)->toBeNull();
});

test('asigna un ítem padre y lo marca como componente', function () {
    $this->actingAs(cuentaConRolItems('administrador'));
    $kit = crearItemDePrueba($this->tipoSimple, 'KIT-1');

    Livewire::test('pages::items.indice')
        ->call('crear')
        ->set('tipoId', (string) $this->tipoSimple)
        ->set('numeroInventario', 'BAT-1')
        ->set('itemPadreId', (string) $kit)
        ->call('guardar')
        ->assertHasNoErrors();

    expect((int) DB::table('items')->where('numero_inventario', 'BAT-1')->first()->item_padre_id)->toBe($kit);
});

test('los candidatos a padre excluyen al ítem en edición', function () {
    $this->actingAs(cuentaConRolItems('administrador'));
    $kit = crearItemDePrueba($this->tipoSimple, 'KIT-1');
    crearItemDePrueba($this->tipoSimple, 'OTRO');

    $candidatos = Livewire::test('pages::items.indice')
        ->call('editar', $kit)
        ->instance()
        ->candidatosAPadre;

    expect(array_map(static fn ($fila): int => (int) $fila->id, $candidatos))->not->toContain($kit);
});

test('el listado filtra por tipo, estado y situación', function () {
    $this->actingAs(cuentaConRolItems('administrador'));
    crearItemDePrueba($this->tipoSimple);
    app(ItemService::class)->crear(GuardarItemDTO::desdeArreglo([
        'tipo_id' => $this->tipoConSerie,
        'estado_conservacion' => 'malo',
        'numero_serie' => 'ABC123',
    ]));

    $componente = Livewire::test('pages::items.indice');

    expect($componente->set('filtroTipo', (string) $this->tipoConSerie)->instance()->items->total())->toBe(1);
    expect($componente->set('filtroTipo', '')->set('filtroEstado', 'malo')->instance()->items->total())->toBe(1);
    expect($componente->set('filtroEstado', '')->set('filtroSituacion', 'sin_registro_daf')->instance()->items->total())->toBe(1);
    expect($componente->set('filtroSituacion', '')->set('busqueda', '11001001')->instance()->items->total())->toBe(1);
});

test('ordena alternando el sentido sobre la misma columna', function () {
    $this->actingAs(cuentaConRolItems('administrador'));

    Livewire::test('pages::items.indice')
        ->assertSet('ordenarPor', 'numero_inventario')
        ->assertSet('direccion', 'asc')
        ->call('ordenar', 'numero_inventario')
        ->assertSet('direccion', 'desc')
        ->call('ordenar', 'marca')
        ->assertSet('ordenarPor', 'marca')
        ->assertSet('direccion', 'asc');
});

test('muestra la alerta de vencimientos próximos', function () {
    $this->actingAs(cuentaConRolItems('administrador'));

    $tipoVence = DB::table('tipos')->insertGetId([
        'nombre' => 'Extintor',
        'requiere_serie' => false,
        'controla_vencimiento' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(ItemService::class)->crear(GuardarItemDTO::desdeArreglo([
        'tipo_id' => $tipoVence,
        'estado_conservacion' => 'bueno',
        'numero_inventario' => 'EXT-1',
        'fecha_vencimiento' => now()->addDays(5)->toDateString(),
    ]));

    Livewire::withoutLazyLoading()->test('pages::items.indice')->assertSee('próximo a vencer');
});

test('el menú incluye ítems dentro de la sección Inventario', function () {
    $this->actingAs(cuentaConRolItems('administrador'))
        ->get(route('items.indice'))
        ->assertSee('Inventario')
        ->assertSee('Ítems');
});
