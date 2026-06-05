<?php

declare(strict_types=1);

namespace App\Modules\Servers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncServerGroupServersRequest extends FormRequest
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
            'serverIds' => ['present', 'array'],
            'serverIds.*' => ['uuid'],
        ];
    }
}
