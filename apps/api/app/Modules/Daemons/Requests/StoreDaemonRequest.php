<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Requests;

use App\Modules\Daemons\Models\SupervisorProcess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDaemonRequest extends FormRequest
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
        $serverId = $this->route('server');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9\-]*$/',
                Rule::unique(SupervisorProcess::class, 'name')->where(
                    fn ($query) => $query->where('server_id', $serverId),
                ),
            ],
            'command' => ['required', 'string', 'max:2000'],
            'directory' => ['nullable', 'string', 'max:500'],
            'user' => ['nullable', 'string', 'max:64'],
            'processes' => ['nullable', 'integer', 'min:1', 'max:32'],
        ];
    }

    public function toDto(): \App\Modules\Daemons\DTOs\CreateDaemonDTO
    {
        $validated = $this->validated();

        return new \App\Modules\Daemons\DTOs\CreateDaemonDTO(
            name: (string) $validated['name'],
            command: (string) $validated['command'],
            directory: isset($validated['directory']) ? (string) $validated['directory'] : null,
            user: (string) ($validated['user'] ?? 'www-data'),
            processes: (int) ($validated['processes'] ?? 1),
        );
    }
}
