<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

// architecture-overview.md idea #1: a loan is an immutable fact; only a line's returned_at changes.
// architecture-overview.md idea #3: status is computed, never stored (Article VII).
class Loan extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $table = 'library.loan';

    protected $fillable = ['member_id', 'loaned_by_user_id', 'loaned_at'];

    protected $casts = ['loaned_at' => 'datetime'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function loanedBy()
    {
        return $this->belongsTo(User::class, 'loaned_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(LoanItem::class);
    }

    /** AC-08.1: open | overdue | closed, computed from the lines, never stored. */
    public function status(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($items->every(fn (LoanItem $item) => $item->returned_at !== null)) {
            return 'closed';
        }

        $hasOverdue = $items->contains(
            fn (LoanItem $item) => $item->returned_at === null && $item->due_at->isPast()
        );

        return $hasOverdue ? 'overdue' : 'open';
    }
}
