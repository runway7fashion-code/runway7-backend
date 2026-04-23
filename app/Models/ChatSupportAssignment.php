<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSupportAssignment extends Model
{
    protected $fillable = ['role', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
