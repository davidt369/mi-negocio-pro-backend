<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 * @OA\Schema(
 *     schema="Purchase",
 *     type="object",
 *     required={"purchase_date", "received_by"},
 *     @OA\Property(property="id", type="integer", example=1, description="ID único de la compra"),
 *     @OA\Property(property="supplier_name", type="string", example="Distribuidora ABC", description="Nombre del proveedor"),
 *     @OA\Property(property="total", type="number", format="decimal", example=250000.50, description="Total de la compra"),
 *     @OA\Property(property="notes", type="string", example="Compra de productos varios", description="Notas adicionales"),
 *     @OA\Property(property="purchase_date", type="string", format="date", example="2025-08-24", description="Fecha de la compra"),
 *     @OA\Property(property="received_by", type="integer", example=1, description="ID del usuario que recibió"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-24T10:30:00Z", description="Fecha de creación"),
 *     @OA\Property(property="user", ref="#/components/schemas/User", description="Usuario que recibió la compra"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/PurchaseItems"), description="Items de la compra"),
 *     @OA\Property(property="items_count", type="integer", example=5, description="Número total de items"),
 *     @OA\Property(property="total_quantity", type="integer", example=25, description="Cantidad total de productos"),
 *     @OA\Property(property="total_formatted", type="string", example="$250,000.50", description="Total formateado")
 * )
 */
class Purchases extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_name',
        'total',
        'notes',
        'purchase_date',
        'received_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected $with = ['user:id,first_name,last_name'];

    public $timestamps = false; // Solo usamos created_at

    /**
     * Relación con el usuario que recibió la compra
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Users::class, 'received_by');
    }

    /**
     * Relación con los items de la compra
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItems::class, 'purchase_id');
    }

    /**
     * Scope para filtrar por fecha
     */
    public function scopeByDate($query, $date)
    {
        return $query->where('purchase_date', $date);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeByDateRange($query, $dateFrom, $dateTo)
    {
        return $query->whereBetween('purchase_date', [$dateFrom, $dateTo]);
    }

    /**
     * Scope para filtrar por usuario
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('received_by', $userId);
    }

    /**
     * Scope para filtrar por proveedor
     */
    public function scopeBySupplier($query, $supplierName)
    {
        return $query->where('supplier_name', 'ILIKE', "%{$supplierName}%");
    }

    /**
     * Scope para cargar items con productos
     */
    public function scopeWithItems($query)
    {
        return $query->with(['items.product:id,name,barcode']);
    }

    /**
     * Scope para ordenar por fecha de creación
     */
    public function scopeOrderByCreated($query, $direction = 'desc')
    {
        return $query->orderBy('created_at', $direction);
    }

    /**
     * Scope para ordenar por fecha de compra
     */
    public function scopeOrderByPurchaseDate($query, $direction = 'desc')
    {
        return $query->orderBy('purchase_date', $direction);
    }

    /**
     * Atributo calculado: Número de items
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Atributo calculado: Cantidad total de productos
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Atributo calculado: Nombre del usuario que recibió
     */
    public function getUserNameAttribute(): string
    {
        return $this->user?->full_name ?? 'Usuario eliminado';
    }

    /**
     * Atributo calculado: Total formateado
     */
    public function getTotalFormattedAttribute(): string
    {
        return '$' . number_format($this->total, 2);
    }

    /**
     * Atributo calculado: Fecha formateada
     */
    public function getPurchaseDateFormattedAttribute(): string
    {
        return $this->purchase_date->format('d/m/Y');
    }

    /**
     * Estadísticas de compras
     */
    public static function getStats(array $filters = []): array
    {
        $query = self::query();

        // Aplicar filtros
        if (!empty($filters['date_from'])) {
            $query->where('purchase_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('purchase_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['received_by'])) {
            $query->where('received_by', $filters['received_by']);
        }

        if (!empty($filters['supplier_name'])) {
            $query->bySupplier($filters['supplier_name']);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_purchases,
            SUM(total) as total_amount,
            AVG(total) as average_amount,
            MIN(total) as min_amount,
            MAX(total) as max_amount
        ')->first();

        // Compras por mes (últimos 6 meses)
        $monthlyPurchases = self::query()
            ->selectRaw('
                DATE_TRUNC(\'month\', purchase_date) as month,
                COUNT(*) as purchases_count,
                SUM(total) as total_amount
            ')
            ->where('purchase_date', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top proveedores
        $topSuppliers = self::query()
            ->selectRaw('
                supplier_name,
                COUNT(*) as purchases_count,
                SUM(total) as total_amount
            ')
            ->whereNotNull('supplier_name')
            ->groupBy('supplier_name')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        return [
            'total_purchases' => (int) $stats->total_purchases,
            'total_amount' => (float) $stats->total_amount ?: 0,
            'average_amount' => (float) $stats->average_amount ?: 0,
            'min_amount' => (float) $stats->min_amount ?: 0,
            'max_amount' => (float) $stats->max_amount ?: 0,
            'monthly_purchases' => $monthlyPurchases,
            'top_suppliers' => $topSuppliers,
        ];
    }

    /**
     * Obtener compras recientes
     */
    public static function getRecent(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->with(['user:id,first_name,last_name'])
            ->orderByCreated()
            ->limit($limit)
            ->get();
    }

    /**
     * Validar si se puede eliminar la compra
     */
    public function canDelete(): bool
    {
        // No permitir eliminar compras que afecten el stock significativamente
        // o que sean de fechas muy antiguas
        $daysSinceCreation = Carbon::now()->diffInDays($this->created_at);
        return $daysSinceCreation <= 30; // Solo últimos 30 días
    }

    /**
     * Obtener resumen de la compra
     */
    public function getSummary(): array
    {
        $items = $this->items()->with('product:id,name')->get();
        
        return [
            'purchase_id' => $this->id,
            'supplier_name' => $this->supplier_name,
            'purchase_date' => $this->purchase_date_formatted,
            'total_items' => $items->count(),
            'total_quantity' => $items->sum('quantity'),
            'total_amount' => $this->total_formatted,
            'received_by' => $this->user_name,
            'items' => $items->map(function ($item) {
                return [
                    'product_name' => $item->product->name ?? 'Producto eliminado',
                    'quantity' => $item->quantity,
                    'unit_cost' => '$' . number_format($item->unit_cost, 2),
                    'line_total' => '$' . number_format($item->line_total, 2),
                ];
            }),
        ];
    }
}
