<?php

namespace App\Repositories\Items;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ItemRepository
{
    /**
     * Columnas por las que se admite ordenar el listado.
     *
     * @var array<int, string>
     */
    private const ORDENABLES = [
        'numero_inventario',
        'codigo_antiguo',
        'marca',
        'modelo',
        'numero_serie',
        'estado_conservacion',
        'situacion',
        'fecha_vencimiento',
        'created_at',
    ];

    /**
     * Listado paginado de los ítems vigentes.
     *
     * Sin Eloquent no existe SoftDeletes: la exclusión de los eliminados se
     * declara en cada consulta de forma explícita.
     *
     * @param  array{
     *     busqueda?: string|null,
     *     tipo_id?: int|null,
     *     ubicacion_id?: int|null,
     *     funcionario_id?: int|null,
     *     estado_conservacion?: string|null,
     *     situacion?: string|null,
     *     vencimiento?: string|null
     * }  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'numero_inventario', string $direccion = 'asc'): LengthAwarePaginator
    {
        return $this->consultaFiltrada($filtros, $ordenarPor, $direccion)
            ->whereNull('items.deleted_at')
            ->paginate($porPagina);
    }

    /**
     * Listado paginado de los ítems eliminados lógicamente, para restaurarlos.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function paginarEliminados(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'numero_inventario', string $direccion = 'asc'): LengthAwarePaginator
    {
        return $this->consultaFiltrada($filtros, $ordenarPor, $direccion)
            ->whereNotNull('items.deleted_at')
            ->paginate($porPagina);
    }

    public function buscarPorId(int $id, bool $incluirEliminados = false): ?object
    {
        return $this->consultaBase()
            ->where('items.id', $id)
            ->when(! $incluirEliminados, fn (Builder $consulta) => $consulta->whereNull('items.deleted_at'))
            ->first();
    }

    public function existeNumeroInventario(string $numero, ?int $exceptoId = null): bool
    {
        return DB::table('items')
            ->where('numero_inventario', $numero)
            ->when($exceptoId, fn (Builder $consulta, int $id) => $consulta->where('id', '!=', $id))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function insertar(array $atributos): int
    {
        return DB::table('items')->insertGetId($atributos);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function actualizar(int $id, array $atributos): bool
    {
        return DB::table('items')->where('id', $id)->update($atributos) > 0;
    }

    public function eliminarLogico(int $id): bool
    {
        return DB::table('items')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]) > 0;
    }

    public function restaurar(int $id): bool
    {
        return DB::table('items')
            ->where('id', $id)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null, 'updated_at' => now()]) > 0;
    }

    /**
     * Componentes directos del ítem indicado.
     *
     * @return array<int, object>
     */
    public function componentesDe(int $id): array
    {
        return $this->consultaBase()
            ->where('items.item_padre_id', $id)
            ->whereNull('items.deleted_at')
            ->orderBy('items.numero_inventario')
            ->get()
            ->all();
    }

    /**
     * Identificadores de todos los descendientes del ítem, en cualquier nivel.
     *
     * Recorre la jerarquía por niveles para detectar referencias circulares
     * antes de asignar un padre.
     *
     * @return array<int, int>
     */
    public function descendientesDe(int $id): array
    {
        $descendientes = [];
        $nivel = [$id];

        while ($nivel !== []) {
            $hijos = DB::table('items')
                ->whereIn('item_padre_id', $nivel)
                ->pluck('id')
                ->map(static fn ($valor): int => (int) $valor)
                ->all();

            $nuevos = array_values(array_diff($hijos, $descendientes));

            if ($nuevos === []) {
                break;
            }

            $descendientes = array_merge($descendientes, $nuevos);
            $nivel = $nuevos;
        }

        return $descendientes;
    }

    public function contarComponentes(int $id): int
    {
        return DB::table('items')
            ->where('item_padre_id', $id)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Candidatos a ítem padre: vigentes, distintos del propio ítem y de sus descendientes.
     *
     * @param  array<int, int>  $excluidos
     * @return array<int, object>
     */
    public function candidatosAPadre(array $excluidos = []): array
    {
        return DB::table('items')
            ->leftJoin('tipos', 'tipos.id', '=', 'items.tipo_id')
            ->whereNull('items.deleted_at')
            ->when($excluidos !== [], fn (Builder $consulta) => $consulta->whereNotIn('items.id', $excluidos))
            ->orderBy('items.numero_inventario')
            ->get([
                'items.id',
                'items.numero_inventario',
                'items.codigo_antiguo',
                'items.marca',
                'items.modelo',
                'tipos.nombre as tipo_nombre',
            ])
            ->all();
    }

    /**
     * Ítems con vencimiento dentro de los días indicados, incluidos los ya vencidos.
     *
     * @return array<int, object>
     */
    public function proximosAVencer(int $dias = 30): array
    {
        return $this->consultaBase()
            ->whereNull('items.deleted_at')
            ->whereNotNull('items.fecha_vencimiento')
            ->whereDate('items.fecha_vencimiento', '<=', now()->addDays($dias)->toDateString())
            ->orderBy('items.fecha_vencimiento')
            ->get()
            ->all();
    }

    public function contarVigentes(): int
    {
        return DB::table('items')->whereNull('deleted_at')->count();
    }

    /**
     * Verificaciones registradas sobre el ítem, de la más reciente a la más antigua.
     *
     * @return array<int, object>
     */
    public function verificacionesDe(int $id): array
    {
        return DB::table('verificaciones')
            ->leftJoin('procesos_verificacion', 'procesos_verificacion.id', '=', 'verificaciones.proceso_verificacion_id')
            ->leftJoin('ubicaciones', 'ubicaciones.id', '=', 'verificaciones.ubicacion_id')
            ->leftJoin('funcionarios', 'funcionarios.id', '=', 'verificaciones.funcionario_id')
            ->leftJoin('users', 'users.id', '=', 'verificaciones.user_id')
            ->where('verificaciones.item_id', $id)
            ->orderByDesc('verificaciones.verificado_at')
            ->get([
                'verificaciones.id',
                'verificaciones.resultado',
                'verificaciones.metodo',
                'verificaciones.estado_conservacion',
                'verificaciones.codigo_observado',
                'verificaciones.observacion',
                'verificaciones.verificado_at',
                'procesos_verificacion.nombre as proceso_nombre',
                'ubicaciones.nombre as ubicacion_nombre',
                DB::raw("concat_ws(', ', funcionarios.apellidos, funcionarios.nombres) as funcionario_nombre"),
                'users.name as verificador',
            ])
            ->all();
    }

    public function contarVerificaciones(int $id): int
    {
        return DB::table('verificaciones')->where('item_id', $id)->count();
    }

    /**
     * Consulta con filtros y orden, sin decidir sobre los eliminados.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function consultaFiltrada(array $filtros, string $ordenarPor, string $direccion): Builder
    {
        $columna = in_array($ordenarPor, self::ORDENABLES, true) ? $ordenarPor : 'numero_inventario';
        $sentido = $direccion === 'desc' ? 'desc' : 'asc';

        return $this->consultaBase()
            ->when($filtros['busqueda'] ?? null, function (Builder $consulta, string $busqueda): void {
                $consulta->where(function (Builder $grupo) use ($busqueda): void {
                    $grupo->where('items.numero_inventario', 'like', '%'.$busqueda.'%')
                        ->orWhere('items.codigo_antiguo', 'like', '%'.$busqueda.'%')
                        ->orWhere('items.numero_serie', 'like', '%'.$busqueda.'%')
                        ->orWhere('items.marca', 'like', '%'.$busqueda.'%')
                        ->orWhere('items.modelo', 'like', '%'.$busqueda.'%');
                });
            })
            ->when($filtros['tipo_id'] ?? null, fn (Builder $consulta, int $tipo) => $consulta->where('items.tipo_id', $tipo))
            ->when($filtros['ubicacion_id'] ?? null, fn (Builder $consulta, int $ubicacion) => $consulta->where('items.ubicacion_id', $ubicacion))
            ->when($filtros['funcionario_id'] ?? null, fn (Builder $consulta, int $funcionario) => $consulta->where('items.funcionario_id', $funcionario))
            ->when($filtros['estado_conservacion'] ?? null, fn (Builder $consulta, string $estado) => $consulta->where('items.estado_conservacion', $estado))
            ->when($filtros['situacion'] ?? null, fn (Builder $consulta, string $situacion) => $consulta->where('items.situacion', $situacion))
            ->when($filtros['vencimiento'] ?? null, function (Builder $consulta, string $vencimiento): void {
                match ($vencimiento) {
                    'vencidos' => $consulta->whereNotNull('items.fecha_vencimiento')
                        ->whereDate('items.fecha_vencimiento', '<', now()->toDateString()),
                    'por_vencer' => $consulta->whereNotNull('items.fecha_vencimiento')
                        ->whereDate('items.fecha_vencimiento', '>=', now()->toDateString())
                        ->whereDate('items.fecha_vencimiento', '<=', now()->addDays(30)->toDateString()),
                    default => $consulta,
                };
            })
            ->orderBy('items.'.$columna, $sentido);
    }

    /**
     * Consulta base con los nombres de tipo, ubicación, responsable y padre.
     */
    private function consultaBase(): Builder
    {
        return DB::table('items')
            ->leftJoin('tipos', 'tipos.id', '=', 'items.tipo_id')
            ->leftJoin('ubicaciones', 'ubicaciones.id', '=', 'items.ubicacion_id')
            ->leftJoin('funcionarios', 'funcionarios.id', '=', 'items.funcionario_id')
            ->leftJoin('items as padres', 'padres.id', '=', 'items.item_padre_id')
            ->select([
                'items.*',
                'tipos.nombre as tipo_nombre',
                'tipos.requiere_serie',
                'tipos.controla_vencimiento',
                'ubicaciones.nombre as ubicacion_nombre',
                DB::raw("concat_ws(', ', funcionarios.apellidos, funcionarios.nombres) as funcionario_nombre"),
                'padres.numero_inventario as item_padre_numero',
            ]);
    }
}
