<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CacheMigrateToRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:migrate-to-redis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate cache from file driver to Redis';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting cache migration to Redis...');

        $this->info('Clearing application cache...');
        Artisan::call('cache:clear');
        $this->info('Application cache cleared.');

        $cachePath = storage_path('framework/cache/data');

        if (File::isDirectory($cachePath)) {
            $this->info('Cleaning up old cache files in '.$cachePath.'...');
            File::cleanDirectory($cachePath);
            $this->info('Old cache files removed.');
        } else {
            $this->warn('Cache directory not found: '.$cachePath);
        }

        $this->info('Cache migration completed successfully!');
        $this->info('Please ensure CACHE_DRIVER=redis is set in your .env file.');

        return self::SUCCESS;
    }
}
