<?php

namespace App\Services\Procesos;

use App\DTOs\Auditoria\RegistroActividadDTO;
use App\DTOs\Procesos\GuardarProcesoDTO;
use App\DTOs\Procesos\ProcesoDTO;
use App\Repositories\Procesos\ProcesoRepository;
use App\Services\Auditoria\RegistroActividadService;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcesoService
{
    /**
     * Módulo con el que se identifica la tabla en la bitácora de actividad.
     */
    private const MODULO = 'procesos_verificacion';

    /**
     * Estados admitidos por el enum, con su etiqueta de interfaz.
     *
     * @var array<string, string>
     */
    public const ESTADOS = [
        'abierto' => 'Abierto',
        'cerrado' => 'Cerrado',
    ];

    public function __construct(
        private ProcesoRepository $repositorio,
        private RegistroActividadService $bitacora,
    ) {}

    /**
     * @param  array{busqueda?: string|null, estado?: string|null}  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'fecha_inicio', string $direccion = 'desc'): LengthAwarePaginator
    {
        return $this->repositorio->paginar($filtros, $porPagina, $ordenarPor, $direccion);
    }

    public function obtener(int $id): ?ProcesoDTO
    {
        $fila = $this->repositorio->buscarPorId($id);

        return $fila === null ? null : ProcesoDTO::desdeFila($fila);
    }

    /**
     * Proceso abierto, si lo hay. Es el único sobre el que se puede verificar.
     */
    public function abierto(): ?ProcesoDTO
    {
        $fila = $this->repositorio->abierto();

        return $fila === null ? null : ProcesoDTO::desdeFila($fila);
    }

    /**
     * Abre una toma de inventario.
     *
     * @throws DomainException
     */
    public function crear(GuardarProcesoDTO $datos): int
    {
        if ($datos->nombre === '') {
            throw new DomainException('El nombre del proceso es obligatorio.');
        }

        if ($this->repositorio->existeNombre($datos->nombre)) {
            throw new DomainException('Ya existe un proceso con ese nombre.');
        }

        if ($this->repositorio->existeAbierto()) {
            throw new DomainException('Ya hay un proceso abierto. Ciérrelo antes de abrir otro.');
        }

        $this->validarFecha($datos->fechaInicio);

        $id = DB::transaction(function () use ($datos): int {
            $ahora = now();

            return $this->repositorio->insertar([
                'nombre' => $datos->nombre,
                'fecha_inicio' => $datos->fechaInicio,
                'fecha_cierre' => null,
                'estado' => 'abierto',
                'user_id' => $datos->userId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        });

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'created',
            descripcion: 'Proceso de verificación abierto',
            registroId: $id,
            valoresNuevos: [
                'nombre' => $datos->nombre,
                'fecha_inicio' => $datos->fechaInicio,
                'estado' => 'abierto',
            ],
        ));

        return $id;
    }

    /**
     * @throws DomainException
     */
    public function actualizar(int $id, GuardarProcesoDTO $datos): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El proceso no existe.');
        }

        if ($actual->estado === 'cerrado') {
            throw new DomainException('No se puede modificar un proceso cerrado.');
        }

        if ($datos->nombre === '') {
            throw new DomainException('El nombre del proceso es obligatorio.');
        }

        if ($this->repositorio->existeNombre($datos->nombre, $id)) {
            throw new DomainException('Ya existe un proceso con ese nombre.');
        }

        $this->validarFecha($datos->fechaInicio);

        DB::transaction(function () use ($id, $datos): void {
            $this->repositorio->actualizar($id, [
                'nombre' => $datos->nombre,
                'fecha_inicio' => $datos->fechaInicio,
                'user_id' => $datos->userId,
                'updated_at' => now(),
            ]);
        });

        $this->bitacora->registrarActualizacion(
            modulo: self::MODULO,
            registroId: $id,
            anteriores: [
                'nombre' => $actual->nombre,
                'fecha_inicio' => $actual->fecha_inicio,
                'user_id' => (int) $actual->user_id,
            ],
            nuevos: [
                'nombre' => $datos->nombre,
                'fecha_inicio' => $datos->fechaInicio,
                'user_id' => $datos->userId,
            ],
            descripcion: 'Proceso de verificación actualizado',
        );
    }

    /**
     * Cierra el proceso: registra la fecha y bloquea nuevas verificaciones.
     *
     * @throws DomainException
     */
    public function cerrar(int $id, ?string $fechaCierre = null): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El proceso no existe.');
        }

        if ($actual->estado === 'cerrado') {
            throw new DomainException('El proceso ya está cerrado.');
        }

        $fecha = $fechaCierre ?? now()->toDateString();

        $this->validarFecha($fecha);

        if (Carbon::parse($fecha)->lt(Carbon::parse($actual->fecha_inicio))) {
            throw new DomainException('La fecha de cierre no puede ser anterior a la de inicio.');
        }

        $this->repositorio->actualizar($id, [
            'estado' => 'cerrado',
            'fecha_cierre' => $fecha,
            'updated_at' => now(),
        ]);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'closed',
            descripcion: 'Proceso de verificación cerrado',
            registroId: $id,
            valoresAnteriores: ['estado' => 'abierto', 'fecha_cierre' => null],
            valoresNuevos: ['estado' => 'cerrado', 'fecha_cierre' => $fecha],
            propiedades: ['verificaciones' => $this->repositorio->contarVerificaciones($id)],
        ));
    }

    /**
     * Reabre un proceso cerrado. La restricción de un solo proceso abierto
     * también rige aquí.
     *
     * @throws DomainException
     */
    public function reabrir(int $id): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El proceso no existe.');
        }

        if ($actual->estado === 'abierto') {
            throw new DomainException('El proceso ya está abierto.');
        }

        if ($this->repositorio->existeAbierto($id)) {
            throw new DomainException('Ya hay un proceso abierto. Ciérrelo antes de reabrir este.');
        }

        $this->repositorio->actualizar($id, [
            'estado' => 'abierto',
            'fecha_cierre' => null,
            'updated_at' => now(),
        ]);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'reopened',
            descripcion: 'Proceso de verificación reabierto',
            registroId: $id,
            valoresAnteriores: ['estado' => 'cerrado', 'fecha_cierre' => $actual->fecha_cierre],
            valoresNuevos: ['estado' => 'abierto', 'fecha_cierre' => null],
        ));
    }

    /**
     * Elimina un proceso que todavía no registra verificaciones.
     *
     * @throws DomainException
     */
    public function eliminar(int $id): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El proceso no existe.');
        }

        $verificaciones = $this->repositorio->contarVerificaciones($id);

        if ($verificaciones > 0) {
            throw new DomainException(
                'No se puede eliminar el proceso porque tiene '.$verificaciones.' verificación(es) registrada(s).',
            );
        }

        $this->repositorio->eliminar($id);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'deleted',
            descripcion: 'Proceso de verificación eliminado',
            registroId: $id,
            valoresAnteriores: [
                'nombre' => $actual->nombre,
                'fecha_inicio' => $actual->fecha_inicio,
                'estado' => $actual->estado,
            ],
        ));
    }

    /**
     * @throws DomainException
     */
    private function validarFecha(string $fecha): void
    {
        if ($fecha === '' || strtotime($fecha) === false) {
            throw new DomainException('La fecha informada no es válida.');
        }
    }
}
