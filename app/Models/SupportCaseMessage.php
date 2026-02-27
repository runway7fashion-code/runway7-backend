<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCaseMessage extends Model
{
    protected $fillable = [
        'support_case_id', 'sender_type', 'team_member_id',
        'message', 'message_date',
    ];

    protected $casts = [
        'message_date' => 'date',
    ];

    public function supportCase() { return $this->belongsTo(SupportCase::class); }
    public function teamMember() { return $this->belongsTo(User::class, 'team_member_id'); }
    public function attachments() { return $this->hasMany(SupportCaseAttachment::class); }
}
