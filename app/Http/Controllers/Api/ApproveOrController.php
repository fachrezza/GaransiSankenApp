<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PtDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApproveOrController extends Controller
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

        $search = $request->query('search');
        $today = now()->toDateString();

        $items = $this->ptService->queryUnion($targetPts, function ($db) use ($search, $today) {
            $detailCounts = $db->table('or131a')
                ->selectRaw('so_noso as mynoso, COUNT(so_noso) as mycnt')
                ->groupBy('so_noso');

            return $db->table('or130a as a')
                ->joinSub($detailCounts, 'b', fn ($join) => $join->on('a.so_noso', '=', 'b.mynoso'))
                ->leftJoin('tb_cab as c', DB::raw('SUBSTR(a.so_nocust, 1, 1)'), '=', 'c.kd_cab')
                ->where('a.so_hdate', '<=', $today)
                ->whereRaw("COALESCE(a.so_cekstat, '') = ''")
                ->where('a.so_status', '@')
                ->where('b.mycnt', '>', 0)
                ->where('a.so_dtso', '>=', '2021-01-01')
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('a.so_nocust', 'like', "%{$search}%")
                            ->orWhere('a.so_name', 'like', "%{$search}%")
                            ->orWhere('a.so_kota', 'like', "%{$search}%")
                            ->orWhere('c.nm_cab', 'like', "%{$search}%");
                    });
                })
                ->groupBy('a.so_nocust', 'a.so_name', 'a.so_kota', 'c.nm_cab')
                ->selectRaw('a.so_kota as jns, c.nm_cab as cabang, a.so_nocust as toko, a.so_name as nm_toko, SUM(a.so_harga) as harga')
                ->get();
        });

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar Approve OR',
            'data' => [
                'allowed_pts' => array_values(array_map(fn ($pt) => [
                    'code' => $pt['code'],
                    'name' => $pt['name'],
                ], $allowedPts)),
                'show_all_tab' => count($allowedPts) > 1,
                'summary' => [
                    'total_amount' => $items->sum('harga'),
                    'total_count' => $items->count(),
                ],
                'items' => $items,
            ],
        ]);
    }

    public function show(Request $request, string $pt, string $toko): JsonResponse
    {
        $targetPts = $this->ptService->resolveTargetPts($request->user(), $pt);

        if ($targetPts === null || empty($targetPts)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Anda tidak memiliki akses ke database PT tersebut.',
            ], 403);
        }

        $entity = reset($targetPts);
        $db = DB::connection($entity['connection']);

        $pendingOrAmount = (int) $db->table('or130a')
            ->where('so_nocust', $toko)
            ->where('so_hdate', '<=', now()->toDateString())
            ->whereRaw("COALESCE(so_cekstat, '') = ''")
            ->where('so_status', '@')
            ->where('so_dtso', '>=', '2021-01-01')
            ->sum('so_harga');

        // Step 2: Check ar010a (if exists in PT DB) for store & group code (mp_custno) + credit limit (mp_t_cost)
        $hasAr010a = Schema::connection($entity['connection'])->hasTable('ar010a');
        $customer = $hasAr010a
            ? $db->table('ar010a')->select('mp_nocust', 'mp_custno', 'mp_t_cost')->where('mp_nocust', $toko)->first()
            : null;

        $groupCode = $customer?->mp_custno ?: null;
        $limit = (int) ($customer?->mp_t_cost ?? 0);

        $relatedStores = ($hasAr010a && $groupCode)
            ? $db->table('ar010a')->where('mp_custno', $groupCode)->pluck('mp_nocust')->all()
            : [$toko];

        if (empty($relatedStores)) {
            $relatedStores = [$toko];
        }

        // Step 4: Calculate belum jadi faktur (sa131a) & piutang faktur (ar020a) across PT databases
        $totalDoDebt = 0;
        $totalArDebt = 0;
        $debtByPt = [];

        foreach (PtDatabaseService::ENTITIES as $ptCode => $ptInfo) {
            $ptDb = DB::connection($ptInfo['connection']);

            $ptStores = ($groupCode && Schema::connection($ptInfo['connection'])->hasTable('ar010a'))
                ? $ptDb->table('ar010a')->where('mp_custno', $groupCode)->pluck('mp_nocust')->all()
                : $relatedStores;

            if (empty($ptStores)) {
                $debtByPt[$ptCode] = ['belum_jadi_faktur' => 0, 'piutang_faktur' => 0, 'total' => 0];
                continue;
            }

            $doDebt = (int) $ptDb->table('sa131a')
                ->where('do_grdo', 'like', 'D%')
                ->where('do_statux', '@')
                ->whereIn('do_nocust', $ptStores)
                ->selectRaw("COALESCE(SUM(CASE WHEN do_status = '@' AND COALESCE(do_nofak, '') = '' AND do_qty != 0 THEN do_harga WHEN COALESCE(do_status, '') = '' THEN do_harga ELSE 0 END), 0) as hutang")
                ->value('hutang');

            $arDebt = (int) $ptDb->table('ar020a')
                ->where('fa_grfak', 'FT')
                ->where('fa_status', '@')
                ->whereIn('fa_nocust', $ptStores)
                ->selectRaw('COALESCE(SUM(fa_harga - fa_hgkas), 0) as hutang')
                ->value('hutang');

            $ptTotal = $doDebt + $arDebt;
            $debtByPt[$ptCode] = [
                'belum_jadi_faktur' => $doDebt,
                'piutang_faktur' => $arDebt,
                'total' => $ptTotal,
            ];
            $totalDoDebt += $doDebt;
            $totalArDebt += $arDebt;
        }

        $totalDebt = $totalDoDebt + $totalArDebt;

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil detail cek kredit OR',
            'data' => [
                'pt_code' => $entity['code'],
                'pt_name' => $entity['name'],
                'toko' => $toko,
                'group_code' => $groupCode,
                'related_stores' => $relatedStores,
                'limit' => $limit,
                'nilai_or' => $pendingOrAmount,
                'belum_jadi_faktur' => $totalDoDebt,
                'piutang_faktur' => $totalArDebt,
                'total_hutang' => $totalDebt,
                'debt_by_pt' => $debtByPt,
                'is_over_limit' => $limit > 0 && ($totalDebt + $pendingOrAmount) > $limit,
            ],
        ]);
    }

    public function approve(Request $request, string $pt, string $toko): JsonResponse
    {
        return $this->updateStatus($request, $pt, $toko, 'Y');
    }

    public function reject(Request $request, string $pt, string $toko): JsonResponse
    {
        return $this->updateStatus($request, $pt, $toko, 'R');
    }

    private function updateStatus(Request $request, string $pt, string $toko, string $cekStat): JsonResponse
    {
        $targetPts = $this->ptService->resolveTargetPts($request->user(), $pt);

        if ($targetPts === null || empty($targetPts)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Anda tidak memiliki akses ke database PT tersebut.',
            ], 403);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $entity = reset($targetPts);
        $db = DB::connection($entity['connection']);

        $pendingQuery = $db->table('or130a')
            ->where('so_nocust', $toko)
            ->where('so_hdate', '<=', now()->toDateString())
            ->whereRaw("COALESCE(so_cekstat, '') = ''")
            ->where('so_status', '@')
            ->where('so_dtso', '>=', '2021-01-01');

        $totalAmount = (int) (clone $pendingQuery)->sum('so_harga');
        $firstRecord = (clone $pendingQuery)->first();

        if (! $firstRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada OR pending untuk toko tersebut.',
            ], 404);
        }

        $now = now();
        $updateData = [
            'so_cekstat' => $cekStat,
            'so_appdate' => $now->toDateString(),
            'so_apptime' => $now->format('H:i:s'),
            'so_huser' => $request->user()->name,
        ];
        if (! empty($validated['notes'])) {
            $updateData['so_ket1'] = $validated['notes'];
        }
        $affected = $pendingQuery->update($updateData);

        $actionLabel = $cekStat === 'Y' ? 'Approve OR' : 'Reject OR';
        $db->table('activity_logs')->insert([
            'type' => $cekStat === 'Y' ? 'or_approved' : 'or_rejected',
            'title' => "{$actionLabel} {$toko}",
            'description' => "{$firstRecord->so_name} ({$affected} SO) - Rp ".number_format($totalAmount, 0, ',', '.'),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => $cekStat === 'Y' ? 'OR toko berhasil di-approve' : 'OR toko berhasil di-reject',
            'data' => [
                'pt_code' => $entity['code'],
                'toko' => $toko,
                'status' => $cekStat,
                'updated_count' => $affected,
                'total_amount' => $totalAmount,
            ],
        ]);
    }
}
