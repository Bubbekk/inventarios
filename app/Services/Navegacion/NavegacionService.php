<?php

namespace App\Services\Navegacion;

use App\DTOs\Navegacion\ModuloNavegacionDTO;
use App\DTOs\Navegacion\SeccionNavegacionDTO;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class NavegacionService
{
    /**
     * Secciones que la cuenta autenticada puede ver.
     *
     * Una entrada se descarta si su ruta todavía no está declarada o si la
     * cuenta carece del permiso. Una sección con módulos se descarta cuando
     * ninguno de ellos queda visible.
     *
     * @return array<int, SeccionNavegacionDTO>
     */
    public function seccionesVisibles(): array
    {
        $usuario = Auth::user();
        $secciones = [];

        foreach (config('navegacion.secciones', []) as $clave => $datos) {
            $modulos = $this->modulosVisibles($datos['modulos'] ?? [], $usuario);

            if ($modulos === [] && ! $this->entradaVisible($datos['ruta'] ?? null, $datos['permiso'] ?? null, $usuario)) {
                continue;
            }

            $secciones[] = SeccionNavegacionDTO::desdeArreglo($clave, $datos, $modulos);
        }

        return $secciones;
    }

    /**
     * Sección a la que pertenece la ruta actual.
     */
    public function seccionActual(): ?SeccionNavegacionDTO
    {
        foreach ($this->seccionesVisibles() as $seccion) {
            if ($seccion->modulos === [] && Route::currentRouteName() === $seccion->ruta) {
                return $seccion;
            }

            foreach ($seccion->modulos as $modulo) {
                if (Route::currentRouteName() === $modulo->ruta) {
                    return $seccion;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{titulo: string, ruta: string, icono: string, permiso?: string|null}>  $modulos
     * @return array<int, ModuloNavegacionDTO>
     */
    private function modulosVisibles(array $modulos, ?Authorizable $usuario): array
    {
        $visibles = [];

        foreach ($modulos as $modulo) {
            if ($this->entradaVisible($modulo['ruta'], $modulo['permiso'] ?? null, $usuario)) {
                $visibles[] = ModuloNavegacionDTO::desdeArreglo($modulo);
            }
        }

        return $visibles;
    }

    private function entradaVisible(?string $ruta, ?string $permiso, ?Authorizable $usuario): bool
    {
        if ($ruta === null || ! Route::has($ruta)) {
            return false;
        }

        if ($permiso === null) {
            return true;
        }

        return $usuario !== null && $usuario->can($permiso);
    }
}
