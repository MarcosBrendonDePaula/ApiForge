<?php

namespace MarcosBrendon\ApiForge;

use Illuminate\Support\ServiceProvider;
use MarcosBrendon\ApiForge\Http\Middleware\ApiPaginationMiddleware;
use MarcosBrendon\ApiForge\Services\ApiFilterService;
use MarcosBrendon\ApiForge\Services\FilterConfigService;
use MarcosBrendon\ApiForge\Services\DocumentationGeneratorService;
use MarcosBrendon\ApiForge\Console\Commands\GenerateDocumentationCommand;

class ApiForgeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/apiforge.php' => config_path('apiforge.php'),
        ], 'config');

        // Register middleware
        $this->app['router']->aliasMiddleware('apiforge', ApiPaginationMiddleware::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDocumentationCommand::class,
            ]);
        }

        // Publish migrations if needed
        if (! class_exists('CreateApiFilterCacheTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_api_filter_cache_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_api_filter_cache_table.php'),
            ], 'migrations');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../config/apiforge.php', 'apiforge');

        // Register services
        $this->app->singleton(ApiFilterService::class, function ($app) {
            return new ApiFilterService();
        });

        $this->app->singleton(FilterConfigService::class, function ($app) {
            return new FilterConfigService();
        });

        $this->app->singleton(DocumentationGeneratorService::class, function ($app) {
            return new DocumentationGeneratorService($app->make(FilterConfigService::class));
        });

        // Register aliases
        $this->app->alias(ApiFilterService::class, 'api-filter-service');
        $this->app->alias(FilterConfigService::class, 'filter-config-service');
        $this->app->alias(DocumentationGeneratorService::class, 'documentation-generator-service');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ApiFilterService::class,
            FilterConfigService::class,
            DocumentationGeneratorService::class,
            'api-filter-service',
            'filter-config-service',
            'documentation-generator-service',
        ];
    }
}