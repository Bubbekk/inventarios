<?php

use App\DTOs\Tipos\GuardarTipoDTO;
use App\Services\Tipos\TipoService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->servicio = app(TipoService::class);
});

function datosTipo(array $sobrescribir = []): GuardarTipoDTO
{
    return GuardarTipoDTO::desdeArreglo(array_merge([
        'nombre' => 'Computador',
        'requiere_serie' => true,
        'controla_vencimiento' => false,
    ], $sobrescribir));
}

function crearItemDe(int $tipoId): int
{
    $ubicacion = DB::table('ubicaciones')->insertGetId([
        'nombre' => 'Bodega central',
        'tipo' => 'bodega',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('items')->insertGetId([
        'numero_inventario' => '10601003.192',
        'tipo_id' => $tipoId,
        'ubicacion_id' => $ubicacion,
        'estado_conservacion' => 'bueno',
        'situacion' => 'registrado_daf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('crea un tipo con sus indicadores', function () {
    $id = $this->servicio->crear(datosTipo(['controla_vencimiento' => true]));

    $tipo = $this->servicio->obtener($id);

    expect($tipo->nombre)->toBe('Computador')
        ->and($tipo->requiereSerie)->toBeTrue()
        ->and($tipo->controlaVencimiento)->toBeTrue()
        ->and($tipo->itemsAsociados)->toBe(0);
});

test('recorta los espacios del nombre', function () {
    $id = $this->servicio->crear(datosTipo(['nombre' => '   Impresora   ']));

    expect($this->servicio->obtener($id)->nombre)->toBe('Impresora');
});

test('rechaza un nombre repetido', function () {
    $this->servicio->crear(datosTipo());

    $this->servicio->crear(datosTipo(['requiere_serie' => false]));
})->throws(DomainException::class, 'Ya existe un tipo con ese nombre.');

test('rechaza un nombre vacío', function () {
    $this->servicio->crear(datosTipo(['nombre' => '   ']));
})->throws(DomainException::class, 'El nombre del tipo es obligatorio.');

test('actualiza el tipo y conserva su nombre sin considerarlo repetido', function () {
    $id = $this->servicio->crear(datosTipo());

    $this->servicio->actualizar($id, datosTipo(['controla_vencimiento' => true]));

    $tipo = $this->servicio->obtener($id);

    expect($tipo->nombre)->toBe('Computador')
        ->and($tipo->controlaVencimiento)->toBeTrue();
});

test('rechaza la actualización cuando el nombre pertenece a otro tipo', function () {
    $this->servicio->crear(datosTipo());
    $otro = $this->servicio->crear(datosTipo(['nombre' => 'Impresora']));

    $this->servicio->actualizar($otro, datosTipo());
})->throws(DomainException::class, 'Ya existe un tipo con ese nombre.');

test('rechaza actualizar un tipo inexistente', function () {
    $this->servicio->actualizar(999, datosTipo());
})->throws(DomainException::class, 'El tipo no existe.');

test('elimina un tipo sin ítems asociados', function () {
    $id = $this->servicio->crear(datosTipo());

    $this->servicio->eliminar($id);

    expect($this->servicio->obtener($id))->toBeNull();
});

test('impide eliminar un tipo con ítems asociados', function () {
    $id = $this->servicio->crear(datosTipo());
    crearItemDe($id);

    $this->servicio->eliminar($id);
})->throws(DomainException::class, 'No se puede eliminar el tipo porque tiene 1 ítem(s) asociado(s).');

test('el listado informa cuántos ítems tiene cada tipo', function () {
    $id = $this->servicio->crear(datosTipo());
    crearItemDe($id);

    $fila = $this->servicio->paginar()->items()[0];

    expect((int) $fila->items_asociados)->toBe(1);
});

test('el listado filtra por texto y por indicadores', function () {
    $this->servicio->crear(datosTipo());
    $this->servicio->crear(datosTipo(['nombre' => 'Extintor', 'requiere_serie' => false, 'controla_vencimiento' => true]));
    $this->servicio->crear(datosTipo(['nombre' => 'Escritorio', 'requiere_serie' => false]));

    expect($this->servicio->paginar(['busqueda' => 'ext'])->total())->toBe(1)
        ->and($this->servicio->paginar(['requiere_serie' => true])->total())->toBe(1)
        ->and($this->servicio->paginar(['controla_vencimiento' => true])->total())->toBe(1)
        ->and($this->servicio->paginar()->total())->toBe(3);
});

test('el listado ordena por nombre en ambos sentidos', function () {
    $this->servicio->crear(datosTipo(['nombre' => 'Zapata']));
    $this->servicio->crear(datosTipo(['nombre' => 'Antena']));

    expect($this->servicio->paginar(ordenarPor: 'nombre', direccion: 'asc')->items()[0]->nombre)->toBe('Antena')
        ->and($this->servicio->paginar(ordenarPor: 'nombre', direccion: 'desc')->items()[0]->nombre)->toBe('Zapata');
});

test('el catálogo completo llega ordenado por nombre', function () {
    $this->servicio->crear(datosTipo(['nombre' => 'Zapata']));
    $this->servicio->crear(datosTipo(['nombre' => 'Antena']));

    expect(array_map(static fn ($tipo): string => $tipo->nombre, $this->servicio->listar()))
        ->toBe(['Antena', 'Zapata']);
});

test('registra en la bitácora la creación, la actualización y la eliminación', function () {
    $id = $this->servicio->crear(datosTipo());
    $this->servicio->actualizar($id, datosTipo(['controla_vencimiento' => true]));
    $this->servicio->eliminar($id);

    $eventos = DB::table('activity_log')
        ->where('subject_type', 'tipos')
        ->where('subject_id', $id)
        ->pluck('event')
        ->all();

    expect($eventos)->toEqualCanonicalizing(['created', 'updated', 'deleted']);
});
