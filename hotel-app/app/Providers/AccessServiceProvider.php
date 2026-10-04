<?php

declare(strict_types=1);

namespace App\Providers;

use App\Security\CapabilityAuthorizer;
use App\Security\DenyAccess;
use App\Security\PrincipalResolver;
use Illuminate\Support\ServiceProvider;

final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Support\RequestWindow::class, fn () => new \App\Support\RequestWindow(storage_path('framework/request-limits')));
        $this->app->bind(PrincipalResolver::class, DenyAccess::class);
        $this->app->bind(CapabilityAuthorizer::class, DenyAccess::class);
    }
}
