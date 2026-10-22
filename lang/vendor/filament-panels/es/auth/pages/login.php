<?php

// Textos de MENUONL (DISEÑO.md). Laravel los combina con los del paquete: solo se listan los que cambian.
return [

    'title' => 'Entrar',
    'heading' => 'Entra a tu restaurante',

    'actions' => [
        'request_password_reset' => [
            'label' => '¿Olvidaste tu contraseña?',
        ],
    ],

    'form' => [
        'email' => [
            'label' => 'Correo',
        ],
        'remember' => [
            'label' => 'Mantener la sesión abierta',
        ],
    ],

    'multi_factor' => [
        'heading' => 'Confirma que eres tú',
        'subheading' => 'Escribe el código de 6 números de tu app de verificación.',
        'form' => [
            'provider' => [
                'label' => '¿Cómo quieres confirmar?',
            ],
            'actions' => [
                'authenticate' => [
                    'label' => 'Entrar',
                ],
            ],
        ],
    ],

    'messages' => [
        'failed' => 'El correo o la contraseña no coinciden. Revísalos o restablece tu contraseña.',
    ],

    'notifications' => [
        'throttled' => [
            'title' => 'Por seguridad, pausamos el acceso unos minutos',
            'body' => 'Vuelve a intentarlo en :seconds segundos.',
        ],
    ],

];
