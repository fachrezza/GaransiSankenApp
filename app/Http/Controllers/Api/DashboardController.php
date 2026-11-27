<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\PtDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private PtDatabaseService $ptService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowedPts = $this->ptService->allowedPts($user);

        $totalOmset = 0;
        $pendingOrCount = 0;

        foreach ($allowedPts as $pt) {
            $stats = DB::connection($pt['connection'])
                ->table('or130a')
                ->where('so_status', '@')
                ->where('so_dtso', '>=', '2021-01-01')
                ->selectRaw("COALESCE(SUM(CASE WHEN so_cekstat = 'Y' THEN so_harga ELSE 0 END), 0) as total_omset")
                ->selectRaw("COUNT(DISTINCT CASE WHEN COALESCE(so_cekstat, '') = '' THEN so_nocust END) as pending_count")
                ->first();

            $totalOmset += (int) ($stats->total_omset ?? 0);
            $pendingOrCount += (int) ($stats->pending_count ?? 0);
        }

        $banners = Banner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data dashboard',
            'data' => [
                'user' => $user,
                'allowed_pts' => array_values(array_map(fn ($pt) => [
                    'code' => $pt['code'],
                    'name' => $pt['name'],
                ], $allowedPts)),
                'show_all_tab' => count($allowedPts) > 1,
                'banners' => $banners,
                'summary' => [
                    'total_omset' => $totalOmset,
                    'pending_or_count' => $pendingOrCount,
                ],
            ],
        ]);
    }
}
