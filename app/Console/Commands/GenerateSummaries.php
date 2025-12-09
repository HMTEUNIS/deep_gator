<?php

namespace App\Console\Commands;

use App\Services\ArticleProcessor;
use App\Services\DeepSeekService;
use Illuminate\Console\Command;

class GenerateSummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:generate-summaries {--classification= : Generate summary for specific classification only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate annotated summaries for articles using DeepSeek API';

    /**
     * Execute the console command.
     */
    public function handle(ArticleProcessor $processor, DeepSeekService $deepSeek): int
    {
        $this->info('Generating summaries...');

        $classifications = ['Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration'];
        $specificClassification = $this->option('classification');

        if ($specificClassification) {
            if (!in_array($specificClassification, $classifications)) {
                $this->error("Invalid classification: {$specificClassification}");
                return Command::FAILURE;
            }
            $classifications = [$specificClassification];
        }

        $successCount = 0;
        foreach ($classifications as $classification) {
            $this->line("Processing {$classification}...");
            $success = $processor->generateSummaryForClassification($classification, $deepSeek);
            if ($success) {
                $this->info("✓ Summary generated for {$classification}");
                $successCount++;
            } else {
                $this->warn("✗ Failed to generate summary for {$classification}");
            }
        }

        $this->info("Summary generation complete! ({$successCount}/" . count($classifications) . " successful)");

        return Command::SUCCESS;
    }
}
