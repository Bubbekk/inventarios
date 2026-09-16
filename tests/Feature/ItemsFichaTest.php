<?php

use App\DTOs\Items\GuardarItemDTO;
use App\Models\User;
use App\Services\Items\ItemService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);

    $this->tipo = DB::table('tipos')->insertGetId([
        'nombre' => 'Kit Radio',
        'requiere_serie' => false,
        'controla_vencimiento' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->usuario = User::factory()->create();
    $this->usuario->assignRole('administrador');
});

function itemDeFicha(int $tipoId, string $numero, ?int $padre = null): int
{
    return app(ItemService::class)->crear(GuardarItemDTO::desdeArreglo([
        'tipo_id' => $tipoId,
        'estado_conservacion' => 'bueno',
        'numero_inventario' => $numero,
        'item_padre_id' => $padre,
    ]));
}

test('la ficha exige autenticación', function () {
    $id = itemDeFicha($this->tipo, 'KIT-1');

    $this->get(route('items.ficha', $id))->assertRedirect(route('login'));
});

test('el rol consulta puede ver la ficha', function () {
    $id = itemDeFicha($this->tipo, 'KIT-1');

    $consulta = User::factory()->create();
    $consulta->assignRole('consulta');

    $this->actingAs($consulta)
        ->get(route('items.ficha', $id))
        ->assertOk()
        ->assertSee('KIT-1');
});

test('muestra los datos del bien', function () {
    $id = app(ItemService::class)->crear(GuardarItemDTO::desdeArreglo([
        'tipo_id' => $this->tipo,
        'estado_conservacion' => 'regular',
        'numero_inventario' => '10601003.192',
        'codigo_antiguo' => 'OC. 2730-387-SE24',
        'marca' => 'HP',
        'modelo' => 'AIO 245 PROONE',
        'numero_serie' => '8CC40830ML',
        'descripcion' => 'Color negro',
    ]));

    $this->actingAs($this->usuario)
        ->get(route('items.ficha', $id))
        ->assertOk()
        ->assertSee('10601003.192')
        ->assertSee('OC. 2730-387-SE24')
        ->assertSee('HP')
        ->assertSee('AIO 245 PROONE')
        ->assertSee('8CC40830ML')
        ->assertSee('Color negro')
        ->assertSee('Regular');
});

test('lista los componentes del bien', function () {
    $kit = itemDeFicha($this->tipo, 'KIT-1');
    itemDeFicha($this->tipo, 'BAT-1', $kit);
    itemDeFicha($this->tipo, 'MON-1', $kit);

    $this->actingAs($this->usuario)
        ->get(route('items.ficha', $kit))
        ->assertOk()
        ->assertSee('BAT-1')
        ->assertSee('MON-1');
});

test('informa cuando el bien no tiene componentes', function () {
    $id = itemDeFicha($this->tipo, 'SOLO-1');

    $this->actingAs($this->usuario)
        ->get(route('items.ficha', $id))
        ->assertSee('no tiene componentes asociados');
});

test('muestra el historial de cambios del bien', function () {
    $id = itemDeFicha($this->tipo, 'KIT-1');

    app(ItemService::class)->actualizar($id, GuardarItemDTO::desdeArreglo([
        'tipo_id' => $this->tipo,
        'estado_conservacion' => 'malo',
        'numero_inventario' => 'KIT-1',
    ]));

    $this->actingAs($this->usuario)
        ->get(route('items.ficha', $id))
        ->assertOk()
        ->assertSee('Ítem creado')
        ->assertSee('Ítem actualizado');
});

test('muestra el historial de verificaciones del bien', function () {
    $id = itemDeFicha($this->tipo, 'KIT-1');

    $proceso = DB::table('procesos_verificacion')->insertGetId([
        'nombre' => 'Inventario 2026',
        'fecha_inicio' => now()->toDateString(),
        'estado' => 'abierto',
        'user_id' => $this->usuario->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('verificaciones')->insert([
        'proceso_verificacion_id' => $proceso,
        'item_id' => $id,
        'resultado' => 'encontrado',
        'metodo' => 'fisica',
        'estado_conservacion' => 'bueno',
        'user_id' => $this->usuario->id,
        'verificado_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->usuario)
        ->get(route('items.ficha', $id))
        ->assertOk()
        ->assertSee('Inventario 2026')
        ->assertSee('encontrado');
});

test('informa cuando el bien no tiene verificaciones', function () {
    $id = itemDeFicha($this->tipo, 'KIT-1');

    $this->actingAs($this->usuario)
        ->get(route('items.ficha', $id))
        ->assertSee('todavía no ha sido verificado');
});

test('la ficha de un ítem eliminado sigue accesible y lo señala', function () {
    $id = itemDeFicha($this->tipo, 'KIT-1');
    app(ItemService::class)->eliminar($id);

    $this->actingAs($this->usuario)
        ->get(route('items.ficha', $id))
        ->assertOk()
        ->assertSee('Eliminado');
});

test('devuelve 404 cuando el ítem no existe', function () {
    $this->actingAs($this->usuario)
        ->get(route('items.ficha', 9999))
        ->assertNotFound();
});
