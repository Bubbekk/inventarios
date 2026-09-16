<?php

namespace App\Services\Auditoria;

use App\DTOs\Auditoria\RegistroActividadDTO;
use App\Models\User;
use App\Repositories\Auditoria\ActividadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class RegistroActividadService
{
    public function __construct(private ActividadRepository $repositorio) {}

    /**
     * Registra un cambio en la bitácora de actividad.
     *
     * El sujeto se identifica por el nombre de la tabla del módulo y el
     * identificador del registro, porque el proyecto no usa Eloquent y
     * por lo tanto no dispone de modelos que entregar a activitylog.
     */
    public function registrar(RegistroActividadDTO $datos): int
    {
        if (! config('activitylog.enabled', true)) {
            return 0;
        }

        $causanteId = $datos->causanteId ?? Auth::id();
        $ahora = now();

        $cambios = array_filter([
            'old' => $datos->valoresAnteriores,
            'attributes' => $datos->valoresNuevos,
        ]);

        return $this->repositorio->insertar([
            'log_name' => config('activitylog.default_log_name', 'default'),
            'description' => $datos->descripcion,
            'subject_type' => $datos->modulo,
            'subject_id' => $datos->registroId,
            'event' => $datos->evento,
            'causer_type' => $causanteId === null ? null : User::class,
            'causer_id' => $causanteId,
            'attribute_changes' => $cambios === [] ? null : json_encode($cambios),
            'properties' => $datos->propiedades === [] ? null : json_encode($datos->propiedades),
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    /**
     * @param  array<string, mixed>  $anteriores
     * @param  array<string, mixed>  $nuevos
     */
    public function registrarActualizacion(string $modulo, int $registroId, array $anteriores, array $nuevos, string $descripcion): int
    {
        $modificados = array_keys(array_diff_assoc($nuevos, $anteriores));

        if ($modificados === []) {
            return 0;
        }

        return $this->registrar(new RegistroActividadDTO(
            modulo: $modulo,
            evento: 'updated',
            descripcion: $descripcion,
            registroId: $registroId,
            valoresAnteriores: array_intersect_key($anteriores, array_flip($modificados)),
            valoresNuevos: array_intersect_key($nuevos, array_flip($modificados)),
        ));
    }

    /**
     * @param  array{modulo?: string|null, evento?: string|null, causante_id?: int|null, desde?: string|null, hasta?: string|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 25): LengthAwarePaginator
    {
        return $this->repositorio->paginar($filtros, $porPagina);
    }

    /**
     * @return array<int, object>
     */
    public function historialDe(string $modulo, int $registroId): array
    {
        return $this->repositorio->historialDe($modulo, $registroId);
    }
}
