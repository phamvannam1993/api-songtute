<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WpUser extends Model
{
    protected $table = 'wp_users';
    protected $primaryKey = 'ID';
    public $incrementing = true;
    public $timestamps = false;

    protected $guarded = [];
}
