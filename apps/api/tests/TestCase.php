<?php

namespace Tests;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function createOrg(User $user): Organization
    {
        return Organization::query()->make([
            'name' => 'Test Organization',
            'owner_id' => $user->getKey(),
        ]);
    }
}
