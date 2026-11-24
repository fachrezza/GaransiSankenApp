<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PtDatabaseService
{
    public const ENTITIES = [
        'IAK' => [
            'code' => 'IAK',
            'name' => 'PT Istana Argo Kencana',
            'connection' => 'dummy_iak',
            'permission' => 'view-db-iak',
        ],
        'SAM' => [
            'code' => 'SAM',
            'name' => 'PT Sambodja Lestari',
            'connection' => 'dummy_sam',
            'permission' => 'view-db-sam',
        ],
        'RVG' => [
            'code' => 'RVG',
            'name' => 'PT Rovega Kirana Indonesia',
            'connection' => 'dummy_rvg',
            'permission' => 'view-db-rvg',
        ],
    ];

    /**
     * Return PT entities the user is permitted to access.
     *
     * @return array<string, array{code: string, name: string, connection: string, permission: string}>
     */
    public function allowedPts(User $user): array
    {
        if ($user->is_superadmin) {
            return self::ENTITIES;
        }

        return array_filter(
            self::ENTITIES,
            fn (array $pt) => $user->hasPermission($pt['permission'])
        );
    }

    /**
     * Resolve target PT list for a request, returning null if a requested PT is forbidden/invalid.
     *
     * @return array<string, array{code: string, name: string, connection: string, permission: string}>|null
     */
    public function resolveTargetPts(User $user, ?string $requestedPt): ?array
    {
        $allowed = $this->allowedPts($user);

        if (! $requestedPt || strtoupper($requestedPt) === 'ALL' || strtoupper($requestedPt) === 'SEMUA') {
            return $allowed;
        }

        $code = strtoupper($requestedPt);

        return isset($allowed[$code]) ? [$code => $allowed[$code]] : null;
    }

    /**
     * Query across all permitted PT connections and attach pt_code/pt_name to each row.
     */
    public function queryUnion(array $pts, callable $queryCallback): Collection
    {
        $results = collect();

        foreach ($pts as $code => $pt) {
            $rows = $queryCallback(DB::connection($pt['connection']), $code, $pt)
                ->map(function ($row) use ($code, $pt) {
                    $row->pt_code = $code;
                    $row->pt_name = $pt['name'];

                    return $row;
                });

            $results = $results->concat($rows);
        }

        return $results->values();
    }
}
