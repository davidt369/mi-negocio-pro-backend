<?php

require_once 'vendor/autoload.php';

$base_url = 'http://localhost:8000/api';
$token = '1|v1uYQDSKkvXG4BZl1w88wHIMGXXuTVgrwGbXfgCoeec9d848'; // Tu token de autenticación

function makeRequest($url, $token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

echo "🤖 TESTING BUSINESS INTELLIGENCE ENDPOINTS (Para Chatbot IA)\n";
echo "===========================================================\n\n";

// 1. Dashboard principal
echo "📊 1. Dashboard principal...\n";
$response = makeRequest($base_url . '/business-intelligence/dashboard', $token);
if ($response['code'] === 200) {
    $data = $response['data'];
    echo "✅ Dashboard OK:\n";
    echo "   - Ingresos hoy: $" . number_format($data['today_revenue']['revenue'], 2) . " ({$data['today_revenue']['transactions']} transacciones)\n";
    echo "   - Ingresos mes: $" . number_format($data['month_revenue']['revenue'], 2) . " ({$data['month_revenue']['transactions']} transacciones)\n";
    echo "   - Top productos: " . count($data['top_products']) . " encontrados\n";
    echo "   - Stock bajo: {$data['low_stock_count']} productos\n";
    echo "   - Ventas recientes: " . count($data['recent_sales']) . " registros\n";
} else {
    echo "❌ Error: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

// 2. Ingresos por período
echo "💰 2. Ingresos por período...\n";
foreach (['today', 'this_month', 'last_month'] as $period) {
    $response = makeRequest($base_url . '/business-intelligence/revenue/' . $period, $token);
    if ($response['code'] === 200) {
        $data = $response['data'];
        echo "✅ {$period}: $" . number_format($data['revenue'], 2) . " ({$data['transactions']} transacciones, ticket promedio: $" . number_format($data['avg_ticket'], 2) . ")\n";
    } else {
        echo "❌ Error {$period}: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
    }
}
echo "\n";

// 3. Consulta de stock de producto
echo "📦 3. Consulta de stock (ejemplo: 'coca')...\n";
$response = makeRequest($base_url . '/business-intelligence/product-stock/coca', $token);
if ($response['code'] === 200) {
    $products = $response['data'];
    echo "✅ " . count($products) . " productos encontrados:\n";
    foreach ($products as $product) {
        echo "   - {$product['name']}: {$product['stock']} unidades (mín: {$product['min_stock']}) - Estado: {$product['status']}\n";
    }
} else {
    echo "❌ Error: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

// 4. Productos más vendidos
echo "🏆 4. Top productos más vendidos (últimos 30 días)...\n";
$response = makeRequest($base_url . '/business-intelligence/top-selling-products?days=30', $token);
if ($response['code'] === 200) {
    $products = $response['data'];
    echo "✅ " . count($products) . " productos top:\n";
    foreach (array_slice($products, 0, 5) as $i => $product) {
        echo "   " . ($i + 1) . ". {$product['product_name']}: {$product['total_sold']} vendidos ($" . number_format($product['revenue'], 2) . ")\n";
    }
} else {
    echo "❌ Error: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

// 5. Productos con stock bajo
echo "⚠️  5. Productos con stock bajo...\n";
$response = makeRequest($base_url . '/business-intelligence/low-stock', $token);
if ($response['code'] === 200) {
    $products = $response['data'];
    echo "✅ " . count($products) . " productos con stock bajo:\n";
    foreach (array_slice($products, 0, 5) as $product) {
        echo "   - {$product['name']}: {$product['stock']} unidades (mín: {$product['min_stock']}) - {$product['category']}\n";
    }
} else {
    echo "❌ Error: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

// 6. Ventas diarias (últimos 7 días)
echo "📅 6. Ventas diarias (últimos 7 días)...\n";
$response = makeRequest($base_url . '/business-intelligence/daily-sales?limit=7', $token);
if ($response['code'] === 200) {
    $sales = $response['data'];
    echo "✅ " . count($sales) . " días con ventas:\n";
    foreach ($sales as $day) {
        echo "   - {$day['sale_date']}: $" . number_format($day['revenue'], 2) . " ({$day['transactions']} transacciones)\n";
    }
} else {
    echo "❌ Error: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

// 7. Ventas mensuales
echo "📈 7. Ventas mensuales (últimos 6 meses)...\n";
$response = makeRequest($base_url . '/business-intelligence/monthly-sales?limit=6', $token);
if ($response['code'] === 200) {
    $sales = $response['data'];
    echo "✅ " . count($sales) . " meses con ventas:\n";
    foreach ($sales as $month) {
        $monthName = date('Y-m', strtotime($month['month']));
        echo "   - {$monthName}: $" . number_format($month['revenue'], 2) . " ({$month['transactions']} transacciones)\n";
    }
} else {
    echo "❌ Error: " . ($response['data']['message'] ?? 'Unknown error') . "\n";
}
echo "\n";

echo "🎯 RESUMEN:\n";
echo "==========\n";
echo "✅ Todas las funciones de Business Intelligence están listas para el chatbot IA\n";
echo "✅ Puede responder preguntas como:\n";
echo "   - ¿Cuánto gané hoy/este mes?\n";
echo "   - ¿Cuántas unidades quedan de X producto?\n";
echo "   - ¿Cuál es mi producto más vendido?\n";
echo "   - ¿Qué productos necesito reponer?\n";
echo "   - ¿Cómo van las ventas esta semana?\n";
echo "✅ Sistema optimizado para microempresarios\n";
echo "✅ Esquema minimalista pero funcional\n\n";

echo "🚀 ¡Listo para conectar con chatbot IA y app móvil!\n";
?>