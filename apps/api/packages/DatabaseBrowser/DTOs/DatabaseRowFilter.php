<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\DTOs;

use App\Packages\DatabaseBrowser\Enums\DatabaseRowFilterOperator;

final class DatabaseRowFilter
{
    public function __construct(
        public readonly string $column,
        public readonly DatabaseRowFilterOperator $operator,
        public readonly ?string $value,
    ) {
    }

    /**
     * @return array{column: string, operator: string, value: string|null}
     */
    public function toArray(): array
    {
        return [
            'column' => $this->column,
            'operator' => $this->operator->value,
            'value' => $this->value,
        ];
    }
}
