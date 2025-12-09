<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Display a listing of articles.
     */
    public function index(Request $request)
    {
        $classification = $request->get('classification');

        if ($classification) {
            $articles = Article::active()
                ->byClassification($classification)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            // Get the most recent summary for this classification
            $summary = Article::active()
                ->byClassification($classification)
                ->whereNotNull('summary')
                ->orderBy('updated_at', 'desc')
                ->value('summary');

            return view('articles.index', compact('articles', 'classification', 'summary'));
        }

        // Overview page - show counts for each classification
        $articleCounts = [];
        foreach (['Climate Change', 'Economic Justice', 'Reproductive Rights', 'LGBTQIA+', 'Immigration'] as $class) {
            $articleCounts[$class] = Article::active()->byClassification($class)->count();
        }

        return view('articles.index', compact('articleCounts'));
    }
}
