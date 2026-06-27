<?php

declare(strict_types=1);

namespace App\Modules\Servers\Requests;

use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseServerDatabaseTablesRequest extends FormRequest
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
            'engine' => ['required', Rule::in(DatabaseEngine::values())],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function engine(): DatabaseEngine
    {
        return DatabaseEngine::from((string) $this->validated('engine'));
    }

    public function shouldRefresh(): bool
    {
        return (bool) $this->boolean('refresh');
    }
}
