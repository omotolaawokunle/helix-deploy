<?php

declare(strict_types=1);

namespace App\Modules\Servers\Requests;

use App\Modules\Monitoring\Services\RemoteLogReader;
use App\Modules\Servers\Enums\ServerLogType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FetchServerLogsRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::enum(ServerLogType::class)],
            'lines' => ['sometimes', 'integer', 'min:'.RemoteLogReader::MIN_LINES, 'max:'.RemoteLogReader::MAX_LINES],
            'refresh' => ['sometimes', 'boolean'],
        ];
    }

    public function logType(): ServerLogType
    {
        return ServerLogType::from((string) $this->validated('type'));
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
