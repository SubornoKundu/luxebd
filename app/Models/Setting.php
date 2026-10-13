<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value store for the admin "Site settings" page.
 */
class Setting extends Model
{
    /** @var list<string> */
    protected $fillable = ['key', 'value'];
}
