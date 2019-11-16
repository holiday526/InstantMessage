<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserKey extends Model
{
    //
    protected $fillable = ['from', 'to', 'aws_data_key_id'];
}
