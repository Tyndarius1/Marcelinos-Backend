<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BubbleChatFaq;
use Illuminate\Http\JsonResponse;

class BubbleChatFaqController extends Controller
{
    public function index(): JsonResponse
    {
        $faqs = BubbleChatFaq::query()
            ->active()
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get(['id', 'question', 'answer', 'sort_order']);

        return response()->json([
            'success' => true,
            'data' => $faqs,
        ]);
    }
}
