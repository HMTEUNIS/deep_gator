<?php

namespace App\Console\Commands;

use App\Services\NaiveBayesClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportInitialTraining extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classifier:import-training {file? : Path to initial training JSON file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import initial training data into the classifier brain';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = $this->argument('file') ?? storage_path('app/initial_training.json');

        if (!File::exists($file)) {
            $this->error("Training file not found: {$file}");
            $this->info('Please save your initial training JSON as: storage/app/initial_training.json');
            return Command::FAILURE;
        }

        $this->info('Importing initial training data...');

        // Create a temporary classifier instance to load the training
        $classifier = new NaiveBayesClassifier();
        
        // Force reload by deleting existing brain
        $brainPath = storage_path('app/classifier_brain.json');
        if (File::exists($brainPath)) {
            File::delete($brainPath);
        }

        // Copy training file to expected location
        File::copy($file, storage_path('app/initial_training.json'));

        // Reload classifier (it will detect and load initial training)
        $classifier = new NaiveBayesClassifier();

        $brain = $classifier->getBrain();
        $this->info("✓ Training data imported successfully!");
        $this->line("Total documents: {$brain['total_documents']}");
        $this->line("Vocabulary size: " . count($brain['vocabulary']));
        
        foreach (['Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration'] as $class) {
            $count = $brain['class_counts'][$class] ?? 0;
            $words = count($brain['word_counts'][$class] ?? []);
            $this->line("  {$class}: {$count} documents, {$words} unique words");
        }

        return Command::SUCCESS;
    }
}

