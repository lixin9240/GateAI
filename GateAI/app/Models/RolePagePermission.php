<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class RolePagePermission extends Model
{
    use HasBeijingTime;

    protected $table = 'role_page_permissions';

    protected $fillable = ['page_id', 'role_name'];
}
