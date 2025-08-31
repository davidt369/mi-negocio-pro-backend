<?php

namespace Database\Seeders;

use App\Models\PurchaseItems;
use App\Models\Purchases;
use App\Models\Products;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar que hay compras y productos disponibles
        $purchasesCount = Purchases::count();
        $productsCount = Products::count();
        
        if ($purchasesCount === 0 || $productsCount === 0) {
            $this->command->warn('No hay compras o productos disponibles. Ejecute los seeders de Purchases y Products primero.');
            return;
        }

        $this->command->info('Generando items de compras...');

        // Obtener todas las compras
        $purchases = Purchases::all();
        $products = Products::all();

        $totalItems = 0;

        // Generar items para cada compra
        foreach ($purchases as $purchase) {
            // Número aleatorio de items por compra (1-5)
            $itemsCount = rand(1, 5);
            
            // Seleccionar productos aleatorios para esta compra
            $selectedProducts = $products->random(min($itemsCount, $products->count()));
            
            foreach ($selectedProducts as $product) {
                // Evitar duplicados en la misma compra
                $existingItem = PurchaseItems::where('purchase_id', $purchase->id)
                    ->where('product_id', $product->id)
                    ->first();
                    
                if ($existingItem) {
                    continue;
                }

                $quantity = rand(1, 50);
                $unitCost = round(rand(500, 20000) / 100, 2); // Entre $5.00 y $200.00

                PurchaseItems::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'created_at' => $purchase->purchase_date ?: now()
                ]);

                $totalItems++;
            }
        }

        // Generar algunos items adicionales con factories para variedad
        $additionalItems = 50;
        
        for ($i = 0; $i < $additionalItems; $i++) {
            $purchase = $purchases->random();
            $product = $products->random();
            
            // Verificar que no existe ya
            $exists = PurchaseItems::where('purchase_id', $purchase->id)
                ->where('product_id', $product->id)
                ->exists();
                
            if (!$exists) {
                PurchaseItems::factory()->create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'created_at' => $purchase->purchase_date ?: now()
                ]);
                $totalItems++;
            }
        }

        // Generar algunos items con características especiales
        $this->generateSpecialItems($purchases, $products);

        $this->command->info("✅ Se generaron {$totalItems} items de compras");
        
        // Mostrar estadísticas
        $this->showStatistics();
    }

    /**
     * Generar items con características especiales
     */
    private function generateSpecialItems($purchases, $products): void
    {
        $specialCount = 0;

        // Items con alta cantidad
        for ($i = 0; $i < 10; $i++) {
            $purchase = $purchases->random();
            $product = $products->random();
            
            $exists = PurchaseItems::where('purchase_id', $purchase->id)
                ->where('product_id', $product->id)
                ->exists();
                
            if (!$exists) {
                PurchaseItems::factory()
                    ->highQuantity()
                    ->create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'created_at' => $purchase->purchase_date ?: now()
                    ]);
                $specialCount++;
            }
        }

        // Items caros
        for ($i = 0; $i < 15; $i++) {
            $purchase = $purchases->random();
            $product = $products->random();
            
            $exists = PurchaseItems::where('purchase_id', $purchase->id)
                ->where('product_id', $product->id)
                ->exists();
                
            if (!$exists) {
                PurchaseItems::factory()
                    ->expensive()
                    ->create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'created_at' => $purchase->purchase_date ?: now()
                    ]);
                $specialCount++;
            }
        }

        // Items baratos con baja cantidad
        for ($i = 0; $i < 20; $i++) {
            $purchase = $purchases->random();
            $product = $products->random();
            
            $exists = PurchaseItems::where('purchase_id', $purchase->id)
                ->where('product_id', $product->id)
                ->exists();
                
            if (!$exists) {
                PurchaseItems::factory()
                    ->cheap()
                    ->lowQuantity()
                    ->create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'created_at' => $purchase->purchase_date ?: now()
                    ]);
                $specialCount++;
            }
        }

        $this->command->info("✅ Se generaron {$specialCount} items especiales");
    }

    /**
     * Mostrar estadísticas de los datos generados
     */
    private function showStatistics(): void
    {
        $stats = PurchaseItems::selectRaw('
            COUNT(*) as total_items,
            SUM(quantity) as total_quantity,
            SUM(line_total) as total_amount,
            AVG(unit_cost) as avg_unit_cost,
            MIN(unit_cost) as min_unit_cost,
            MAX(unit_cost) as max_unit_cost
        ')->first();

        $this->command->info("\n📊 Estadísticas de Items de Compras:");
        $this->command->line("   • Total items: " . number_format($stats->total_items));
        $this->command->line("   • Cantidad total: " . number_format($stats->total_quantity));
        $this->command->line("   • Monto total: $" . number_format($stats->total_amount, 2));
        $this->command->line("   • Costo promedio: $" . number_format($stats->avg_unit_cost, 2));
        $this->command->line("   • Costo mínimo: $" . number_format($stats->min_unit_cost, 2));
        $this->command->line("   • Costo máximo: $" . number_format($stats->max_unit_cost, 2));

        // Top 5 productos más comprados
        $topProducts = PurchaseItems::select('product_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByRaw('SUM(quantity) DESC')
            ->limit(5)
            ->get();

        $this->command->info("\n🏆 Top 5 Productos Más Comprados:");
        foreach ($topProducts as $index => $item) {
            $productName = $item->product->name ?? 'N/A';
            $this->command->line("   " . ($index + 1) . ". {$productName} - {$item->total_quantity} unidades");
        }
    }
}
