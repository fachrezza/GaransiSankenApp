<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar banner',
            'data' => [
                'banners' => Banner::orderBy('sort_order')->get(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'badge_text' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $banner = Banner::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Banner berhasil dibuat',
            'data' => [
                'banner' => $banner,
            ],
        ], 201);
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $validated = $request->validate([
            'badge_text' => ['nullable', 'string', 'max:100'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $banner->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Banner berhasil diperbarui',
            'data' => [
                'banner' => $banner,
            ],
        ]);
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner berhasil dihapus',
            'data' => null,
        ]);
    }
}
