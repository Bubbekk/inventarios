<?php

use App\DTOs\Funcionarios\GuardarFuncionarioDTO;
use App\Services\Funcionarios\FuncionarioService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->servicio = app(FuncionarioService::class);
});

function datosFuncionario(array $sobrescribir = []): GuardarFuncionarioDTO
{
    return GuardarFuncionarioDTO::desdeArreglo(array_merge([
        'nombres' => 'Alejandro',
        'apellidos' => 'Pino',
        'rut' => '11111111-1',
        'cargo' => 'Inspector',
        'activo' => true,
    ], $sobrescribir));
}

function tipoParaFuncionario(): int
{
    return DB::table('tipos')->insertGetId([
        'nombre' => 'Computador',
        'requiere_serie' => true,
        'controla_vencimiento' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('crea un funcionario con RUT válido y lo normaliza', function () {
    $id = $this->servicio->crear(datosFuncionario(['rut' => '11.111.111-1']));

    $funcionario = $this->servicio->obtener($id);

    expect($funcionario->rut)->toBe('11111111-1')
        ->and($funcionario->nombres)->toBe('Alejandro')
        ->and($funcionario->apellidos)->toBe('Pino')
        ->and($funcionario->cargo)->toBe('Inspector')
        ->and($funcionario->activo)->toBeTrue();
});

test('registra un responsable que no es persona natural, sin RUT', function () {
    $id = $this->servicio->crear(datosFuncionario([
        'nombres' => 'Centralista 1',
        'apellidos' => 'Turno',
        'rut' => null,
        'cargo' => 'Centralista de turno',
    ]));

    expect($this->servicio->obtener($id)->rut)->toBeNull();
});

test('admite varios responsables sin RUT', function () {
    $this->servicio->crear(datosFuncionario(['nombres' => 'Centralista 1', 'apellidos' => 'Turno', 'rut' => null]));
    $this->servicio->crear(datosFuncionario(['nombres' => 'Centralista 2', 'apellidos' => 'Turno', 'rut' => null]));
    $this->servicio->crear(datosFuncionario(['nombres' => 'Todo', 'apellidos' => 'Uso', 'rut' => '']));

    expect($this->servicio->paginar(['con_rut' => false])->total())->toBe(3);
});

test('rechaza un RUT con dígito verificador incorrecto', function () {
    $this->servicio->crear(datosFuncionario(['rut' => '11111111-2']));
})->throws(DomainException::class, 'El RUT informado no es válido.');

test('rechaza un RUT repetido', function () {
    $this->servicio->crear(datosFuncionario());

    $this->servicio->crear(datosFuncionario(['nombres' => 'Otro', 'apellidos' => 'Distinto']));
})->throws(DomainException::class, 'Ya existe un funcionario con ese RUT.');

test('valida el dígito verificador de casos conocidos', function () {
    expect(FuncionarioService::rutEsValido('11111111-1'))->toBeTrue()
        ->and(FuncionarioService::rutEsValido('12.345.678-5'))->toBeTrue()
        ->and(FuncionarioService::rutEsValido('7.654.321-6'))->toBeTrue()
        ->and(FuncionarioService::rutEsValido('12345678-9'))->toBeFalse()
        ->and(FuncionarioService::rutEsValido('abc'))->toBeFalse()
        ->and(FuncionarioService::rutEsValido('1-9'))->toBeFalse();
});

test('reconoce el dígito verificador K', function () {
    expect(FuncionarioService::rutEsValido('20.000.003-K'))->toBeTrue()
        ->and(FuncionarioService::normalizarRut('20000003k'))->toBe('20000003-K');
});

test('rechaza nombres o apellidos vacíos', function () {
    $this->servicio->crear(datosFuncionario(['nombres' => '   ']));
})->throws(DomainException::class, 'Los nombres y apellidos son obligatorios.');

test('rechaza un funcionario con el mismo nombre y apellidos', function () {
    $this->servicio->crear(datosFuncionario());

    $this->servicio->crear(datosFuncionario(['rut' => '12.345.678-5']));
})->throws(DomainException::class, 'Ya existe un funcionario con ese nombre y apellidos.');

test('actualiza el funcionario sin considerar repetido su propio RUT', function () {
    $id = $this->servicio->crear(datosFuncionario());

    $this->servicio->actualizar($id, datosFuncionario(['cargo' => 'Jefe de turno']));

    expect($this->servicio->obtener($id)->cargo)->toBe('Jefe de turno');
});

test('permite quitar el RUT a un funcionario existente', function () {
    $id = $this->servicio->crear(datosFuncionario());

    $this->servicio->actualizar($id, datosFuncionario(['rut' => null]));

    expect($this->servicio->obtener($id)->rut)->toBeNull();
});

test('rechaza actualizar un funcionario inexistente', function () {
    $this->servicio->actualizar(999, datosFuncionario());
})->throws(DomainException::class, 'El funcionario no existe.');

test('desactiva un funcionario sin eliminarlo ni perder su historial', function () {
    $id = $this->servicio->crear(datosFuncionario());

    DB::table('items')->insert([
        'numero_inventario' => '10601003.192',
        'tipo_id' => tipoParaFuncionario(),
        'funcionario_id' => $id,
        'estado_conservacion' => 'bueno',
        'situacion' => 'registrado_daf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->servicio->cambiarEstado($id, false);

    $funcionario = $this->servicio->obtener($id);

    expect($funcionario->activo)->toBeFalse()
        ->and($funcionario->itemsAsociados)->toBe(1)
        ->and(DB::table('funcionarios')->where('id', $id)->exists())->toBeTrue();
});

test('el catálogo de activos excluye a los desactivados', function () {
    $id = $this->servicio->crear(datosFuncionario());
    $this->servicio->crear(datosFuncionario(['nombres' => 'Rocío', 'apellidos' => 'Soto', 'rut' => '12.345.678-5']));
    $this->servicio->cambiarEstado($id, false);

    expect($this->servicio->listarActivos())->toHaveCount(1);
});

test('el listado filtra por estado, por presencia de RUT y por texto', function () {
    $this->servicio->crear(datosFuncionario());
    $this->servicio->crear(datosFuncionario(['nombres' => 'Centralista 1', 'apellidos' => 'Turno', 'rut' => null]));
    $inactivo = $this->servicio->crear(datosFuncionario(['nombres' => 'Rocío', 'apellidos' => 'Soto', 'rut' => '12.345.678-5']));
    $this->servicio->cambiarEstado($inactivo, false);

    expect($this->servicio->paginar(['activo' => false])->total())->toBe(1)
        ->and($this->servicio->paginar(['con_rut' => false])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => 'central'])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => '11111111'])->total())->toBe(1)
        ->and($this->servicio->paginar()->total())->toBe(3);
});

test('el listado ordena por apellidos en ambos sentidos', function () {
    $this->servicio->crear(datosFuncionario(['apellidos' => 'Zúñiga']));
    $this->servicio->crear(datosFuncionario(['nombres' => 'Ana', 'apellidos' => 'Acuña', 'rut' => '12.345.678-5']));

    expect($this->servicio->paginar(ordenarPor: 'apellidos', direccion: 'asc')->items()[0]->apellidos)->toBe('Acuña')
        ->and($this->servicio->paginar(ordenarPor: 'apellidos', direccion: 'desc')->items()[0]->apellidos)->toBe('Zúñiga');
});

test('registra en la bitácora la creación, la actualización y la desactivación', function () {
    $id = $this->servicio->crear(datosFuncionario());
    $this->servicio->actualizar($id, datosFuncionario(['cargo' => 'Jefe']));
    $this->servicio->cambiarEstado($id, false);

    $eventos = DB::table('activity_log')
        ->where('subject_type', 'funcionarios')
        ->where('subject_id', $id)
        ->pluck('event')
        ->all();

    expect($eventos)->toEqualCanonicalizing(['created', 'updated', 'deactivated']);
});
