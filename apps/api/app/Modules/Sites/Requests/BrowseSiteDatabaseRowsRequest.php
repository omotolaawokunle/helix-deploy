<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Support\DatabaseRowQueryFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BrowseSiteDatabaseRowsRequest extends FormRequest
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
            ...DatabaseRowQueryFactory::validationRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        DatabaseRowQueryFactory::configureValidator($validator);
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
