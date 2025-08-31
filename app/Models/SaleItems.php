<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 * @OA\Schema(
 *     schema="SaleItem",
 *     type="object",
 *     title="Sale Item",
 *     description="Item de venta - detalle de productos vendidos",
 *     required={"sale_id", "product_id", "quantity", "unit_price"},
 *     @OA\Property(property="id", type="integer", format="int64", description="ID único del item de venta"),
 *     @OA\Property(property="sale_id", type="integer", format="int64", description="ID de la venta"),
 *     @OA\Property(property="product_id", type="integer", format="int64", description="ID del producto"),
 *     @OA\Property(property="quantity", type="integer", minimum=1, description="Cantidad vendida"),
 *     @OA\Property(property="unit_price", type="number", format="decimal", minimum=0, description="Precio unitario"),
 *     @OA\Property(property="line_total", type="number", format="decimal", description="Total de la línea (calculado automáticamente)"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Fecha de creación"),
 *     @OA\Property(property="sale", ref="#/components/schemas/Sale", description="Venta asociada"),
 *     @OA\Property(property="product", ref="#/components/schemas/Product", description="Producto vendido"),
 * )
 */
class SaleItems extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'sale_items';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total', // Aunque se calcula automáticamente, lo permitimos por flexibilidad
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'product_name',
        'total_formatted',
    ];

    /**
     * Relación con la venta
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sales::class, 'sale_id');
    }

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id');
    }

    /**
     * Accessor para el nombre del producto
     */
    public function getProductNameAttribute(): ?string
    {
        return $this->product?->name;
    }

    /**
     * Accessor para el total formateado
     */
    public function getTotalFormattedAttribute(): string
    {
        return '$' . number_format((float) $this->line_total, 2, '.', ',');
    }

    /**
     * Accessor para el precio unitario formateado
     */
    public function getUnitPriceFormattedAttribute(): string
    {
        return '$' . number_format((float) $this->unit_price, 2, '.', ',');
    }

    /**
     * Scope para filtrar por venta
     */
    public function scopeBySale($query, $saleId)
    {
        return $query->where('sale_id', $saleId);
    }

    /**
     * Scope para filtrar por producto
     */
    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope para incluir información del producto
     */
    public function scopeWithProduct($query)
    {
        return $query->with(['product:id,name,barcode,sale_price']);
    }

    /**
     * Scope para incluir información de la venta
     */
    public function scopeWithSale($query)
    {
        return $query->with(['sale:id,sale_number,customer_name,sale_date']);
    }

    /**
     * Scope para ordenar por fecha de creación
     */
    public function scopeOrderByCreated($query, $direction = 'desc')
    {
        return $query->orderBy('created_at', $direction);
    }

    /**
     * Calcular el subtotal antes de guardar
     */
    protected static function booted()
    {
        static::saving(function ($saleItem) {
            // El cálculo se hace en la base de datos por el trigger,
            // pero aquí podemos validar o hacer cálculos adicionales
            if (empty($saleItem->line_total)) {
                $saleItem->line_total = $saleItem->quantity * $saleItem->unit_price;
            }
        });
    }

    /**
     * Validar que hay suficiente stock antes de crear
     */
    public function validateStock(): bool
    {
        $product = $this->product;
        if (!$product) {
            return false;
        }

        return $product->stock >= $this->quantity;
    }

    /**
     * Obtener la diferencia de stock si se está actualizando
     */
    public function getStockDifference(): int
    {
        if (!$this->exists) {
            return $this->quantity; // Nueva venta, reducir stock
        }

        $original = $this->getOriginal('quantity');
        return $this->quantity - $original; // Diferencia para actualización
    }

    /**
     * Estadísticas de items de venta
     */
    public static function getStats($filters = [])
    {
        $query = static::query();

        // Aplicar filtros
        if (!empty($filters['sale_id'])) {
            $query->where('sale_id', $filters['sale_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereHas('sale', function ($q) use ($filters) {
                $q->where('sale_date', '>=', $filters['date_from']);
            });
        }

        if (!empty($filters['date_to'])) {
            $query->whereHas('sale', function ($q) use ($filters) {
                $q->where('sale_date', '<=', $filters['date_to']);
            });
        }

        return [
            'total_items' => $query->count(),
            'total_quantity' => $query->sum('quantity'),
            'total_amount' => $query->sum('line_total'),
            'average_unit_price' => $query->avg('unit_price'),
            'most_sold_products' => static::getMostSoldProducts($filters),
        ];
    }

    /**
     * Productos más vendidos
     */
    public static function getMostSoldProducts($filters = [], $limit = 10)
    {
        $query = static::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(line_total) as total_revenue')
            ->selectRaw('COUNT(*) as times_sold')
            ->with(['product:id,name,barcode,sale_price'])
            ->groupBy('product_id');

        // Aplicar filtros de fecha a través de la venta
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $query->whereHas('sale', function ($q) use ($filters) {
                if (!empty($filters['date_from'])) {
                    $q->where('sale_date', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $q->where('sale_date', '<=', $filters['date_to']);
                }
            });
        }

        return $query->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }
}