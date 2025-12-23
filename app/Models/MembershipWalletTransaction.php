<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipWalletTransaction extends Model
{
    protected $table = 'membership_wallet_transaction';         // Nếu tên bảng không theo chuẩn
    protected $primaryKey = 'id';          // Nếu khoá chính khác tên mặc định

    public $timestamps = false;            // Nếu bảng có cột created_at & updated_at

    protected $fillable = [
        'user_id',
        'membership_plan_id',
        'transaction_type',
        'transaction_date'
    ];
}
