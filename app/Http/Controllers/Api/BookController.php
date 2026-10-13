<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Category;
use App\Models\LoanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookController extends Controller
{
    // spec.md AC-04.1/AC-04.2: partial text search, category filter, active only, by title,
    // paginated with a total count.
    public function index(Request $request)
    {
        $size = min((int) $request->integer('size', 20), 50);
        $page = max((int) $request->integer('page', 1), 1);

        $query = Book::query()->with('category')->orderBy('title');

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ilike', "%{$q}%")->orWhere('author', 'ilike', "%{$q}%");
            });
        }

        if ($categoryId = $request->string('category_id')->toString()) {
            $query->where('category_id', $categoryId);
        }

        $total = $query->count();
        $items = $query->forPage($page, $size)->get();

        return response()->json([
            'items' => $items->map($this->present(...)),
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ]);
    }

    // spec.md AC-04.3: nonexistent or soft-deleted id -> 404.
    public function show(string $id)
    {
        return response()->json($this->present(Book::with('category')->findOrFail($id)));
    }

    // spec.md AC-03.1-03.2: available copies start equal to the total; ISBN unique among active books.
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'isbn' => ['required', 'regex:/^\d{13}$/'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        if (! Category::whereKey($data['category_id'])->exists()) {
            throw new ApiException('VALIDATION_ERROR', 'La categoría no existe.', 422, [
                ['field' => 'category_id', 'issue' => 'no existe'],
            ]);
        }

        if (Book::where('isbn', $data['isbn'])->exists()) {
            throw new ApiException('ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.', 409);
        }

        $book = Book::create([
            ...$data,
            'available_copies' => $data['total_copies'],
        ]);

        return response()->json($this->present($book->load('category')), 201);
    }

    // spec.md AC-03.3: changing the total shifts available copies by the same difference,
    // and it may never go negative.
    public function update(Request $request, string $id)
    {
        $book = Book::findOrFail($id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ]);

        if (! Category::whereKey($data['category_id'])->exists()) {
            throw new ApiException('VALIDATION_ERROR', 'La categoría no existe.', 422, [
                ['field' => 'category_id', 'issue' => 'no existe'],
            ]);
        }

        $newAvailable = $book->available_copies + ($data['total_copies'] - $book->total_copies);

        if ($newAvailable < 0) {
            throw new ApiException('VALIDATION_ERROR', 'El nuevo total deja ejemplares disponibles negativos.', 422, [
                ['field' => 'total_copies', 'issue' => 'deja disponibles negativos'],
            ]);
        }

        $book->update([...$data, 'available_copies' => $newAvailable]);

        return response()->json($this->present($book->load('category')));
    }

    // ADR-003: a book with open loans is not deleted.
    public function destroy(string $id)
    {
        $book = Book::findOrFail($id);

        $hasOpenLoans = LoanItem::where('book_id', $id)->whereNull('returned_at')->exists();

        if ($hasOpenLoans) {
            throw new ApiException('BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos abiertos.', 409);
        }

        $book->delete();

        return response()->json(status: 204);
    }

    // spec.md AC-03.4: opaque key, validated by content, max 2 MB.
    public function uploadCover(Request $request, string $id)
    {
        $book = Book::findOrFail($id);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png', 'max:2048'],
        ]);

        if ($book->cover_key) {
            Storage::disk('covers')->delete($book->cover_key);
        }

        $key = Str::uuid().'.'.$request->file('file')->guessExtension();
        Storage::disk('covers')->putFileAs('', $request->file('file'), $key);

        $book->update(['cover_key' => $key]);

        return response()->json($this->present($book->load('category')));
    }

    public function showCover(string $id)
    {
        $book = Book::findOrFail($id);

        if (! $book->cover_key || ! Storage::disk('covers')->exists($book->cover_key)) {
            throw new ApiException('NOT_FOUND', 'Este libro no tiene portada.', 404);
        }

        return Storage::disk('covers')->response($book->cover_key);
    }

    public function deleteCover(string $id)
    {
        $book = Book::findOrFail($id);

        if ($book->cover_key) {
            Storage::disk('covers')->delete($book->cover_key);
        }

        $book->update(['cover_key' => null]);

        return response()->json(status: 204);
    }

    private function present(Book $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'category' => ['id' => $book->category->id, 'name' => $book->category->name],
            'total_copies' => $book->total_copies,
            'available_copies' => $book->available_copies,
            'cover_key' => $book->cover_key,
        ];
    }
}
