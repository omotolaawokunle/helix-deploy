<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Requests;

use App\Modules\CronJobs\Services\CronService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCronJobRequest extends FormRequest
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
            'expression' => ['required', 'string', 'max:255'],
            'command' => ['required', 'string', 'max:2000'],
            'user' => ['nullable', 'string', 'max:64'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $expression = (string) $this->input('expression');

            try {
                app(CronService::class)->validate($expression);
            } catch (\App\Modules\CronJobs\Exceptions\InvalidCronExpressionException $exception) {
                $validator->errors()->add('expression', $exception->getMessage());
            }
        });
    }
}
