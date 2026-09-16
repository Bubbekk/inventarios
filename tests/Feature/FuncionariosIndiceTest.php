<?php

use App\DTOs\Funcionarios\GuardarFuncionarioDTO;
use App\Models\User;
use App\Services\Funcionarios\FuncionarioService;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
});

function cuentaConRolFuncionarios(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function crearFuncionario(string $nombres = 'Alejandro', string $apellidos = 'Pino', ?string $rut = '11111111-1'): int
{
    return app(FuncionarioService::class)->crear(GuardarFuncionarioDTO::desdeArreglo([
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'rut' => $rut,
        'cargo' => 'Inspector',
    ]));
}

test('la ruta exige autenticación', function () {
    $this->get(route('funcionarios.indice'))->assertRedirect(route('login'));
});

test('el administrador accede al catálogo', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'))
        ->get(route('funcionarios.indice'))
        ->assertOk();
});

test('el verificador accede al catálogo en modo lectura', function () {
    $this->actingAs(cuentaConRolFuncionarios('verificador'))
        ->get(route('funcionarios.indice'))
        ->assertOk();

    // El listado es diferido: el contenido llega en la segunda petición.
    Livewire::withoutLazyLoading()->test('pages::funcionarios.indice')->assertDontSee('Nuevo funcionario');
});

test('crea un funcionario con RUT desde el componente', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));

    Livewire::test('pages::funcionarios.indice')
        ->call('crear')
        ->set('nombres', 'Rocío')
        ->set('apellidos', 'Soto')
        ->set('rut', '12.345.678-5')
        ->set('cargo', 'Administrativa')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(DB::table('funcionarios')->where('rut', '12345678-5')->exists())->toBeTrue();
});

test('crea un responsable de turno sin RUT', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));

    Livewire::test('pages::funcionarios.indice')
        ->call('crear')
        ->set('nombres', 'Centralista 1')
        ->set('apellidos', 'Turno')
        ->set('cargo', 'Centralista de turno')
        ->call('guardar')
        ->assertHasNoErrors();

    $funcionario = DB::table('funcionarios')->where('nombres', 'Centralista 1')->first();

    expect($funcionario)->not->toBeNull()
        ->and($funcionario->rut)->toBeNull();
});

test('exige nombres y apellidos', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));

    Livewire::test('pages::funcionarios.indice')
        ->call('crear')
        ->call('guardar')
        ->assertHasErrors(['nombres' => 'required', 'apellidos' => 'required']);
});

test('informa el RUT inválido como error del campo', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));

    Livewire::test('pages::funcionarios.indice')
        ->call('crear')
        ->set('nombres', 'Rocío')
        ->set('apellidos', 'Soto')
        ->set('rut', '12345678-9')
        ->call('guardar')
        ->assertHasErrors('rut');
});

test('informa el RUT repetido como error del campo', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));
    crearFuncionario();

    Livewire::test('pages::funcionarios.indice')
        ->call('crear')
        ->set('nombres', 'Otro')
        ->set('apellidos', 'Distinto')
        ->set('rut', '11.111.111-1')
        ->call('guardar')
        ->assertHasErrors('rut');
});

test('edita un funcionario', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));
    $id = crearFuncionario();

    Livewire::test('pages::funcionarios.indice')
        ->call('editar', $id)
        ->assertSet('nombres', 'Alejandro')
        ->assertSet('rut', '11111111-1')
        ->set('cargo', 'Jefe de turno')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(DB::table('funcionarios')->find($id)->cargo)->toBe('Jefe de turno');
});

test('desactiva y reactiva un funcionario sin eliminarlo', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));
    $id = crearFuncionario();

    Livewire::test('pages::funcionarios.indice')
        ->call('cambiarEstado', $id, false);

    expect((bool) DB::table('funcionarios')->find($id)->activo)->toBeFalse();

    Livewire::test('pages::funcionarios.indice')
        ->call('cambiarEstado', $id, true);

    expect((bool) DB::table('funcionarios')->find($id)->activo)->toBeTrue()
        ->and(DB::table('funcionarios')->where('id', $id)->exists())->toBeTrue();
});

test('el listado filtra por estado, por presencia de RUT y por texto', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));
    crearFuncionario();
    crearFuncionario('Centralista 1', 'Turno', null);
    $inactivo = crearFuncionario('Rocío', 'Soto', '12.345.678-5');
    app(FuncionarioService::class)->cambiarEstado($inactivo, false);

    $componente = Livewire::test('pages::funcionarios.indice');

    expect($componente->set('filtroEstado', 'inactivos')->instance()->funcionarios->total())->toBe(1);
    expect($componente->set('filtroEstado', '')->set('filtroRut', 'sin')->instance()->funcionarios->total())->toBe(1);
    expect($componente->set('filtroRut', '')->set('busqueda', 'pino')->instance()->funcionarios->total())->toBe(1);
});

test('ordena alternando el sentido sobre la misma columna', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'));

    Livewire::test('pages::funcionarios.indice')
        ->assertSet('ordenarPor', 'apellidos')
        ->assertSet('direccion', 'asc')
        ->call('ordenar', 'apellidos')
        ->assertSet('direccion', 'desc')
        ->call('ordenar', 'rut')
        ->assertSet('ordenarPor', 'rut')
        ->assertSet('direccion', 'asc');
});

test('el menú incluye funcionarios dentro de la sección Inventario', function () {
    $this->actingAs(cuentaConRolFuncionarios('administrador'))
        ->get(route('funcionarios.indice'))
        ->assertSee('Inventario')
        ->assertSee('Funcionarios');
});
