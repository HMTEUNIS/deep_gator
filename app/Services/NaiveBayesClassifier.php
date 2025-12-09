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

    // Class weights to balance the dataset (calculated dynamically based on class imbalance)
    protected array $classWeights = [];

    // Minimal stopwords list - don't remove important discriminative words
    protected array $stopwords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 
        'for', 'of', 'with', 'by', 'as', 'is', 'was', 'are', 'were', 
        'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did'
    ];

    public function __construct()
    {
        $this->brainPath = storage_path('app/classifier_brain.json');
        $this->loadBrain();
        $this->calculateClassWeights();
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
                    'vocabulary_frequency' => [],
                    'class_counts' => array_fill_keys($this->classifications, 0),
                    'word_counts' => array_fill_keys($this->classifications, []),
                    'total_documents' => 0,
                ];
                $this->saveBrain();
            }
        }
        
        // Ensure vocabulary_frequency exists
        if (!isset($this->brain['vocabulary_frequency'])) {
            $this->brain['vocabulary_frequency'] = [];
        }
    }

    /**
     * Calculate class weights to balance the dataset.
     */
    protected function calculateClassWeights(): void
    {
        $classCounts = $this->brain['class_counts'] ?? [];
        $maxCount = 0;
        
        // Find the maximum class count
        foreach ($this->classifications as $classification) {
            $count = $classCounts[$classification] ?? 0;
            if ($count > $maxCount) {
                $maxCount = $count;
            }
        }
        
        // Calculate weights (inverse of relative frequency)
        if ($maxCount > 0) {
            foreach ($this->classifications as $classification) {
                $count = $classCounts[$classification] ?? 0;
                if ($count > 0) {
                    $this->classWeights[$classification] = $maxCount / $count;
                } else {
                    $this->classWeights[$classification] = 1.0;
                }
            }
        } else {
            // If no training data, use equal weights
            foreach ($this->classifications as $classification) {
                $this->classWeights[$classification] = 1.0;
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
     * @param float $confidenceThreshold Minimum probability threshold (default 0.3)
     * @return string|null Classification or null if no match
     */
    public function classify(string $title, string $content, float $confidenceThreshold = 0.3): ?string
    {
        $text = strtolower($title . ' ' . $content);
        $words = $this->tokenize($text);

        if (empty($words)) {
            return null;
        }

        $scores = [];
        $totalScore = 0;

        foreach ($this->classifications as $classification) {
            $score = $this->calculateScore($words, $classification);
            $scores[$classification] = $score;
            $totalScore += exp($score); // Convert log prob back to probability
        }

        // Normalize to probabilities
        $probabilities = [];
        foreach ($scores as $classification => $score) {
            $prob = $totalScore > 0 ? exp($score) / $totalScore : 0;
            $probabilities[$classification] = $prob;
        }

        arsort($probabilities);
        $topClassification = array_key_first($probabilities);
        $topProbability = $probabilities[$topClassification];

        // Only classify if probability exceeds threshold AND has reasonable confidence
        if ($topProbability > $confidenceThreshold && $this->hasReasonableConfidence($probabilities)) {
            return $topClassification;
        }

        return null;
    }

    /**
     * Check if the classification has reasonable confidence over second best.
     */
    protected function hasReasonableConfidence(array $probabilities, float $margin = 0.1): bool
    {
        arsort($probabilities);
        $sorted = array_values($probabilities);

        if (count($sorted) < 2) {
            return true;
        }

        return ($sorted[0] - $sorted[1]) >= $margin;
    }

    /**
     * Get classification confidence scores for all categories.
     */
    public function getConfidenceScores(string $title, string $content): array
    {
        $text = strtolower($title . ' ' . $content);
        $words = $this->tokenize($text);

        if (empty($words)) {
            return [];
        }

        $scores = [];
        $totalScore = 0;

        foreach ($this->classifications as $classification) {
            $score = $this->calculateScore($words, $classification);
            $scores[$classification] = $score;
            $totalScore += exp($score);
        }

        // Convert to probabilities
        $probabilities = [];
        foreach ($scores as $classification => $score) {
            $probabilities[$classification] = $totalScore > 0 ? exp($score) / $totalScore : 0;
        }

        arsort($probabilities);
        return $probabilities;
    }

    /**
     * Tokenize text into words, removing stopwords and non-alphabetic characters.
     * Enhanced version that preserves important short words (like LGBTQ, HIV, etc.)
     */
    protected function tokenize(string $text): array
    {
        // Remove HTML tags and special characters (but keep hyphens for words like LGBTQIA+)
        $text = strip_tags($text);
        $text = preg_replace('/[^a-z0-9\s\-]/', ' ', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stopwords (but keep important short words)
        // Keep words with length >= 1 (important for LGBTQ, HIV, etc.)
        $words = array_filter($words, function ($word) {
            return strlen($word) >= 1 && !in_array($word, $this->stopwords);
        });

        // Return unique words to avoid word frequency bias
        return array_values(array_unique($words));
    }

    /**
     * Calculate the Naive Bayes score for a set of words given a classification.
     * Enhanced with class weights and TF-IDF weighting.
     */
    protected function calculateScore(array $words, string $classification): float
    {
        if (!isset($this->brain['word_counts'][$classification])) {
            return -1000.0;
        }

        $classWordCounts = $this->brain['word_counts'][$classification];
        $classTotalWords = array_sum($classWordCounts);
        $vocabularySize = count($this->brain['vocabulary'] ?? []);

        // If no words in this class and no vocabulary, return very low score
        if ($classTotalWords == 0 && $vocabularySize == 0) {
            return -1000.0;
        }

        // Prior probability (frequency of this class) with class weighting
        $classCount = $this->brain['class_counts'][$classification] ?? 0;
        $totalDocs = $this->brain['total_documents'] ?? 1;
        $prior = log(($classCount + 1) / ($totalDocs + count($this->classifications)));

        // Apply class weight to balance the dataset
        $weight = $this->classWeights[$classification] ?? 1.0;
        $prior *= $weight;

        // Likelihood with TF-IDF weighting
        $likelihood = 0.0;
        $denominator = $classTotalWords + $vocabularySize;
        
        // Prevent division by zero
        if ($denominator == 0) {
            $denominator = 1;
        }

        foreach ($words as $word) {
            $wordCount = $classWordCounts[$word] ?? 0;
            
            // TF (Term Frequency)
            $tf = ($wordCount + 1) / $denominator;
            
            // IDF (Inverse Document Frequency) - penalize common words
            $docFrequency = 0;
            foreach ($this->classifications as $cls) {
                if (isset($this->brain['word_counts'][$cls][$word]) && $this->brain['word_counts'][$cls][$word] > 0) {
                    $docFrequency++;
                }
            }
            $idf = log(($totalDocs + 1) / ($docFrequency + 1)) + 1;
            
            // TF-IDF weighted probability
            $probability = $tf * $idf;
            $likelihood += log($probability + 0.000001); // Add small epsilon to avoid log(0)
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
     * Enhanced with word frequency tracking and minimum occurrence threshold.
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

        // Count word occurrences
        $wordFrequency = array_count_values($words);

        foreach ($wordFrequency as $word => $count) {
            // Track overall word frequency
            $this->brain['vocabulary_frequency'][$word] = 
                ($this->brain['vocabulary_frequency'][$word] ?? 0) + $count;

            // Add to class-specific counts
            if (!isset($this->brain['word_counts'][$classification][$word])) {
                $this->brain['word_counts'][$classification][$word] = 0;
            }
            $this->brain['word_counts'][$classification][$word] += $count;

            // Add to vocabulary only if appears >= 2 times overall
            if ($this->brain['vocabulary_frequency'][$word] >= 2 && 
                !in_array($word, $this->brain['vocabulary'])) {
                $this->brain['vocabulary'][] = $word;
            }
        }

        // Recalculate class weights after training
        $this->calculateClassWeights();
        $this->saveBrain();
    }

    /**
     * Retrain with balanced dataset.
     */
    public function retrainWithBalancedData(array $trainingData): void
    {
        // Reset brain
        $this->brain = [
            'vocabulary' => [],
            'vocabulary_frequency' => [],
            'class_counts' => array_fill_keys($this->classifications, 0),
            'word_counts' => array_fill_keys($this->classifications, []),
            'total_documents' => 0,
        ];

        // Group by classification
        $grouped = [];
        foreach ($trainingData as $item) {
            if (in_array($item['classification'], $this->classifications)) {
                $grouped[$item['classification']][] = $item;
            }
        }

        // Find minimum class size
        $minSize = min(array_map('count', $grouped));

        // Train with balanced samples
        foreach ($grouped as $classification => $items) {
            // Shuffle and take only minSize items to balance
            shuffle($items);
            $items = array_slice($items, 0, $minSize);

            foreach ($items as $item) {
                // Train directly without calling train() to avoid double counting
                $text = strtolower($item['title'] . ' ' . $item['content']);
                $words = $this->tokenize($text);

                // Increment class count
                $this->brain['class_counts'][$classification] = ($this->brain['class_counts'][$classification] ?? 0) + 1;
                $this->brain['total_documents'] = ($this->brain['total_documents'] ?? 0) + 1;

                // Count word occurrences
                $wordFrequency = array_count_values($words);

                foreach ($wordFrequency as $word => $count) {
                    // Track overall word frequency
                    $this->brain['vocabulary_frequency'][$word] = 
                        ($this->brain['vocabulary_frequency'][$word] ?? 0) + $count;

                    // Add to class-specific counts
                    if (!isset($this->brain['word_counts'][$classification][$word])) {
                        $this->brain['word_counts'][$classification][$word] = 0;
                    }
                    $this->brain['word_counts'][$classification][$word] += $count;

                    // Add to vocabulary only if appears >= 2 times overall
                    if ($this->brain['vocabulary_frequency'][$word] >= 2 && 
                        !in_array($word, $this->brain['vocabulary'])) {
                        $this->brain['vocabulary'][] = $word;
                    }
                }
            }
        }

        // Recalculate class weights
        $this->calculateClassWeights();
        $this->saveBrain();

        Log::info("Retrained classifier with balanced data. Each class has {$minSize} samples.");
    }

    /**
     * Learn from a correction (when user corrects a misclassification).
     */
    public function learnFromCorrection(string $title, string $content, string $correctClassification): void
    {
        // Train with the correct classification
        $this->train($title, $content, $correctClassification);

        // Untrain from incorrect classifications (optional)
        $currentClassification = $this->classify($title, $content);
        if ($currentClassification && $currentClassification !== $correctClassification) {
            // Reduce weight of incorrect classification
            $this->brain['class_counts'][$currentClassification] = 
                max(0, ($this->brain['class_counts'][$currentClassification] ?? 0) - 1);
            $this->brain['total_documents'] = max(0, ($this->brain['total_documents'] ?? 0) - 1);
        }

        $this->calculateClassWeights();
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

