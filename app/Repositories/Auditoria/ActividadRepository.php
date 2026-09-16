<?php

namespace App\Repositories\Auditoria;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ActividadRepository
{
    /**
     * Inserta un registro en la bitácora de actividad.
     *
     * @param  array<string, mixed>  $atributos
     */
    public function insertar(array $atributos): int
    {
        return DB::table('activity_log')->insertGetId($atributos);
    }

    /**
     * Devuelve la bitácora paginada, de la más reciente a la más antigua.
     *
     * @param  array{modulo?: string|null, evento?: string|null, causante_id?: int|null, desde?: string|null, hasta?: string|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 25): LengthAwarePaginator
    {
        return DB::table('activity_log')
            ->leftJoin('users', function ($union): void {
                $union->on('users.id', '=', 'activity_log.causer_id')
                    ->where('activity_log.causer_type', '=', User::class);
            })
            ->select([
                'activity_log.id',
                'activity_log.log_name',
                'activity_log.description',
                'activity_log.subject_type',
                'activity_log.subject_id',
                'activity_log.event',
                'activity_log.causer_id',
                'activity_log.attribute_changes',
                'activity_log.properties',
                'activity_log.created_at',
                'users.name as causante',
            ])
            ->when($filtros['modulo'] ?? null, fn ($consulta, $modulo) => $consulta->where('activity_log.subject_type', $modulo))
            ->when($filtros['evento'] ?? null, fn ($consulta, $evento) => $consulta->where('activity_log.event', $evento))
            ->when($filtros['causante_id'] ?? null, fn ($consulta, $causanteId) => $consulta->where('activity_log.causer_id', $causanteId))
            ->when($filtros['desde'] ?? null, fn ($consulta, $desde) => $consulta->whereDate('activity_log.created_at', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($consulta, $hasta) => $consulta->whereDate('activity_log.created_at', '<=', $hasta))
            ->orderByDesc('activity_log.id')
            ->paginate($porPagina);
    }

    /**
     * Devuelve el historial de un registro concreto.
     *
     * @return array<int, object>
     */
    public function historialDe(string $modulo, int $registroId): array
    {
        return DB::table('activity_log')
            ->where('subject_type', $modulo)
            ->where('subject_id', $registroId)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function contar(): int
    {
        return DB::table('activity_log')->count();
    }
}
