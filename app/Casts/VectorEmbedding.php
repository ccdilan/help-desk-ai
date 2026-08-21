<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Converts between pgvector literal strings ('[0.1,0.2,...]') and float arrays.
 *
 * @implements CastsAttributes<list<float>|null, list<float>|string|null>
 */
class VectorEmbedding implements CastsAttributes
{
    /**
     * Parse a stored vector literal into a list of floats.
     *
     * @return list<float>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value, '[]');

        if ($trimmed === '') {
            return [];
        }

        /** @var list<float> */
        return array_map('floatval', explode(',', $trimmed));
    }

    /**
     * Encode a list of floats into a pgvector literal.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return '['.implode(',', $value).']';
    }
}
