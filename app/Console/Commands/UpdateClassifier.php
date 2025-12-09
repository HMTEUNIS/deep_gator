<?php

namespace App\Console\Commands;

use App\Models\Stopword;
use App\Services\NaiveBayesClassifier;
use Illuminate\Console\Command;

class UpdateClassifier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classifier:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the Naive Bayes classifier with new stopwords from the database';

    /**
     * Execute the console command.
     */
    public function handle(NaiveBayesClassifier $classifier): int
    {
        $this->info('Updating classifier with stopwords...');

        // Get all stopwords from database
        $stopwords = Stopword::all()->map(function ($stopword) {
            return [
                'word' => $stopword->word,
                'classification' => $stopword->classification,
            ];
        })->toArray();

        if (empty($stopwords)) {
            $this->warn('No stopwords found in database.');
            return Command::SUCCESS;
        }

        $classifier->updateFromStopwords($stopwords);

        $this->info("Updated classifier with " . count($stopwords) . " stopwords.");

        return Command::SUCCESS;
    }
}

