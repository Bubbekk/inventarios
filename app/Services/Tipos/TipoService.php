<?php

namespace App\Services\Tipos;

use App\DTOs\Auditoria\RegistroActividadDTO;
use App\DTOs\Tipos\GuardarTipoDTO;
use App\DTOs\Tipos\TipoDTO;
use App\Repositories\Tipos\TipoRepository;
use App\Services\Auditoria\RegistroActividadService;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TipoService
{
    /**
     * Módulo con el que se identifica la tabla en la bitácora de actividad.
     */
    private const MODULO = 'tipos';

    public function __construct(
        private TipoRepository $repositorio,
        private RegistroActividadService $bitacora,
    ) {}

    /**
     * @param  array{busqueda?: string|null, requiere_serie?: bool|null, controla_vencimiento?: bool|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'nombre', string $direccion = 'asc'): LengthAwarePaginator
    {
        return $this->repositorio->paginar($filtros, $porPagina, $ordenarPor, $direccion);
    }

    public function obtener(int $id): ?TipoDTO
    {
        $fila = $this->repositorio->buscarPorId($id);

        return $fila === null ? null : TipoDTO::desdeFila($fila);
    }

    /**
     * Catálogo completo, para los selectores de otros módulos.
     *
     * @return array<int, TipoDTO>
     */
    public function listar(): array
    {
        return array_map(
            static fn (object $fila): TipoDTO => TipoDTO::desdeFila($fila),
            $this->repositorio->todos(),
        );
    }

    /**
     * @throws DomainException
     */
    public function crear(GuardarTipoDTO $datos): int
    {
        $this->validarNombre($datos->nombre);

        $id = DB::transaction(function () use ($datos): int {
            $ahora = now();

            return $this->repositorio->insertar([
                'nombre' => $datos->nombre,
                'requiere_serie' => $datos->requiereSerie,
                'controla_vencimiento' => $datos->controlaVencimiento,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        });

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'created',
            descripcion: 'Tipo creado',
            registroId: $id,
            valoresNuevos: [
                'nombre' => $datos->nombre,
                'requiere_serie' => $datos->requiereSerie,
                'controla_vencimiento' => $datos->controlaVencimiento,
            ],
        ));

        return $id;
    }

    /**
     * @throws DomainException
     */
    public function actualizar(int $id, GuardarTipoDTO $datos): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El tipo no existe.');
        }

        $this->validarNombre($datos->nombre, $id);

        DB::transaction(function () use ($id, $datos): void {
            $this->repositorio->actualizar($id, [
                'nombre' => $datos->nombre,
                'requiere_serie' => $datos->requiereSerie,
                'controla_vencimiento' => $datos->controlaVencimiento,
                'updated_at' => now(),
            ]);
        });

        $this->bitacora->registrarActualizacion(
            modulo: self::MODULO,
            registroId: $id,
            anteriores: [
                'nombre' => $actual->nombre,
                'requiere_serie' => (bool) $actual->requiere_serie,
                'controla_vencimiento' => (bool) $actual->controla_vencimiento,
            ],
            nuevos: [
                'nombre' => $datos->nombre,
                'requiere_serie' => $datos->requiereSerie,
                'controla_vencimiento' => $datos->controlaVencimiento,
            ],
            descripcion: 'Tipo actualizado',
        );
    }

    /**
     * Elimina el tipo. La clave foránea de items es restrictiva, por lo que la
     * regla se verifica antes de intentar el borrado.
     *
     * @throws DomainException
     */
    public function eliminar(int $id): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El tipo no existe.');
        }

        $asociados = $this->repositorio->contarItems($id);

        if ($asociados > 0) {
            throw new DomainException(
                'No se puede eliminar el tipo porque tiene '.$asociados.' ítem(s) asociado(s).',
            );
        }

        $this->repositorio->eliminar($id);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'deleted',
            descripcion: 'Tipo eliminado',
            registroId: $id,
            valoresAnteriores: [
                'nombre' => $actual->nombre,
                'requiere_serie' => (bool) $actual->requiere_serie,
                'controla_vencimiento' => (bool) $actual->controla_vencimiento,
            ],
        ));
    }

    /**
     * @throws DomainException
     */
    private function validarNombre(string $nombre, ?int $exceptoId = null): void
    {
        if ($nombre === '') {
            throw new DomainException('El nombre del tipo es obligatorio.');
        }

        if ($this->repositorio->existeNombre($nombre, $exceptoId)) {
            throw new DomainException('Ya existe un tipo con ese nombre.');
        }
    }
}
