<?php

namespace App\Models;

use App\Models\User;
use App\Models\UserPost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use HasFactory;

    protected $primaryKey = 'postID';

    protected $fillable = [
        'post_images', 'post_caption', 'post_location', 'post_status',
        'post_views_count', 'post_likes_count', 'post_type', 'entID'
    ];

    public function user() {
        return $this->belongsTo(User::class, 'entID');
    }

    public function userPosts() {
        return $this->hasMany(UserPost::class, 'postID');
    }
}
