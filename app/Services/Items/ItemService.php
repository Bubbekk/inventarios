<?php

namespace App\Services\Items;

use App\DTOs\Auditoria\RegistroActividadDTO;
use App\DTOs\Items\GuardarItemDTO;
use App\DTOs\Items\ItemDTO;
use App\Repositories\Items\ItemRepository;
use App\Repositories\Tipos\TipoRepository;
use App\Services\Auditoria\RegistroActividadService;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ItemService
{
    /**
     * Módulo con el que se identifica la tabla en la bitácora de actividad.
     */
    private const MODULO = 'items';

    /**
     * Estados de conservación admitidos por el enum, con su etiqueta de interfaz.
     *
     * @var array<string, string>
     */
    public const ESTADOS = [
        'bueno' => 'Bueno',
        'regular' => 'Regular',
        'malo' => 'Malo',
    ];

    /**
     * Situaciones administrativas admitidas por el enum.
     *
     * @var array<string, string>
     */
    public const SITUACIONES = [
        'registrado_daf' => 'Registrado en DAF',
        'sin_registro_daf' => 'Sin registro DAF',
        'solicitud_baja' => 'Solicitud de baja',
        'retirado' => 'Retirado',
    ];

    public function __construct(
        private ItemRepository $repositorio,
        private TipoRepository $tipos,
        private RegistroActividadService $bitacora,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function paginar(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'numero_inventario', string $direccion = 'asc'): LengthAwarePaginator
    {
        return $this->repositorio->paginar($filtros, $porPagina, $ordenarPor, $direccion);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function paginarEliminados(array $filtros = [], int $porPagina = 15, string $ordenarPor = 'numero_inventario', string $direccion = 'asc'): LengthAwarePaginator
    {
        return $this->repositorio->paginarEliminados($filtros, $porPagina, $ordenarPor, $direccion);
    }

    public function obtener(int $id, bool $incluirEliminados = false): ?ItemDTO
    {
        $fila = $this->repositorio->buscarPorId($id, $incluirEliminados);

        return $fila === null ? null : ItemDTO::desdeFila($fila);
    }

    /**
     * Componentes directos del ítem.
     *
     * @return array<int, ItemDTO>
     */
    public function componentesDe(int $id): array
    {
        return ItemDTO::desdeFilas($this->repositorio->componentesDe($id));
    }

    /**
     * Verificaciones registradas sobre el ítem.
     *
     * @return array<int, object>
     */
    public function verificacionesDe(int $id): array
    {
        return $this->repositorio->verificacionesDe($id);
    }

    /**
     * Candidatos a ítem padre, excluido el propio ítem y toda su descendencia.
     *
     * @return array<int, object>
     */
    public function candidatosAPadre(?int $itemId = null): array
    {
        $excluidos = $itemId === null
            ? []
            : array_merge([$itemId], $this->repositorio->descendientesDe($itemId));

        return $this->repositorio->candidatosAPadre($excluidos);
    }

    /**
     * Ítems vencidos o próximos a vencer.
     *
     * @return array<int, ItemDTO>
     */
    public function proximosAVencer(int $dias = 30): array
    {
        return ItemDTO::desdeFilas($this->repositorio->proximosAVencer($dias));
    }

    /**
     * @throws DomainException
     */
    public function crear(GuardarItemDTO $datos): int
    {
        $tipo = $this->tipoObligatorio($datos->tipoId);

        $this->validarEnumerados($datos);
        $this->validarNumeroInventario($datos->numeroInventario);
        $this->validarSegunTipo($datos, $tipo);
        $this->validarPadre($datos->itemPadreId);

        $situacion = $this->situacionResuelta($datos);

        $id = DB::transaction(function () use ($datos, $situacion): int {
            $ahora = now();

            return $this->repositorio->insertar([
                'numero_inventario' => $datos->numeroInventario,
                'codigo_antiguo' => $datos->codigoAntiguo,
                'tipo_id' => $datos->tipoId,
                'ubicacion_id' => $datos->ubicacionId,
                'funcionario_id' => $datos->funcionarioId,
                'item_padre_id' => $datos->itemPadreId,
                'marca' => $datos->marca,
                'modelo' => $datos->modelo,
                'numero_serie' => $datos->numeroSerie,
                'descripcion' => $datos->descripcion,
                'fecha_vencimiento' => $datos->fechaVencimiento,
                'estado_conservacion' => $datos->estadoConservacion,
                'situacion' => $situacion,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        });

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'created',
            descripcion: 'Ítem creado',
            registroId: $id,
            valoresNuevos: [
                'numero_inventario' => $datos->numeroInventario,
                'tipo_id' => $datos->tipoId,
                'situacion' => $situacion,
                'estado_conservacion' => $datos->estadoConservacion,
            ],
        ));

        return $id;
    }

    /**
     * @throws DomainException
     */
    public function actualizar(int $id, GuardarItemDTO $datos): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El ítem no existe.');
        }

        $tipo = $this->tipoObligatorio($datos->tipoId);

        $this->validarEnumerados($datos);
        $this->validarNumeroInventario($datos->numeroInventario, $id);
        $this->validarSegunTipo($datos, $tipo);
        $this->validarPadre($datos->itemPadreId, $id);

        $situacion = $datos->situacion ?? $actual->situacion;

        DB::transaction(function () use ($id, $datos, $situacion): void {
            $this->repositorio->actualizar($id, [
                'numero_inventario' => $datos->numeroInventario,
                'codigo_antiguo' => $datos->codigoAntiguo,
                'tipo_id' => $datos->tipoId,
                'ubicacion_id' => $datos->ubicacionId,
                'funcionario_id' => $datos->funcionarioId,
                'item_padre_id' => $datos->itemPadreId,
                'marca' => $datos->marca,
                'modelo' => $datos->modelo,
                'numero_serie' => $datos->numeroSerie,
                'descripcion' => $datos->descripcion,
                'fecha_vencimiento' => $datos->fechaVencimiento,
                'estado_conservacion' => $datos->estadoConservacion,
                'situacion' => $situacion,
                'updated_at' => now(),
            ]);
        });

        $this->bitacora->registrarActualizacion(
            modulo: self::MODULO,
            registroId: $id,
            anteriores: [
                'numero_inventario' => $actual->numero_inventario,
                'codigo_antiguo' => $actual->codigo_antiguo,
                'tipo_id' => (int) $actual->tipo_id,
                'ubicacion_id' => $actual->ubicacion_id === null ? null : (int) $actual->ubicacion_id,
                'funcionario_id' => $actual->funcionario_id === null ? null : (int) $actual->funcionario_id,
                'item_padre_id' => $actual->item_padre_id === null ? null : (int) $actual->item_padre_id,
                'marca' => $actual->marca,
                'modelo' => $actual->modelo,
                'numero_serie' => $actual->numero_serie,
                'fecha_vencimiento' => $actual->fecha_vencimiento,
                'estado_conservacion' => $actual->estado_conservacion,
                'situacion' => $actual->situacion,
            ],
            nuevos: [
                'numero_inventario' => $datos->numeroInventario,
                'codigo_antiguo' => $datos->codigoAntiguo,
                'tipo_id' => $datos->tipoId,
                'ubicacion_id' => $datos->ubicacionId,
                'funcionario_id' => $datos->funcionarioId,
                'item_padre_id' => $datos->itemPadreId,
                'marca' => $datos->marca,
                'modelo' => $datos->modelo,
                'numero_serie' => $datos->numeroSerie,
                'fecha_vencimiento' => $datos->fechaVencimiento,
                'estado_conservacion' => $datos->estadoConservacion,
                'situacion' => $situacion,
            ],
            descripcion: 'Ítem actualizado',
        );
    }

    /**
     * Eliminación lógica. Sin Eloquent no hay SoftDeletes: el Repository escribe
     * deleted_at y todas sus consultas filtran esa columna de forma explícita.
     *
     * @throws DomainException
     */
    public function eliminar(int $id): void
    {
        $actual = $this->repositorio->buscarPorId($id);

        if ($actual === null) {
            throw new DomainException('El ítem no existe.');
        }

        $componentes = $this->repositorio->contarComponentes($id);

        if ($componentes > 0) {
            throw new DomainException(
                'No se puede eliminar el ítem porque tiene '.$componentes.' componente(s) asociado(s).',
            );
        }

        $this->repositorio->eliminarLogico($id);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'deleted',
            descripcion: 'Ítem eliminado',
            registroId: $id,
            valoresAnteriores: [
                'numero_inventario' => $actual->numero_inventario,
                'situacion' => $actual->situacion,
            ],
        ));
    }

    /**
     * @throws DomainException
     */
    public function restaurar(int $id): void
    {
        $actual = $this->repositorio->buscarPorId($id, incluirEliminados: true);

        if ($actual === null) {
            throw new DomainException('El ítem no existe.');
        }

        if ($actual->deleted_at === null) {
            return;
        }

        $this->repositorio->restaurar($id);

        $this->bitacora->registrar(new RegistroActividadDTO(
            modulo: self::MODULO,
            evento: 'restored',
            descripcion: 'Ítem restaurado',
            registroId: $id,
            valoresNuevos: [
                'numero_inventario' => $actual->numero_inventario,
            ],
        ));
    }

    /**
     * Situación al registrar: el número de inventario DAF determina el valor
     * cuando el formulario no lo informa explícitamente.
     */
    private function situacionResuelta(GuardarItemDTO $datos): string
    {
        if ($datos->situacion !== null) {
            return $datos->situacion;
        }

        return $datos->numeroInventario === null ? 'sin_registro_daf' : 'registrado_daf';
    }

    /**
     * @throws DomainException
     */
    private function tipoObligatorio(int $tipoId): object
    {
        $tipo = $this->tipos->buscarPorId($tipoId);

        if ($tipo === null) {
            throw new DomainException('El tipo indicado no existe.');
        }

        return $tipo;
    }

    /**
     * @throws DomainException
     */
    private function validarEnumerados(GuardarItemDTO $datos): void
    {
        if (! array_key_exists($datos->estadoConservacion, self::ESTADOS)) {
            throw new DomainException('El estado de conservación indicado no es válido.');
        }

        if ($datos->situacion !== null && ! array_key_exists($datos->situacion, self::SITUACIONES)) {
            throw new DomainException('La situación indicada no es válida.');
        }
    }

    /**
     * @throws DomainException
     */
    private function validarNumeroInventario(?string $numero, ?int $exceptoId = null): void
    {
        if ($numero === null) {
            return;
        }

        if ($this->repositorio->existeNumeroInventario($numero, $exceptoId)) {
            throw new DomainException('Ya existe un ítem con ese número de inventario.');
        }
    }

    /**
     * Aplica las reglas que impone el tipo del bien.
     *
     * @throws DomainException
     */
    private function validarSegunTipo(GuardarItemDTO $datos, object $tipo): void
    {
        if ((bool) $tipo->requiere_serie && $datos->numeroSerie === null) {
            throw new DomainException('El tipo '.$tipo->nombre.' exige informar el número de serie.');
        }

        if ((bool) $tipo->controla_vencimiento && $datos->fechaVencimiento === null) {
            throw new DomainException('El tipo '.$tipo->nombre.' exige informar la fecha de vencimiento.');
        }
    }

    /**
     * Impide la autorreferencia y las referencias circulares en la jerarquía.
     *
     * @throws DomainException
     */
    private function validarPadre(?int $padreId, ?int $itemId = null): void
    {
        if ($padreId === null) {
            return;
        }

        if ($itemId !== null && $padreId === $itemId) {
            throw new DomainException('Un ítem no puede ser componente de sí mismo.');
        }

        if ($this->repositorio->buscarPorId($padreId) === null) {
            throw new DomainException('El ítem padre indicado no existe.');
        }

        if ($itemId !== null && in_array($padreId, $this->repositorio->descendientesDe($itemId), true)) {
            throw new DomainException('El ítem padre indicado es componente de este ítem.');
        }
    }
}
