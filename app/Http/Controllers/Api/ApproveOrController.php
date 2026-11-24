<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PtDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $status = $request->query('status');
        $search = $request->query('search');

        $items = $this->ptService->queryUnion($targetPts, function ($db) use ($status, $search) {
            return $db->table('or_requests')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('document_no', 'like', "%{$search}%")
                            ->orWhere('branch_name', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%");
                    });
                })
                ->orderByDesc('id')
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
                    'total_amount' => $items->sum('amount'),
                    'total_count' => $items->count(),
                    'pending_count' => $items->where('status', 'pending')->count(),
                ],
                'items' => $items,
            ],
        ]);
    }

    public function approve(Request $request, string $pt, int $id): JsonResponse
    {
        return $this->updateStatus($request, $pt, $id, 'approved');
    }

    public function reject(Request $request, string $pt, int $id): JsonResponse
    {
        return $this->updateStatus($request, $pt, $id, 'rejected');
    }

    private function updateStatus(Request $request, string $pt, int $id, string $newStatus): JsonResponse
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
        $record = $db->table('or_requests')->where('id', $id)->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Data OR tidak ditemukan.',
            ], 404);
        }

        if ($record->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "OR sudah berstatus {$record->status}.",
            ], 422);
        }

        $now = now();
        $db->table('or_requests')->where('id', $id)->update([
            'status' => $newStatus,
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => $now,
            'notes' => $validated['notes'] ?? $record->notes,
            'updated_at' => $now,
        ]);

        $db->table('activity_logs')->insert([
            'type' => $newStatus === 'approved' ? 'or_approved' : 'or_rejected',
            'title' => ($newStatus === 'approved' ? 'Approve OR ' : 'Reject OR ').$record->document_no,
            'description' => "{$record->customer_name} (Cabang {$record->branch_name}) - Rp ".number_format($record->amount, 0, ',', '.'),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $updated = $db->table('or_requests')->where('id', $id)->first();
        $updated->pt_code = $entity['code'];
        $updated->pt_name = $entity['name'];

        return response()->json([
            'success' => true,
            'message' => $newStatus === 'approved' ? 'OR berhasil di-approve' : 'OR berhasil di-reject',
            'data' => [
                'item' => $updated,
            ],
        ]);
    }
}
