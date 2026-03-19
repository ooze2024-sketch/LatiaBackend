<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'cost',
        'price',
        'description',
        'is_active',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function ingredients()
    {
        return $this->hasMany(ProductIngredient::class);
    }
}
