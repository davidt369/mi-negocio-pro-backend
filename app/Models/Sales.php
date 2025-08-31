<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;
use Illuminate\Database\Eloquent\SoftDeletes;
#[OA\Schema(
    schema: "Sale",
    type: "object",
    title: "Sale",
    description: "Modelo de venta para registro de transacciones en micronegocios",
    properties: [
        new OA\Property(property: "id", type: "integer", description: "ID único de la venta"),
        new OA\Property(property: "sale_number", type: "string", description: "Número único de venta"),
        new OA\Property(property: "customer_name", type: "string", nullable: true, description: "Nombre del cliente"),
        new OA\Property(property: "total", type: "number", format: "decimal", description: "Total de la venta"),
        new OA\Property(property: "payment_method", type: "string", enum: ["cash", "card", "transfer", "credit"], description: "Método de pago"),
        new OA\Property(property: "notes", type: "string", nullable: true, description: "Notas adicionales"),
        new OA\Property(property: "sale_date", type: "string", format: "date", description: "Fecha de la venta"),
        new OA\Property(property: "sold_by", type: "integer", description: "ID del usuario que realizó la venta"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Fecha de creación"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Fecha de actualización"),
        new OA\Property(property: "seller", type: "object", description: "Usuario que realizó la venta"),
        new OA\Property(property: "sale_items", type: "array", items: new OA\Items(type: "object"), description: "Productos vendidos"),
        new OA\Property(property: "items_count", type: "integer", description: "Cantidad total de productos"),
        new OA\Property(property: "payment_method_label", type: "string", description: "Etiqueta del método de pago"),
    ]
)]
class Sales extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_number',
        'customer_name',
        'total',
        'payment_method',
        'notes',
        'sale_date',
        'sold_by'
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'sale_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    const PAYMENT_METHODS = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'credit' => 'Crédito'
    ];

    /**
     * Relación con el usuario que realizó la venta
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /**
     * Relación con los items de la venta
     */
    public function saleItems()
    {
        return $this->hasMany(SaleItems::class, 'sale_id');
    }

    /**
     * Scope para buscar por rango de fechas
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        if ($endDate) {
            return $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        return $query->whereDate('sale_date', $startDate);
    }

    /**
     * Scope para filtrar por método de pago
     */
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope para filtrar por vendedor
     */
    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('sold_by', $sellerId);
    }

    /**
     * Scope para buscar por número de venta o cliente
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('sale_number', 'ILIKE', "%{$search}%")
              ->orWhere('customer_name', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * Atributo computado para la etiqueta del método de pago
     */
    public function getPaymentMethodLabelAttribute()
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    /**
     * Atributo computado para contar items
     */
    public function getItemsCountAttribute()
    {
        return $this->saleItems->sum('quantity');
    }

    /**
     * Calcular el total basado en los items
     */
    public function calculateTotal()
    {
        return $this->saleItems->sum('line_total');
    }

    /**
     * Actualizar el total de la venta
     */
    public function updateTotal()
    {
        $this->total = $this->calculateTotal();
        $this->save();
        return $this->total;
    }

    /**
     * Obtener ventas del día
     */
    public static function todaySales()
    {
        return self::whereDate('sale_date', today());
    }

    /**
     * Obtener ventas del mes
     */
    public static function monthSales()
    {
        return self::whereMonth('sale_date', now()->month)
                   ->whereYear('sale_date', now()->year);
    }

    /**
     * Obtener el total de ventas por período
     */
    public static function totalByPeriod($period = 'today')
    {
        switch ($period) {
            case 'today':
                return self::todaySales()->sum('total');
            case 'month':
                return self::monthSales()->sum('total');
            case 'week':
                return self::whereBetween('sale_date', [now()->startOfWeek(), now()->endOfWeek()])->sum('total');
            default:
                return 0;
        }
    }
}
