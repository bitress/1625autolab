<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{
    public function list(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));

        $activities = Activity::orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        return array_map([$this, 'formatActivity'], $activities->all());
    }

    public function listByCauserUser(?int $userId = null, int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));

        $query = Activity::whereIn('causer_type', ['users', 'user', 'App\\Models\\User']);

        if ($userId !== null && $userId > 0) {
            $query->where('causer_id', $userId);
        }

        $activities = $query->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return array_map([$this, 'formatActivity'], $activities->all());
    }

    public function summarizeByUsers(?string $sort = 'most_recent'): array
    {
        $sort = strtolower(trim((string) $sort));

        $orderBy = 'MAX(activity_log.created_at) DESC, totalActivities DESC, users.name ASC';
        if ($sort === 'most_active') {
            $orderBy = 'totalActivities DESC, MAX(activity_log.created_at) DESC, users.name ASC';
        } elseif ($sort === 'name_asc') {
            $orderBy = 'users.name ASC, totalActivities DESC';
        } elseif ($sort === 'name_desc') {
            $orderBy = 'users.name DESC, totalActivities DESC';
        }

        // We use DB facade here because of the complex GROUP BY and raw expressions
        $rows = DB::table('activity_log')
            ->join('users', 'users.id', '=', DB::raw('CAST(activity_log.causer_id AS UNSIGNED)'))
            ->whereIn('activity_log.causer_type', ['users', 'user', 'App\\Models\\User'])
            ->select(
                DB::raw('CAST(activity_log.causer_id AS UNSIGNED) as userId'),
                'users.name as userName',
                'users.email as userEmail',
                DB::raw('COUNT(*) as totalActivities'),
                DB::raw('MAX(activity_log.created_at) as lastActivityAt')
            )
            ->groupBy(DB::raw('CAST(activity_log.causer_id AS UNSIGNED)'), 'users.name', 'users.email')
            ->orderByRaw($orderBy)
            ->get();

        return array_map(static function ($row) {
            return [
                'userId' => (int) ($row->userId ?? 0),
                'userName' => (string) ($row->userName ?? ''),
                'userEmail' => (string) ($row->userEmail ?? ''),
                'totalActivities' => (int) ($row->totalActivities ?? 0),
                'lastActivityAt' => isset($row->lastActivityAt) ? (string) $row->lastActivityAt : null,
            ];
        }, $rows->all());
    }

    private function formatActivity(Activity $activity): array
    {
        // Spatie's models use 'properties' collection which parses json for us
        return [
            'id' => $activity->id,
            'logName' => $activity->log_name,
            'description' => $activity->description,
            'subjectType' => $activity->subject_type,
            'subjectId' => $activity->subject_id,
            'causerType' => $activity->causer_type,
            'causerId' => $activity->causer_id,
            'properties' => $activity->properties ? $activity->properties->toArray() : [],
            // Spatie doesn't have an attribute_changes column by default,
            // but it puts old/attributes inside properties.
            'attribute_changes' => null,
            'createdAt' => $activity->created_at ? $activity->created_at->toDateTimeString() : '',
            'subject' => $this->resolveEntity($activity->subject_type, (string) $activity->subject_id),
            'causer' => $this->resolveEntity($activity->causer_type, (string) $activity->causer_id),
        ];
    }

    private function resolveEntity(?string $type, ?string $id): ?array
    {
        if ($type === null || $id === null || $type === '' || $id === '') {
            return null;
        }

        // Remap Laravel model types to table names for raw query if needed
        $tableName = $type;
        if (str_starts_with($type, 'App\\Models\\')) {
            $model = new $type;
            $tableName = $model->getTable();
        }

        if (! preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            return [
                'id' => $id,
                'type' => $tableName,
            ];
        }

        try {
            $row = DB::table($tableName)->where('id', $id)->first();
            if ($row) {
                return (array) $row;
            }
        } catch (\Throwable $e) {
            // Ignore schema errors if table doesn't exist
        }

        return [
            'id' => $id,
            'type' => $type,
        ];
    }
}
