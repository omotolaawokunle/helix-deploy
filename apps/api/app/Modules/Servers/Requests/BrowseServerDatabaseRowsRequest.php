<?php

declare(strict_types=1);

namespace App\Modules\Servers\Requests;

use App\Http\Requests\Concerns\NormalizesRefreshQueryParameter;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use App\Packages\DatabaseBrowser\Support\DatabaseRowQueryFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BrowseServerDatabaseRowsRequest extends FormRequest
{
    use NormalizesRefreshQueryParameter;

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
            ...DatabaseRowQueryFactory::validationRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        DatabaseRowQueryFactory::configureValidator($validator);
    }

    public function engine(): DatabaseEngine
    {
        return DatabaseEngine::from((string) $this->validated('engine'));
    }

    public function rowQuery(): DatabaseRowQuery
    {
        return DatabaseRowQueryFactory::fromRequest($this);
    }

    public function shouldRefresh(): bool
    {
        return (bool) $this->boolean('refresh');
    }
}
