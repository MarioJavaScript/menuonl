<?php

/*
 * Solo se declara lo que esta aplicación cambia. Livewire combina este archivo con el suyo solo en el primer nivel,
 * así que `temporary_file_upload` se repite completo con los valores por defecto de Livewire.
 */
return [

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        // Nombre registrado en AppServiceProvider (SubidaRecibida). Va como texto para que `config:cache` pueda serializarlo.
        'rules' => ['subida_recibida'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
