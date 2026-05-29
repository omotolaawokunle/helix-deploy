<?php

declare(strict_types=1);

namespace App\Modules\Shared\Concerns;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait OwnedByOrganization
{
    public static function bootOwnedByOrganization(): void
    {
        static::addGlobalScope('owned_by_organization', function (Builder $builder): void {
            $organizationId = static::currentOrgId();

            if ($organizationId === null) {
                return;
            }

            $builder->where($builder->qualifyColumn('organization_id'), $organizationId);
        });
    }

    protected static function currentOrgId(): ?string
    {
        $container = Container::getInstance();

        if ($container === null || ! $container->bound('auth')) {
            return null;
        }

        $user = Auth::user();

        if (! $user instanceof Model) {
            return null;
        }

        $organizationId = $user->getAttribute('current_organization_id');

        return is_string($organizationId) && $organizationId !== '' ? $organizationId : null;
    }
}
