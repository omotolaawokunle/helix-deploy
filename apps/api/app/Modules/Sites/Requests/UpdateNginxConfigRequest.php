<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNginxConfigRequest extends FormRequest
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
            'config' => ['required', 'string', 'max:65535'],
        ];
    }

    public function config(): string
    {
        return (string) $this->validated('config');
    }
}
