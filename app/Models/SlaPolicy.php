<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use Auditable, BelongsToProperty;

    protected $guarded = ['id'];
}
