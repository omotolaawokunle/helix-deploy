<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrowseSiteDatabaseTablesRequest extends FormRequest
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
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function shouldRefresh(): bool
    {
        return (bool) $this->boolean('refresh');
    }
}
