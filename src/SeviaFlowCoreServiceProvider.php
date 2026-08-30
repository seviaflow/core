<?php

declare(strict_types=1);

namespace SeviaFlow\Core;

use Illuminate\Support\ServiceProvider;
use SeviaFlow\Core\Analytics\AnalyticsCompiler;
use SeviaFlow\Core\Analytics\AnalyticsExecutor;
use SeviaFlow\Core\Analytics\AnalyticsRegistry;
use SeviaFlow\Core\Auth\Permissions\PermissionChecker;
use SeviaFlow\Core\Resources\Metadata\ResourceMetadataCache;
use SeviaFlow\Core\Resources\Metadata\ResourceMetadataResolver;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Support\Cache\CacheParticipant;
use SeviaFlow\Core\Support\Cache\ResourceMetadataCacheParticipant;
use SeviaFlow\Core\Support\Capabilities\CapabilityRegistry;

final class SeviaFlowCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/seviaflow.php', 'seviaflow');

        $this->app->singleton(CapabilityRegistry::class, fn (): CapabilityRegistry => new CapabilityRegistry);
        $this->app->singleton(AnalyticsRegistry::class, fn (): AnalyticsRegistry => new AnalyticsRegistry);
        $this->app->singleton(AnalyticsCompiler::class, fn (): AnalyticsCompiler => new AnalyticsCompiler);
        $this->app->singleton(
            AnalyticsExecutor::class,
            fn ($app): AnalyticsExecutor => new AnalyticsExecutor(
                registry: $app->make(AnalyticsRegistry::class),
                compiler: $app->make(AnalyticsCompiler::class),
            )
        );
        $this->app->singleton(ResourceRegistry::class, function ($app): ResourceRegistry {
            /** @var array<string, mixed> $resources */
            $resources = $app['config']->get('seviaflow.resources', []);

            return new ResourceRegistry($resources);
        });
        $this->app->singleton(PermissionChecker::class, fn (): PermissionChecker => new PermissionChecker);
        $this->app->singleton(ResourceMetadataResolver::class, fn (): ResourceMetadataResolver => new ResourceMetadataResolver);
        $this->app->singleton(ResourceMetadataCache::class, function ($app): ResourceMetadataCache {
            return new ResourceMetadataCache(
                resourceRegistry: $app->make(ResourceRegistry::class),
                resolver: $app->make(ResourceMetadataResolver::class),
            );
        });
        $this->app->singleton(ResourceMetadataCacheParticipant::class);
        $this->app->tag([ResourceMetadataCacheParticipant::class], CacheParticipant::class);
    }

    public function boot(CapabilityRegistry $capabilities): void
    {
        $capabilities->register('core', true, 'Resource, form, table and workflow primitives');
        $capabilities->register('analytics', true, 'Semantic analytics datasets, queries and execution');
        $capabilities->register('workflows', (bool) config('seviaflow.workflows.enabled', true), 'Workflow definitions and activity logging');
        $capabilities->register('import-export', true, 'Import and export primitives');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/seviaflow.php' => config_path('seviaflow.php'),
        ], 'seviaflow-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'seviaflow-migrations');
    }
}
