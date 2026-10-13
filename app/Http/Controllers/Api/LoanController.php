<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:3'],
            'items.*.book_id' => ['required', 'uuid'],
        ]);

        $bookIds = collect($data['items'])->pluck('book_id');

        if ($bookIds->unique()->count() !== $bookIds->count()) {
            throw new ApiException('DUPLICATE_BOOK_IN_LOAN', 'No se puede repetir un libro en el mismo préstamo.', 422, [
                ['field' => 'items', 'issue' => 'contiene libros repetidos'],
            ]);
        }

        $loan = DB::transaction(function () use ($data, $bookIds, $request) {
            $member = Member::query()->whereKey($data['member_id'])->lockForUpdate()->firstOrFail();
            $activeItems = LoanItem::query()
                ->whereHas('loan', fn ($query) => $query->where('member_id', $member->id))
                ->whereNull('returned_at');
            $now = CarbonImmutable::now('UTC');

            if ((clone $activeItems)->where('due_at', '<', $now)->exists()) {
                throw new ApiException('MEMBER_HAS_OVERDUE', 'El socio tiene préstamos vencidos.', 409);
            }

            if ((clone $activeItems)->count() + count($data['items']) > 3) {
                throw new ApiException('MEMBER_LIMIT_EXCEEDED', 'El socio no puede tener más de tres libros sin devolver.', 422, [
                    ['field' => 'member_id', 'issue' => 'supera el límite de libros sin devolver'],
                ]);
            }

            $books = Book::query()
                ->with('category')
                ->whereIn('id', $bookIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($books->count() !== $bookIds->count()) {
                throw new ApiException('NOT_FOUND', 'Uno o más libros no existen o están dados de baja.', 404);
            }

            foreach ($bookIds as $bookId) {
                if ($books[$bookId]->available_copies < 1) {
                    throw new ApiException('BOOK_NOT_AVAILABLE', 'No quedan ejemplares disponibles.', 409, [
                        ['field' => 'items', 'issue' => 'un libro no tiene ejemplares disponibles'],
                    ]);
                }
            }

            $loan = Loan::create([
                'member_id' => $member->id,
                'loaned_by_user_id' => $request->attributes->get('auth_user_id'),
                'loaned_at' => $now,
            ]);

            foreach ($bookIds as $bookId) {
                $book = $books[$bookId];
                $book->decrement('available_copies');

                $loan->items()->create([
                    'book_id' => $book->id,
                    'book_title' => $book->title,
                    'isbn' => $book->isbn,
                    'category_name' => $book->category->name,
                    'due_at' => $now->addDays(14),
                ]);
            }

            return $loan->load('items');
        });

        return response()->json($this->presentLoan($loan), 201);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'in:open,overdue,closed'],
            'member_id' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $this->validateDateRange($data['from'] ?? null, $data['to'] ?? null);

        $page = (int) ($data['page'] ?? 1);
        $size = (int) ($data['size'] ?? 20);
        $now = CarbonImmutable::now('UTC');
        $query = Loan::query()->with('items')->orderByDesc('loaned_at');

        if (isset($data['from'])) {
            $query->whereDate('loaned_at', '>=', $data['from']);
        }

        if (isset($data['to'])) {
            $query->whereDate('loaned_at', '<=', $data['to']);
        }

        if (isset($data['member_id'])) {
            $query->where('member_id', $data['member_id']);
        }

        if (($data['status'] ?? null) === 'closed') {
            $query->whereDoesntHave('items', fn ($items) => $items->whereNull('returned_at'));
        } elseif (($data['status'] ?? null) === 'overdue') {
            $query->whereHas('items', fn ($items) => $items->whereNull('returned_at')->where('due_at', '<', $now));
        } elseif (($data['status'] ?? null) === 'open') {
            $query->whereHas('items', fn ($items) => $items->whereNull('returned_at'))
                ->whereDoesntHave('items', fn ($items) => $items->whereNull('returned_at')->where('due_at', '<', $now));
        }

        $total = (clone $query)->count();
        $loans = $query->forPage($page, $size)->get();

        return response()->json([
            'items' => $loans->map($this->presentLoan(...)),
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function show(string $id)
    {
        $loan = Loan::with('items')->findOrFail($id);

        return response()->json($this->presentLoan($loan));
    }

    public function returnItem(string $loanId, string $itemId)
    {
        $item = DB::transaction(function () use ($loanId, $itemId) {
            $item = LoanItem::query()
                ->where('loan_id', $loanId)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->returned_at !== null) {
                throw new ApiException('ITEM_ALREADY_RETURNED', 'Esta línea ya fue devuelta.', 409);
            }

            $book = Book::query()->whereKey($item->book_id)->lockForUpdate()->firstOrFail();
            $item->update(['returned_at' => CarbonImmutable::now('UTC')]);
            $book->increment('available_copies');

            return $item->fresh();
        });

        return response()->json($this->presentItem($item));
    }

    public function overdue()
    {
        $now = CarbonImmutable::now('UTC');
        $items = LoanItem::query()
            ->with(['loan.member'])
            ->whereNull('returned_at')
            ->where('due_at', '<', $now)
            ->orderBy('due_at')
            ->get();

        return response()->json([
            'items' => $items->map(fn (LoanItem $item) => [
                'loan_id' => $item->loan_id,
                'item_id' => $item->id,
                'book_title' => $item->book_title,
                'member_name' => $item->loan->member->full_name,
                'due_at' => $item->due_at->toISOString(),
                'days_late' => (int) $item->due_at->diffInDays($now),
            ]),
        ]);
    }

    private function validateDateRange(?string $from, ?string $to): void
    {
        if ($from !== null && $to !== null && $to < $from) {
            throw new ApiException('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.', 422, [
                ['field' => 'to', 'issue' => 'debe ser igual o posterior a from'],
            ]);
        }
    }

    private function presentLoan(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'member_id' => $loan->member_id,
            'loaned_at' => $loan->loaned_at->toISOString(),
            'status' => $loan->status(),
            'items' => $loan->items->map($this->presentItem(...))->all(),
        ];
    }

    private function presentItem(LoanItem $item): array
    {
        return [
            'id' => $item->id,
            'book_id' => $item->book_id,
            'book_title' => $item->book_title,
            'isbn' => $item->isbn,
            'category_name' => $item->category_name,
            'due_at' => $item->due_at->toISOString(),
            'returned_at' => $item->returned_at?->toISOString(),
        ];
    }
}
