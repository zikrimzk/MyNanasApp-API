<?php

namespace App\Models;

use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Premise extends Model
{
    use HasFactory;

    protected $primaryKey = 'premiseID';

    protected $fillable = [
        'premise_type', 'premise_name', 'premise_address', 'premise_state',
        'premise_city', 'premise_postcode', 'premise_landsize', 'premise_status',
        'premise_coordinates', 'entID'
    ];

    public function user() {
        return $this->belongsTo(User::class, 'entID');
    }

    public function products() {
        return $this->hasMany(Product::class, 'premiseID');
    }
}
