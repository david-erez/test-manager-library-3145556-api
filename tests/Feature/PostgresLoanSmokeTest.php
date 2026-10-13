<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\User;
use App\Support\Jwt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgresLoanSmokeTest extends TestCase
{
    private ?Connection $pgConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_POSTGRES_SMOKE') !== '1') {
            $this->markTestSkipped('Set RUN_POSTGRES_SMOKE=1 to run against PostgreSQL.');
        }

        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        $this->pgConnection = DB::connection('pgsql');
        $this->pgConnection->getPdo();
        $this->pgConnection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pgConnection !== null && $this->pgConnection->transactionLevel() > 0) {
            $this->pgConnection->rollBack();
        }

        parent::tearDown();
    }

    public function test_postgres_loan_return_overdue_and_report_flow(): void
    {
        $user = User::create([
            'username' => 'smoke-'.Str::uuid(),
            'password_hash' => Hash::make(Str::random(32)),
            'role' => User::ROLE_ADMIN,
        ]);
        $member = Member::create([
            'full_name' => 'Smoke Test Member',
            'document_number' => 'SMOKE-'.Str::random(20),
        ]);
        $category = Category::query()->firstOrFail();
        $book = Book::create([
            'title' => 'Smoke Test Book',
            'author' => 'Smoke Test',
            'isbn' => sprintf('%013d', random_int(1000000000000, 9999999999999)),
            'category_id' => $category->id,
            'total_copies' => 3,
            'available_copies' => 3,
        ]);

        $this->withHeader('Authorization', 'Bearer '.Jwt::issue($user->id, User::ROLE_ADMIN)['access_token']);

        $loanResponse = $this->postJson('/api/v1/loans', [
            'member_id' => $member->id,
            'items' => [['book_id' => $book->id]],
        ]);
        $loanResponse->assertCreated()
            ->assertJsonPath('items.0.book_title', $book->title)
            ->assertJsonPath('items.0.isbn', $book->isbn)
            ->assertJsonPath('items.0.category_name', $category->name);

        $loan = $loanResponse->json();
        $returnResponse = $this->postJson('/api/v1/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return');
        $returnResponse->assertOk()->assertJsonPath('returned_at', fn ($value) => $value !== null);

        $secondLoanResponse = $this->postJson('/api/v1/loans', [
            'member_id' => $member->id,
            'items' => [['book_id' => $book->id]],
        ]);
        $secondLoanResponse->assertCreated();
        $secondLoan = $secondLoanResponse->json();

        LoanItem::whereKey($secondLoan['items'][0]['id'])
            ->update(['due_at' => CarbonImmutable::now('UTC')->subDays(3)]);

        $this->getJson('/api/v1/loans/overdue')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.book_title', $book->title);

        $from = CarbonImmutable::now('UTC')->subDay()->toDateString();
        $to = CarbonImmutable::now('UTC')->addDay()->toDateString();
        $this->getJson('/api/v1/reports/most-borrowed?from='.$from.'&to='.$to)
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.book_id', $book->id)
            ->assertJsonPath('rows.0.times_borrowed', 2);
    }
}
