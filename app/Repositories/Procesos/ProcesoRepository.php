<?php

namespace App\Repositories\Procesos;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProcesoRepository
{
    /**
     * Columnas por las que se admite ordenar el listado.
     *
     * @var array<int, string>
     */
    private const ORDENABLES = ['nombre', 'fecha_inicio', 'fecha_cierre', 'estado', 'created_at'];

    /**
     * Listado paginado con el avance de cada proceso.
     *
     * @param  array{busqueda?: string|null, estado?: string|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'fecha_inicio', string $direccion = 'desc'): LengthAwarePaginator
    {
        $columna = in_array($ordenarPor, self::ORDENABLES, true) ? $ordenarPor : 'fecha_inicio';
        $sentido = $direccion === 'asc' ? 'asc' : 'desc';

        return $this->consultaBase()
            ->when($filtros['busqueda'] ?? null, fn (Builder $consulta, string $busqueda) => $consulta->where('procesos_verificacion.nombre', 'like', '%'.$busqueda.'%'))
            ->when($filtros['estado'] ?? null, fn (Builder $consulta, string $estado) => $consulta->where('procesos_verificacion.estado', $estado))
            ->orderBy('procesos_verificacion.'.$columna, $sentido)
            ->paginate($porPagina);
    }

    public function buscarPorId(int $id): ?object
    {
        return $this->consultaBase()->where('procesos_verificacion.id', $id)->first();
    }

    /**
     * Proceso abierto, si lo hay. El sistema admite solo uno a la vez.
     */
    public function abierto(): ?object
    {
        return $this->consultaBase()->where('procesos_verificacion.estado', 'abierto')->first();
    }

    public function existeAbierto(?int $exceptoId = null): bool
    {
        return DB::table('procesos_verificacion')
            ->where('estado', 'abierto')
            ->when($exceptoId, fn (Builder $consulta, int $id) => $consulta->where('id', '!=', $id))
            ->exists();
    }

    public function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        return DB::table('procesos_verificacion')
            ->where('nombre', $nombre)
            ->when($exceptoId, fn (Builder $consulta, int $id) => $consulta->where('id', '!=', $id))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function insertar(array $atributos): int
    {
        return DB::table('procesos_verificacion')->insertGetId($atributos);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function actualizar(int $id, array $atributos): bool
    {
        return DB::table('procesos_verificacion')->where('id', $id)->update($atributos) > 0;
    }

    public function eliminar(int $id): bool
    {
        return DB::table('procesos_verificacion')->where('id', $id)->delete() > 0;
    }

    public function contarVerificaciones(int $id): int
    {
        return DB::table('verificaciones')->where('proceso_verificacion_id', $id)->count();
    }

    /**
     * Total de ítems vigentes, que es el universo a verificar.
     */
    public function totalItemsVigentes(): int
    {
        return DB::table('items')->whereNull('deleted_at')->count();
    }

    /**
     * Consulta base con el responsable y el avance del proceso.
     */
    private function consultaBase(): Builder
    {
        $vigentes = DB::table('items')->whereNull('deleted_at')->count();

        return DB::table('procesos_verificacion')
            ->leftJoin('users', 'users.id', '=', 'procesos_verificacion.user_id')
            ->leftJoin('verificaciones', 'verificaciones.proceso_verificacion_id', '=', 'procesos_verificacion.id')
            ->groupBy(
                'procesos_verificacion.id',
                'procesos_verificacion.nombre',
                'procesos_verificacion.fecha_inicio',
                'procesos_verificacion.fecha_cierre',
                'procesos_verificacion.estado',
                'procesos_verificacion.user_id',
                'procesos_verificacion.created_at',
                'procesos_verificacion.updated_at',
                'users.name',
            )
            ->select([
                'procesos_verificacion.id',
                'procesos_verificacion.nombre',
                'procesos_verificacion.fecha_inicio',
                'procesos_verificacion.fecha_cierre',
                'procesos_verificacion.estado',
                'procesos_verificacion.user_id',
                'procesos_verificacion.created_at',
                'users.name as responsable',
                DB::raw('count(distinct verificaciones.id) as verificados'),
                DB::raw("count(distinct case when verificaciones.resultado = 'sin_registro' then verificaciones.id end) as sin_registro"),
                DB::raw($vigentes.' as total_items'),
            ]);
    }
}
