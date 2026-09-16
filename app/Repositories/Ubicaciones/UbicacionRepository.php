<?php

namespace App\Repositories\Ubicaciones;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class UbicacionRepository
{
    /**
     * Columnas por las que se admite ordenar el listado.
     *
     * @var array<int, string>
     */
    private const ORDENABLES = ['nombre', 'tipo', 'created_at'];

    /**
     * Devuelve el listado paginado con el conteo de ítems de cada ubicación.
     *
     * @param  array{busqueda?: string|null, tipo?: string|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'nombre', string $direccion = 'asc'): LengthAwarePaginator
    {
        $columna = in_array($ordenarPor, self::ORDENABLES, true) ? $ordenarPor : 'nombre';
        $sentido = $direccion === 'desc' ? 'desc' : 'asc';

        return $this->consultaBase()
            ->when($filtros['busqueda'] ?? null, function (Builder $consulta, string $busqueda): void {
                $consulta->where(function (Builder $grupo) use ($busqueda): void {
                    $grupo->where('ubicaciones.nombre', 'like', '%'.$busqueda.'%')
                        ->orWhere('ubicaciones.direccion', 'like', '%'.$busqueda.'%');
                });
            })
            ->when($filtros['tipo'] ?? null, fn (Builder $consulta, string $tipo) => $consulta->where('ubicaciones.tipo', $tipo))
            ->orderBy('ubicaciones.'.$columna, $sentido)
            ->paginate($porPagina);
    }

    public function buscarPorId(int $id): ?object
    {
        return $this->consultaBase()->where('ubicaciones.id', $id)->first();
    }

    public function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        return DB::table('ubicaciones')
            ->where('nombre', $nombre)
            ->when($exceptoId, fn (Builder $consulta, int $id) => $consulta->where('id', '!=', $id))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function insertar(array $atributos): int
    {
        return DB::table('ubicaciones')->insertGetId($atributos);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function actualizar(int $id, array $atributos): bool
    {
        return DB::table('ubicaciones')->where('id', $id)->update($atributos) > 0;
    }

    public function eliminar(int $id): bool
    {
        return DB::table('ubicaciones')->where('id', $id)->delete() > 0;
    }

    /**
     * Cuenta los ítems asociados, incluidos los eliminados lógicamente.
     */
    public function contarItems(int $id): int
    {
        return DB::table('items')->where('ubicacion_id', $id)->count();
    }

    /**
     * Cuenta las verificaciones que observaron la ubicación.
     */
    public function contarVerificaciones(int $id): int
    {
        return DB::table('verificaciones')->where('ubicacion_id', $id)->count();
    }

    /**
     * Catálogo completo, para los selectores de otros módulos.
     *
     * @return array<int, object>
     */
    public function todas(): array
    {
        return DB::table('ubicaciones')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'direccion'])
            ->all();
    }

    public function contar(): int
    {
        return DB::table('ubicaciones')->count();
    }

    /**
     * Consulta base del listado, con el conteo de ítems por ubicación.
     */
    private function consultaBase(): Builder
    {
        return DB::table('ubicaciones')
            ->leftJoin('items', 'items.ubicacion_id', '=', 'ubicaciones.id')
            ->groupBy('ubicaciones.id', 'ubicaciones.nombre', 'ubicaciones.tipo', 'ubicaciones.direccion', 'ubicaciones.created_at', 'ubicaciones.updated_at')
            ->select([
                'ubicaciones.id',
                'ubicaciones.nombre',
                'ubicaciones.tipo',
                'ubicaciones.direccion',
                'ubicaciones.created_at',
                DB::raw('count(items.id) as items_asociados'),
            ]);
    }
}
