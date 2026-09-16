<?php

use App\DTOs\Ubicaciones\GuardarUbicacionDTO;
use App\Models\User;
use App\Services\Ubicaciones\UbicacionService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->servicio = app(UbicacionService::class);
});

function datosUbicacion(array $sobrescribir = []): GuardarUbicacionDTO
{
    return GuardarUbicacionDTO::desdeArreglo(array_merge([
        'nombre' => 'Central',
        'tipo' => 'oficina',
        'direccion' => 'Av. Principal 123',
    ], $sobrescribir));
}

function tipoDePrueba(): int
{
    return DB::table('tipos')->insertGetId([
        'nombre' => 'Computador',
        'requiere_serie' => true,
        'controla_vencimiento' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function itemEn(int $ubicacionId): int
{
    return DB::table('items')->insertGetId([
        'numero_inventario' => '10601003.192',
        'tipo_id' => tipoDePrueba(),
        'ubicacion_id' => $ubicacionId,
        'estado_conservacion' => 'bueno',
        'situacion' => 'registrado_daf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('crea una ubicación con su tipo y dirección', function () {
    $id = $this->servicio->crear(datosUbicacion());

    $ubicacion = $this->servicio->obtener($id);

    expect($ubicacion->nombre)->toBe('Central')
        ->and($ubicacion->tipo)->toBe('oficina')
        ->and($ubicacion->direccion)->toBe('Av. Principal 123')
        ->and($ubicacion->itemsAsociados)->toBe(0);
});

test('admite los cuatro tipos del enum', function () {
    foreach (array_keys(UbicacionService::TIPOS) as $indice => $tipo) {
        $id = $this->servicio->crear(datosUbicacion([
            'nombre' => 'Ubicación '.$indice,
            'tipo' => $tipo,
        ]));

        expect($this->servicio->obtener($id)->tipo)->toBe($tipo);
    }
});

test('rechaza un tipo fuera del enum', function () {
    $this->servicio->crear(datosUbicacion(['tipo' => 'galpon']));
})->throws(DomainException::class, 'El tipo de ubicación indicado no es válido.');

test('deja la dirección en nulo cuando llega vacía', function () {
    $id = $this->servicio->crear(datosUbicacion(['direccion' => '   ']));

    expect($this->servicio->obtener($id)->direccion)->toBeNull();
});

test('rechaza un nombre repetido', function () {
    $this->servicio->crear(datosUbicacion());

    $this->servicio->crear(datosUbicacion(['tipo' => 'bodega']));
})->throws(DomainException::class, 'Ya existe una ubicación con ese nombre.');

test('rechaza un nombre vacío', function () {
    $this->servicio->crear(datosUbicacion(['nombre' => '   ']));
})->throws(DomainException::class, 'El nombre de la ubicación es obligatorio.');

test('actualiza la ubicación y conserva su nombre sin considerarlo repetido', function () {
    $id = $this->servicio->crear(datosUbicacion());

    $this->servicio->actualizar($id, datosUbicacion(['tipo' => 'bodega', 'direccion' => 'Subterráneo']));

    $ubicacion = $this->servicio->obtener($id);

    expect($ubicacion->tipo)->toBe('bodega')
        ->and($ubicacion->direccion)->toBe('Subterráneo');
});

test('rechaza la actualización cuando el nombre pertenece a otra ubicación', function () {
    $this->servicio->crear(datosUbicacion());
    $otra = $this->servicio->crear(datosUbicacion(['nombre' => 'Bodega norte', 'tipo' => 'bodega']));

    $this->servicio->actualizar($otra, datosUbicacion());
})->throws(DomainException::class, 'Ya existe una ubicación con ese nombre.');

test('rechaza actualizar una ubicación inexistente', function () {
    $this->servicio->actualizar(999, datosUbicacion());
})->throws(DomainException::class, 'La ubicación no existe.');

test('elimina una ubicación sin ítems ni verificaciones', function () {
    $id = $this->servicio->crear(datosUbicacion());

    $this->servicio->eliminar($id);

    expect($this->servicio->obtener($id))->toBeNull();
});

test('impide eliminar una ubicación con ítems asociados', function () {
    $id = $this->servicio->crear(datosUbicacion());
    itemEn($id);

    $this->servicio->eliminar($id);
})->throws(DomainException::class, 'No se puede eliminar la ubicación porque tiene 1 ítem(s) asociado(s).');

test('impide eliminar una ubicación registrada en una verificación', function () {
    $id = $this->servicio->crear(datosUbicacion());

    $usuario = User::factory()->create();

    $proceso = DB::table('procesos_verificacion')->insertGetId([
        'nombre' => 'Inventario 2026',
        'fecha_inicio' => now()->toDateString(),
        'estado' => 'abierto',
        'user_id' => $usuario->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('verificaciones')->insert([
        'proceso_verificacion_id' => $proceso,
        'ubicacion_id' => $id,
        'resultado' => 'sin_registro',
        'metodo' => 'fisica',
        'user_id' => $usuario->id,
        'verificado_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->servicio->eliminar($id);
})->throws(DomainException::class, 'No se puede eliminar la ubicación porque está registrada en 1 verificación(es).');

test('el listado informa cuántos ítems tiene cada ubicación', function () {
    $id = $this->servicio->crear(datosUbicacion());
    itemEn($id);

    $fila = $this->servicio->paginar()->items()[0];

    expect((int) $fila->items_asociados)->toBe(1);
});

test('el listado filtra por tipo y por texto de nombre o dirección', function () {
    $this->servicio->crear(datosUbicacion());
    $this->servicio->crear(datosUbicacion(['nombre' => 'Bodega norte', 'tipo' => 'bodega', 'direccion' => 'Calle Sur 45']));
    $this->servicio->crear(datosUbicacion(['nombre' => 'Móvil 1', 'tipo' => 'vehiculo', 'direccion' => null]));

    expect($this->servicio->paginar(['tipo' => 'bodega'])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => 'norte'])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => 'Calle Sur'])->total())->toBe(1)
        ->and($this->servicio->paginar()->total())->toBe(3);
});

test('el listado ordena por nombre en ambos sentidos', function () {
    $this->servicio->crear(datosUbicacion(['nombre' => 'Zona sur']));
    $this->servicio->crear(datosUbicacion(['nombre' => 'Acceso norte']));

    expect($this->servicio->paginar(ordenarPor: 'nombre', direccion: 'asc')->items()[0]->nombre)->toBe('Acceso norte')
        ->and($this->servicio->paginar(ordenarPor: 'nombre', direccion: 'desc')->items()[0]->nombre)->toBe('Zona sur');
});

test('el catálogo completo llega ordenado por nombre', function () {
    $this->servicio->crear(datosUbicacion(['nombre' => 'Zona sur']));
    $this->servicio->crear(datosUbicacion(['nombre' => 'Acceso norte']));

    expect(array_map(static fn ($ubicacion): string => $ubicacion->nombre, $this->servicio->listar()))
        ->toBe(['Acceso norte', 'Zona sur']);
});

test('expone la etiqueta de interfaz de cada tipo', function () {
    expect(UbicacionService::etiquetaTipo('via_publica'))->toBe('Vía pública')
        ->and(UbicacionService::etiquetaTipo('vehiculo'))->toBe('Vehículo');
});

test('registra en la bitácora la creación, la actualización y la eliminación', function () {
    $id = $this->servicio->crear(datosUbicacion());
    $this->servicio->actualizar($id, datosUbicacion(['tipo' => 'bodega']));
    $this->servicio->eliminar($id);

    $eventos = DB::table('activity_log')
        ->where('subject_type', 'ubicaciones')
        ->where('subject_id', $id)
        ->pluck('event')
        ->all();

    expect($eventos)->toEqualCanonicalizing(['created', 'updated', 'deleted']);
});
