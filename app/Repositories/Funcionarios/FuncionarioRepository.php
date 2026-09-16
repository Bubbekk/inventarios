<?php

namespace App\Repositories\Funcionarios;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FuncionarioRepository
{
    /**
     * Columnas por las que se admite ordenar el listado.
     *
     * @var array<int, string>
     */
    private const ORDENABLES = ['apellidos', 'nombres', 'rut', 'cargo', 'activo', 'created_at'];

    /**
     * Devuelve el listado paginado con el conteo de ítems a cargo.
     *
     * @param  array{busqueda?: string|null, activo?: bool|null, con_rut?: bool|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'apellidos', string $direccion = 'asc'): LengthAwarePaginator
    {
        $columna = in_array($ordenarPor, self::ORDENABLES, true) ? $ordenarPor : 'apellidos';
        $sentido = $direccion === 'desc' ? 'desc' : 'asc';

        return $this->consultaBase()
            ->when($filtros['busqueda'] ?? null, function (Builder $consulta, string $busqueda): void {
                $consulta->where(function (Builder $grupo) use ($busqueda): void {
                    $grupo->where('funcionarios.nombres', 'like', '%'.$busqueda.'%')
                        ->orWhere('funcionarios.apellidos', 'like', '%'.$busqueda.'%')
                        ->orWhere('funcionarios.rut', 'like', '%'.$busqueda.'%')
                        ->orWhere('funcionarios.cargo', 'like', '%'.$busqueda.'%');
                });
            })
            ->when(isset($filtros['activo']), fn (Builder $consulta) => $consulta->where('funcionarios.activo', $filtros['activo']))
            ->when(isset($filtros['con_rut']), function (Builder $consulta) use ($filtros): void {
                $filtros['con_rut']
                    ? $consulta->whereNotNull('funcionarios.rut')
                    : $consulta->whereNull('funcionarios.rut');
            })
            ->orderBy('funcionarios.'.$columna, $sentido)
            ->orderBy('funcionarios.nombres', $sentido)
            ->paginate($porPagina);
    }

    public function buscarPorId(int $id): ?object
    {
        return $this->consultaBase()->where('funcionarios.id', $id)->first();
    }

    public function existeRut(string $rut, ?int $exceptoId = null): bool
    {
        return DB::table('funcionarios')
            ->where('rut', $rut)
            ->when($exceptoId, fn (Builder $consulta, int $id) => $consulta->where('id', '!=', $id))
            ->exists();
    }

    /**
     * Verifica si ya existe otro responsable con el mismo nombre y apellidos.
     */
    public function existeNombre(string $nombres, string $apellidos, ?int $exceptoId = null): bool
    {
        return DB::table('funcionarios')
            ->where('nombres', $nombres)
            ->where('apellidos', $apellidos)
            ->when($exceptoId, fn (Builder $consulta, int $id) => $consulta->where('id', '!=', $id))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function insertar(array $atributos): int
    {
        return DB::table('funcionarios')->insertGetId($atributos);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function actualizar(int $id, array $atributos): bool
    {
        return DB::table('funcionarios')->where('id', $id)->update($atributos) > 0;
    }

    public function cambiarEstado(int $id, bool $activo): bool
    {
        return $this->actualizar($id, ['activo' => $activo, 'updated_at' => now()]);
    }

    /**
     * Cuenta los ítems a cargo, incluidos los eliminados lógicamente.
     */
    public function contarItems(int $id): int
    {
        return DB::table('items')->where('funcionario_id', $id)->count();
    }

    /**
     * Catálogo de responsables activos, para los selectores de otros módulos.
     *
     * @return array<int, object>
     */
    public function activos(): array
    {
        return DB::table('funcionarios')
            ->where('activo', true)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'rut', 'cargo', 'activo'])
            ->all();
    }

    public function contar(): int
    {
        return DB::table('funcionarios')->count();
    }

    /**
     * Consulta base del listado, con el conteo de ítems por responsable.
     */
    private function consultaBase(): Builder
    {
        return DB::table('funcionarios')
            ->leftJoin('items', 'items.funcionario_id', '=', 'funcionarios.id')
            ->groupBy('funcionarios.id', 'funcionarios.rut', 'funcionarios.nombres', 'funcionarios.apellidos', 'funcionarios.cargo', 'funcionarios.activo', 'funcionarios.created_at', 'funcionarios.updated_at')
            ->select([
                'funcionarios.id',
                'funcionarios.rut',
                'funcionarios.nombres',
                'funcionarios.apellidos',
                'funcionarios.cargo',
                'funcionarios.activo',
                'funcionarios.created_at',
                DB::raw('count(items.id) as items_asociados'),
            ]);
    }
}
