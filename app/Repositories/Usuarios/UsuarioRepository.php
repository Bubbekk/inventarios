<?php

namespace App\Repositories\Usuarios;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class UsuarioRepository
{
    /**
     * Columnas por las que se admite ordenar el listado.
     *
     * @var array<int, string>
     */
    private const ORDENABLES = ['name', 'email', 'activo', 'created_at'];

    /**
     * Devuelve el listado paginado con el rol de cada cuenta.
     *
     * @param  array{busqueda?: string|null, rol?: string|null, activo?: bool|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'name', string $direccion = 'asc'): LengthAwarePaginator
    {
        $columna = in_array($ordenarPor, self::ORDENABLES, true) ? $ordenarPor : 'name';
        $sentido = $direccion === 'desc' ? 'desc' : 'asc';

        return $this->consultaBase()
            ->when($filtros['busqueda'] ?? null, function ($consulta, string $busqueda) {
                $consulta->where(function ($grupo) use ($busqueda): void {
                    $grupo->where('users.name', 'like', '%'.$busqueda.'%')
                        ->orWhere('users.email', 'like', '%'.$busqueda.'%');
                });
            })
            ->when($filtros['rol'] ?? null, fn ($consulta, string $rol) => $consulta->where('roles.name', $rol))
            ->when(isset($filtros['activo']), fn ($consulta) => $consulta->where('users.activo', $filtros['activo']))
            ->orderBy('users.'.$columna, $sentido)
            ->paginate($porPagina);
    }

    public function buscarPorId(int $id): ?object
    {
        return $this->consultaBase()->where('users.id', $id)->first();
    }

    public function buscarPorCorreo(string $correo): ?object
    {
        return $this->consultaBase()->where('users.email', $correo)->first();
    }

    public function existeCorreo(string $correo, ?int $exceptoId = null): bool
    {
        return DB::table('users')
            ->where('email', $correo)
            ->when($exceptoId, fn ($consulta, int $id) => $consulta->where('id', '!=', $id))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function insertar(array $atributos): int
    {
        return DB::table('users')->insertGetId($atributos);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function actualizar(int $id, array $atributos): bool
    {
        return DB::table('users')->where('id', $id)->update($atributos) > 0;
    }

    public function cambiarEstado(int $id, bool $activo): bool
    {
        return $this->actualizar($id, ['activo' => $activo, 'updated_at' => now()]);
    }

    /**
     * Reemplaza el rol de la cuenta. El sistema asigna un solo rol por usuario.
     */
    public function sincronizarRol(int $usuarioId, int $rolId): void
    {
        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $usuarioId)
            ->delete();

        DB::table('model_has_roles')->insert([
            'role_id' => $rolId,
            'model_type' => User::class,
            'model_id' => $usuarioId,
        ]);
    }

    public function rolPorNombre(string $nombre): ?object
    {
        return DB::table('roles')
            ->where('name', $nombre)
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->first();
    }

    /**
     * @return array<int, object>
     */
    public function roles(): array
    {
        return DB::table('roles')
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * Cuenta las cuentas activas que tienen el rol indicado.
     */
    public function contarActivosConRol(string $rol, ?int $exceptoId = null): int
    {
        return DB::table('users')
            ->join('model_has_roles', function ($union): void {
                $union->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $rol)
            ->where('users.activo', true)
            ->when($exceptoId, fn ($consulta, int $id) => $consulta->where('users.id', '!=', $id))
            ->count();
    }

    /**
     * Consulta base del listado, con el rol unido por la tabla pivote de Spatie.
     */
    private function consultaBase(): Builder
    {
        return DB::table('users')
            ->leftJoin('model_has_roles', function ($union): void {
                $union->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', User::class);
            })
            ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.email_verified_at',
                'users.activo',
                'users.two_factor_confirmed_at',
                'users.created_at',
                'roles.name as rol',
            ]);
    }
}
