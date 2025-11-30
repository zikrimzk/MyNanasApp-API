<?php

namespace App\Models;

use App\Models\Premise;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'productID';

    protected $fillable = [
        'product_name', 'product_desc', 'product_qty', 'product_unit',
        'product_price', 'product_status', 'product_image', 'categoryID', 'premiseID'
    ];

    public function category() {
        return $this->belongsTo(ProductCategory::class, 'categoryID');
    }

    public function premise() {
        return $this->belongsTo(Premise::class, 'premiseID');
    }
}
