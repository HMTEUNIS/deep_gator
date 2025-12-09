# Newsfeed Aggregator - Setup Guide

## Overview

This Laravel application aggregates news articles from RSS feeds, classifies them using a Naive Bayes classifier, and provides AI-generated summaries using DeepSeek API.

## Features

- RSS Feed Fetching: Automatically fetches articles from multiple RSS feeds
- Article Classification: Uses Naive Bayes classifier to categorize articles into:
  - Climate Change
  - Economic Justice
  - Reproductive Rights
  - LGBTQIA+
  - Immigration
- AI Summaries: Generates annotated summaries using DeepSeek API
- Stopword Extraction: Automatically extracts and learns new stopwords to improve classification
- Auto-cleanup: Removes articles older than 48 hours automatically

## Installation

1. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Environment Configuration**
   Create a `.env` file and add:
   ```env
   DEEPSEEK_API_KEY=your_deepseek_api_key_here
   ```

3. **Database Setup**
   ```bash
   php artisan migrate
   ```

4. **Configure RSS Feeds**
   Edit `config/newsfeed.php` and add your RSS feed URLs:
   ```php
   'rss_feeds' => [
       'https://example.com/feed.xml',
       'https://another-site.com/rss',
   ],
   ```

5. **Initialize Classifier Brain**
   
   If you have initial training data (JSON format), save it as `storage/app/initial_training.json` and run:
   ```bash
   php artisan classifier:import-training
   ```
   
   Or import from a specific file:
   ```bash
   php artisan classifier:import-training /path/to/your/training.json
   ```
   
   The classifier will automatically convert and load your training data. If no initial training is provided, an empty brain will be created when you first run the application.

6. **Build Assets**
   ```bash
   npm run build
   ```

## Scheduled Tasks

The following tasks are automatically scheduled:

- **Every 30 minutes**: Fetch and process articles from RSS feeds (`articles:fetch`)
- **Daily**: Generate summaries for each classification (`articles:generate-summaries`)
- **Hourly**: Clean up expired articles (`articles:cleanup`)
- **Weekly**: Update classifier with new stopwords (`classifier:update`)

To run the scheduler, add this to your crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Manual Commands

You can also run commands manually:

```bash
# Fetch articles
php artisan articles:fetch

# Generate summaries
php artisan articles:generate-summaries

# Generate summary for specific classification
php artisan articles:generate-summaries --classification="Climate Change"

# Cleanup expired articles
php artisan articles:cleanup

# Update classifier
php artisan classifier:update
```

## Usage

1. Start the development server:
   ```bash
   php artisan serve
   ```

2. Visit `http://localhost:8000` to see the landing page

3. Click on any classification tab to view articles and summaries

## Project Structure

- `app/Services/`: Core business logic services
  - `RSSFeedFetcher.php`: Fetches and parses RSS feeds
  - `NaiveBayesClassifier.php`: Classifies articles
  - `DeepSeekService.php`: Integrates with DeepSeek API
  - `ArticleProcessor.php`: Orchestrates article processing

- `app/Console/Commands/`: Scheduled commands
  - `FetchArticles.php`: Fetches articles from RSS feeds
  - `GenerateSummaries.php`: Generates AI summaries
  - `CleanupArticles.php`: Removes expired articles
  - `UpdateClassifier.php`: Updates classifier with stopwords

- `app/Models/`: Database models
  - `Article.php`: Article model
  - `Stopword.php`: Stopword model

- `database/migrations/`: Database schema
  - `create_articles_table.php`: Articles table
  - `create_stopwords_table.php`: Stopwords table

- `resources/views/`: Blade templates
  - `layouts/app.blade.php`: Main layout
  - `articles/index.blade.php`: Article listing page

## Configuration Files

- `config/newsfeed.php`: RSS feed URLs
- `config/services.php`: DeepSeek API configuration

## Notes

- Articles are automatically deleted after 48 hours
- The classifier brain is stored in `storage/app/classifier_brain.json`
- Make sure to add your DeepSeek API key to the `.env` file
- RSS feeds should be valid XML/RSS format

