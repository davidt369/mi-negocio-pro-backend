#!/usr/bin/env php
<?php

// Script simple para probar los endpoints de Purchase Items
$baseUrl = 'http://127.0.0.1:8000/api';
$token = '1|v1uYQDSKkvXG4BZl1w88wHIMGXXuTVgrwGbXfgCoeec9d848'; // Token de autenticación

// Función simple para hacer requests
function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

echo "🚀 Probando endpoints de Purchase Items API...\n\n";

// 1. Obtener estadísticas
echo "📊 1. Obteniendo estadísticas de purchase items...\n";
$stats = makeRequest("$baseUrl/purchase-items/stats", 'GET', null, $token);
if ($stats['http_code'] === 200) {
    echo "✅ Estadísticas obtenidas correctamente\n";
    echo "   - Total items: " . $stats['response']['general_stats']['total_items'] . "\n";
    echo "   - Total cantidad: " . $stats['response']['general_stats']['total_quantity'] . "\n";
    echo "   - Monto total: $" . number_format($stats['response']['general_stats']['total_amount'], 2) . "\n";
} else {
    echo "❌ Error: HTTP " . $stats['http_code'] . "\n";
}

echo "\n";

// 2. Listar purchase items (primeros 5)
echo "📋 2. Listando purchase items (primeros 5)...\n";
$list = makeRequest("$baseUrl/purchase-items?per_page=5&with_relations=true", 'GET', null, $token);
if ($list['http_code'] === 200) {
    echo "✅ Lista obtenida correctamente\n";
    echo "   - Total en BD: " . $list['response']['total'] . "\n";
    echo "   - Página actual: " . $list['response']['current_page'] . "\n";
    echo "   - Items en esta página: " . count($list['response']['data']) . "\n";
    
    if (!empty($list['response']['data'])) {
        $firstItem = $list['response']['data'][0];
        echo "   - Primer item: ID " . $firstItem['id'] . 
             ", Producto: " . ($firstItem['product']['name'] ?? 'N/A') . 
             ", Cantidad: " . $firstItem['quantity'] . 
             ", Costo: $" . $firstItem['unit_cost'] . "\n";
    }
} else {
    echo "❌ Error: HTTP " . $list['http_code'] . "\n";
}

echo "\n";

// 3. Obtener items de una compra específica
echo "🛒 3. Obteniendo items de la primera compra...\n";
$purchaseItems = makeRequest("$baseUrl/purchases/1/items", 'GET', null, $token);
if ($purchaseItems['http_code'] === 200) {
    echo "✅ Items de compra obtenidos correctamente\n";
    echo "   - Items en la compra: " . count($purchaseItems['response']) . "\n";
    
    if (!empty($purchaseItems['response'])) {
        foreach ($purchaseItems['response'] as $item) {
            echo "     * " . ($item['product']['name'] ?? 'N/A') . 
                 " - Qty: " . $item['quantity'] . 
                 " - $" . $item['unit_cost'] . 
                 " = $" . $item['line_total'] . "\n";
        }
    }
} else {
    echo "❌ Error: HTTP " . $purchaseItems['http_code'] . "\n";
}

echo "\n";

// 4. Obtener historial de compras de un producto
echo "📦 4. Obteniendo historial de compras del producto 1...\n";
$history = makeRequest("$baseUrl/products/1/purchase-history?limit=3", 'GET', null, $token);
if ($history['http_code'] === 200) {
    echo "✅ Historial obtenido correctamente\n";
    echo "   - Registros de compra: " . count($history['response']) . "\n";
    
    if (!empty($history['response'])) {
        foreach ($history['response'] as $item) {
            echo "     * Compra ID: " . $item['purchase_id'] . 
                 " - Qty: " . $item['quantity'] . 
                 " - $" . $item['unit_cost'] . 
                 " - " . date('Y-m-d', strtotime($item['created_at'])) . "\n";
        }
    }
} else {
    echo "❌ Error: HTTP " . $history['http_code'] . "\n";
}

echo "\n✨ Pruebas completadas!\n";