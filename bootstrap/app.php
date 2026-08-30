<?php

use App\Http\Middleware\AuthenticateFinanceService;
use App\Http\Middleware\EnsurePlantationEntityAccess;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('integration:dispatch-outbox')->everyMinute();
        $schedule->command('integration:prune-outbox')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'plantation.access' => EnsurePlantationEntityAccess::class,
            'internal.finance' => AuthenticateFinanceService::class,
        ]);

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            AuthenticateFinanceService::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
