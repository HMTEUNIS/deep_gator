<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;

class CleanupArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove articles older than 48 hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Cleaning up expired articles...');

        $deleted = Article::where('expires_at', '<=', now())->delete();

        $this->info("Deleted {$deleted} expired articles.");

        return Command::SUCCESS;
    }
}

