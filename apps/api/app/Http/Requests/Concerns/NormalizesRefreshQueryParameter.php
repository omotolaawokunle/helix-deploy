<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait NormalizesRefreshQueryParameter
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('refresh')) {
            return;
        }

        $refresh = $this->input('refresh');

        if (is_bool($refresh) || is_int($refresh)) {
            return;
        }

        if (! is_string($refresh)) {
            return;
        }

        $normalized = filter_var($refresh, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized !== null) {
            $this->merge(['refresh' => $normalized]);
        }
    }
}
