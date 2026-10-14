<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleMenuAccess extends Model
{
    protected $table = 'role_menu_access';

    protected $fillable = ['role_id', 'menu_key', 'is_visible', 'updated_by'];

    protected $casts = [
        'is_visible' => 'boolean',
    ];
}
