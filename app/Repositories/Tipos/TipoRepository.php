<?php

namespace App\Repositories\Tipos;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TipoRepository
{
    /**
     * Columnas por las que se admite ordenar el listado.
     *
     * @var array<int, string>
     */
    private const ORDENABLES = ['nombre', 'requiere_serie', 'controla_vencimiento', 'created_at'];

    /**
     * Devuelve el listado paginado con el conteo de ítems de cada tipo.
     *
     * @param  array{busqueda?: string|null, requiere_serie?: bool|null, controla_vencimiento?: bool|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'nombre', string $direccion = 'asc'): LengthAwarePaginator
    {
        $columna = in_array($ordenarPor, self::ORDENABLES, true) ? $ordenarPor : 'nombre';
        $sentido = $direccion === 'desc' ? 'desc' : 'asc';

        return $this->consultaBase()
            ->when($filtros['busqueda'] ?? null, fn (Builder $consulta, string $busqueda) => $consulta->where('tipos.nombre', 'like', '%'.$busqueda.'%'))
            ->when(isset($filtros['requiere_serie']), fn (Builder $consulta) => $consulta->where('tipos.requiere_serie', $filtros['requiere_serie']))
            ->when(isset($filtros['controla_vencimiento']), fn (Builder $consulta) => $consulta->where('tipos.controla_vencimiento', $filtros['controla_vencimiento']))
            ->orderBy('tipos.'.$columna, $sentido)
            ->paginate($porPagina);
    }

    public function buscarPorId(int $id): ?object
    {
        return $this->consultaBase()->where('tipos.id', $id)->first();
    }

    public function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        return DB::table('tipos')
            ->where('nombre', $nombre)
            ->when($exceptoId, fn (Builder $consulta, int $id) => $consulta->where('id', '!=', $id))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function insertar(array $atributos): int
    {
        return DB::table('tipos')->insertGetId($atributos);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function actualizar(int $id, array $atributos): bool
    {
        return DB::table('tipos')->where('id', $id)->update($atributos) > 0;
    }

    public function eliminar(int $id): bool
    {
        return DB::table('tipos')->where('id', $id)->delete() > 0;
    }

    /**
     * Cuenta los ítems asociados al tipo, incluidos los eliminados lógicamente.
     */
    public function contarItems(int $id): int
    {
        return DB::table('items')->where('tipo_id', $id)->count();
    }

    /**
     * Catálogo completo, para los selectores de otros módulos.
     *
     * @return array<int, object>
     */
    public function todos(): array
    {
        return DB::table('tipos')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'requiere_serie', 'controla_vencimiento'])
            ->all();
    }

    public function contar(): int
    {
        return DB::table('tipos')->count();
    }

    /**
     * Consulta base del listado, con el conteo de ítems por tipo.
     */
    private function consultaBase(): Builder
    {
        return DB::table('tipos')
            ->leftJoin('items', 'items.tipo_id', '=', 'tipos.id')
            ->groupBy('tipos.id', 'tipos.nombre', 'tipos.requiere_serie', 'tipos.controla_vencimiento', 'tipos.created_at', 'tipos.updated_at')
            ->select([
                'tipos.id',
                'tipos.nombre',
                'tipos.requiere_serie',
                'tipos.controla_vencimiento',
                'tipos.created_at',
                DB::raw('count(items.id) as items_asociados'),
            ]);
    }
}
