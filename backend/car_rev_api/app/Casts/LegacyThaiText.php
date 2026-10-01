<?php

namespace App\Casts;

use App\Support\LegacyText;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a text column as TIS-620 and exposes it to the application as UTF-8.
 *
 * The intra database is WE8DEC and keeps whatever bytes it is handed, so the
 * legacy intranet pages and SQL tools read every column as TIS-620. Writing
 * UTF-8 there turns each Thai character into three bytes that show up as
 * "เธ..." everywhere outside this application.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class LegacyThaiText implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return LegacyText::toUtf8($value === null ? null : (string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return LegacyText::toLegacy($value === null ? null : (string) $value);
    }
}
