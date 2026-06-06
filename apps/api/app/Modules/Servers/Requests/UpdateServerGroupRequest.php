<?php

declare(strict_types=1);

namespace App\Modules\Servers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServerGroupRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
