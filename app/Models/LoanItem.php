<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

// BR-4 / ADR-004: book_title, isbn and category_name are frozen at loan time and never read
// from the live catalog again.
class LoanItem extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $table = 'library.loan_item';

    protected $fillable = [
        'loan_id', 'book_id', 'book_title', 'isbn', 'category_name', 'due_at', 'returned_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
