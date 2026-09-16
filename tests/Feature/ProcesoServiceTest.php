<?php

use App\DTOs\Procesos\GuardarProcesoDTO;
use App\Models\User;
use App\Services\Procesos\ProcesoService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->servicio = app(ProcesoService::class);
    $this->usuario = User::factory()->create();
});

function datosProceso(array $sobrescribir = []): GuardarProcesoDTO
{
    return GuardarProcesoDTO::desdeArreglo(array_merge([
        'nombre' => 'Inventario 2026',
        'fecha_inicio' => now()->toDateString(),
        'user_id' => test()->usuario->id,
    ], $sobrescribir));
}

function itemVigente(string $numero = '11001001.76'): int
{
    $tipo = DB::table('tipos')->where('nombre', 'Escritorio')->value('id')
        ?? DB::table('tipos')->insertGetId([
            'nombre' => 'Escritorio',
            'requiere_serie' => false,
            'controla_vencimiento' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    return DB::table('items')->insertGetId([
        'numero_inventario' => $numero,
        'tipo_id' => $tipo,
        'estado_conservacion' => 'bueno',
        'situacion' => 'registrado_daf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function verificacionEn(int $procesoId, int $itemId, string $resultado = 'encontrado'): void
{
    DB::table('verificaciones')->insert([
        'proceso_verificacion_id' => $procesoId,
        'item_id' => $resultado === 'sin_registro' ? null : $itemId,
        'resultado' => $resultado,
        'metodo' => 'fisica',
        'user_id' => test()->usuario->id,
        'verificado_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('abre un proceso con su responsable', function () {
    $id = $this->servicio->crear(datosProceso());

    $proceso = $this->servicio->obtener($id);

    expect($proceso->nombre)->toBe('Inventario 2026')
        ->and($proceso->estado)->toBe('abierto')
        ->and($proceso->fechaCierre)->toBeNull()
        ->and($proceso->userId)->toBe($this->usuario->id)
        ->and($proceso->responsable)->toBe($this->usuario->name)
        ->and($proceso->estaAbierto())->toBeTrue();
});

test('impide abrir un segundo proceso mientras hay uno abierto', function () {
    $this->servicio->crear(datosProceso());

    $this->servicio->crear(datosProceso(['nombre' => 'Inventario 2027']));
})->throws(DomainException::class, 'Ya hay un proceso abierto. Ciérrelo antes de abrir otro.');

test('permite abrir otro proceso después de cerrar el anterior', function () {
    $primero = $this->servicio->crear(datosProceso());
    $this->servicio->cerrar($primero);

    $segundo = $this->servicio->crear(datosProceso(['nombre' => 'Inventario 2027']));

    expect($this->servicio->obtener($segundo)->estaAbierto())->toBeTrue()
        ->and($this->servicio->obtener($primero)->estaAbierto())->toBeFalse();
});

test('rechaza un nombre repetido', function () {
    $id = $this->servicio->crear(datosProceso());
    $this->servicio->cerrar($id);

    $this->servicio->crear(datosProceso());
})->throws(DomainException::class, 'Ya existe un proceso con ese nombre.');

test('rechaza un nombre vacío', function () {
    $this->servicio->crear(datosProceso(['nombre' => '   ']));
})->throws(DomainException::class, 'El nombre del proceso es obligatorio.');

test('rechaza una fecha de inicio inválida', function () {
    $this->servicio->crear(datosProceso(['fecha_inicio' => 'no es fecha']));
})->throws(DomainException::class, 'La fecha informada no es válida.');

test('cierra el proceso y registra la fecha', function () {
    $id = $this->servicio->crear(datosProceso());

    $this->servicio->cerrar($id, now()->addDays(5)->toDateString());

    $proceso = $this->servicio->obtener($id);

    expect($proceso->estado)->toBe('cerrado')
        ->and($proceso->fechaCierre)->toContain(now()->addDays(5)->toDateString());
});

test('usa la fecha de hoy cuando el cierre no la informa', function () {
    $id = $this->servicio->crear(datosProceso());

    $this->servicio->cerrar($id);

    expect($this->servicio->obtener($id)->fechaCierre)->toContain(now()->toDateString());
});

test('rechaza una fecha de cierre anterior a la de inicio', function () {
    $id = $this->servicio->crear(datosProceso(['fecha_inicio' => now()->toDateString()]));

    $this->servicio->cerrar($id, now()->subDay()->toDateString());
})->throws(DomainException::class, 'La fecha de cierre no puede ser anterior a la de inicio.');

test('rechaza cerrar un proceso ya cerrado', function () {
    $id = $this->servicio->crear(datosProceso());
    $this->servicio->cerrar($id);

    $this->servicio->cerrar($id);
})->throws(DomainException::class, 'El proceso ya está cerrado.');

test('reabre un proceso cerrado y limpia su fecha de cierre', function () {
    $id = $this->servicio->crear(datosProceso());
    $this->servicio->cerrar($id);

    $this->servicio->reabrir($id);

    $proceso = $this->servicio->obtener($id);

    expect($proceso->estado)->toBe('abierto')
        ->and($proceso->fechaCierre)->toBeNull();
});

test('impide reabrir cuando ya hay otro proceso abierto', function () {
    $primero = $this->servicio->crear(datosProceso());
    $this->servicio->cerrar($primero);
    $this->servicio->crear(datosProceso(['nombre' => 'Inventario 2027']));

    $this->servicio->reabrir($primero);
})->throws(DomainException::class, 'Ya hay un proceso abierto. Ciérrelo antes de reabrir este.');

test('rechaza reabrir un proceso que ya está abierto', function () {
    $id = $this->servicio->crear(datosProceso());

    $this->servicio->reabrir($id);
})->throws(DomainException::class, 'El proceso ya está abierto.');

test('impide modificar un proceso cerrado', function () {
    $id = $this->servicio->crear(datosProceso());
    $this->servicio->cerrar($id);

    $this->servicio->actualizar($id, datosProceso(['nombre' => 'Otro nombre']));
})->throws(DomainException::class, 'No se puede modificar un proceso cerrado.');

test('actualiza un proceso abierto', function () {
    $id = $this->servicio->crear(datosProceso());

    $this->servicio->actualizar($id, datosProceso(['nombre' => 'Inventario anual 2026']));

    expect($this->servicio->obtener($id)->nombre)->toBe('Inventario anual 2026');
});

test('informa el avance sobre el total de ítems vigentes', function () {
    itemVigente('A-1');
    itemVigente('A-2');
    itemVigente('A-3');
    itemVigente('A-4');

    $id = $this->servicio->crear(datosProceso());

    verificacionEn($id, DB::table('items')->where('numero_inventario', 'A-1')->value('id'));

    $proceso = $this->servicio->obtener($id);

    expect($proceso->totalItems)->toBe(4)
        ->and($proceso->verificados)->toBe(1)
        ->and($proceso->pendientes())->toBe(3)
        ->and($proceso->porcentajeAvance())->toBe(25);
});

test('cuenta aparte las verificaciones sin registro', function () {
    $item = itemVigente('A-1');
    $id = $this->servicio->crear(datosProceso());

    verificacionEn($id, $item);
    verificacionEn($id, $item, 'sin_registro');

    $proceso = $this->servicio->obtener($id);

    expect($proceso->verificados)->toBe(2)
        ->and($proceso->sinRegistro)->toBe(1);
});

test('el avance es cero cuando no hay ítems registrados', function () {
    $id = $this->servicio->crear(datosProceso());

    expect($this->servicio->obtener($id)->porcentajeAvance())->toBe(0);
});

test('expone el proceso abierto del sistema', function () {
    expect($this->servicio->abierto())->toBeNull();

    $id = $this->servicio->crear(datosProceso());

    expect($this->servicio->abierto()->id)->toBe($id);

    $this->servicio->cerrar($id);

    expect($this->servicio->abierto())->toBeNull();
});

test('elimina un proceso sin verificaciones', function () {
    $id = $this->servicio->crear(datosProceso());

    $this->servicio->eliminar($id);

    expect($this->servicio->obtener($id))->toBeNull();
});

test('impide eliminar un proceso con verificaciones', function () {
    $item = itemVigente('A-1');
    $id = $this->servicio->crear(datosProceso());
    verificacionEn($id, $item);

    $this->servicio->eliminar($id);
})->throws(DomainException::class, 'No se puede eliminar el proceso porque tiene 1 verificación(es) registrada(s).');

test('el listado filtra por estado y por texto', function () {
    $primero = $this->servicio->crear(datosProceso());
    $this->servicio->cerrar($primero);
    $this->servicio->crear(datosProceso(['nombre' => 'Inventario 2027']));

    expect($this->servicio->paginar(['estado' => 'abierto'])->total())->toBe(1)
        ->and($this->servicio->paginar(['estado' => 'cerrado'])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => '2027'])->total())->toBe(1)
        ->and($this->servicio->paginar()->total())->toBe(2);
});

test('registra en la bitácora la apertura, el cierre y la reapertura', function () {
    $id = $this->servicio->crear(datosProceso());
    $this->servicio->cerrar($id);
    $this->servicio->reabrir($id);

    $eventos = DB::table('activity_log')
        ->where('subject_type', 'procesos_verificacion')
        ->where('subject_id', $id)
        ->pluck('event')
        ->all();

    expect($eventos)->toEqualCanonicalizing(['created', 'closed', 'reopened']);
});
