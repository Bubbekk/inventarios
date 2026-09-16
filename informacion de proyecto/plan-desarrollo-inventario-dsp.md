# Plan de desarrollo — Sistema de Inventario DSP

Versión 3. Elimina la carga inicial de datos desde la planilla. El sistema parte sin datos y el inventario se construye digitando el levantamiento real. El detalle de los cambios está en la sección final.

## Reglas transversales

1. Toda fase se cierra con sus pruebas Pest en verde (`php artisan test`). Está prohibido avanzar a la fase siguiente sin cumplirlo. La Fase 11 verifica flujos integrales, seguridad y rendimiento; no es la fase donde se escriben las pruebas.
2. Toda fase respeta el flujo Componente Livewire → Service → Repository → DTO, sin Eloquent salvo `app/Models/User.php` y `app/Actions/Fortify/`.
3. Toda incorporación de dependencias requiere autorización explícita previa y se registra en el historial técnico del protocolo.
4. Toda vista se construye íntegramente con Flux UI Pro, con fechas en formato `d-m-Y`.
5. La planilla `INVENTARIO DSP.xlsx` no es fuente de datos del sistema. Se usa solo como referencia documental para poblar los catálogos iniciales de tipos y ubicaciones.

---

## Fase 0: Decisiones previas y habilitación técnica

Objetivo: resolver lo que bloquea fases posteriores y dejar el entorno completo.

1. Política de RUT en `funcionarios`. Se define si el RUT es obligatorio con dato real, si se admite RUT provisorio o si la columna pasa a nullable. Bloquea la Fase 4.
2. Tratamiento de los responsables que no son personas naturales (centralistas de turno, bienes de uso común, bienes sin asignar): se define si se modelan como funcionarios genéricos o como ubicación sin responsable.
3. Criterio de jerarquía `item_padre_id` para kits y sus componentes, por ejemplo un kit de radio con batería y monófono. Se define si la jerarquía se registra al crear el ítem o si no se usa en la primera versión.
4. Autorización e instalación de `spatie/laravel-activitylog`. El paquete no está instalado y las Fases 2 y 10 dependen de él.
5. Autorización del paquete de exportación a Excel y PDF requerido por la Fase 9.
6. Registro de las decisiones anteriores en el historial técnico, con fecha y motivo.

Entregable: decisiones registradas y dependencias instaladas.

---

## Fase 1: Base de datos

Objetivo: construir la estructura completa del MER.

1. Migraciones de `tipos`, `ubicaciones`, `funcionarios`, `items`, `procesos_verificacion` y `verificaciones`.
2. Publicación y ejecución de las migraciones de Spatie Permission y Spatie Activitylog.
3. Claves foráneas con su comportamiento de eliminación (`restrict` o `null on delete`) según la regla de negocio de cada relación.
4. Índices en `items.numero_inventario`, `items.codigo_antiguo`, `items.numero_serie`, `verificaciones.resultado` y todas las claves foráneas.
5. Restricciones de unicidad: `tipos.nombre`, `ubicaciones.nombre`, `funcionarios.rut` según lo resuelto en la Fase 0, y único compuesto `(proceso_verificacion_id, item_id)` en `verificaciones` para hacer cumplir en base de datos la regla de una verificación por ítem por proceso.
6. `items.numero_inventario` declarado como `varchar` y tratado siempre como texto. Está prohibido castearlo a numérico: `10601018.10` y `10601018.1` son bienes distintos.
7. Seeders: catálogo inicial de tipos y ubicaciones tomado como referencia de la planilla, roles, permisos y usuario administrador. No se cargan ítems ni funcionarios.
8. Mecanismo de datos de prueba sin Eloquent. Las factories de Laravel dependen de Eloquent, prohibido por la sección 4.2 del protocolo; se construyen constructores de datos en `tests/` que insertan mediante Query Builder.

Entregable: `php artisan migrate:fresh --seed` ejecuta sin errores y los constructores de datos de prueba están operativos.

---

## Fase 2: Seguridad y control de acceso

Objetivo: definir quién puede hacer qué dentro del sistema.

1. Definición de roles: administrador, verificador y consulta.
2. Catálogo completo de permisos por módulo, definido de una sola vez para todos los módulos previstos en este plan (ver, crear, editar, eliminar, cerrar proceso, conciliar, exportar, auditar), de modo que las fases siguientes no reabran esta fase.
3. Asignación de permisos a roles.
4. Mantenedor de usuarios con asignación de rol.
5. Protección de rutas y componentes mediante middleware y verificación de permisos.
6. Registro de actividad. Sin modelos Eloquent no existe `LogsActivity`: la auditoría se emite explícitamente desde los Services con `activity()`. Se define en esta fase el mecanismo común de registro (sujeto, evento, valores anteriores y nuevos, causante) que usarán todos los Services posteriores.

Entregable: acceso restringido por rol y cambios registrados en `activity_log` desde los Services.

---

## Fase 3: Layout y navegación base

Objetivo: dejar el marco visual sobre el que se montan todos los módulos.

1. Shell de la aplicación con Flux UI Pro: barra lateral, encabezado y menú filtrado por permisos.
2. `resources/views/partials/placeholder.blade.php` para la carga diferida de componentes.
3. Declaración de rutas con `Route::livewire()` y URL en español.
4. Página de inicio mínima en la ruta raíz, sin métricas. El panel con indicadores se construye en la Fase 9.
5. Estandarización de la cromática: color de texto único, escala `zinc` para bordes, fondos y separadores.
6. Componentes compartidos de tabla, filtros y confirmación de eliminación, reutilizables por los mantenedores.

Entregable: aplicación navegable con menú por rol y patrón de vista definido.

---

## Fase 4: Mantenedores de catálogos

Objetivo: administrar los datos maestros.

1. `tipos`: listado, creación, edición, eliminación, con indicadores `requiere_serie` y `controla_vencimiento`.
2. `ubicaciones`: listado, creación, edición, eliminación, filtro por tipo.
3. `funcionarios`: listado, creación, edición, activación y desactivación, con la regla de RUT resuelta en la Fase 0. Si el RUT es obligatorio, se valida formato y dígito verificador.
4. Services, Repositories y DTOs de cada catálogo.
5. Restricción de eliminación cuando existan ítems asociados.
6. Un funcionario inactivo no se elimina y conserva su historial.

Entregable: tres catálogos operativos con pruebas Pest.

---

## Fase 5: Gestión de ítems

Objetivo: administrar el registro maestro de bienes, que se construye íntegramente desde el sistema.

1. Listado con búsqueda por número de inventario, código antiguo, serie, marca y modelo.
2. Filtros por tipo, ubicación, funcionario, estado de conservación y situación.
3. Formulario de creación y edición con validaciones:
   - número de serie obligatorio si el tipo tiene `requiere_serie`;
   - fecha de vencimiento obligatoria si el tipo tiene `controla_vencimiento`;
   - número de inventario único cuando se informa.
4. Asignación de la situación al registrar: `registrado_daf` para los bienes con número de inventario DAF, `sin_registro_daf` para los que no lo tienen.
5. Asignación de ítem padre para componentes, impidiendo autorreferencia y referencias circulares.
6. Ficha del ítem: datos, componentes, historial de verificaciones e historial de cambios.
7. Eliminación lógica y restauración. Sin Eloquent no hay `SoftDeletes`: todo Repository filtra `deleted_at` de forma explícita, y existe un método de consulta que incluye eliminados para la restauración.
8. Alerta de ítems con vencimiento próximo o vencido.

Entregable: módulo de ítems completo con pruebas de validaciones y reglas.

---

## Fase 6: Procesos de verificación

Objetivo: administrar las tomas de inventario.

1. Creación de proceso con nombre, fecha de inicio y responsable.
2. Listado de procesos con estado y avance (verificados sobre total).
3. Cierre de proceso: registra fecha de cierre y bloquea nuevas verificaciones.
4. Restricción de un solo proceso abierto a la vez, validada en el Service.
5. Reapertura de proceso solo para administrador, con registro en auditoría.

Entregable: ciclo de vida del proceso operativo.

---

## Fase 7: Registro de verificaciones

Objetivo: ejecutar la toma de inventario en terreno.

1. Selección de ubicación a revisar y carga de los ítems que deberían estar en ella.
2. Marcado de cada ítem como encontrado o no encontrado, con estado de conservación observado, código observado y funcionario.
3. Distinción entre método `fisica` y `declarada`, para no consignar como verificación presencial lo que solo fue declarado.
4. Búsqueda rápida por número de inventario, código antiguo o serie.
5. Registro de bienes sin registro (`sin_registro`) con descripción observada y sin `item_id`.
6. Detección de ítems encontrados en una ubicación distinta a la registrada.
7. Advertencia cuando el código observado no coincide con el registrado.
8. Restricción de una sola verificación por ítem dentro del mismo proceso, validada en el Service y respaldada por el índice único de la Fase 1.
9. Interfaz adaptada a dispositivos móviles y tablets.

Entregable: toma de inventario completa desde el sistema.

---

## Fase 8: Conciliación de resultados

Objetivo: aplicar lo observado al registro maestro de ítems.

1. Revisión de diferencias entre lo registrado y lo verificado (ubicación, funcionario, estado de conservación).
2. Aprobación individual o masiva de diferencias para actualizar `items`.
3. Conversión de verificaciones `sin_registro` en ítems nuevos.
4. Listado de ítems no encontrados para gestión posterior.
5. Resolución de etiquetas cruzadas mediante asociación manual entre código observado e ítem, situación conocida en la DSP: un código antiguo adherido a un bien distinto del que DAF tiene asignado.

Entregable: registro maestro actualizado según el último proceso cerrado.

---

## Fase 9: Reportes y exportación

Objetivo: entregar información para la Dirección.

1. Panel de inicio con indicadores: total de ítems, distribución por estado de conservación y situación, avance del proceso abierto y vencimientos. Reemplaza la página mínima de la Fase 3.
2. Reporte por proceso: encontrados, no encontrados y sin registro.
3. Reporte por ubicación y por funcionario.
4. Reporte de ítems con solicitud de baja o retirados.
5. Reporte de vencimientos.
6. Comparación entre dos procesos.
7. Exportación a Excel y PDF, con el paquete autorizado en la Fase 0.
8. Acta de inventario por funcionario en PDF para firma.
9. Gráficos construidos según la regla de tres capas del protocolo, con `SerieGraficoDTO::desdeFilas()`.

Entregable: reportes disponibles según permisos.

---

## Fase 10: Auditoría

Objetivo: trazabilidad de cambios.

1. Visor de `activity_log` con filtros por usuario, fecha, módulo y evento.
2. Detalle de cada cambio con valores anteriores y nuevos.
3. Historial integrado en la ficha del ítem.

Entregable: módulo de auditoría disponible solo para administrador.

---

## Fase 11: Calidad y pruebas

Objetivo: validar el sistema completo antes de producción. Las pruebas de cada módulo ya existen desde su fase; aquí se verifica el conjunto.

1. Revisión de cobertura acumulada: Services, Repositories, validaciones, permisos y flujos Livewire.
2. Pruebas de flujo completo: crear proceso, verificar, cerrar y conciliar.
3. Revisión de seguridad OWASP: autorización, validación de entradas y exportaciones.
4. Revisión de rendimiento con el volumen esperado, del orden de 300 ítems y sus verificaciones, y detección de consultas N+1.
5. Verificación del cumplimiento de las reglas de UI del protocolo.
6. Pruebas de aceptación con usuarios de la DSP.

Entregable: informe de QA y correcciones aplicadas.

---

## Fase 12: Despliegue y cierre

Objetivo: dejar el sistema en producción.

1. Configuración del servidor de producción: PHP, base de datos, HTTPS y permisos de carpetas.
2. Despliegue de la aplicación y ejecución de migraciones y seeders de producción.
3. Configuración de respaldos automáticos de la base de datos.
4. Creación de cuentas de usuario definitivas con sus roles.
5. Manual de usuario y documentación técnica.
6. Capacitación a usuarios de la DSP.
7. Levantamiento real del inventario en el sistema: registro de los bienes por parte de los usuarios de la DSP, dentro del primer proceso de verificación.
8. Entrega formal del sistema.

Entregable: sistema operativo en producción, documentado, con usuarios capacitados y con el inventario real registrado.

---

## Cambios respecto de la versión 2

| Cambio | Motivo |
|---|---|
| Se elimina la Fase 6, carga inicial de ítems desde la planilla. | Decisión del proyecto: partir con un inventario real levantado desde cero, para no arrastrar datos sucios u obsoletos. |
| Se elimina el punto 8.10, carga del levantamiento histórico como primer proceso cerrado. | Depende de la carga inicial eliminada. |
| Se elimina la ejecución de la carga inicial en producción, antes Fase 13.3. | Depende de la carga inicial eliminada. |
| Se agrega el punto 12.7, levantamiento real del inventario en producción. | El inventario pasa a construirse con los usuarios, no por importación. |
| Las fases se renumeran: el plan queda en 13 fases, de la 0 a la 12. | Consecuencia de la eliminación de una fase. |
| La planilla pasa a ser referencia documental, según la regla transversal 5. | Deja de ser fuente de datos del sistema. |
| El seeder de la Fase 1 carga solo catálogos, roles, permisos y administrador. | La base debe quedar sin ítems ni funcionarios de origen desconocido. |
| Se agrega el punto 5.4: asignación de la situación al registrar el ítem. | `registrado_daf` y `sin_registro_daf` ahora se digitan, ya no se derivan de la planilla. |
| La Fase 0 deja de referirse a la normalización de datos de la planilla. | Las decisiones sobre RUT, responsables no personales y jerarquía de kits siguen vigentes, pero ahora aplican al ingreso manual. |

## Cambios de la versión 2 respecto de la versión 1

| Cambio | Motivo |
|---|---|
| Se agregó la Fase 0. | Decisiones bloqueantes y dependencias no instaladas. |
| Se agregó la fase de layout y navegación base. | La versión 1 no contemplaba el shell, el menú, `partials.placeholder` ni las rutas base. |
| Se reordenó la carga inicial y el registro del levantamiento histórico. | En la versión 1 dependían de fases posteriores. Ambos elementos quedaron eliminados en la versión 3. |
| Se eliminó el punto de factories y se reemplazó por constructores de datos de prueba. | Las factories dependen de Eloquent, prohibido por la sección 4.2 del protocolo. |
| Se explicitó la auditoría desde los Services con `activity()` y el filtrado manual de `deleted_at`. | Sin modelos Eloquent no aplican `LogsActivity` ni `SoftDeletes`. |
| Se agregó el índice único `(proceso_verificacion_id, item_id)`. | La regla de una verificación por ítem por proceso solo estaba en la capa de aplicación. |
| Se agregó la regla transversal de cierre de fase con pruebas. | La versión 1 concentraba el aseguramiento de calidad en la fase final. |
