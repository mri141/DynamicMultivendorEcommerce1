<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCare extends Model
{
protected $table = 'customer_care';

    protected $fillable = [
        'reason',
        'email',
        'mobile',
        'decription'
        
    ];
}
