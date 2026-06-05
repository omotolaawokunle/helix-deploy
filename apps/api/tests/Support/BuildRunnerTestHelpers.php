<?php

declare(strict_types=1);

use App\Modules\BuildRunners\Contracts\RunnerSlotStoreInterface;
use App\Modules\BuildRunners\Services\InMemoryRunnerSlotStore;

function useInMemoryRunnerSlotStore(): InMemoryRunnerSlotStore
{
    $store = new InMemoryRunnerSlotStore();
    app()->instance(RunnerSlotStoreInterface::class, $store);

    return $store;
}
