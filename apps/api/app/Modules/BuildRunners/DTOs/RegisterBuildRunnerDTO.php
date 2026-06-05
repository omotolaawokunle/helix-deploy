<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\DTOs;

use Illuminate\Http\Request;

readonly class RegisterBuildRunnerDTO
{
    /**
     * @param list<string> $supportedRuntimes
     */
    public function __construct(
        public string $name,
        public string $ipAddress,
        public int $sshPort,
        public string $sshUser,
        public string $authMethod,
        public ?string $privateKey,
        public int $maxConcurrentBuilds,
        public ?int $cpuCores,
        public ?int $ramGb,
        public array $supportedRuntimes,
        public ?string $projectId,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array{
         *   name?: string,
         *   ipAddress?: string,
         *   sshPort?: int|string,
         *   sshUser?: string,
         *   authMethod?: string,
         *   privateKey?: string|null,
         *   maxConcurrentBuilds?: int|string,
         *   cpuCores?: int|string|null,
         *   ramGb?: int|string|null,
         *   supportedRuntimes?: array<int, string>,
         *   projectId?: string|null,
         * } $validated
         */
        $validated = method_exists($request, 'validated')
            ? $request->validated()
            : $request->all();

        return new self(
            name: (string) ($validated['name'] ?? ''),
            ipAddress: (string) ($validated['ipAddress'] ?? ''),
            sshPort: (int) ($validated['sshPort'] ?? 22),
            sshUser: (string) ($validated['sshUser'] ?? 'deploy'),
            authMethod: (string) ($validated['authMethod'] ?? 'generate'),
            privateKey: isset($validated['privateKey']) ? (string) $validated['privateKey'] : null,
            maxConcurrentBuilds: (int) ($validated['maxConcurrentBuilds'] ?? 1),
            cpuCores: isset($validated['cpuCores']) ? (int) $validated['cpuCores'] : null,
            ramGb: isset($validated['ramGb']) ? (int) $validated['ramGb'] : null,
            supportedRuntimes: array_values(array_map(
                static fn (string $runtime): string => trim($runtime),
                $validated['supportedRuntimes'] ?? [],
            )),
            projectId: isset($validated['projectId']) ? (string) $validated['projectId'] : null,
        );
    }
}
