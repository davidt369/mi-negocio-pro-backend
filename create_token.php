<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Obtener el primer usuario
$user = App\Models\User::first();

if ($user) {
    // Crear token
    $token = $user->createToken('test-token')->plainTextToken;
    
    echo "Token creado para usuario: {$user->first_name} {$user->last_name}\n";
    echo "Role: {$user->role}\n";
    echo "Token: {$token}\n";
} else {
    echo "No hay usuarios en la base de datos\n";
}