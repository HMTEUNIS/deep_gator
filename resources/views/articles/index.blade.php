@extends('layouts.app')

@section('content')
<div class="px-4 py-6 sm:px-0">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Newsfeed Aggregator</h1>
        <p class="text-gray-600 dark:text-gray-400">Stay informed about Climate Change, Economic Justice, Reproductive Rights, LGBTQIA+, and Immigration issues</p>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('articles.index') }}" 
               class="@if(!request('classification')) border-blue-500 text-blue-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm dark:text-gray-300 dark:hover:text-gray-200">
                Overview
            </a>
            @foreach(['Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration'] as $classification)
            <a href="{{ route('articles.index', ['classification' => $classification]) }}" 
               class="@if(request('classification') === $classification) border-blue-500 text-blue-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm dark:text-gray-300 dark:hover:text-gray-200">
                {{ $classification }}
            </a>
            @endforeach
        </nav>
    </div>

    @if(request('classification'))
        <!-- Summary Section -->
        @if(isset($summary) && $summary)
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Recent Developments Summary</h2>
            <div class="prose dark:prose-invert max-w-none">
                {!! nl2br(e($summary)) !!}
            </div>
        </div>
        @endif

        <!-- Articles List -->
        <div class="space-y-6">
            @forelse($articles as $article)
            <article class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-start space-x-4">
                        @if($article->image_url)
                        <div class="flex-shrink-0">
                            <img src="{{ $article->image_url }}" alt="{{ $article->title }}" class="h-24 w-24 object-cover rounded-lg">
                        </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                <a href="{{ $article->link }}" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $article->title }}
                                </a>
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-3 line-clamp-3">
                                {{ Str::limit($article->content, 200) }}
                            </p>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                    @if($article->source)
                                    <span class="font-medium">{{ $article->source }}</span>
                                    @else
                                    <span>{{ $article->source_feed }}</span>
                                    @endif
                                    @if($article->published_at)
                                    <span>{{ $article->published_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                                <a href="{{ $article->link }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">
                                    Read more →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
            @empty
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-12 text-center">
                <p class="text-gray-500 dark:text-gray-400">No articles found for this classification.</p>
            </div>
            @endforelse
        </div>
    @else
        <!-- Overview/Landing Page -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
            @foreach(['Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration'] as $classification)
            <a href="{{ route('articles.index', ['classification' => $classification]) }}" 
               class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ $classification }}</h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">
                    {{ $articleCounts[$classification] ?? 0 }} articles
                </p>
                <span class="text-blue-600 dark:text-blue-400 text-sm font-medium">View articles →</span>
            </a>
            @endforeach
        </div>
    @endif
</div>
@endsection

