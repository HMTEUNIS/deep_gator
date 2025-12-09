<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RSS Feeds Configuration
    |--------------------------------------------------------------------------
    |
    | Add your RSS feed URLs here. The application will fetch articles from
    | these feeds and classify them using the Naive Bayes classifier.
    |
    | You can also map feed URLs to source names for better organization.
    | If a feed URL is not in the mapping, the source name will be extracted
    | from the URL automatically.
    |
    */

    'rss_feeds' => [
        'https://feeds.nbcnews.com/nbcnews/public/news',
        'https://abcnews.go.com/abcnews/topstories',
        'https://www.cbsnews.com/latest/rss/main',
        'https://www.vox.com/rss/index.xml',
        'https://feedx.net/rss/ap.xml',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feed Source Name Mapping
    |--------------------------------------------------------------------------
    |
    | Map RSS feed URLs to their source names (e.g., "NBC", "CNN", "BBC").
    | If a feed URL is not mapped here, the source name will be automatically
    | extracted from the URL domain.
    |
    */

    'feed_sources' => [
        'https://feeds.nbcnews.com/nbcnews/public/news' => 'NBC',
        'https://abcnews.go.com/abcnews/topstories' => 'ABC',
        'https://www.cbsnews.com/latest/rss/main' => 'CBS',
        'https://www.vox.com/rss/index.xml' => 'Vox',
        'https://feedx.net/rss/ap.xml' => 'Associated Press',
    ],
];

