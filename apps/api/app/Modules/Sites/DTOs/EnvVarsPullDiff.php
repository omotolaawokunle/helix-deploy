<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

final readonly class EnvVarsPullDiff
{
    /**
     * @param  list<string>  $new
     * @param  list<string>  $changed
     * @param  list<string>  $unchanged
     * @param  list<string>  $helixOnly
     * @param  list<array{key: string, reason: string}>  $skipped
     */
    public function __construct(
        public bool $serverFileExists,
        public array $new,
        public array $changed,
        public array $unchanged,
        public array $helixOnly,
        public array $skipped,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'serverFileExists' => $this->serverFileExists,
            'new' => $this->new,
            'changed' => $this->changed,
            'unchanged' => $this->unchanged,
            'helixOnly' => $this->helixOnly,
            'skipped' => $this->skipped,
        ];
    }
}
