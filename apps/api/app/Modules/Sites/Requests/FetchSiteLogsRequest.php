<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Modules\Monitoring\Services\RemoteLogReader;
use Illuminate\Foundation\Http\FormRequest;

class FetchSiteLogsRequest extends FormRequest
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
            'lines' => ['sometimes', 'integer', 'min:'.RemoteLogReader::MIN_LINES, 'max:'.RemoteLogReader::MAX_LINES],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function lineCount(): int
    {
        return (int) ($this->validated('lines') ?? 100);
    }

    public function shouldRefresh(): bool
    {
        return (bool) ($this->validated('refresh') ?? false);
    }
}
