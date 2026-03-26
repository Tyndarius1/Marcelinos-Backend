<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $posts = BlogPost::query()
                ->published()
                ->with('media')
                ->orderByDesc('sort_order')
                ->orderByDesc('published_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $posts->map(fn (BlogPost $post) => $this->formatPost($post)),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch blog posts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $slug): JsonResponse
    {
        try {
            $post = BlogPost::query()
                ->published()
                ->with('media')
                ->where('slug', $slug)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $this->formatPost($post),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Blog post not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch blog post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPost(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'embed_src' => $post->embed_src,
            'iframe_width' => $post->iframe_width,
            'iframe_height' => $post->iframe_height,
            'meta_description' => $post->meta_description,
            'meta_keywords' => $post->meta_keywords,
            'og_image' => $post->og_image,
            'featured_image' => $post->featured_image_url,
            'excerpt' => $post->excerpt,
            'published_at' => $post->published_at?->toIso8601String(),
        ];
    }
}
