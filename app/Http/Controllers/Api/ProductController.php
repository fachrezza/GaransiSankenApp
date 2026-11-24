<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PtDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private PtDatabaseService $ptService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowedPts = $this->ptService->allowedPts($user);
        $targetPts = $this->ptService->resolveTargetPts($user, $request->query('pt'));

        if ($targetPts === null) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Anda tidak memiliki akses ke database PT tersebut.',
            ], 403);
        }

        $category = $request->query('category');
        $search = $request->query('search');

        $products = $this->ptService->queryUnion($targetPts, function ($db) use ($category, $search) {
            return $db->table('products')
                ->when($category && $category !== 'Semua', fn ($q) => $q->where('category', $category))
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->get();
        });

        $categories = $products->pluck('category')->unique()->values();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar produk',
            'data' => [
                'allowed_pts' => array_values(array_map(fn ($pt) => [
                    'code' => $pt['code'],
                    'name' => $pt['name'],
                ], $allowedPts)),
                'show_all_tab' => count($allowedPts) > 1,
                'categories' => $categories,
                'products' => $products,
            ],
        ]);
    }
}
