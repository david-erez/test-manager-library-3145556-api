<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function mostBorrowed(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($data['to'] < $data['from']) {
            throw new ApiException('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la inicial.', 422, [
                ['field' => 'to', 'issue' => 'debe ser igual o posterior a from'],
            ]);
        }

        $rows = DB::table('library.loan_item as item')
            ->join('library.loan as loan', 'loan.id', '=', 'item.loan_id')
            ->whereDate('loan.loaned_at', '>=', $data['from'])
            ->whereDate('loan.loaned_at', '<=', $data['to'])
            ->select([
                'item.book_id',
                'item.book_title',
                'item.category_name',
            ])
            ->selectRaw('COUNT(*) AS times_borrowed')
            ->groupBy('item.book_id', 'item.book_title', 'item.category_name')
            ->orderByDesc('times_borrowed')
            ->orderBy('item.book_title')
            ->get()
            ->map(fn ($row) => [
                'book_id' => $row->book_id,
                'book_title' => $row->book_title,
                'category_name' => $row->category_name,
                'times_borrowed' => (int) $row->times_borrowed,
            ]);

        return response()->json([
            'from' => $data['from'],
            'to' => $data['to'],
            'rows' => $rows,
        ]);
    }
}
