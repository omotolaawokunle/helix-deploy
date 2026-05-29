<?php

declare(strict_types=1);

namespace App\Modules\Servers\Actions;

use App\Models\User;
use App\Modules\Servers\DTOs\RegisterServerDTO;
use App\Modules\Organizations\Models\Organization;
use InvalidArgumentException;

class ImportServerAction
{
    public function __construct(
        private readonly RegisterServerAction $registerServerAction,
    ) {
    }

    /**
     * @return array{server: \App\Modules\Servers\Models\Server, publicKey: string|null}
     */
    public function execute(Organization $org, User $actor, RegisterServerDTO $dto): array
    {
        if ($dto->authMethod !== 'import') {
            throw new InvalidArgumentException('ImportServerAction can only be used with import auth method.');
        }

        return $this->registerServerAction->execute($org, $actor, $dto);
    }
}
