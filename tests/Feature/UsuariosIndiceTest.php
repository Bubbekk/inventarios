<?php

use App\DTOs\Usuarios\GuardarUsuarioDTO;
use App\Models\User;
use App\Services\Usuarios\UsuarioService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
});

function usuarioCon(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

test('la ruta exige autenticación', function () {
    $this->get(route('usuarios.indice'))->assertRedirect(route('login'));
});

test('el administrador accede al mantenedor', function () {
    $this->actingAs(usuarioCon('administrador'))
        ->get(route('usuarios.indice'))
        ->assertOk();
});

test('el verificador no accede al mantenedor', function () {
    $this->actingAs(usuarioCon('verificador'))
        ->get(route('usuarios.indice'))
        ->assertForbidden();
});

test('el rol consulta no accede al mantenedor', function () {
    $this->actingAs(usuarioCon('consulta'))
        ->get(route('usuarios.indice'))
        ->assertForbidden();
});

test('crea una cuenta desde el componente', function () {
    $this->actingAs(usuarioCon('administrador'));

    Livewire::test('pages::usuarios.indice')
        ->call('crear')
        ->set('nombre', 'Ana Pérez')
        ->set('correo', 'ana@dsp.cl')
        ->set('rol', 'verificador')
        ->set('clave', 'clave-muy-segura')
        ->set('clave_confirmation', 'clave-muy-segura')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(DB::table('users')->where('email', 'ana@dsp.cl')->exists())->toBeTrue();
});

test('exige contraseña al crear y confirma su coincidencia', function () {
    $this->actingAs(usuarioCon('administrador'));

    Livewire::test('pages::usuarios.indice')
        ->call('crear')
        ->set('nombre', 'Ana Pérez')
        ->set('correo', 'ana@dsp.cl')
        ->set('rol', 'verificador')
        ->call('guardar')
        ->assertHasErrors(['clave' => 'required']);

    Livewire::test('pages::usuarios.indice')
        ->call('crear')
        ->set('nombre', 'Ana Pérez')
        ->set('correo', 'ana@dsp.cl')
        ->set('rol', 'verificador')
        ->set('clave', 'clave-muy-segura')
        ->set('clave_confirmation', 'otra-distinta')
        ->call('guardar')
        ->assertHasErrors(['clave' => 'confirmed']);
});

test('informa el correo repetido como error del formulario', function () {
    $this->actingAs(usuarioCon('administrador'));
    User::factory()->create(['email' => 'ana@dsp.cl']);

    Livewire::test('pages::usuarios.indice')
        ->call('crear')
        ->set('nombre', 'Ana Pérez')
        ->set('correo', 'ana@dsp.cl')
        ->set('rol', 'verificador')
        ->set('clave', 'clave-muy-segura')
        ->set('clave_confirmation', 'clave-muy-segura')
        ->call('guardar')
        ->assertHasErrors('correo');
});

test('edita una cuenta sin cambiar su contraseña', function () {
    $this->actingAs(usuarioCon('administrador'));
    $id = app(UsuarioService::class)->crear(GuardarUsuarioDTO::desdeArreglo([
        'nombre' => 'Ana Pérez',
        'correo' => 'ana@dsp.cl',
        'rol' => 'verificador',
        'clave' => 'clave-muy-segura',
    ]));
    $cifrada = DB::table('users')->find($id)->password;

    Livewire::test('pages::usuarios.indice')
        ->call('editar', $id)
        ->assertSet('nombre', 'Ana Pérez')
        ->assertSet('rol', 'verificador')
        ->set('nombre', 'Ana Soto')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(DB::table('users')->find($id)->name)->toBe('Ana Soto')
        ->and(DB::table('users')->find($id)->password)->toBe($cifrada);
});

test('desactiva una cuenta desde el componente', function () {
    $this->actingAs(usuarioCon('administrador'));
    $otra = User::factory()->create(['activo' => true]);
    $otra->assignRole('consulta');

    Livewire::test('pages::usuarios.indice')
        ->call('cambiarEstado', $otra->id, false);

    expect((bool) DB::table('users')->find($otra->id)->activo)->toBeFalse();
});

test('el listado filtra por texto, rol y estado', function () {
    $this->actingAs(usuarioCon('administrador'));

    $verificador = User::factory()->create(['name' => 'Bruno Díaz', 'email' => 'bruno@dsp.cl']);
    $verificador->assignRole('verificador');

    $inactiva = User::factory()->create(['name' => 'Carla Ruiz', 'email' => 'carla@dsp.cl', 'activo' => false]);
    $inactiva->assignRole('consulta');

    $componente = Livewire::test('pages::usuarios.indice');

    expect($componente->set('busqueda', 'bruno')->instance()->usuarios->total())->toBe(1);

    expect($componente->set('busqueda', '')->set('filtroEstado', 'inactivos')->instance()->usuarios->total())->toBe(1);

    expect($componente->set('filtroEstado', '')->set('filtroRol', 'verificador')->instance()->usuarios->total())->toBe(1);
});

test('ordena alternando el sentido sobre la misma columna', function () {
    $this->actingAs(usuarioCon('administrador'));

    Livewire::test('pages::usuarios.indice')
        ->assertSet('ordenarPor', 'name')
        ->assertSet('direccion', 'asc')
        ->call('ordenar', 'email')
        ->assertSet('ordenarPor', 'email')
        ->assertSet('direccion', 'asc')
        ->call('ordenar', 'email')
        ->assertSet('direccion', 'desc');
});

test('la contraseña asignada permite iniciar sesión', function () {
    $this->actingAs(usuarioCon('administrador'));

    Livewire::test('pages::usuarios.indice')
        ->call('crear')
        ->set('nombre', 'Ana Pérez')
        ->set('correo', 'ana@dsp.cl')
        ->set('rol', 'verificador')
        ->set('clave', 'clave-muy-segura')
        ->set('clave_confirmation', 'clave-muy-segura')
        ->call('guardar');

    expect(Hash::check('clave-muy-segura', DB::table('users')->where('email', 'ana@dsp.cl')->first()->password))
        ->toBeTrue();
});
