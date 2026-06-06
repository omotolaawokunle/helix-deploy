<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignProjectDnsZoneRequest extends FormRequest
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
            'zoneId' => ['required', 'string', 'max:255'],
            'baseDomain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/i'],
        ];
    }
}
