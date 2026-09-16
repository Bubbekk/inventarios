<?php

use App\DTOs\Auditoria\RegistroActividadDTO;
use App\Models\User;
use App\Services\Auditoria\RegistroActividadService;
use Illuminate\Support\Facades\DB;

test('registra una creación con su causante', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    app(RegistroActividadService::class)->registrar(new RegistroActividadDTO(
        modulo: 'items',
        evento: 'created',
        descripcion: 'Ítem creado',
        registroId: 7,
        valoresNuevos: ['marca' => 'HP'],
    ));

    $registro = DB::table('activity_log')->first();

    expect($registro->subject_type)->toBe('items')
        ->and($registro->subject_id)->toBe(7)
        ->and($registro->event)->toBe('created')
        ->and($registro->causer_id)->toBe($usuario->id)
        ->and($registro->causer_type)->toBe(User::class)
        ->and(json_decode($registro->attribute_changes, true))->toBe(['attributes' => ['marca' => 'HP']]);
});

test('registra solo los atributos modificados en una actualización', function () {
    app(RegistroActividadService::class)->registrarActualizacion(
        modulo: 'items',
        registroId: 3,
        anteriores: ['marca' => 'HP', 'modelo' => 'X1'],
        nuevos: ['marca' => 'Dell', 'modelo' => 'X1'],
        descripcion: 'Ítem actualizado',
    );

    $cambios = json_decode(DB::table('activity_log')->value('attribute_changes'), true);

    expect($cambios)->toBe([
        'old' => ['marca' => 'HP'],
        'attributes' => ['marca' => 'Dell'],
    ]);
});

test('no registra nada cuando la actualización no modifica valores', function () {
    app(RegistroActividadService::class)->registrarActualizacion(
        modulo: 'items',
        registroId: 3,
        anteriores: ['marca' => 'HP'],
        nuevos: ['marca' => 'HP'],
        descripcion: 'Ítem actualizado',
    );

    expect(DB::table('activity_log')->count())->toBe(0);
});

test('no registra cuando la bitácora está deshabilitada', function () {
    config()->set('activitylog.enabled', false);

    app(RegistroActividadService::class)->registrar(new RegistroActividadDTO(
        modulo: 'tipos',
        evento: 'deleted',
        descripcion: 'Tipo eliminado',
        registroId: 1,
    ));

    expect(DB::table('activity_log')->count())->toBe(0);
});

test('el historial de un registro devuelve sus cambios del más reciente al más antiguo', function () {
    $servicio = app(RegistroActividadService::class);

    $servicio->registrar(new RegistroActividadDTO('items', 'created', 'Creado', 5));
    $servicio->registrar(new RegistroActividadDTO('items', 'updated', 'Actualizado', 5));
    $servicio->registrar(new RegistroActividadDTO('items', 'created', 'Otro ítem', 9));

    $historial = $servicio->historialDe('items', 5);

    expect($historial)->toHaveCount(2)
        ->and($historial[0]->event)->toBe('updated');
});

test('la bitácora paginada expone el nombre del causante', function () {
    $usuario = User::factory()->create(['name' => 'Ana Soto']);
    $this->actingAs($usuario);

    app(RegistroActividadService::class)->registrar(new RegistroActividadDTO(
        modulo: 'ubicaciones',
        evento: 'created',
        descripcion: 'Ubicación creada',
        registroId: 1,
    ));

    $pagina = app(RegistroActividadService::class)->paginar();

    expect($pagina->total())->toBe(1)
        ->and($pagina->items()[0]->causante)->toBe('Ana Soto');
});
