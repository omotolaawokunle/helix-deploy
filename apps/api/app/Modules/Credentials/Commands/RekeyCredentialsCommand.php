<?php

declare(strict_types=1);

namespace App\Modules\Credentials\Commands;

use App\Models\Organization;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Packages\Encryption\EncryptedPayload;
use App\Packages\Encryption\MasterKeyManager;
use App\Packages\Encryption\SodiumEncryption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RekeyCredentialsCommand extends Command
{
    protected $signature = 'credentials:rekey {--old-key= : Base64 encoded previous APP_KEY}';

    protected $description = 'Re-encrypt all organization credentials using the current application key.';

    public function __construct(
        private readonly CredentialVaultInterface $credentialVault,
        private readonly SodiumEncryption $encryption,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $oldKeyOption = (string) $this->option('old-key');

        if ($oldKeyOption === '') {
            $this->error('The --old-key option is required.');

            return self::FAILURE;
        }

        $oldAppKey = str_starts_with($oldKeyOption, 'base64:')
            ? $oldKeyOption
            : 'base64:'.$oldKeyOption;

        $oldMasterKeyManager = new MasterKeyManager(
            encryption: $this->encryption,
            appKey: $oldAppKey,
        );

        $organizations = Organization::query()->get();
        $progressBar = $this->output->createProgressBar($organizations->count());
        $progressBar->start();

        $rekeyed = 0;
        $errors = 0;

        foreach ($organizations as $organization) {
            try {
                DB::transaction(function () use ($organization, $oldMasterKeyManager): void {
                    $oldMasterKeyPayload = EncryptedPayload::fromJson((string) $organization->master_key_encrypted);
                    $oldMasterKey = $oldMasterKeyManager->decryptMasterKey($oldMasterKeyPayload);

                    try {
                        $this->credentialVault->rekeyOrganization($organization, $oldMasterKey);
                    } finally {
                        sodium_memzero($oldMasterKey);
                    }
                });

                $rekeyed++;
            } catch (Throwable $exception) {
                $errors++;
                $this->newLine();
                $this->error(sprintf(
                    'Failed to rekey organization %s: %s',
                    (string) $organization->getKey(),
                    $exception->getMessage(),
                ));
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info(sprintf('Rekey complete. %d organizations rekeyed, %d errors.', $rekeyed, $errors));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
