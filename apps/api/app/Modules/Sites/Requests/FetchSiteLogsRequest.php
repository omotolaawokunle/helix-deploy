<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Modules\Monitoring\Services\RemoteLogReader;
use App\Modules\Sites\Enums\SiteLogType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'type' => ['required', 'string', Rule::enum(SiteLogType::class)],
            'lines' => ['sometimes', 'integer', 'min:'.RemoteLogReader::MIN_LINES, 'max:'.RemoteLogReader::MAX_LINES],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function logType(): SiteLogType
    {
        return SiteLogType::from((string) $this->validated('type'));
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
