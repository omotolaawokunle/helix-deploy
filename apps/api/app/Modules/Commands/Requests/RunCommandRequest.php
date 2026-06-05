<?php

declare(strict_types=1);

namespace App\Modules\Commands\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxTimeout = (int) config('helixdeploy.command_timeout_max_seconds', 300);

        return [
            'command' => ['required', 'string', 'max:65535'],
            'confirmed' => ['sometimes', 'boolean'],
            'timeout' => ['sometimes', 'integer', 'min:5', 'max:'.$maxTimeout],
        ];
    }

    public function command(): string
    {
        return (string) $this->validated('command');
    }

    public function isConfirmed(): bool
    {
        return (bool) ($this->validated('confirmed') ?? false);
    }

    public function timeoutSeconds(): ?int
    {
        $timeout = $this->validated('timeout');

        return is_int($timeout) ? $timeout : null;
    }
}
