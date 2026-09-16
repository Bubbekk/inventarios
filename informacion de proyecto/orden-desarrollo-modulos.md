# Orden de desarrollo de módulos — Sistema de Inventario DSP

Orden aprobado para la construcción de los módulos. Las fases corresponden al documento `plan-desarrollo-inventario-dsp.md`, versión 3.

| Orden | Módulo | Fase |
|---|---|---|
| 1 | Autenticación | Ya configurada; se cierra con el control de acceso de la Fase 2 |
| 2 | Usuarios y roles | 2 |
| 3 | Layout, navegación e inicio mínimo | 3 |
| 4 | Tipos | 4 |
| 5 | Ubicaciones | 4 |
| 6 | Funcionarios | 4 |
| 7 | Ítems | 5 |
| 8 | Procesos de verificación | 6 |
| 9 | Registro de verificaciones | 7 |
| 10 | Conciliación | 8 |
| 11 | Panel de inicio con indicadores | 9 |
| 12 | Reportes y exportación | 9 |
| 13 | Auditoría | 10 |

## Funcionalidad de cada módulo

1. Autenticación: ingreso con Fortify, 2FA, sin registro público.
2. Usuarios y roles: alta de cuentas y asignación de rol (administrador, verificador, consulta).
3. Layout, navegación e inicio mínimo: shell con Flux UI Pro, menú filtrado por permisos, rutas en español y página de inicio sin métricas.
4. Tipos: catálogo de clases de bien, con los indicadores `requiere_serie` y `controla_vencimiento`.
5. Ubicaciones: catálogo de dependencias, bodegas, vehículos y vía pública.
6. Funcionarios: catálogo de responsables, con activación y desactivación sin pérdida de historial.
7. Ítems: registro maestro de bienes; alta, edición, búsqueda, filtros, ficha, componentes, eliminación lógica y alertas de vencimiento.
8. Procesos de verificación: ciclo de vida de cada toma de inventario; apertura, avance, cierre y reapertura.
9. Registro de verificaciones: captura en terreno del resultado por ítem, con ubicación y responsable observados, y registro de bienes sin inventario.
10. Conciliación: aplicación al registro maestro de las diferencias detectadas y resolución de etiquetas cruzadas.
11. Panel de inicio con indicadores: totales, distribución por estado y situación, avance del proceso abierto y vencimientos. Reemplaza la página de inicio mínima.
12. Reportes y exportación: informes por proceso, ubicación, funcionario, bajas y vencimientos, con salida a Excel y PDF y acta para firma.
13. Auditoría: visor de `activity_log` con el detalle de cada cambio, disponible solo para administrador.

## Condiciones de avance

- Ningún módulo se da por terminado sin sus pruebas Pest en verde.
- La Fase 0 del plan debe estar resuelta antes de iniciar el módulo 6, Funcionarios, porque define la política de RUT.
- Los módulos 11 y 12 requieren el paquete de exportación autorizado en la Fase 0.
- El módulo 13 requiere `spatie/laravel-activitylog` instalado, también en la Fase 0.
