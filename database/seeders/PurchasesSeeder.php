<?php

namespace Database\Seeders;

use App\Models\Purchases;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchasesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla existente
        DB::table('purchases')->truncate();

        echo "🛒 Creando compras de prueba...\n";

        // Obtener usuarios existentes
        $users = User::where('active', true)->pluck('id')->toArray();
        
        if (empty($users)) {
            echo "⚠️  No hay usuarios disponibles. Ejecute UsersSeeder primero.\n";
            return;
        }

        // Crear compras de diferentes tipos
        $purchases = collect();

        // 1. Compras de este mes (30 compras)
        echo "📅 Creando compras del mes actual...\n";
        $monthlyPurchases = Purchases::factory()
            ->count(30)
            ->thisMonth()
            ->create([
                'received_by' => fn() => fake()->randomElement($users),
            ]);
        $purchases = $purchases->merge($monthlyPurchases);

        // 2. Compras de esta semana (15 compras)
        echo "📅 Creando compras de esta semana...\n";
        $weeklyPurchases = Purchases::factory()
            ->count(15)
            ->thisWeek()
            ->create([
                'received_by' => fn() => fake()->randomElement($users),
            ]);
        $purchases = $purchases->merge($weeklyPurchases);

        // 3. Compras de hoy (5 compras)
        echo "📅 Creando compras de hoy...\n";
        $todayPurchases = Purchases::factory()
            ->count(5)
            ->today()
            ->create([
                'received_by' => fn() => fake()->randomElement($users),
            ]);
        $purchases = $purchases->merge($todayPurchases);

        // 4. Compras grandes (10 compras)
        echo "🏪 Creando compras grandes...\n";
        $largePurchases = Purchases::factory()
            ->count(10)
            ->large()
            ->create([
                'received_by' => fn() => fake()->randomElement($users),
            ]);
        $purchases = $purchases->merge($largePurchases);

        // 5. Compras pequeñas (15 compras)
        echo "🛍️ Creando compras pequeñas...\n";
        $smallPurchases = Purchases::factory()
            ->count(15)
            ->small()
            ->create([
                'received_by' => fn() => fake()->randomElement($users),
            ]);
        $purchases = $purchases->merge($smallPurchases);

        // 6. Compras urgentes (5 compras)
        echo "🚨 Creando compras urgentes...\n";
        $urgentPurchases = Purchases::factory()
            ->count(5)
            ->urgent()
            ->create([
                'received_by' => fn() => fake()->randomElement($users),
            ]);
        $purchases = $purchases->merge($urgentPurchases);

        // 7. Compras de proveedores específicos
        echo "🏢 Creando compras por proveedor...\n";
        $supplierPurchases = collect([
            'Distribuidora Central' => 8,
            'Mayorista Los Andes' => 6,
            'Comercial San José' => 5,
            'Distribuciones El Sol' => 4,
            'Almacén La Rebaja' => 3,
        ])->map(function ($count, $supplier) use ($users) {
            return Purchases::factory()
                ->count($count)
                ->withSupplier($supplier)
                ->create([
                    'received_by' => fn() => fake()->randomElement($users),
                ]);
        })->flatten();
        $purchases = $purchases->merge($supplierPurchases);

        $totalPurchases = $purchases->count();
        echo "✅ Se crearon {$totalPurchases} compras exitosamente\n";

        // Mostrar estadísticas
        $this->showStatistics($purchases);
    }

    /**
     * Mostrar estadísticas de las compras creadas
     */
    private function showStatistics($purchases): void
    {
        echo "\n📊 ESTADÍSTICAS DE COMPRAS CREADAS:\n";
        echo "════════════════════════════════════\n";

        // Total de compras
        $total = $purchases->count();
        echo "📦 Total de compras: {$total}\n";

        // Compras por fecha
        $today = $purchases->where('purchase_date', now()->format('Y-m-d'))->count();
        $thisWeek = $purchases->where('purchase_date', '>=', now()->subWeek()->format('Y-m-d'))->count();
        $thisMonth = $purchases->where('purchase_date', '>=', now()->subMonth()->format('Y-m-d'))->count();

        echo "📅 Compras de hoy: {$today}\n";
        echo "📅 Compras de esta semana: {$thisWeek}\n";
        echo "📅 Compras de este mes: {$thisMonth}\n";

        // Top 5 proveedores
        $topSuppliers = $purchases
            ->whereNotNull('supplier_name')
            ->groupBy('supplier_name')
            ->map->count()
            ->sortDesc()
            ->take(5);

        echo "\n🏆 Top 5 proveedores:\n";
        $topSuppliers->each(function ($count, $supplier) {
            echo "   {$supplier}: {$count} compras\n";
        });

        // Compras por usuario
        $userStats = $purchases
            ->groupBy('received_by')
            ->map->count()
            ->sortDesc();

        echo "\n👥 Compras por usuario:\n";
        $userStats->each(function ($count, $userId) {
            $user = User::find($userId);
            $userName = $user ? $user->full_name : "Usuario #{$userId}";
            echo "   {$userName}: {$count} compras\n";
        });

        // Distribución por mes
        $monthlyDistribution = $purchases
            ->groupBy(function ($purchase) {
                return \Carbon\Carbon::parse($purchase->purchase_date)->format('Y-m');
            })
            ->map->count()
            ->sortKeysDesc();

        echo "\n📈 Distribución por mes:\n";
        $monthlyDistribution->each(function ($count, $month) {
            $monthName = \Carbon\Carbon::parse($month . '-01')->format('F Y');
            echo "   {$monthName}: {$count} compras\n";
        });

        echo "\n🎉 ¡Compras de prueba creadas exitosamente!\n";
    }
}
