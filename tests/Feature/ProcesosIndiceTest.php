<?php

use App\DTOs\Procesos\GuardarProcesoDTO;
use App\Models\User;
use App\Services\Procesos\ProcesoService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
});

function cuentaConRolProcesos(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function crearProceso(string $nombre = 'Inventario 2026'): int
{
    return app(ProcesoService::class)->crear(GuardarProcesoDTO::desdeArreglo([
        'nombre' => $nombre,
        'fecha_inicio' => now()->toDateString(),
        'user_id' => User::query()->value('id'),
    ]));
}

test('la ruta exige autenticación', function () {
    $this->get(route('procesos.indice'))->assertRedirect(route('login'));
});

test('el administrador accede al módulo', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'))
        ->get(route('procesos.indice'))
        ->assertOk();
});

test('el verificador accede en modo lectura', function () {
    $this->actingAs(cuentaConRolProcesos('verificador'))
        ->get(route('procesos.indice'))
        ->assertOk();

    // El listado es diferido: el contenido llega en la segunda petición.
    Livewire::withoutLazyLoading()->test('pages::procesos.indice')
        ->assertDontSee('Nuevo proceso')
        ->assertDontSee('Cerrar proceso');
});

test('abre un proceso desde el componente', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));

    Livewire::test('pages::procesos.indice')
        ->call('crear')
        ->set('nombre', 'Inventario 2026')
        ->set('fechaInicio', now()->toDateString())
        ->call('guardar')
        ->assertHasNoErrors();

    $proceso = DB::table('procesos_verificacion')->where('nombre', 'Inventario 2026')->first();

    expect($proceso)->not->toBeNull()
        ->and($proceso->estado)->toBe('abierto');
});

test('informa que ya hay un proceso abierto', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));
    crearProceso();

    Livewire::test('pages::procesos.indice')
        ->call('crear')
        ->set('nombre', 'Inventario 2027')
        ->set('fechaInicio', now()->toDateString())
        ->call('guardar')
        ->assertHasErrors('nombre');
});

test('exige nombre y fecha de inicio', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));

    Livewire::test('pages::procesos.indice')
        ->call('crear')
        ->set('fechaInicio', '')
        ->call('guardar')
        ->assertHasErrors(['nombre' => 'required', 'fechaInicio' => 'required']);
});

test('cierra un proceso con su fecha', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));
    $id = crearProceso();

    Livewire::test('pages::procesos.indice')
        ->call('confirmarCierre', $id)
        ->assertSet('procesoPorCerrar', $id)
        ->set('fechaCierre', now()->addDays(3)->toDateString())
        ->call('cerrar')
        ->assertHasNoErrors();

    $proceso = DB::table('procesos_verificacion')->find($id);

    expect($proceso->estado)->toBe('cerrado')
        ->and($proceso->fecha_cierre)->toContain(now()->addDays(3)->toDateString());
});

test('rechaza una fecha de cierre anterior al inicio', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));
    $id = crearProceso();

    Livewire::test('pages::procesos.indice')
        ->call('confirmarCierre', $id)
        ->set('fechaCierre', now()->subDays(2)->toDateString())
        ->call('cerrar')
        ->assertHasErrors('fechaCierre');

    expect(DB::table('procesos_verificacion')->find($id)->estado)->toBe('abierto');
});

test('el verificador no puede cerrar un proceso', function () {
    $this->actingAs(cuentaConRolProcesos('verificador'));
    $id = crearProceso();

    Livewire::test('pages::procesos.indice')->call('confirmarCierre', $id);
})->throws(HttpException::class);

test('solo el administrador reabre un proceso', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));
    $id = crearProceso();
    app(ProcesoService::class)->cerrar($id);

    Livewire::test('pages::procesos.indice')->call('reabrir', $id);

    expect(DB::table('procesos_verificacion')->find($id)->estado)->toBe('abierto');
});

test('el verificador no puede reabrir un proceso', function () {
    $administrador = cuentaConRolProcesos('administrador');
    $this->actingAs($administrador);
    $id = crearProceso();
    app(ProcesoService::class)->cerrar($id);

    $this->actingAs(cuentaConRolProcesos('verificador'));

    Livewire::test('pages::procesos.indice')->call('reabrir', $id);
})->throws(HttpException::class);

test('muestra el avance del proceso abierto', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));

    $tipo = DB::table('tipos')->insertGetId([
        'nombre' => 'Escritorio',
        'requiere_serie' => false,
        'controla_vencimiento' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (['A-1', 'A-2'] as $numero) {
        DB::table('items')->insert([
            'numero_inventario' => $numero,
            'tipo_id' => $tipo,
            'estado_conservacion' => 'bueno',
            'situacion' => 'registrado_daf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $id = crearProceso();

    DB::table('verificaciones')->insert([
        'proceso_verificacion_id' => $id,
        'item_id' => DB::table('items')->where('numero_inventario', 'A-1')->value('id'),
        'resultado' => 'encontrado',
        'metodo' => 'fisica',
        'user_id' => User::query()->value('id'),
        'verificado_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::withoutLazyLoading()->test('pages::procesos.indice')
        ->assertSee('Proceso abierto')
        ->assertSee('50%');
});

test('elimina un proceso sin verificaciones', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));
    $id = crearProceso();

    Livewire::test('pages::procesos.indice')
        ->call('confirmarEliminacion', $id)
        ->assertSet('procesoPorEliminar', $id)
        ->call('eliminar');

    expect(DB::table('procesos_verificacion')->where('id', $id)->exists())->toBeFalse();
});

test('el listado filtra por estado y por texto', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));
    $primero = crearProceso();
    app(ProcesoService::class)->cerrar($primero);
    crearProceso('Inventario 2027');

    $componente = Livewire::test('pages::procesos.indice');

    expect($componente->set('filtroEstado', 'abierto')->instance()->procesos->total())->toBe(1);
    expect($componente->set('filtroEstado', '')->set('busqueda', '2027')->instance()->procesos->total())->toBe(1);
    expect($componente->set('busqueda', '')->instance()->procesos->total())->toBe(2);
});

test('ordena alternando el sentido sobre la misma columna', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'));

    Livewire::test('pages::procesos.indice')
        ->assertSet('ordenarPor', 'fecha_inicio')
        ->assertSet('direccion', 'desc')
        ->call('ordenar', 'fecha_inicio')
        ->assertSet('direccion', 'asc')
        ->call('ordenar', 'nombre')
        ->assertSet('ordenarPor', 'nombre')
        ->assertSet('direccion', 'asc');
});

test('el menú incluye procesos dentro de la sección Verificación', function () {
    $this->actingAs(cuentaConRolProcesos('administrador'))
        ->get(route('procesos.indice'))
        ->assertSee('Verificación')
        ->assertSee('Procesos');
});
