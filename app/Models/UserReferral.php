<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserReferral extends Model
{
    protected $table = 'wp_user_referrals';        // Nếu tên bảng không theo chuẩn
    protected $primaryKey = 'id';          // Nếu khoá chính khác tên mặc định

    public $timestamps = false;          // Nếu bảng có cột created_at & updated_at

    protected $guarded = [];
}
