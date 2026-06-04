<?php

declare(strict_types=1);

namespace App\Modules\Audit\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ExportAuditLogsRequest extends FormRequest
{
    private const MAX_EXPORT_DAYS = 90;

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
            'operation' => ['sometimes', 'string', 'max:255'],
            'actor_id' => ['sometimes', 'uuid'],
            'resource_type' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dateFrom = $this->query('date_from');
            $dateTo = $this->query('date_to');

            if (! is_string($dateFrom) || ! is_string($dateTo)) {
                return;
            }

            $from = CarbonImmutable::parse($dateFrom);
            $to = CarbonImmutable::parse($dateTo);

            if ($from->diffInDays($to) > self::MAX_EXPORT_DAYS) {
                $validator->errors()->add(
                    'date_to',
                    'Export range cannot exceed '.self::MAX_EXPORT_DAYS.' days.',
                );
            }
        });
    }
}
