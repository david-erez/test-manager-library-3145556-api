<?php

// plan.md §2 — the project's own environment configuration, kept out of config/app.php
// on purpose so it reads the same as the variable table in plan.md.
return [
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl_minutes' => (int) env('JWT_TTL_MINUTES', 480),

    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),

    'covers_dir' => env('COVERS_DIR', storage_path('app/covers')),

    'cors_origin' => env('CORS_ORIGIN', 'http://localhost:5173'),
];
