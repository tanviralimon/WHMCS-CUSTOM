<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KnowledgebaseController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function index()
    {
        $categories = $this->whmcs->getKnowledgebaseCategories();

        return Inertia::render('Client/Knowledgebase/Index', [
            'categories' => $categories['categories']['category'] ?? [],
        ]);
    }

    public function category(int $id)
    {
        $categories = $this->whmcs->getKnowledgebaseCategories();
        $articles   = $this->whmcs->getKnowledgebaseArticles($id);

        // Find the category name
        $categoryName = 'Knowledge Base';
        foreach ($categories['categories']['category'] ?? [] as $cat) {
            if ((int) ($cat['id'] ?? 0) === $id) {
                $categoryName = $cat['name'] ?? $categoryName;
                break;
            }
        }

        return Inertia::render('Client/Knowledgebase/Category', [
            'categoryId'   => $id,
            'categoryName' => $categoryName,
            'articles'     => $articles['articles']['article'] ?? [],
            'total'        => (int) ($articles['totalresults'] ?? 0),
        ]);
    }

    public function article(int $id)
    {
        $result  = $this->whmcs->getKnowledgebaseArticle($id);
        $article = ($result['articles']['article'] ?? [null])[0] ?? null;

        if (!$article) {
            abort(404);
        }

        return Inertia::render('Client/Knowledgebase/Article', [
            'article' => $article,
        ]);
    }
}
