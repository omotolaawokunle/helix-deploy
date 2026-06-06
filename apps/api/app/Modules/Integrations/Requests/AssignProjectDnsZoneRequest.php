<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Requests;

use App\Modules\Integrations\Enums\DnsProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'dnsProvider' => ['required', 'string', Rule::enum(DnsProvider::class)],
            'zoneId' => ['required', 'string', 'max:255'],
            'baseDomain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/i'],
        ];
    }
}
