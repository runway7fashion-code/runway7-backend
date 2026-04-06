<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpAttachment extends Model
{
    protected $fillable = ['article_id', 'file_path', 'file_name', 'file_type'];

    public function article()
    {
        return $this->belongsTo(HelpArticle::class, 'article_id');
    }
}
