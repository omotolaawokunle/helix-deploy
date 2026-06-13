<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Modules\Sites\Enums\EnvVarPullStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyEnvVarsPullRequest extends FormRequest
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
        return [
            'strategy' => ['required', 'string', Rule::enum(EnvVarPullStrategy::class)],
        ];
    }

    public function strategy(): EnvVarPullStrategy
    {
        return EnvVarPullStrategy::from((string) $this->validated('strategy'));
    }
}
