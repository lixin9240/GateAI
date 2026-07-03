<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'code',
        'remark',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
