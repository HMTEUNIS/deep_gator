<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\NaiveBayesClassifier;
use Illuminate\Console\Command;

class RebalanceClassifier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classifier:rebalance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebalance the classifier training data to fix class imbalance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Rebalancing classifier training data...');

        // Get all articles grouped by classification
        $articles = Article::all();
        $grouped = [];

        foreach ($articles as $article) {
            $grouped[$article->classification][] = $article;
        }

        // Display current distribution
        $this->info('Current class distribution:');
        foreach ($grouped as $classification => $items) {
            $this->line("  {$classification}: " . count($items));
        }

        // Find the smallest group size
        $minSize = min(array_map('count', $grouped));
        $this->info("\nMinimum class size: {$minSize}");

        if ($minSize == 0) {
            $this->error('Cannot rebalance: at least one class has no articles.');
            return 1;
        }

        // Create balanced training set
        $trainingData = [];
        foreach ($grouped as $classification => $articles) {
            // Shuffle and take only minSize items to balance
            $articlesArray = is_array($articles) ? $articles : $articles->all();
            shuffle($articlesArray);
            $balanced = array_slice($articlesArray, 0, $minSize);

            foreach ($balanced as $article) {
                $trainingData[] = [
                    'title' => $article->title,
                    'content' => $article->content,
                    'classification' => $article->classification,
                ];
            }
        }

        $this->info('Created balanced training set with ' . count($trainingData) . ' articles');

        // Retrain classifier
        $classifier = new NaiveBayesClassifier();
        $classifier->retrainWithBalancedData($trainingData);

        $this->info('Classifier rebalanced successfully!');
        $this->info('Each class now has ' . $minSize . ' training samples.');

        // Display new class weights
        $brain = $classifier->getBrain();
        $this->info("\nNew class distribution:");
        foreach ($brain['class_counts'] as $classification => $count) {
            $this->line("  {$classification}: {$count}");
        }

        return 0;
    }
}
