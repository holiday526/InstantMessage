<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    //
    protected $table = 'messages';
    protected $fillable = ['from', 'to', 'message', 'is_read'];
}
