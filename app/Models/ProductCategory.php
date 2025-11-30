<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCategory extends Model
{
    use HasFactory;

    protected $primaryKey = 'categoryID';

    protected $fillable = [
        'category_name',
        'category_desc',
        'category_status'
    ];

    public function products() {
        return $this->hasMany(Product::class, 'categoryID');
    }
}
