<?php

use App\DTOs\Items\GuardarItemDTO;
use App\Services\Items\ItemService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->servicio = app(ItemService::class);

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

    $this->tipoConVencimiento = DB::table('tipos')->insertGetId([
        'nombre' => 'Extintor',
        'requiere_serie' => false,
        'controla_vencimiento' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function datosItem(array $sobrescribir = []): GuardarItemDTO
{
    return GuardarItemDTO::desdeArreglo(array_merge([
        'tipo_id' => test()->tipoSimple,
        'estado_conservacion' => 'bueno',
        'numero_inventario' => '11001001.76',
    ], $sobrescribir));
}

test('crea un ítem y deriva la situación registrado_daf cuando tiene número', function () {
    $id = $this->servicio->crear(datosItem());

    $item = $this->servicio->obtener($id);

    expect($item->numeroInventario)->toBe('11001001.76')
        ->and($item->situacion)->toBe('registrado_daf')
        ->and($item->tipoNombre)->toBe('Escritorio');
});

test('deriva sin_registro_daf cuando el ítem no tiene número de inventario', function () {
    $id = $this->servicio->crear(datosItem(['numero_inventario' => null, 'codigo_antiguo' => '4266']));

    expect($this->servicio->obtener($id)->situacion)->toBe('sin_registro_daf');
});

test('respeta la situación informada explícitamente', function () {
    $id = $this->servicio->crear(datosItem(['situacion' => 'solicitud_baja']));

    expect($this->servicio->obtener($id)->situacion)->toBe('solicitud_baja');
});

test('trata el número de inventario como texto y distingue 10601018.1 de 10601018.10', function () {
    $primero = $this->servicio->crear(datosItem(['numero_inventario' => '10601018.1']));
    $segundo = $this->servicio->crear(datosItem(['numero_inventario' => '10601018.10']));

    expect($this->servicio->obtener($primero)->numeroInventario)->toBe('10601018.1')
        ->and($this->servicio->obtener($segundo)->numeroInventario)->toBe('10601018.10');
});

test('rechaza un número de inventario repetido', function () {
    $this->servicio->crear(datosItem());

    $this->servicio->crear(datosItem());
})->throws(DomainException::class, 'Ya existe un ítem con ese número de inventario.');

test('admite varios ítems sin número de inventario', function () {
    $this->servicio->crear(datosItem(['numero_inventario' => null]));
    $this->servicio->crear(datosItem(['numero_inventario' => null]));

    expect($this->servicio->paginar()->total())->toBe(2);
});

test('exige número de serie cuando el tipo lo requiere', function () {
    $this->servicio->crear(datosItem(['tipo_id' => $this->tipoConSerie]));
})->throws(DomainException::class, 'El tipo Computador exige informar el número de serie.');

test('acepta el ítem cuando el número de serie se informa', function () {
    $id = $this->servicio->crear(datosItem([
        'tipo_id' => $this->tipoConSerie,
        'numero_serie' => '8CC40830ML',
    ]));

    expect($this->servicio->obtener($id)->numeroSerie)->toBe('8CC40830ML');
});

test('exige fecha de vencimiento cuando el tipo la controla', function () {
    $this->servicio->crear(datosItem(['tipo_id' => $this->tipoConVencimiento]));
})->throws(DomainException::class, 'El tipo Extintor exige informar la fecha de vencimiento.');

test('rechaza un tipo inexistente', function () {
    $this->servicio->crear(datosItem(['tipo_id' => 999]));
})->throws(DomainException::class, 'El tipo indicado no existe.');

test('rechaza un estado de conservación fuera del enum', function () {
    $this->servicio->crear(datosItem(['estado_conservacion' => 'pesimo']));
})->throws(DomainException::class, 'El estado de conservación indicado no es válido.');

test('rechaza una situación fuera del enum', function () {
    $this->servicio->crear(datosItem(['situacion' => 'extraviado']));
})->throws(DomainException::class, 'La situación indicada no es válida.');

test('asigna un ítem padre y lo lista como componente', function () {
    $kit = $this->servicio->crear(datosItem(['numero_inventario' => 'KIT-1']));
    $bateria = $this->servicio->crear(datosItem([
        'numero_inventario' => 'BAT-1',
        'item_padre_id' => $kit,
    ]));

    $componentes = $this->servicio->componentesDe($kit);

    expect($componentes)->toHaveCount(1)
        ->and($componentes[0]->id)->toBe($bateria)
        ->and($this->servicio->obtener($bateria)->itemPadreNumero)->toBe('KIT-1');
});

test('impide que un ítem sea componente de sí mismo', function () {
    $id = $this->servicio->crear(datosItem());

    $this->servicio->actualizar($id, datosItem(['item_padre_id' => $id]));
})->throws(DomainException::class, 'Un ítem no puede ser componente de sí mismo.');

test('impide una referencia circular entre ítems', function () {
    $kit = $this->servicio->crear(datosItem(['numero_inventario' => 'KIT-1']));
    $bateria = $this->servicio->crear(datosItem(['numero_inventario' => 'BAT-1', 'item_padre_id' => $kit]));

    $this->servicio->actualizar($kit, datosItem([
        'numero_inventario' => 'KIT-1',
        'item_padre_id' => $bateria,
    ]));
})->throws(DomainException::class, 'El ítem padre indicado es componente de este ítem.');

test('impide una referencia circular en tres niveles', function () {
    $abuelo = $this->servicio->crear(datosItem(['numero_inventario' => 'A']));
    $padre = $this->servicio->crear(datosItem(['numero_inventario' => 'B', 'item_padre_id' => $abuelo]));
    $nieto = $this->servicio->crear(datosItem(['numero_inventario' => 'C', 'item_padre_id' => $padre]));

    $this->servicio->actualizar($abuelo, datosItem([
        'numero_inventario' => 'A',
        'item_padre_id' => $nieto,
    ]));
})->throws(DomainException::class, 'El ítem padre indicado es componente de este ítem.');

test('rechaza un ítem padre inexistente', function () {
    $this->servicio->crear(datosItem(['item_padre_id' => 999]));
})->throws(DomainException::class, 'El ítem padre indicado no existe.');

test('los candidatos a padre excluyen al propio ítem y a sus descendientes', function () {
    $kit = $this->servicio->crear(datosItem(['numero_inventario' => 'KIT-1']));
    $bateria = $this->servicio->crear(datosItem(['numero_inventario' => 'BAT-1', 'item_padre_id' => $kit]));
    $suelto = $this->servicio->crear(datosItem(['numero_inventario' => 'OTRO']));

    $candidatos = array_map(static fn ($fila): int => (int) $fila->id, $this->servicio->candidatosAPadre($kit));

    expect($candidatos)->toBe([$suelto])
        ->and($candidatos)->not->toContain($kit)
        ->and($candidatos)->not->toContain($bateria);
});

test('elimina lógicamente y lo excluye del listado vigente', function () {
    $id = $this->servicio->crear(datosItem());

    $this->servicio->eliminar($id);

    expect($this->servicio->paginar()->total())->toBe(0)
        ->and($this->servicio->paginarEliminados()->total())->toBe(1)
        ->and($this->servicio->obtener($id))->toBeNull()
        ->and($this->servicio->obtener($id, incluirEliminados: true)->eliminado)->toBeTrue()
        ->and(DB::table('items')->where('id', $id)->exists())->toBeTrue();
});

test('restaura un ítem eliminado', function () {
    $id = $this->servicio->crear(datosItem());
    $this->servicio->eliminar($id);

    $this->servicio->restaurar($id);

    expect($this->servicio->paginar()->total())->toBe(1)
        ->and($this->servicio->obtener($id)->eliminado)->toBeFalse();
});

test('impide eliminar un ítem con componentes asociados', function () {
    $kit = $this->servicio->crear(datosItem(['numero_inventario' => 'KIT-1']));
    $this->servicio->crear(datosItem(['numero_inventario' => 'BAT-1', 'item_padre_id' => $kit]));

    $this->servicio->eliminar($kit);
})->throws(DomainException::class, 'No se puede eliminar el ítem porque tiene 1 componente(s) asociado(s).');

test('el listado busca por número, código antiguo, serie, marca y modelo', function () {
    $this->servicio->crear(datosItem(['marca' => 'HP', 'modelo' => 'AIO 245']));
    $this->servicio->crear(datosItem([
        'numero_inventario' => null,
        'codigo_antiguo' => 'C0070',
        'tipo_id' => $this->tipoConSerie,
        'numero_serie' => 'U63980K6N509760',
        'marca' => 'BROTHER',
    ]));

    expect($this->servicio->paginar(['busqueda' => '11001001'])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => 'C0070'])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => 'U63980'])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => 'HP'])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => 'AIO'])->total())->toBe(1);
});

test('el listado filtra por tipo, estado y situación', function () {
    $this->servicio->crear(datosItem());
    $this->servicio->crear(datosItem([
        'numero_inventario' => null,
        'tipo_id' => $this->tipoConSerie,
        'numero_serie' => 'ABC123',
        'estado_conservacion' => 'malo',
    ]));

    expect($this->servicio->paginar(['tipo_id' => $this->tipoConSerie])->total())->toBe(1)
        ->and($this->servicio->paginar(['estado_conservacion' => 'malo'])->total())->toBe(1)
        ->and($this->servicio->paginar(['situacion' => 'sin_registro_daf'])->total())->toBe(1);
});

test('informa los ítems vencidos y los próximos a vencer', function () {
    $this->servicio->crear(datosItem([
        'numero_inventario' => 'VENC-1',
        'tipo_id' => $this->tipoConVencimiento,
        'fecha_vencimiento' => now()->subDay()->toDateString(),
    ]));

    $this->servicio->crear(datosItem([
        'numero_inventario' => 'VENC-2',
        'tipo_id' => $this->tipoConVencimiento,
        'fecha_vencimiento' => now()->addDays(10)->toDateString(),
    ]));

    $this->servicio->crear(datosItem([
        'numero_inventario' => 'VENC-3',
        'tipo_id' => $this->tipoConVencimiento,
        'fecha_vencimiento' => now()->addYear()->toDateString(),
    ]));

    expect($this->servicio->proximosAVencer())->toHaveCount(2)
        ->and($this->servicio->paginar(['vencimiento' => 'vencidos'])->total())->toBe(1)
        ->and($this->servicio->paginar(['vencimiento' => 'por_vencer'])->total())->toBe(1);
});

test('registra en la bitácora la creación, la actualización, la eliminación y la restauración', function () {
    $id = $this->servicio->crear(datosItem());
    $this->servicio->actualizar($id, datosItem(['estado_conservacion' => 'regular']));
    $this->servicio->eliminar($id);
    $this->servicio->restaurar($id);

    $eventos = DB::table('activity_log')
        ->where('subject_type', 'items')
        ->where('subject_id', $id)
        ->pluck('event')
        ->all();

    expect($eventos)->toEqualCanonicalizing(['created', 'updated', 'deleted', 'restored']);
});
