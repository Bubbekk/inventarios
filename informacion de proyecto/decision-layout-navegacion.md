# Decisión de layout y navegación — Sistema de Inventario DSP

Fecha: 16-09-2026
Ámbito: módulo 3 del orden de desarrollo, Fase 3 del plan de desarrollo versión 3.

## Decisión

El sistema adopta el patrón de layout del demo "header con navegación secundaria" de Flux UI Pro, en reemplazo del layout heredado del starter kit de Livewire.

## Patrón adoptado

Tres niveles de navegación:

1. `flux:navbar` dentro de `flux:header`: secciones principales, visible solo en escritorio (`max-lg:hidden`).
2. `flux:navlist` dentro de `flux:main`, en una columna fija de `md:w-[220px]`: subsecciones del área en que se encuentra el usuario.
3. `flux:sidebar` con `collapsible="mobile"` y `lg:hidden`: navegación completa, exclusiva de dispositivos móviles, desplegada con `flux:sidebar.toggle`.

La barra lateral no se muestra en escritorio. Es el comportamiento propio de este patrón y no debe tratarse como un defecto.

## Cambios requeridos

1. `resources/views/layouts/app.blade.php`: reemplazar `<flux:main container>{{ $slot }}</flux:main>` por la estructura de dos columnas, con la columna `md:w-[220px]` que contiene el `flux:navlist`, un `flux:separator` para móvil y la columna de contenido con `flex-1`.
2. `resources/views/layouts/app/header.blade.php`: reemplazar el componente propio `x-app-logo` por `flux:brand` con `logo` y `logo:dark`, y `x-desktop-user-menu` por `flux:dropdown` con `flux:profile`.
3. Disponer los archivos de logotipo claro y oscuro en `public/`.
4. Quitar `class="dark"` de la etiqueta `<html>` en `resources/views/layouts/app/header.blade.php`, de modo que la apariencia quede gobernada por `@fluxAppearance`.
5. Mantener `lg:hidden` en `flux:sidebar`.

## Cumplimiento del protocolo

- La cromática del patrón es escala `zinc`, conforme a la sección 5.6.
- `flux:brand`, `flux:navbar`, `flux:navlist`, `flux:sidebar`, `flux:header` y `flux:main` son componentes reales de Flux UI Pro 2.19.0, verificados en `resources/views/flux`.
- No incorpora dependencias nuevas.

## Pendiente antes de implementar

Definir el reparto de los 13 módulos entre el `flux:navbar` del header y el `flux:navlist` de la página. Es una decisión de diseño de información, no técnica, y condiciona la implementación de la Fase 3.

## Antecedente

El layout actual proviene del starter kit de Livewire y no reproduce el demo por cuatro razones verificadas: ausencia de la columna con `flux:navlist`, sidebar oculto en escritorio por diseño del patrón, modo oscuro forzado en la etiqueta `<html>`, y uso de componentes propios de marca y perfil en lugar de `flux:brand` y `flux:profile`.
