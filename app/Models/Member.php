<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

// BR-5: members are never deleted.
class Member extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $table = 'library.member';

    protected $fillable = ['full_name', 'document_number'];
}
