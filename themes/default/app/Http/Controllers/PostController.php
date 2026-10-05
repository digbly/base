<?php

namespace Themes\Default\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Inertia\Response;
use Modules\Blog\Models\Post;
use Themes\Default\Http\Controllers\Concerns\ListsPosts;
use Themes\Default\Support\PostPresenter;

class PostController extends Controller
{
    use ListsPosts;

    public function show(string $slug): Response
    {
        $post = $this->publishedPostsQuery()
            ->whereHas('translations', fn (Builder $query) => $query->where('slug', $slug))
            ->firstOrFail();

        Post::query()
            ->whereKey($post->getKey())
            ->increment('views');

        $post->views++;

        $comments = $post->comments()
            ->approved()
            ->whereNull('parent_id')
            ->with(['replies' => fn ($query) => $query->approved()->with('author')])
            ->with('author')
            ->latest()
            ->get();

        return $this->render('Post', [
            'post' => PostPresenter::post($post, true),
            'comments' => $comments
                ->map(fn ($comment) => PostPresenter::comment($comment))
                ->values()
                ->all(),
            'commentStatus' => session('comment_status'),
        ]);
    }
}
