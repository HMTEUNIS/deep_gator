<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Stopword;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ArticleProcessor
{
    protected RSSFeedFetcher $feedFetcher;
    protected NaiveBayesClassifier $classifier;

    public function __construct(
        RSSFeedFetcher $feedFetcher,
        NaiveBayesClassifier $classifier
    ) {
        $this->feedFetcher = $feedFetcher;
        $this->classifier = $classifier;
    }

    /**
     * Process RSS feeds: fetch, classify, and store articles.
     *
     * @param array $feeds Array of RSS feed URLs
     * @return array Statistics about processed articles
     */
    public function processFeeds(array $feeds): array
    {
        $stats = [
            'fetched' => 0,
            'classified' => 0,
            'stored' => 0,
            'skipped' => 0,
        ];

        $articles = $this->feedFetcher->fetchFeeds($feeds);
        $stats['fetched'] = count($articles);

        // Group articles by source for efficient processing
        $articlesBySource = [];
        foreach ($articles as $articleData) {
            $source = $articleData['source'] ?? 'Unknown';
            if (!isset($articlesBySource[$source])) {
                $articlesBySource[$source] = [];
            }
            $articlesBySource[$source][] = $articleData;
        }

        // Process each source's articles separately
        foreach ($articlesBySource as $source => $sourceArticles) {
            // Get the most recent published_at we already have for this source
            $latestExisting = Article::where('source', $source)
                ->whereNotNull('published_at')
                ->orderBy('published_at', 'desc')
                ->value('published_at');

            $stopProcessing = false;

            foreach ($sourceArticles as $articleData) {
                // If we've hit an article older than what we already have, stop processing this source
                if ($stopProcessing) {
                    $stats['skipped']++;
                    continue;
                }

                try {
                    $publishedAt = $articleData['published_at'];

                    // Check if article already exists by link (primary check)
                    $existing = Article::where('link', $articleData['link'])->first();
                    if ($existing) {
                        $stats['skipped']++;
                        // If this is an existing article and we're processing newest first,
                        // stop processing older articles from this source
                        if ($latestExisting && $publishedAt && $publishedAt->lte($latestExisting)) {
                            $stopProcessing = true;
                        }
                        continue;
                    }

                    // Also check by source + published_at to avoid duplicates
                    if ($publishedAt) {
                        $existingBySource = Article::where('source', $source)
                            ->where('published_at', $publishedAt)
                            ->first();
                        if ($existingBySource) {
                            $stats['skipped']++;
                            // Stop if we've reached articles we already have
                            if ($latestExisting && $publishedAt->lte($latestExisting)) {
                                $stopProcessing = true;
                            }
                            continue;
                        }

                        // Stop if this article is older than the latest we already have
                        if ($latestExisting && $publishedAt->lt($latestExisting)) {
                            $stats['skipped']++;
                            $stopProcessing = true;
                            continue;
                        }
                    }

                    // Classify the article
                    $classification = $this->classifier->classify(
                        $articleData['title'],
                        $articleData['content']
                    );

                    if (!$classification) {
                        $stats['skipped']++;
                        continue;
                    }

                    $stats['classified']++;

                    // Store the article
                    $article = Article::create([
                        'title' => $articleData['title'],
                        'content' => $articleData['content'],
                        'link' => $articleData['link'],
                        'classification' => $classification,
                        'image_url' => $articleData['image_url'],
                        'source_feed' => $articleData['source_feed'],
                        'source' => $source,
                        'published_at' => $publishedAt,
                        'expires_at' => Carbon::now()->addHours(48),
                    ]);

                    $stats['stored']++;

                    // Train the classifier with this article
                    $this->classifier->train(
                        $articleData['title'],
                        $articleData['content'],
                        $classification
                    );
                } catch (\Exception $e) {
                    Log::error('Error processing article', [
                        'error' => $e->getMessage(),
                        'article' => $articleData['title'] ?? 'Unknown',
                    ]);
                    $stats['skipped']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Generate summaries and extract stopwords for a classification.
     *
     * @param string $classification
     * @param DeepSeekService $deepSeekService
     * @return bool Success status
     */
    public function generateSummaryForClassification(
        string $classification,
        DeepSeekService $deepSeekService
    ): bool {
        try {
            // Get recent articles for this classification
            $articles = Article::active()
                ->byClassification($classification)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            if ($articles->isEmpty()) {
                Log::info("No articles found for classification: {$classification}");
                return false;
            }

            // Prepare article data for DeepSeek
            $articleData = $articles->map(function ($article) {
                return [
                    'title' => $article->title,
                    'content' => $article->content,
                ];
            })->toArray();

            // Generate summary
            $summary = $deepSeekService->generateSummary($articleData, $classification);

            if ($summary) {
                // Update all articles with the same summary (or store in a separate summaries table)
                // For now, we'll update the most recent article's summary field
                $articles->first()->update(['summary' => $summary]);
            }

            // Extract stopwords
            $stopwords = $deepSeekService->extractStopwords($articleData, $classification);

            // Store stopwords
            foreach ($stopwords as $word) {
                // Check if stopword already exists
                $exists = Stopword::where('word', $word)
                    ->where('classification', $classification)
                    ->exists();

                if (!$exists) {
                    Stopword::create([
                        'word' => $word,
                        'classification' => $classification,
                        'source_article_id' => $articles->first()->id,
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Error generating summary for {$classification}", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

