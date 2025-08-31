<?php

namespace Database\Seeders;

use App\Models\SaleItems;
use App\Models\Sales;
use App\Models\Products;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaleItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla existente
        SaleItems::truncate();

        echo "🛒 Creando items de venta...\n";

        // Obtener todas las ventas y productos activos
        $sales = Sales::all();
        $products = Products::where('is_active', true)->get();

        if ($sales->isEmpty()) {
            echo "❌ No hay ventas disponibles. Ejecuta SalesSeeder primero.\n";
            return;
        }

        if ($products->isEmpty()) {
            echo "❌ No hay productos activos. Ejecuta ProductsSeeder primero.\n";
            return;
        }

        $totalItems = 0;

        // Para cada venta, crear entre 1-8 items
        foreach ($sales as $sale) {
            $numItems = rand(1, 8); // Número aleatorio de items por venta
            $usedProducts = []; // Para evitar duplicar productos en la misma venta
            
            for ($i = 0; $i < $numItems; $i++) {
                // Seleccionar un producto que no se haya usado en esta venta
                $availableProducts = $products->filter(function ($product) use ($usedProducts) {
                    return !in_array($product->id, $usedProducts);
                });

                if ($availableProducts->isEmpty()) {
                    break; // No hay más productos disponibles para esta venta
                }

                $product = $availableProducts->random();
                $usedProducts[] = $product->id;

                // Determinar cantidad basada en el tipo de producto
                $quantity = $this->getQuantityByCategory($product->category?->name);
                
                // Precio unitario con pequeña variación del precio del producto
                $basePrice = (float) $product->sale_price;
                $unitPrice = $this->getPriceVariation($basePrice);

                // Verificar que hay stock suficiente (aunque esto se manejará en el trigger)
                $stockToUse = min($quantity, max(1, $product->stock));

                // Solo crear el item si hay stock disponible
                if ($product->stock >= $stockToUse) {
                    $saleItem = SaleItems::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'quantity' => $stockToUse,
                        'unit_price' => $unitPrice,
                        'created_at' => $sale->created_at,
                    ]);

                    $totalItems++;
                    
                    // Actualizar el stock local para evitar problemas en el siguiente item
                    $product->stock -= $stockToUse;
                    $product->save();
                }
            }
        }

        // Crear algunos items adicionales para ventas más recientes
        $this->createRecentSaleItems($sales, $products, $totalItems);

        echo "✅ Se crearon {$totalItems} items de venta exitosamente\n";
        
        // Mostrar estadísticas
        $this->showStats();
    }

    /**
     * Determinar cantidad basada en la categoría del producto
     */
    private function getQuantityByCategory(?string $categoryName): int
    {
        return match($categoryName) {
            'Bebidas' => rand(1, 6),        // 1-6 bebidas
            'Snacks' => rand(1, 5),         // 1-5 snacks
            'Dulces' => rand(1, 8),         // 1-8 dulces
            'Cigarrillos' => rand(1, 3),    // 1-3 cajetillas
            'Aseo' => rand(1, 2),           // 1-2 productos de aseo
            default => rand(1, 4),          // 1-4 otros productos
        };
    }

    /**
     * Aplicar pequeña variación al precio del producto
     */
    private function getPriceVariation(float $basePrice): float
    {
        // Variación de ±5% del precio base
        $variation = rand(-5, 5) / 100;
        $newPrice = $basePrice * (1 + $variation);
        
        // Asegurar que el precio no sea menor a 100 pesos
        return max(100, round($newPrice, 2));
    }

    /**
     * Crear items adicionales para ventas recientes
     */
    private function createRecentSaleItems($sales, $products, &$totalItems): void
    {
        // Obtener ventas de los últimos 7 días
        $recentSales = $sales->filter(function ($sale) {
            return $sale->sale_date >= now()->subDays(7)->format('Y-m-d');
        });

        foreach ($recentSales as $sale) {
            // 30% de probabilidad de agregar items adicionales a ventas recientes
            if (rand(1, 100) <= 30) {
                $extraItems = rand(1, 3);
                
                for ($i = 0; $i < $extraItems; $i++) {
                    $product = $products->random();
                    $quantity = $this->getQuantityByCategory($product->category?->name);
                    $unitPrice = $this->getPriceVariation((float) $product->sale_price);

                    // Verificar que no existe ya este producto en la venta
                    $exists = SaleItems::where('sale_id', $sale->id)
                        ->where('product_id', $product->id)
                        ->exists();

                    if (!$exists) {
                        // Verificar stock disponible
                        if ($product->stock >= $quantity) {
                            SaleItems::create([
                                'sale_id' => $sale->id,
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'unit_price' => $unitPrice,
                                'created_at' => $sale->created_at,
                            ]);

                            $totalItems++;
                            
                            // Actualizar el stock local
                            $product->stock -= $quantity;
                            $product->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * Mostrar estadísticas de los datos creados
     */
    private function showStats(): void
    {
        $totalItems = SaleItems::count();
        $totalQuantity = SaleItems::sum('quantity');
        $totalRevenue = SaleItems::sum('line_total');
        $averageItemsPerSale = $totalItems / max(1, Sales::count());

        echo "\n📊 Estadísticas de Items de Venta:\n";
        echo "   • Total de items: {$totalItems}\n";
        echo "   • Cantidad total vendida: {$totalQuantity} unidades\n";
        echo "   • Ingresos totales: $" . number_format($totalRevenue, 2) . "\n";
        echo "   • Promedio de items por venta: " . round($averageItemsPerSale, 1) . "\n";

        // Top 5 productos más vendidos
        $topProducts = DB::table('sale_items')
            ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('products.name as product_name')
            ->groupBy('product_id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        echo "\n🏆 Top 5 productos más vendidos:\n";
        foreach ($topProducts as $index => $product) {
            echo "   " . ($index + 1) . ". {$product->product_name}: {$product->total_sold} unidades\n";
        }

        echo "\n";
    }
}
