<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

// spec.md HU-02/AC-02.2: username normalized (lowercase, trimmed) and unique. The password
// hash is never visible outside the model (Article IX / NFR-3).
class User extends Model
{
    use HasUuid;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_LIBRARIAN = 'librarian';

    public $timestamps = false;

    protected $table = 'library.user';

    protected $fillable = ['username', 'password_hash', 'role'];

    protected $hidden = ['password_hash'];

    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = strtolower(trim($value));
    }
}
