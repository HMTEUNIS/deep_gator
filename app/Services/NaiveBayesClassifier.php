<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NaiveBayesClassifier
{
    protected string $brainPath;
    protected array $brain = [];
    protected array $classifications = ['Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration'];

    public function __construct()
    {
        $this->brainPath = storage_path('app/classifier_brain.json');
        $this->loadBrain();
    }

    /**
     * Load the classifier brain from JSON file.
     */
    public function loadBrain(): void
    {
        if (File::exists($this->brainPath)) {
            $content = File::get($this->brainPath);
            $this->brain = json_decode($content, true) ?? [];
            
            // Fix brain if it has vocabulary_size instead of vocabulary
            if (isset($this->brain['vocabulary_size']) && !isset($this->brain['vocabulary'])) {
                // Rebuild vocabulary from word_counts
                $this->brain['vocabulary'] = [];
                if (isset($this->brain['word_counts'])) {
                    foreach ($this->brain['word_counts'] as $classification => $wordCounts) {
                        if (is_array($wordCounts)) {
                            foreach (array_keys($wordCounts) as $word) {
                                if (!in_array($word, $this->brain['vocabulary'])) {
                                    $this->brain['vocabulary'][] = $word;
                                }
                            }
                        }
                    }
                }
                $this->brain['vocabulary'] = array_values(array_unique($this->brain['vocabulary']));
                sort($this->brain['vocabulary']);
                unset($this->brain['vocabulary_size']);
                $this->saveBrain();
            }
            
            // Ensure vocabulary exists even if empty
            if (!isset($this->brain['vocabulary'])) {
                $this->brain['vocabulary'] = [];
            }
            
            // Fix category_counts to class_counts if needed
            if (isset($this->brain['category_counts']) && !isset($this->brain['class_counts'])) {
                $this->brain['class_counts'] = $this->brain['category_counts'];
                unset($this->brain['category_counts']);
                $this->saveBrain();
            }
        } else {
            // Check for initial training data
            $initialTrainingPath = storage_path('app/initial_training.json');
            if (File::exists($initialTrainingPath)) {
                $this->loadInitialTraining($initialTrainingPath);
            } else {
                // Initialize empty brain structure
                $this->brain = [
                    'vocabulary' => [],
                    'class_counts' => array_fill_keys($this->classifications, 0),
                    'word_counts' => array_fill_keys($this->classifications, []),
                    'total_documents' => 0,
                ];
                $this->saveBrain();
            }
        }
    }

    /**
     * Load and convert initial training data to brain format.
     */
    protected function loadInitialTraining(string $path): void
    {
        $content = File::get($path);
        $trainingData = json_decode($content, true);

        if (!$trainingData) {
            Log::warning('Failed to parse initial training data');
            $this->brain = [
                'vocabulary' => [],
                'class_counts' => array_fill_keys($this->classifications, 0),
                'word_counts' => array_fill_keys($this->classifications, []),
                'total_documents' => 0,
            ];
            $this->saveBrain();
            return;
        }

        // Convert training data format to brain format
        $this->brain = [
            'vocabulary' => [],
            'class_counts' => [],
            'word_counts' => [],
            'total_documents' => $trainingData['total_documents'] ?? 0,
        ];

        // Convert category_counts to class_counts
        $categoryCounts = $trainingData['category_counts'] ?? [];
        foreach ($this->classifications as $classification) {
            $this->brain['class_counts'][$classification] = $categoryCounts[$classification] ?? 0;
        }

        // Convert word_counts
        $wordCounts = $trainingData['word_counts'] ?? [];
        foreach ($this->classifications as $classification) {
            $this->brain['word_counts'][$classification] = $wordCounts[$classification] ?? [];
            
            // Build vocabulary from all words in this classification
            foreach ($this->brain['word_counts'][$classification] as $word => $count) {
                if (!in_array($word, $this->brain['vocabulary'])) {
                    $this->brain['vocabulary'][] = $word;
                }
            }
        }

        // Ensure vocabulary is unique and sorted
        $this->brain['vocabulary'] = array_values(array_unique($this->brain['vocabulary']));
        sort($this->brain['vocabulary']);

        // Store stop_words if available in training data
        if (isset($trainingData['stop_words']) && is_array($trainingData['stop_words'])) {
            $this->brain['stop_words'] = array_map('strtolower', $trainingData['stop_words']);
        }

        $this->saveBrain();
        Log::info('Loaded initial training data into classifier brain');
    }

    /**
     * Save the classifier brain to JSON file.
     */
    public function saveBrain(): void
    {
        File::put($this->brainPath, json_encode($this->brain, JSON_PRETTY_PRINT));
    }

    /**
     * Classify an article into one of the categories.
     *
     * @param string $title
     * @param string $content
     * @return string|null Classification or null if no match
     */
    public function classify(string $title, string $content): ?string
    {
        $text = strtolower($title . ' ' . $content);
        $words = $this->tokenize($text);

        if (empty($words)) {
            return null;
        }

        $scores = [];

        foreach ($this->classifications as $classification) {
            $score = $this->calculateScore($words, $classification);
            $scores[$classification] = $score;
        }

        // Get the classification with the highest score
        arsort($scores);
        $topClassification = array_key_first($scores);
        $topScore = $scores[$topClassification];

        // Only return classification if score is above a threshold
        // Adjust threshold as needed (negative values are normal for log probabilities)
        // A threshold of -50.0 means we accept classifications with reasonable confidence
        if ($topScore > -50.0) {
            return $topClassification;
        }

        return null;
    }

    /**
     * Tokenize text into words, removing stopwords and non-alphabetic characters.
     */
    protected function tokenize(string $text): array
    {
        // Remove HTML tags and special characters
        $text = strip_tags($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Get stopwords from brain if available, otherwise use default list
        $stopwords = $this->brain['stop_words'] ?? [];
        if (empty($stopwords)) {
            $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'what', 'which', 'who', 'when', 'where', 'why', 'how'];
        }

        $words = array_filter($words, function ($word) use ($stopwords) {
            return strlen($word) > 2 && !in_array($word, $stopwords);
        });

        return array_values($words);
    }

    /**
     * Calculate the Naive Bayes score for a set of words given a classification.
     */
    protected function calculateScore(array $words, string $classification): float
    {
        if (!isset($this->brain['word_counts'][$classification])) {
            return 0.0;
        }

        $classWordCounts = $this->brain['word_counts'][$classification];
        $classTotalWords = array_sum($classWordCounts);
        $vocabularySize = count($this->brain['vocabulary'] ?? []);

        // If no words in this class and no vocabulary, return very low score
        if ($classTotalWords == 0 && $vocabularySize == 0) {
            return -1000.0;
        }

        // Prior probability (frequency of this class)
        $classCount = $this->brain['class_counts'][$classification] ?? 0;
        $totalDocs = $this->brain['total_documents'] ?? 1;
        $prior = log(($classCount + 1) / ($totalDocs + count($this->classifications)));

        // Likelihood (probability of words given the class)
        $likelihood = 0.0;
        $denominator = $classTotalWords + $vocabularySize;
        
        // Prevent division by zero
        if ($denominator == 0) {
            $denominator = 1;
        }

        foreach ($words as $word) {
            $wordCount = $classWordCounts[$word] ?? 0;
            // Laplace smoothing
            $probability = ($wordCount + 1) / $denominator;
            $likelihood += log($probability);
        }

        return $prior + $likelihood;
    }

    /**
     * Update the brain with new stopwords from the database.
     */
    public function updateFromStopwords(array $stopwords): void
    {
        foreach ($stopwords as $stopword) {
            $word = strtolower($stopword['word']);
            $classification = $stopword['classification'];

            if (!in_array($classification, $this->classifications)) {
                continue;
            }

            // Add word to vocabulary if not present
            if (!in_array($word, $this->brain['vocabulary'])) {
                $this->brain['vocabulary'][] = $word;
            }

            // Increment word count for this classification
            if (!isset($this->brain['word_counts'][$classification][$word])) {
                $this->brain['word_counts'][$classification][$word] = 0;
            }
            $this->brain['word_counts'][$classification][$word]++;
        }

        $this->saveBrain();
    }

    /**
     * Train the classifier with a new article.
     */
    public function train(string $title, string $content, string $classification): void
    {
        if (!in_array($classification, $this->classifications)) {
            return;
        }

        $text = strtolower($title . ' ' . $content);
        $words = $this->tokenize($text);

        // Increment class count
        $this->brain['class_counts'][$classification] = ($this->brain['class_counts'][$classification] ?? 0) + 1;
        $this->brain['total_documents'] = ($this->brain['total_documents'] ?? 0) + 1;

        // Add words to vocabulary and increment counts
        foreach ($words as $word) {
            if (!in_array($word, $this->brain['vocabulary'])) {
                $this->brain['vocabulary'][] = $word;
            }

            if (!isset($this->brain['word_counts'][$classification][$word])) {
                $this->brain['word_counts'][$classification][$word] = 0;
            }
            $this->brain['word_counts'][$classification][$word]++;
        }

        $this->saveBrain();
    }

    /**
     * Get the current brain data.
     */
    public function getBrain(): array
    {
        return $this->brain;
    }
}

