<?php

namespace App\Console\Commands;

use App\Services\ArticleProcessor;
use Illuminate\Console\Command;

class FetchArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and process articles from RSS feeds';

    /**
     * Execute the console command.
     */
    public function handle(ArticleProcessor $processor): int
    {
        $this->info('Fetching articles from RSS feeds...');

        $feeds = config('newsfeed.rss_feeds', []);

        if (empty($feeds)) {
            $this->error('No RSS feeds configured. Please add feeds to config/newsfeed.php');
            return Command::FAILURE;
        }

        $stats = $processor->processFeeds($feeds);

        $this->info("Processing complete!");
        $this->line("Fetched: {$stats['fetched']}");
        $this->line("Classified: {$stats['classified']}");
        $this->line("Stored: {$stats['stored']}");
        $this->line("Skipped: {$stats['skipped']}");

        return Command::SUCCESS;
    }
}
