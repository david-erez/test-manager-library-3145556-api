# Technical decisions — translating the spec to React + Laravel

This document translates `plan.md` §1 (written for Python) to the real stack of the challenge:
**React + Laravel**, as required by the six repository READMEs. The business rules, the data model
and the API contract **do not change** — they are fixed in
`test-manager-library-3145556-docs/spec-python/` (or `spec-.net/`, same business content). Only the
"Choice" column changes here.

| # | Decision | Choice (Python, spec) | Choice (this repo) | Why |
|---|---|---|---|---|
| D-01 | Language and runtime | Python 3.12 | PHP 8.3 + Laravel 11 | Challenge stack (README) |
| D-02 | HTTP framework | FastAPI | Laravel (`routes/api.php`, prefix `/api/v1`) | Same contract in `api-contract.md` for both |
| D-03 | Persistence | SQLAlchemy 2 + Alembic | Eloquent + Laravel migrations, on PostgreSQL 16 | The schema still belongs to the migrations (ADR-001); `infra` boots an empty engine |
| D-04 | Concurrency | Version witness + range `CHECK` | Range `CHECK` in the migration (`ck_book_available_range`) + a transaction with row-level locking (`lockForUpdate`) | Same requirement, RNF-2: the engine is the last line of defense |
| D-05 | Dates | `timestamptz`; the clock is a port (`Clock`) | `timestamptz`; an injectable `Clock` of our own (no `now()` scattered around) so due dates can be tested without waiting 14 days | Same reason |
| D-06 | Report | One aggregated query behind a read port | Query Builder/Eloquent with one aggregated query in a read-only class | Stable and cheap, same as the original |
| D-07 | Value objects | `Isbn`, `Quantity`, `DocumentNumber`, `DateRange` | Same concepts as immutable PHP classes in `app/Domain/ValueObjects/`, no Eloquent | They have no identity; validated in the domain, not the framework |
| D-08 | Covers | Local disk / S3 behind a port | Laravel `Storage` (`local` driver, swappable to `s3` by config) | Opaque key (`cover_key`), never a path or raw bytes |
| D-09 | Passwords | Argon2id behind `PasswordHasher` | Laravel `Hash::make()` (Bcrypt/Argon2id via `config/hashing.php`) | The domain never sees the plain password (Article IX) |
| D-10 | Bootstrap | The app creates the initial admin from `ADMIN_USERNAME`/`ADMIN_PASSWORD` | Same: an idempotent seeder/artisan command that reads those variables on boot, not in a migration | The database cannot produce the hash |
| D-11 | Authentication | JWT HS256, 8h, `sub` + `role` | Hand-rolled JWT (HS256, 8h) issued with `firebase/php-jwt`, a verification middleware plus role-based authorization | Same contract (`Authorization: Bearer <token>`); avoids the overhead of a session package we don't need (stateless API) |
| D-12 | Tests | pytest with fakes + a real database for integration | Pest/PHPUnit: domain unit tests (no framework), feature tests against the real API, migrations applied on a real Postgres | Same test pyramid |

## What is not translated (business, not technology)

User stories (HU-01 to HU-10), business rules (RN-1 to RN-6), non-functional requirements (RNF-1 to
RNF-5), the full data model (`data-model.md`) and the HTTP contract (`api-contract.md`) are
implemented **as written**. No decision in this table changes them.

## Folder structure (Laravel equivalent of `architecture.md` §2)

```text
app/
├── Domain/              # Book, Loan, LoanItem, Member, User; Isbn, Quantity, DateRange (plain PHP, no Eloquent)
├── Services/            # Use cases: LendBooksService, ReturnItemService, LoginService, ...
├── Models/               # Eloquent: Category, Book, Member, Loan, LoanItem, User (persistence adapter)
├── Http/
│   ├── Controllers/Api/ # Thin controllers: validate, call Services/, format the response
│   ├── Requests/        # Form Requests = input validation
│   └── Middleware/       # JwtAuthenticate, EnsureRole
└── Support/              # Clock, TokenIssuer, CoverStorage (the "ports" of the spec, as PHP interfaces)
```

Controllers hold no business rules; the rules live in `Domain/` and `Services/`. This is not the
hexagonal architecture with `import-linter` from the Python spec (not idiomatic in Laravel, and the
test's time budget doesn't cover building that automated check), but it keeps the principle: business
logic doesn't depend on framework details, and there is a single place (`Services/`) where each use
case is orchestrated.
