<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Categories extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'categories';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'active_products_count',
    ];

    /**
     * Scope to get only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to search categories by name
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'ILIKE', "%{$search}%");
    }

    /**
     * Get products that belong to this category
     */
    public function products()
    {
        return $this->hasMany(Products::class, 'category_id');
    }

    /**
     * Get active products count
     */
    public function getActiveProductsCountAttribute()
    {
        return $this->products()->where('is_active', true)->count();
    }
}
