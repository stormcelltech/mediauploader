<?php

namespace StormcellTech\Console;

use Illuminate\Console\Command;

class InstallPackageCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mediauploader:install';

    /**
     * The console command description.
     */
    protected $description = 'Publish MediaUploader assets and run outstanding database migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=========================================');
        $this->info('   Installing StormcellTech MediaUploader  ');
        $this->info('=========================================');

        // 1. Export Vendor Assets & Configurations
        $this->newLine();
        $this->info('Step 1: Exporting package assets and migrations...');

        // Corrected provider string value to align with the actual boot provider class paths
        $this->call('vendor:publish', [
            '--provider' => 'StormcellTech\MediaUploaderServiceProvider',
            '--force'    => true
        ]);

        // 2. Run Database Migrations
        $this->newLine();
        $this->info('Step 2: Database synchronization...');

        $this->call('migrate');

        $this->newLine();
        $this->info('=========================================');
        $this->info('  MediaUploader Installation Complete! ');
        $this->info('=========================================');

        return self::SUCCESS;
    }
}
