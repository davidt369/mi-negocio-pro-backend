<?php
// filepath: c:\Users\David\Documents\APKMobile\apkMobileSaaS\backend-mi-negocio-pro\app\Models\Business.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'businesses';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'owner_name',
        'phone',
        'email',
        'address',
        'currency',
        'tax_rate'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'tax_rate' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [];

    /**
     * Get the validation rules for the model.
     */
    public static function rules($id = null): array
    {
        return [
            'name' => 'required|string|max:100',
            'owner_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:200',
            'currency' => 'required|string|size:3|in:COP,USD,MXN,PEN,ARS,CLP,BOB,UYU,PYG,VES',
            'tax_rate' => 'required|numeric|min:0|max:1'
        ];
    }

    /**
     * Get the business instance (singleton pattern).
     */
    public static function getInstance(): self
    {
        $business = self::find(1);
        
        if (!$business) {
            $business = self::create([
                'id' => 1,
                'name' => 'Mi Negocio',
                'owner_name' => 'Propietario'
            ]);
        }

        return $business;
    }

    /**
     * Override the save method to ensure only one record exists.
     */
    public function save(array $options = []): bool
    {
        $this->id = 1;
        return parent::save($options);
    }

    /**
     * Get the tax rate as percentage.
     */
    protected function taxRatePercentage(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->tax_rate * 100,
        );
    }

    /**
     * Get formatted currency display.
     */
    protected function currencySymbol(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => match($this->currency) {
                'COP' => '$',
                'USD' => 'US$',
                'MXN' => 'MX$',
                'PEN' => 'S/',
                'ARS' => 'AR$',
                'CLP' => 'CL$',
                'BOB' => 'Bs',
                'UYU' => 'UY$',
                'PYG' => '₲',
                'VES' => 'Bs.S',
                default => '$'
            }
        );
    }

    /**
     * Scope for active business.
     */
    public function scopeActive($query)
    {
        return $query->where('id', 1);
    }
}