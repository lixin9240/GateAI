<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLock extends Model
{
    protected $table = 'user_locks';

    protected $fillable = [
        'user_id',
        'reason',
        'duration',
        'locked_at',
        'unlock_at',
        'unlock_type',
        'unlocked_by',
        'unlocked_at',
        'locked_by',
    ];

    protected $casts = [
        'id'        => 'integer',
        'user_id'   => 'integer',
        'duration'  => 'integer',
        'locked_by' => 'integer',
        'unlocked_by' => 'integer',
        'locked_at' => 'datetime',
        'unlock_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function unlocker()
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }
}
