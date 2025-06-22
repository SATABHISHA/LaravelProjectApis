<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounts';
    protected $fillable = [
        'user_id', 'account_name', 'bank_name', 'remarks',
        'purpose', 'date_time', 'account_type', 'balance'
    ];
}
