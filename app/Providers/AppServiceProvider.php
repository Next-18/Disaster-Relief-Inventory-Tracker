<?php

namespace App\Providers;

use App\Filesystem\R2FilesystemAdapter;
use Aws\S3\S3Client;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production (Render).
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Supports older MySQL/MariaDB installations with a 1000-byte index limit.
        Schema::defaultStringLength(191);

        // Register the custom R2 filesystem adapter.
        Storage::extend('r2', function ($app, array $config) {
            $client = new S3Client([
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
                'region' => $config['region'],
                'version' => 'latest',
                'endpoint' => $config['endpoint'],
                'use_path_style_endpoint' => $config['use_path_style_endpoint'],
            ]);

            $adapter = new R2FilesystemAdapter(
                $client,
                $config['bucket'],
                $config['root'] ?? '',
            );

            return new LaravelFilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }
}