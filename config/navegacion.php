<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Estructura de navegación
    |--------------------------------------------------------------------------
    |
    | Cada sección se muestra en el flux:navbar del encabezado y sus módulos en
    | el flux:navlist de la columna izquierda. Una entrada aparece solo si su
    | ruta existe y la cuenta tiene el permiso indicado; una sección se oculta
    | cuando ninguno de sus módulos es visible.
    |
    | Reparto acordado el 16-09-2026, registrado en
    | "informacion de proyecto/decision-layout-navegacion.md".
    |
    */

    'secciones' => [

        'inicio' => [
            'titulo' => 'Inicio',
            'icono' => 'home',
            'ruta' => 'inicio',
            'permiso' => null,
            'modulos' => [],
        ],

        'inventario' => [
            'titulo' => 'Inventario',
            'icono' => 'archive-box',
            'modulos' => [
                ['titulo' => 'Ítems', 'ruta' => 'items.indice', 'permiso' => 'items.ver', 'icono' => 'cube'],
                ['titulo' => 'Tipos', 'ruta' => 'tipos.indice', 'permiso' => 'tipos.ver', 'icono' => 'tag'],
                ['titulo' => 'Ubicaciones', 'ruta' => 'ubicaciones.indice', 'permiso' => 'ubicaciones.ver', 'icono' => 'map-pin'],
                ['titulo' => 'Funcionarios', 'ruta' => 'funcionarios.indice', 'permiso' => 'funcionarios.ver', 'icono' => 'identification'],
            ],
        ],

        'verificacion' => [
            'titulo' => 'Verificación',
            'icono' => 'clipboard-document-check',
            'modulos' => [
                ['titulo' => 'Procesos', 'ruta' => 'procesos.indice', 'permiso' => 'procesos.ver', 'icono' => 'clipboard-document-list'],
                ['titulo' => 'Registro', 'ruta' => 'verificaciones.indice', 'permiso' => 'verificaciones.ver', 'icono' => 'clipboard-document-check'],
                ['titulo' => 'Conciliación', 'ruta' => 'conciliacion.indice', 'permiso' => 'conciliacion.ver', 'icono' => 'arrows-right-left'],
            ],
        ],

        'reportes' => [
            'titulo' => 'Reportes',
            'icono' => 'chart-bar',
            'modulos' => [
                ['titulo' => 'Por proceso', 'ruta' => 'reportes.proceso', 'permiso' => 'reportes.ver', 'icono' => 'document-chart-bar'],
                ['titulo' => 'Por ubicación', 'ruta' => 'reportes.ubicacion', 'permiso' => 'reportes.ver', 'icono' => 'map-pin'],
                ['titulo' => 'Por funcionario', 'ruta' => 'reportes.funcionario', 'permiso' => 'reportes.ver', 'icono' => 'identification'],
                ['titulo' => 'Vencimientos', 'ruta' => 'reportes.vencimientos', 'permiso' => 'reportes.ver', 'icono' => 'chart-bar'],
            ],
        ],

        'administracion' => [
            'titulo' => 'Administración',
            'icono' => 'shield-check',
            'modulos' => [
                ['titulo' => 'Usuarios', 'ruta' => 'usuarios.indice', 'permiso' => 'usuarios.ver', 'icono' => 'users'],
                ['titulo' => 'Auditoría', 'ruta' => 'auditoria.indice', 'permiso' => 'auditoria.ver', 'icono' => 'clipboard-document-list'],
            ],
        ],

    ],

];
