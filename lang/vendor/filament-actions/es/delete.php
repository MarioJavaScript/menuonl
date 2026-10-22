<?php

// Textos de MENUONL (DISEÑO.md). Laravel los combina con los del paquete: solo se listan los que cambian.
return [

    'single' => [
        'label' => 'Eliminar',
        'modal' => [
            'heading' => '¿Eliminar :label?',
            'actions' => [
                'delete' => [
                    'label' => 'Eliminar',
                ],
            ],
        ],
        'notifications' => [
            'deleted' => [
                'title' => 'Eliminado',
            ],
        ],
    ],

    'multiple' => [
        'label' => 'Eliminar seleccionados',
        'modal' => [
            'heading' => '¿Eliminar los :label seleccionados?',
            'actions' => [
                'delete' => [
                    'label' => 'Eliminar',
                ],
            ],
        ],
        'notifications' => [
            'deleted' => [
                'title' => 'Eliminados',
            ],
            'deleted_partial' => [
                'title' => 'Se eliminaron :count de :total',
                'missing_authorization_failure_message' => 'No tienes permiso para eliminar :count.',
                'missing_processing_failure_message' => 'No pudimos eliminar :count.',
            ],
            'deleted_none' => [
                'title' => 'No se eliminó nada',
                'missing_authorization_failure_message' => 'No tienes permiso para eliminar :count.',
                'missing_processing_failure_message' => 'No pudimos eliminar :count.',
            ],
        ],
    ],

];
