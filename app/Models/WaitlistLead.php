<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitlistLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'full_name',
        'email',
        'phone',
        'business_type',
        'type',
        'message',
        'status',
    ];
}
