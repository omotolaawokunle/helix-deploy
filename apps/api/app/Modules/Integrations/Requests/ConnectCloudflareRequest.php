<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConnectCloudflareRequest extends FormRequest
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
            'token' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
