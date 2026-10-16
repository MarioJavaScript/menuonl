<?php

namespace Tests;

use App\Models\Scopes\RestauranteScope;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        Filament::setServingStatus(false);
        Filament::setTenant(null, isQuiet: true);

        parent::tearDown();
    }
}
