<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 * @OA\Schema(
 *     schema="PurchaseItems",
 *     type="object",
 *     required={"purchase_id", "product_id", "quantity", "unit_cost"},
 *     @OA\Property(property="id", type="integer", description="ID único del item de compra"),
 *     @OA\Property(property="purchase_id", type="integer", description="ID de la compra"),
 *     @OA\Property(property="product_id", type="integer", description="ID del producto"),
 *     @OA\Property(property="quantity", type="integer", description="Cantidad comprada"),
 *     @OA\Property(property="unit_cost", type="number", format="float", description="Costo unitario"),
 *     @OA\Property(property="line_total", type="number", format="float", description="Total de la línea"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Fecha de creación"),
 *     @OA\Property(property="product_name", type="string", description="Nombre del producto"),
 *     @OA\Property(property="total_formatted", type="string", description="Total formateado")
 * )
 */
class PurchaseItems extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_items';
    
    public $timestamps = false;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_cost'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
        'created_at' => 'datetime'
    ];

    protected $appends = [
        'product_name',
        'total_formatted'
    ];

    // Relaciones
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchases::class, 'purchase_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id');
    }

    // Atributos computados
    public function getProductNameAttribute(): string
    {
        return $this->product ? $this->product->name : '';
    }

    public function getTotalFormattedAttribute(): string
    {
        return '$' . number_format((float)$this->line_total, 2);
    }

    // Scopes
    public function scopeByPurchase(Builder $query, int $purchaseId): Builder
    {
        return $query->where('purchase_id', $purchaseId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeWithProduct(Builder $query): Builder
    {
        return $query->with('product:id,name,barcode,category_id');
    }

    public function scopeWithPurchase(Builder $query): Builder
    {
        return $query->with('purchase:id,supplier_name,purchase_date,total');
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with(['product:id,name,barcode,category_id', 'purchase:id,supplier_name,purchase_date,total']);
    }

    public function scopeOrderByLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeMinQuantity(Builder $query, int $minQuantity): Builder
    {
        return $query->where('quantity', '>=', $minQuantity);
    }

    public function scopeMaxUnitCost(Builder $query, float $maxCost): Builder
    {
        return $query->where('unit_cost', '<=', $maxCost);
    }

    // Métodos estáticos para estadísticas
    public static function getStats(): array
    {
        $stats = self::selectRaw('
            COUNT(*) as total_items,
            SUM(quantity) as total_quantity,
            SUM(line_total) as total_amount,
            AVG(unit_cost) as avg_unit_cost,
            MIN(unit_cost) as min_unit_cost,
            MAX(unit_cost) as max_unit_cost,
            AVG(quantity) as avg_quantity
        ')->first();

        return [
            'total_items' => (int) $stats->total_items,
            'total_quantity' => (int) $stats->total_quantity,
            'total_amount' => (float) $stats->total_amount,
            'avg_unit_cost' => round((float) $stats->avg_unit_cost, 2),
            'min_unit_cost' => (float) $stats->min_unit_cost,
            'max_unit_cost' => (float) $stats->max_unit_cost,
            'avg_quantity' => round((float) $stats->avg_quantity, 2)
        ];
    }

    public static function getMostPurchasedProducts(int $limit = 10): array
    {
        return self::select('product_id')
            ->selectRaw('SUM(quantity) as total_quantity, SUM(line_total) as total_amount, COUNT(*) as purchase_count')
            ->with('product:id,name,barcode')
            ->groupBy('product_id')
            ->orderByRaw('SUM(quantity) DESC')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'N/A',
                    'product_barcode' => $item->product->barcode ?? 'N/A',
                    'total_quantity' => (int) $item->total_quantity,
                    'total_amount' => (float) $item->total_amount,
                    'purchase_count' => (int) $item->purchase_count
                ];
            })
            ->toArray();
    }

    public static function getRecentPurchases(int $limit = 20): array
    {
        return self::with(['product:id,name,barcode', 'purchase:id,supplier_name,purchase_date'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product->name ?? 'N/A',
                    'product_barcode' => $item->product->barcode ?? 'N/A',
                    'supplier_name' => $item->purchase->supplier_name ?? 'N/A',
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $item->line_total,
                    'purchase_date' => $item->purchase->purchase_date ?? null,
                    'created_at' => $item->created_at
                ];
            })
            ->toArray();
    }

    public static function getCostAnalysis(): array
    {
        $costRanges = [
            ['min' => 0, 'max' => 10, 'label' => '$0 - $10'],
            ['min' => 10.01, 'max' => 50, 'label' => '$10 - $50'],
            ['min' => 50.01, 'max' => 100, 'label' => '$50 - $100'],
            ['min' => 100.01, 'max' => 500, 'label' => '$100 - $500'],
            ['min' => 500.01, 'max' => 999999, 'label' => '$500+']
        ];

        $analysis = [];
        foreach ($costRanges as $range) {
            $count = self::whereBetween('unit_cost', [$range['min'], $range['max']])->count();
            $analysis[] = [
                'range' => $range['label'],
                'count' => $count,
                'percentage' => $count > 0 ? round(($count / self::count()) * 100, 2) : 0
            ];
        }

        return $analysis;
    }

    public static function getTopSuppliersByQuantity(int $limit = 10): array
    {
        return self::join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->select('purchases.supplier_name')
            ->selectRaw('SUM(purchase_items.quantity) as total_quantity, COUNT(DISTINCT purchase_items.product_id) as unique_products')
            ->groupBy('purchases.supplier_name')
            ->orderByRaw('SUM(purchase_items.quantity) DESC')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'supplier_name' => $item->supplier_name,
                    'total_quantity' => (int) $item->total_quantity,
                    'unique_products' => (int) $item->unique_products
                ];
            })
            ->toArray();
    }
}