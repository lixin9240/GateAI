<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $table = 'roles';

    protected $fillable = [
        'name',   // 角色名称
        'code',   // 角色编码
        'remark', // 备注
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
