<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Http\Requests\Concerns\NormalizesRefreshQueryParameter;
use Illuminate\Foundation\Http\FormRequest;

class BrowseSiteDatabaseTablesRequest extends FormRequest
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
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function shouldRefresh(): bool
    {
        return (bool) $this->boolean('refresh');
    }
}
