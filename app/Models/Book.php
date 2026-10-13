<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// ADR-003: deleted_at is a soft delete; SoftDeletes already filters active-only by default,
// matching "el catálogo filtra por deleted_at IS NULL en un único lugar".
class Book extends Model
{
    use HasUuid, SoftDeletes;

    public $timestamps = false;

    protected $table = 'library.book';

    protected $fillable = [
        'title', 'author', 'isbn', 'category_id', 'total_copies', 'available_copies', 'cover_key',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
