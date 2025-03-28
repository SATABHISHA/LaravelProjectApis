<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseUser extends Model
{
    use HasFactory;
    protected $table = 'User';

    // Specify the columns that are mass assignable
    protected $fillable = ['id', 'name', 'password'];
}
