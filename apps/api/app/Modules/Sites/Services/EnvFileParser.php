<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

final class EnvFileParser
{
    private const string VALID_KEY_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    /**
     * @return array{
     *     entries: array<string, string>,
     *     skipped: list<array{key: string, reason: string}>
     * }
     */
    public function parse(string $content): array
    {
        $entries = [];
        $skipped = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $exportPrefix = str_starts_with($line, 'export ') ? 7 : 0;
            $normalized = substr($line, $exportPrefix);
            $separatorIndex = strpos($normalized, '=');

            if ($separatorIndex === false || $separatorIndex <= 0) {
                continue;
            }

            $key = trim(substr($normalized, 0, $separatorIndex));
            $value = trim(substr($normalized, $separatorIndex + 1));
            $value = $this->unquoteValue($value);

            if (! preg_match(self::VALID_KEY_PATTERN, $key)) {
                $skipped[] = [
                    'key' => $key,
                    'reason' => 'Key must match ^[A-Z][A-Z0-9_]*$',
                ];

                continue;
            }

            $entries[$key] = $value;
        }

        return [
            'entries' => $entries,
            'skipped' => $skipped,
        ];
    }

    private function unquoteValue(string $value): string
    {
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, '\'') && str_ends_with($value, '\''))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
