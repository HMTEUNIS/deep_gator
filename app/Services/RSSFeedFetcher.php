<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class RSSFeedFetcher
{
    /**
     * Fetch articles from multiple RSS feeds.
     *
     * @param array $feeds Array of RSS feed URLs
     * @return array Array of articles with title, content, link, image_url, source_feed, source, published_at
     */
    public function fetchFeeds(array $feeds): array
    {
        $articles = [];

        foreach ($feeds as $feedUrl) {
            try {
                $response = Http::timeout(30)->get($feedUrl);

                if (!$response->successful()) {
                    Log::warning("Failed to fetch RSS feed: {$feedUrl}", [
                        'status' => $response->status(),
                    ]);
                    continue;
                }

                $xml = @simplexml_load_string($response->body());

                if ($xml === false) {
                    Log::warning("Failed to parse XML from feed: {$feedUrl}");
                    continue;
                }

                $items = $xml->channel->item ?? [];
                $feedArticles = [];

                foreach ($items as $item) {
                    $article = $this->parseRSSItem($item, $feedUrl);
                    if ($article) {
                        $feedArticles[] = $article;
                    }
                }

                // Sort by published_at descending (newest first) so we can stop early on duplicates
                usort($feedArticles, function ($a, $b) {
                    $aTime = $a['published_at'] ? $a['published_at']->timestamp : 0;
                    $bTime = $b['published_at'] ? $b['published_at']->timestamp : 0;
                    return $bTime <=> $aTime; // Descending order
                });

                $articles = array_merge($articles, $feedArticles);
            } catch (\Exception $e) {
                Log::error("Error fetching RSS feed: {$feedUrl}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $articles;
    }

    /**
     * Parse an RSS item into an article array.
     */
    protected function parseRSSItem(SimpleXMLElement $item, string $sourceFeed): ?array
    {
        try {
            $title = (string) ($item->title ?? '');
            $link = (string) ($item->link ?? '');
            $description = (string) ($item->description ?? '');
            $pubDate = isset($item->pubDate) ? $this->parseDate((string) $item->pubDate) : null;

            // Extract source name from feed URL or use mapping
            $source = $this->extractSourceName($sourceFeed);

            // Try to extract image from enclosure or media:content
            $imageUrl = null;
            if (isset($item->enclosure) && isset($item->enclosure['type'])) {
                $type = (string) $item->enclosure['type'];
                if (str_starts_with($type, 'image/')) {
                    $imageUrl = (string) $item->enclosure['url'];
                }
            }

            // Try media:content namespace
            if (!$imageUrl && isset($item->children('media', true)->content)) {
                $mediaContent = $item->children('media', true)->content;
                if (isset($mediaContent['url'])) {
                    $imageUrl = (string) $mediaContent['url'];
                }
            }

            // Try to extract from description HTML
            if (!$imageUrl && $description) {
                preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches);
                if (!empty($matches[1])) {
                    $imageUrl = $matches[1];
                }
            }

            return [
                'title' => $title,
                'content' => strip_tags($description),
                'link' => $link,
                'image_url' => $imageUrl,
                'source_feed' => $sourceFeed,
                'source' => $source,
                'published_at' => $pubDate,
            ];
        } catch (\Exception $e) {
            Log::error("Error parsing RSS item", [
                'error' => $e->getMessage(),
                'feed' => $sourceFeed,
            ]);
            return null;
        }
    }

    /**
     * Extract source name from feed URL or use configured mapping.
     */
    protected function extractSourceName(string $feedUrl): string
    {
        // Check if there's a configured mapping
        $feedSources = config('newsfeed.feed_sources', []);
        if (isset($feedSources[$feedUrl])) {
            return $feedSources[$feedUrl];
        }

        // Extract from URL domain
        $parsedUrl = parse_url($feedUrl);
        $host = $parsedUrl['host'] ?? '';

        // Remove www. prefix
        $host = preg_replace('/^www\./', '', $host);

        // Extract main domain name (e.g., "nbcnews.com" -> "NBC", "cnn.com" -> "CNN")
        $domainParts = explode('.', $host);
        
        // Common domain patterns
        $domainMap = [
            'nbcnews' => 'NBC',
            'abcnews' => 'ABC',
            'cbsnews' => 'CBS',
            'vox' => 'Vox',
            'feedx' => 'Associated Press', // feedx.net hosts AP feeds
            'cnn' => 'CNN',
            'bbc' => 'BBC',
            'reuters' => 'Reuters',
            'ap' => 'AP',
            'theguardian' => 'The Guardian',
            'nytimes' => 'NY Times',
            'washingtonpost' => 'Washington Post',
            'theatlantic' => 'The Atlantic',
        ];

        $mainDomain = $domainParts[0] ?? '';
        if (isset($domainMap[$mainDomain])) {
            return $domainMap[$mainDomain];
        }

        // Fallback: capitalize first letter of domain
        return ucfirst($mainDomain ?: 'Unknown');
    }

    /**
     * Parse various date formats from RSS feeds.
     */
    protected function parseDate(string $dateString): ?\Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }
}

