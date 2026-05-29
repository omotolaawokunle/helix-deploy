<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

use App\Modules\Servers\Enums\ManagementMode;
use App\Modules\Servers\Enums\ServerProvider;
use Illuminate\Http\Request;

readonly class RegisterServerDTO
{
    public function __construct(
        public string $name,
        public string $hostname,
        public string $ipAddress,
        public int $sshPort,
        public string $sshUser,
        public ServerProvider $provider,
        public ?string $region,
        public ?string $serverType,
        public ManagementMode $managementMode,
        public string $authMethod,
        public ?string $privateKey,
        public ?string $projectId,
        public ?string $environmentId,
        /** @var array<int, string> */
        public array $tags,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array{
         *   name?: string,
         *   hostname?: string,
         *   ipAddress?: string,
         *   sshPort?: int|string,
         *   sshUser?: string,
         *   provider?: string,
         *   region?: string|null,
         *   serverType?: string|null,
         *   managementMode?: string,
         *   authMethod?: string,
         *   privateKey?: string|null,
         *   projectId?: string|null,
         *   environmentId?: string|null,
         *   tags?: array<int, string>,
         * } $validated
         */
        $validated = method_exists($request, 'validated')
            ? $request->validated()
            : $request->all();

        return new self(
            name: (string) ($validated['name'] ?? ''),
            hostname: (string) ($validated['hostname'] ?? ''),
            ipAddress: (string) ($validated['ipAddress'] ?? ''),
            sshPort: (int) ($validated['sshPort'] ?? 22),
            sshUser: (string) ($validated['sshUser'] ?? 'deploy'),
            provider: ServerProvider::from((string) ($validated['provider'] ?? ServerProvider::GENERIC->value)),
            region: isset($validated['region']) ? (string) $validated['region'] : null,
            serverType: isset($validated['serverType']) ? (string) $validated['serverType'] : null,
            managementMode: ManagementMode::from((string) ($validated['managementMode'] ?? ManagementMode::MANAGED->value)),
            authMethod: (string) ($validated['authMethod'] ?? 'generate'),
            privateKey: isset($validated['privateKey']) ? (string) $validated['privateKey'] : null,
            projectId: isset($validated['projectId']) ? (string) $validated['projectId'] : null,
            environmentId: isset($validated['environmentId']) ? (string) $validated['environmentId'] : null,
            tags: array_values(array_map(static fn (string $tag): string => trim($tag), $validated['tags'] ?? [])),
        );
    }
}
