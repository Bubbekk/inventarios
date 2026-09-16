<?php

use App\DTOs\Usuarios\GuardarUsuarioDTO;
use App\Models\User;
use App\Services\Usuarios\UsuarioService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    $this->servicio = app(UsuarioService::class);
});

function datosUsuario(array $sobrescribir = []): GuardarUsuarioDTO
{
    return GuardarUsuarioDTO::desdeArreglo(array_merge([
        'nombre' => 'Ana Pérez',
        'correo' => 'ana@dsp.cl',
        'rol' => 'verificador',
        'activo' => true,
        'clave' => 'clave-muy-segura',
    ], $sobrescribir));
}

test('crea una cuenta con su rol y la contraseña cifrada', function () {
    $id = $this->servicio->crear(datosUsuario());

    $usuario = $this->servicio->obtener($id);

    expect($usuario->nombre)->toBe('Ana Pérez')
        ->and($usuario->correo)->toBe('ana@dsp.cl')
        ->and($usuario->rol)->toBe('verificador')
        ->and($usuario->activo)->toBeTrue()
        ->and(Hash::check('clave-muy-segura', DB::table('users')->find($id)->password))->toBeTrue();
});

test('normaliza el correo a minúsculas al crear', function () {
    $id = $this->servicio->crear(datosUsuario(['correo' => '  ANA@DSP.CL  ']));

    expect($this->servicio->obtener($id)->correo)->toBe('ana@dsp.cl');
});

test('rechaza la creación cuando el correo ya existe', function () {
    $this->servicio->crear(datosUsuario());

    $this->servicio->crear(datosUsuario(['nombre' => 'Otra']));
})->throws(DomainException::class, 'Ya existe una cuenta con ese correo.');

test('rechaza la creación sin contraseña', function () {
    $this->servicio->crear(datosUsuario(['clave' => null]));
})->throws(DomainException::class, 'La contraseña es obligatoria al crear una cuenta.');

test('rechaza un rol inexistente', function () {
    $this->servicio->crear(datosUsuario(['rol' => 'supervisor']));
})->throws(DomainException::class, 'El rol indicado no existe.');

test('actualiza el rol reemplazando el anterior', function () {
    $id = $this->servicio->crear(datosUsuario());

    $this->servicio->actualizar($id, datosUsuario(['rol' => 'consulta', 'clave' => null]));

    expect($this->servicio->obtener($id)->rol)->toBe('consulta')
        ->and(DB::table('model_has_roles')->where('model_id', $id)->count())->toBe(1);
});

test('conserva la contraseña cuando la actualización no la informa', function () {
    $id = $this->servicio->crear(datosUsuario());
    $cifrada = DB::table('users')->find($id)->password;

    $this->servicio->actualizar($id, datosUsuario(['nombre' => 'Ana Soto', 'clave' => null]));

    expect(DB::table('users')->find($id)->password)->toBe($cifrada);
});

test('cambia la contraseña cuando la actualización la informa', function () {
    $id = $this->servicio->crear(datosUsuario());

    $this->servicio->actualizar($id, datosUsuario(['clave' => 'otra-clave-seguraaa']));

    expect(Hash::check('otra-clave-seguraaa', DB::table('users')->find($id)->password))->toBeTrue();
});

test('desactiva una cuenta sin eliminarla', function () {
    $id = $this->servicio->crear(datosUsuario());

    $this->servicio->cambiarEstado($id, false);

    expect($this->servicio->obtener($id)->activo)->toBeFalse()
        ->and(DB::table('users')->where('id', $id)->exists())->toBeTrue();
});

test('impide desactivar la propia cuenta', function () {
    $id = $this->servicio->crear(datosUsuario());
    $this->actingAs(User::find($id));

    $this->servicio->cambiarEstado($id, false);
})->throws(DomainException::class, 'No puede desactivar su propia cuenta.');

test('impide desactivar al último administrador activo', function () {
    $id = $this->servicio->crear(datosUsuario(['rol' => 'administrador']));

    $this->servicio->cambiarEstado($id, false);
})->throws(DomainException::class, 'El sistema debe conservar al menos una cuenta de administrador activa.');

test('impide quitarle el rol al último administrador activo', function () {
    $id = $this->servicio->crear(datosUsuario(['rol' => 'administrador']));

    $this->servicio->actualizar($id, datosUsuario(['rol' => 'consulta', 'clave' => null]));
})->throws(DomainException::class, 'El sistema debe conservar al menos una cuenta de administrador activa.');

test('permite desactivar a un administrador cuando queda otro activo', function () {
    $primero = $this->servicio->crear(datosUsuario(['rol' => 'administrador']));
    $segundo = $this->servicio->crear(datosUsuario([
        'correo' => 'otro@dsp.cl',
        'rol' => 'administrador',
    ]));

    $this->servicio->cambiarEstado($segundo, false);

    expect($this->servicio->obtener($segundo)->activo)->toBeFalse()
        ->and($this->servicio->obtener($primero)->activo)->toBeTrue();
});

test('el listado filtra por rol, por estado y por texto', function () {
    $this->servicio->crear(datosUsuario());
    $this->servicio->crear(datosUsuario(['nombre' => 'Bruno Díaz', 'correo' => 'bruno@dsp.cl', 'rol' => 'consulta']));
    $inactivo = $this->servicio->crear(datosUsuario(['nombre' => 'Carla Ruiz', 'correo' => 'carla@dsp.cl', 'rol' => 'consulta']));
    $this->servicio->cambiarEstado($inactivo, false);

    expect($this->servicio->paginar(['rol' => 'consulta'])->total())->toBe(2)
        ->and($this->servicio->paginar(['activo' => false])->total())->toBe(1)
        ->and($this->servicio->paginar(['busqueda' => 'bruno'])->total())->toBe(1);
});

test('registra en la bitácora la creación y la desactivación', function () {
    $id = $this->servicio->crear(datosUsuario());
    $this->servicio->cambiarEstado($id, false);

    $eventos = DB::table('activity_log')
        ->where('subject_type', 'users')
        ->where('subject_id', $id)
        ->pluck('event')
        ->all();

    expect($eventos)->toEqualCanonicalizing(['created', 'deactivated']);
});

test('los permisos del rol quedan disponibles inmediatamente después de crear la cuenta', function () {
    $id = $this->servicio->crear(datosUsuario());

    expect(User::find($id)->can('verificaciones.registrar'))->toBeTrue()
        ->and(User::find($id)->can('usuarios.crear'))->toBeFalse();
});

test('expone los roles disponibles del sistema', function () {
    expect($this->servicio->rolesDisponibles())
        ->toEqualCanonicalizing(['administrador', 'verificador', 'consulta']);
});
