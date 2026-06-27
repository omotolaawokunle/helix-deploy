<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Rules;

use App\Packages\Provisioning\ProvisioningVersionCatalog;
use Illuminate\Validation\Validator;

class ProvisionOptionsValidator
{
    public function __construct(
        private readonly ProvisioningVersionCatalog $versionCatalog,
    ) {
    }

    /**
     * @param array<int, string> $scripts
     * @param array<string, mixed> $options
     */
    public function validate(array $scripts, array $options, Validator $validator): void
    {
        foreach ($scripts as $script) {
            $definition = $this->versionCatalog->forService($script);

            if ($definition === null) {
                continue;
            }

            $optionKey = $definition->optionKey;
            $value = $options[$optionKey] ?? null;

            if ($value === null || $value === '') {
                $validator->errors()->add(
                    "options.{$optionKey}",
                    sprintf('The %s version is required when %s is selected.', $definition->label, $script),
                );

                continue;
            }

            $allowed = array_map(
                static fn (string|int $item): string => (string) $item,
                $definition->values,
            );

            if (! in_array((string) $value, $allowed, true)) {
                $validator->errors()->add(
                    "options.{$optionKey}",
                    sprintf('The selected %s version is invalid.', $definition->label),
                );
            }
        }

        if (in_array('redis', $scripts, true)) {
            $password = $options['redisPassword'] ?? null;

            if ($password !== null && $password !== '' && strlen((string) $password) < 8) {
                $validator->errors()->add('options.redisPassword', 'The redis password must be at least 8 characters.');
            }
        }
    }
}
