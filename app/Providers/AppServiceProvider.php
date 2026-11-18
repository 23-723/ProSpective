<?php

namespace App\Providers;

use App\Repositories\DemoAcademicRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DemoAcademicRepository::class, fn ($app) => new DemoAcademicRepository(
            (int) $app['config']->get('academic.sete_min_responses'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
