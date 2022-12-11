<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication,LazilyRefreshDatabase,withFaker;

    public function actingAs(UserContract $user,$abilities=null)
    {
        Sanctum::actingAs($user, $abilities ?: ['*']);
        return $this;
    }
}
