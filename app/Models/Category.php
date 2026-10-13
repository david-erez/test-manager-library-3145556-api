<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $table = 'library.category';

    protected $fillable = ['name'];
}
