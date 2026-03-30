<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HelpArticle extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'description', 'content',
        'status', 'author_id', 'sort_order',
    ];

    const CATEGORIES = [
        'general'    => 'General',
        'sales'      => 'Sales',
        'operations' => 'Operations',
        'models'     => 'Models',
        'designers'  => 'Designers',
        'media'      => 'Media',
        'volunteers' => 'Volunteers',
        'accounting' => 'Accounting',
        'events'     => 'Events',
        'tickets'    => 'Tickets',
        'marketing'  => 'Marketing',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function attachments()
    {
        return $this->hasMany(HelpAttachment::class, 'article_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
        });
    }
}
