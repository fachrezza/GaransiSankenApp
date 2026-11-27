<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PtDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(private PtDatabaseService $ptService) {}

    public function activities(Request $request): JsonResponse
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

        $approvedOrCount = 0;
        $totalOmset = 0;
        $totalStock = 0;

        foreach ($targetPts as $pt) {
            $db = DB::connection($pt['connection']);
            $orStats = $db->table('or130a')
                ->where('so_status', '@')
                ->where('so_cekstat', 'Y')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(so_harga), 0) as total')
                ->first();

            $approvedOrCount += (int) ($orStats->count ?? 0);
            $totalOmset += (int) ($orStats->total ?? 0);
            $totalStock += (int) $db->table('products')->sum('stock');
        }

        $activities = $this->ptService->queryUnion(
            $targetPts,
            fn ($db) => $db->table('activity_logs')->orderByDesc('occurred_at')->limit(20)->get()
        )->sortByDesc('occurred_at')->values();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil laporan & aktivitas',
            'data' => [
                'allowed_pts' => array_values(array_map(fn ($pt) => [
                    'code' => $pt['code'],
                    'name' => $pt['name'],
                ], $allowedPts)),
                'show_all_tab' => count($allowedPts) > 1,
                'performance' => [
                    'approved_or_count' => $approvedOrCount,
                    'total_omset' => $totalOmset,
                    'total_stock' => $totalStock,
                ],
                'activities' => $activities,
            ],
        ]);
    }
}
