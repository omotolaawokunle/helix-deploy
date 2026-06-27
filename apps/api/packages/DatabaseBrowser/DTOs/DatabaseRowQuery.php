<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\DTOs;

final class DatabaseRowQuery
{
    /**
     * @param list<DatabaseRowFilter> $filters
     */
    public function __construct(
        public readonly int $page = 1,
        public readonly int $limit = 50,
        public readonly array $filters = [],
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public function fetchLimit(): int
    {
        return $this->limit + 1;
    }

    public function fingerprint(): string
    {
        $normalized = array_map(
            static fn (DatabaseRowFilter $filter): array => $filter->toArray(),
            $this->filters,
        );

        return hash('sha256', json_encode([
            'page' => $this->page,
            'limit' => $this->limit,
            'filters' => $normalized,
        ], JSON_THROW_ON_ERROR));
    }
}
