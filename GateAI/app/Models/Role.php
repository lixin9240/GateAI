<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'remark',
    ];

    /**
     * 关联用户
     */
    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
