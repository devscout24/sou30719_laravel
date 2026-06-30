<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeedCategoryResource;
use App\Models\FeedCategory;
use App\Traits\ApiResponse;

class FeedCategoryController extends Controller
{
    use ApiResponse;

    /**
     * Return all active fixed feed categories ordered by sort_order.
     */
    public function index()
    {
        $categories = FeedCategory::active()
            ->orderBy('sort_order')
            ->get();

        return $this->success(
            FeedCategoryResource::collection($categories),
            'Feed categories fetched successfully'
        );
    }
}
