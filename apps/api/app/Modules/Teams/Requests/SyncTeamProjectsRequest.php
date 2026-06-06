<?php

declare(strict_types=1);

namespace App\Modules\Teams\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncTeamProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'projectIds' => ['present', 'array'],
            'projectIds.*' => ['uuid'],
        ];
    }
}
