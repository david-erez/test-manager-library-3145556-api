<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoanEndpointValidationTest extends TestCase
{
    public function test_loan_rejects_duplicate_books_with_the_business_error_code(): void
    {
        $this->withoutMiddleware();

        $response = $this->postJson('/api/v1/loans', [
            'member_id' => '11111111-1111-4111-8111-111111111111',
            'items' => [
                ['book_id' => '22222222-2222-4222-8222-222222222222'],
                ['book_id' => '22222222-2222-4222-8222-222222222222'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'DUPLICATE_BOOK_IN_LOAN');
    }

    public function test_report_rejects_an_inverted_date_range(): void
    {
        $this->withoutMiddleware();

        $response = $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-20&to=2026-10-01');

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_DATE_RANGE');
    }
}
