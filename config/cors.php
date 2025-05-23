<?php

return [
    'paths' => ['api/*'],  // Rutas de la API

    'allowed_methods' => ['*'],  // Permitir todos los métodos HTTP

    'allowed_origins' => ['http://localhost:4200', 'http://localhost:4201'],  // La URL de tu aplicación Angular

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],  // Permitir todos los headers

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,  // Si usas autenticación con cookies, ponlo a true
];
