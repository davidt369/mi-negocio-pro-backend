<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Illuminate\Database\Eloquent\SoftDeletes;
#[OA\Schema(
    schema: "Product",
    type: "object",
    title: "Product",
    description: "Modelo de producto para gestión de inventario en micronegocios",
    properties: [
        new OA\Property(property: "id", type: "integer", description: "ID único del producto"),
        new OA\Property(property: "name", type: "string", description: "Nombre del producto"),
        new OA\Property(property: "image_path", type: "string", nullable: true, description: "Ruta de la imagen del producto"),
        new OA\Property(property: "category_id", type: "integer", description: "ID de la categoría"),
        new OA\Property(property: "cost_price", type: "number", format: "decimal", description: "Precio de costo"),
        new OA\Property(property: "sale_price", type: "number", format: "decimal", description: "Precio de venta"),
        new OA\Property(property: "stock", type: "integer", description: "Cantidad en stock"),
        new OA\Property(property: "min_stock", type: "integer", description: "Stock mínimo para alertas"),
        new OA\Property(property: "is_active", type: "boolean", description: "Estado activo del producto"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Fecha de creación"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", description: "Fecha de actualización"),
        new OA\Property(property: "category", ref: "#/components/schemas/Category", description: "Categoría asociada"),
        new OA\Property(property: "image_url", type: "string", nullable: true, description: "URL completa de la imagen del producto"),
        new OA\Property(property: "profit_margin", type: "number", format: "decimal", description: "Margen de ganancia en porcentaje"),
        new OA\Property(property: "profit_amount", type: "number", format: "decimal", description: "Ganancia en valor absoluto"),
        new OA\Property(property: "stock_status", type: "string", enum: ["in_stock", "low_stock", "out_of_stock"], description: "Estado del stock"),
    ]
)]
class Products extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'image_path',
        'category_id',
        'cost_price',
        'sale_price',
        'stock',
        'min_stock',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'profit_margin',
        'profit_amount',
        'is_low_stock',
        'stock_status',
        'image_url',
    ];

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'ILIKE', "%{$search}%");
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock <= min_stock');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock', 0);
    }

    public function getProfitMarginAttribute()
    {
        if (!$this->cost_price || $this->cost_price == 0) {
            return null;
        }
        
        return round((($this->sale_price - $this->cost_price) / $this->cost_price) * 100, 2);
    }

    public function getProfitAmountAttribute()
    {
        if (!$this->cost_price) {
            return $this->sale_price;
        }
        
        return round($this->sale_price - $this->cost_price, 2);
    }

    public function getIsLowStockAttribute()
    {
        return $this->stock <= $this->min_stock;
    }

    public function getStockStatusAttribute()
    {
        if ($this->stock == 0) {
            return 'out_of_stock';
        } elseif ($this->stock <= $this->min_stock) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    public function decreaseStock($quantity)
    {
        $this->stock = max(0, $this->stock - $quantity);
        $this->save();
        
        return $this;
    }

    public function increaseStock($quantity)
    {
        $this->stock += $quantity;
        $this->save();
        
        return $this;
    }

    /**
     * Get the full URL for the product image
     */
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }
        
        return null;
    }

    /**
     * Delete the product image file
     */
    public function deleteImage()
    {
        if ($this->image_path && Storage::disk('public')->exists($this->image_path)) {
            Storage::disk('public')->delete($this->image_path);
        }
    }
}