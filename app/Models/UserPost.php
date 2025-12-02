<?php

namespace App\Models;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPost extends Model
{
    use HasFactory;

    protected $primaryKey = 'userpostID';

    protected $fillable = [
        'is_liked', 'userpost_status', 'entID', 'postID'
    ];

    public function post() {
        return $this->belongsTo(Post::class, 'postID');
    }

    public function user() {
        return $this->belongsTo(User::class, 'entID');
    }
}
