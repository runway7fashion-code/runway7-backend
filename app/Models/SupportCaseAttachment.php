<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCaseAttachment extends Model
{
    protected $fillable = ['support_case_message_id', 'file_url', 'file_name', 'file_type', 'file_size'];

    public function message() { return $this->belongsTo(SupportCaseMessage::class, 'support_case_message_id'); }
}
