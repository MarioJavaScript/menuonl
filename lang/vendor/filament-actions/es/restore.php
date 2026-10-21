<?php

// Textos de MENUONL (DISEÑO.md). Laravel los combina con los del paquete: solo se listan los que cambian.
return [

    'single' => [
        'label' => 'Recuperar',
        'modal' => [
            'heading' => '¿Recuperar :label?',
            'actions' => [
                'restore' => [
                    'label' => 'Recuperar',
                ],
            ],
        ],
        'notifications' => [
            'restored' => [
                'title' => 'Recuperado',
            ],
        ],
    ],

    'multiple' => [
        'label' => 'Recuperar seleccionados',
        'modal' => [
            'heading' => '¿Recuperar los :label seleccionados?',
            'actions' => [
                'restore' => [
                    'label' => 'Recuperar',
                ],
            ],
        ],
        'notifications' => [
            'restored' => [
                'title' => 'Recuperados',
            ],
            'restored_partial' => [
                'title' => 'Se recuperaron :count de :total',
                'missing_authorization_failure_message' => 'No tienes permiso para recuperar :count.',
                'missing_processing_failure_message' => 'No pudimos recuperar :count.',
            ],
            'restored_none' => [
                'title' => 'No se recuperó nada',
                'missing_authorization_failure_message' => 'No tienes permiso para recuperar :count.',
                'missing_processing_failure_message' => 'No pudimos recuperar :count.',
            ],
        ],
    ],

];
