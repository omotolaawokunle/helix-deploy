<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Modules\Sites\Enums\LaravelWorkerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetupLaravelWorkersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'workerType' => ['required', 'string', Rule::enum(LaravelWorkerType::class)],
        ];
    }

    public function workerType(): LaravelWorkerType
    {
        return LaravelWorkerType::from((string) $this->validated('workerType'));
    }
}
