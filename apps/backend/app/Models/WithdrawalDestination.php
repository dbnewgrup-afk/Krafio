<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalDestination extends Model
{
    protected $table = 'withdrawal_destinations';

    protected $guarded = ['id'];

    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
        ];
    }
}
