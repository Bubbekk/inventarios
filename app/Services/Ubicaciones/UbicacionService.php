<?php

namespace App\Services\Ubicaciones;

use App\DTOs\Auditoria\RegistroActividadDTO;
use App\DTOs\Ubicaciones\GuardarUbicacionDTO;
use App\DTOs\Ubicaciones\UbicacionDTO;
use App\Repositories\Ubicaciones\UbicacionRepository;
use App\Services\Auditoria\RegistroActividadService;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UbicacionService
{
    /**
     * Módulo con el que se identifica la tabla en la bitácora de actividad.
     */
    private const MODULO = 'ubicaciones';

    /**
     * Tipos admitidos por el enum de la tabla, con su etiqueta de interfaz.
     *
     * @var array<string, string>
     */
    public const TIPOS = [
        'oficina' => 'Oficina',
        'bodega' => 'Bodega',
        'vehiculo' => 'Vehículo',
        'via_publica' => 'Vía pública',
    ];

    public function __construct(
        private UbicacionRepository $repositorio,
        private RegistroActividadService $bitacora,
    ) {}

    /**
     * @param  array{busqueda?: string|null, tipo?: string|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'nombre', string $direccion = 'asc'): LengthAwarePaginator
    {
        return $this->repositorio->paginar($filtros, $porPagina, $ordenarPor, $direccion);
    }

    public function obtener(int $id): ?UbicacionDTO
    {
        $fila = $this->repositorio->buscarPorId($id);

        return $fila === null ? null : UbicacionDTO::desdeFila($fila);
    }

    /**
     * Catálogo completo, para los selectores de otros módulos.
     *
     * @return array<int, UbicacionDTO>
     */
    public function listar(): array
    {
        return array_map(
            static fn (object $fila): UbicacionDTO => UbicacionDTO::desdeFila($fila),
            $this->repositorio->todas(),
        );
    }

    /**
     * Etiqueta de interfaz de un tipo de ubicación.
     */
    public static function etiquetaTipo(string $tipo): string
    {
        return self::TIPOS[$tipo] ?? $tipo;
    }

    /**
     * @throws DomainException
     */
    public function crear(GuardarUbicacionDTO $datos): int
    {
        $this->validarNombre($datos->nombre);
        $this->validarTipo($datos->tipo);

        $id = DB::transaction(function () use ($datos): int {
            $ahora = now();

            return $this->repositorio->insertar([
                'nombre' => $datos->nombre,
                'tipo' => $datos->tipo,
                'direccion' => $datos->direccion,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        });

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'created',
            descripcion: 'Ubicación creada',
            registroId: $id,
            valoresNuevos: [
                'nombre' => $datos->nombre,
                'tipo' => $datos->tipo,
                'direccion' => $datos->direccion,
            ],
        ));

        return $id;
    }

    /**
     * @throws DomainException
     */
    public function actualizar(int $id, GuardarUbicacionDTO $datos): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('La ubicación no existe.');
        }

        $this->validarNombre($datos->nombre, $id);
        $this->validarTipo($datos->tipo);

        DB::transaction(function () use ($id, $datos): void {
            $this->repositorio->actualizar($id, [
                'nombre' => $datos->nombre,
                'tipo' => $datos->tipo,
                'direccion' => $datos->direccion,
                'updated_at' => now(),
            ]);
        });

        $this->bitacora->registrarActualizacion(
            modulo: self::MODULO,
            registroId: $id,
            anteriores: [
                'nombre' => $actual->nombre,
                'tipo' => $actual->tipo,
                'direccion' => $actual->direccion,
            ],
            nuevos: [
                'nombre' => $datos->nombre,
                'tipo' => $datos->tipo,
                'direccion' => $datos->direccion,
            ],
            descripcion: 'Ubicación actualizada',
        );
    }

    /**
     * Elimina la ubicación. La clave foránea de items es restrictiva y la de
     * verificaciones anula el valor, por lo que ambos casos se verifican antes.
     *
     * @throws DomainException
     */
    public function eliminar(int $id): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('La ubicación no existe.');
        }

        $items = $this->repositorio->contarItems($id);

        if ($items > 0) {
            throw new DomainException(
                'No se puede eliminar la ubicación porque tiene '.$items.' ítem(s) asociado(s).',
            );
        }

        $verificaciones = $this->repositorio->contarVerificaciones($id);

        if ($verificaciones > 0) {
            throw new DomainException(
                'No se puede eliminar la ubicación porque está registrada en '.$verificaciones.' verificación(es).',
            );
        }

        $this->repositorio->eliminar($id);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'deleted',
            descripcion: 'Ubicación eliminada',
            registroId: $id,
            valoresAnteriores: [
                'nombre' => $actual->nombre,
                'tipo' => $actual->tipo,
                'direccion' => $actual->direccion,
            ],
        ));
    }

    /**
     * @throws DomainException
     */
    private function validarNombre(string $nombre, ?int $exceptoId = null): void
    {
        if ($nombre === '') {
            throw new DomainException('El nombre de la ubicación es obligatorio.');
        }

        if ($this->repositorio->existeNombre($nombre, $exceptoId)) {
            throw new DomainException('Ya existe una ubicación con ese nombre.');
        }
    }

    /**
     * @throws DomainException
     */
    private function validarTipo(string $tipo): void
    {
        if (! array_key_exists($tipo, self::TIPOS)) {
            throw new DomainException('El tipo de ubicación indicado no es válido.');
        }
    }
}
