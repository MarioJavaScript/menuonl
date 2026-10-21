<?php

// Textos de MENUONL (DISEÑO.md). Laravel los combina con los del paquete: solo se listan los que cambian.
return [

    'fields' => [
        'search' => [
            'label' => 'Buscar',
            'placeholder' => 'Buscar',
        ],
    ],

    'actions' => [
        'disable_reordering' => [
            'label' => 'Listo',
        ],
        'enable_reordering' => [
            'label' => 'Cambiar orden',
        ],
        'reorder_record' => [
            'label' => 'Mover :key',
        ],
        'open_bulk_actions' => [
            'label' => 'Acciones con los seleccionados',
        ],
        'toggle_columns' => [
            'label' => 'Elegir columnas',
        ],
    ],

    'empty' => [
        'heading' => 'No hay nada aquí todavía',
        'description' => 'Cuando agregues algo, aparecerá aquí.',
    ],

    'filters' => [
        'actions' => [
            'remove_all' => [
                'label' => 'Quitar filtros',
            ],
            'reset' => [
                'label' => 'Quitar filtros',
            ],
        ],
        'trashed' => [
            'label' => 'Eliminados',
            'only_trashed' => 'Solo eliminados',
            'with_trashed' => 'Todos',
            'without_trashed' => 'Sin eliminados',
        ],
    ],

];
