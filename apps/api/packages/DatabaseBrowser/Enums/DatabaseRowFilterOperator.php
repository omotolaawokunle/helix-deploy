<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\Enums;

enum DatabaseRowFilterOperator: string
{
    case EQ = 'eq';
    case NEQ = 'neq';
    case CONTAINS = 'contains';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';
    case GT = 'gt';
    case GTE = 'gte';
    case LT = 'lt';
    case LTE = 'lte';
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $operator): string => $operator->value, self::cases());
    }

    public function requiresValue(): bool
    {
        return ! in_array($this, [self::IS_NULL, self::IS_NOT_NULL], true);
    }
}
