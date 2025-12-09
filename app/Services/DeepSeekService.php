<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.deepseek.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.deepseek.api_key');
    }

    /**
     * Generate an annotated summary for articles of a specific classification.
     *
     * @param array $articles Array of article content
     * @param string $classification
     * @return string|null Summary text or null on failure
     */
    public function generateSummary(array $articles, string $classification): ?string
    {
        if (empty($articles)) {
            return null;
        }

        $content = $this->prepareContentForSummary($articles, $classification);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post($this->apiUrl, [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert news analyst. Provide annotated summaries of recent developments, highlighting key points and trends.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);

            if (!$response->successful()) {
                Log::error('DeepSeek API error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? null;
        } catch (\Exception $e) {
            Log::error('Error calling DeepSeek API', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract potential stopwords from article content.
     *
     * @param array $articles Array of article content
     * @param string $classification
     * @return array Array of potential stopwords
     */
    public function extractStopwords(array $articles, string $classification): array
    {
        if (empty($articles)) {
            return [];
        }

        $content = $this->prepareContentForStopwordExtraction($articles, $classification);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a text analysis expert. Extract relevant keywords and terms that would help classify articles. Return only a JSON array of words/phrases, one per line, no explanations.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

            if (!$response->successful()) {
                Log::error('DeepSeek API error for stopword extraction', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';

            // Try to parse as JSON first
            $stopwords = json_decode($text, true);
            if (is_array($stopwords)) {
                return array_map('strtolower', $stopwords);
            }

            // Otherwise, split by newlines and clean
            $lines = explode("\n", $text);
            $stopwords = [];
            foreach ($lines as $line) {
                $line = trim($line);
                // Remove JSON array markers, quotes, commas
                $line = trim($line, '[],"\'');
                if (!empty($line) && strlen($line) > 2) {
                    $stopwords[] = strtolower($line);
                }
            }

            return array_unique($stopwords);
        } catch (\Exception $e) {
            Log::error('Error extracting stopwords from DeepSeek', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Prepare article content for summary generation.
     */
    protected function prepareContentForSummary(array $articles, string $classification): string
    {
        $text = "Please provide an annotated summary of recent developments in {$classification} based on the following articles:\n\n";

        foreach ($articles as $index => $article) {
            $text .= "Article " . ($index + 1) . ":\n";
            $text .= "Title: {$article['title']}\n";
            $text .= "Content: " . substr($article['content'], 0, 1000) . "\n\n";
        }

        $text .= "Provide a comprehensive summary with key points, trends, and notable developments. Use annotations to highlight important information.";

        return $text;
    }

    /**
     * Prepare article content for stopword extraction.
     */
    protected function prepareContentForStopwordExtraction(array $articles, string $classification): string
    {
        $text = "Extract relevant keywords and terms from these {$classification} articles that would help classify similar articles in the future. Focus on domain-specific terms, key phrases, and important concepts.\n\n";

        foreach ($articles as $index => $article) {
            $text .= "Article " . ($index + 1) . ": {$article['title']}\n";
            $text .= substr($article['content'], 0, 500) . "\n\n";
        }

        $text .= "Return a JSON array of unique keywords/phrases (lowercase, no duplicates).";

        return $text;
    }
}

