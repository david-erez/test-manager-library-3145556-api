<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\LoanItem;
use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    // spec.md AC-05.2: list and search by name or document, paginated.
    public function index(Request $request)
    {
        $size = min((int) $request->integer('size', 20), 50);
        $page = max((int) $request->integer('page', 1), 1);

        $query = Member::query()->orderBy('full_name');

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'ilike', "%{$q}%")->orWhere('document_number', 'ilike', "%{$q}%");
            });
        }

        $total = $query->count();
        $items = $query->forPage($page, $size)->get();

        return response()->json([
            'items' => $items,
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ]);
    }

    // api-contract.md §3.5: detail includes active_loans (count of unreturned items).
    public function show(string $id)
    {
        $member = Member::findOrFail($id);

        $activeLoans = LoanItem::whereHas('loan', fn ($q) => $q->where('member_id', $id))
            ->whereNull('returned_at')
            ->count();

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->full_name,
            'document_number' => $member->document_number,
            'active_loans' => $activeLoans,
        ]);
    }

    // spec.md AC-05.1: document number is unique.
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'document_number' => ['required', 'string', 'max:30'],
        ]);

        if (Member::where('document_number', $data['document_number'])->exists()) {
            throw new ApiException('DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.', 409);
        }

        $member = Member::create($data);

        return response()->json($member, 201);
    }
}
