<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RefreshToken;

class CleanupExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:cleanup {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired refresh tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $count = RefreshToken::where('expires_at', '<', now())->count();
            $this->info("Would delete {$count} expired refresh tokens");
            return;
        }

        $deletedCount = RefreshToken::cleanupExpired();
        
        $this->info("Cleaned up {$deletedCount} expired refresh tokens");
    }
}
