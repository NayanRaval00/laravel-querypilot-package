<?php

namespace QueryPilot\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryDatabaseTool implements Tool
{
    public function __construct(
        protected array $tables = [],
        protected int $maxRows = 100,
        protected int $cacheTtl = 0,
    ) {}

    public function description(): Stringable|string
    {
        $tableList = implode(', ', array_keys($this->tables));

        return "Retrieve data from database safely. Available tables: {$tableList}. Supports columns, filters and JOIN relationships.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()->required(),

            'columns' => $schema->string()
                ->description(
                    'Comma separated columns. Example: users.name, profiles.bio'
                )->required(),

            'joins' => $schema->string()
                ->description(
                    'REQUIRED when related tables are needed. Example: LEFT JOIN profiles ON profiles.user_id = users.id'
                )
                ->required(),

            'where' => $schema->string()
                ->nullable(),

            'order_by' => $schema->string()
                ->nullable(),

            'limit' => $schema->integer()
                ->nullable(),

            'explanation' => $schema->string()
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode(
            $this->execute(
                table: (string)$request->string('table'),
                columns: (string)$request->string('columns'),
                joins: (string)$request->string('joins'),
                where: (string)$request->string('where'),
                orderBy: (string)$request->string('order_by'),
                limit: (int)($request->integer('limit') ?: $this->maxRows),
                explanation: (string)$request->string('explanation'),
            )
        );
    }

    public function execute(
        string $table,
        string $columns = '*',
        string $joins = '',
        string $where = '',
        string $orderBy = '',
        int $limit = 100,
        string $explanation = ''
    ): array {

        if (!isset($this->tables[$table])) {
            return [
                'error' => "Table {$table} not allowed."
            ];
        }

        $allAllowedColumns = [];

        foreach ($this->tables as $tbl => $config) {
            foreach (($config['searchable'] ?? []) as $col) {
                $allAllowedColumns[] = $col;
                $allAllowedColumns[] = "{$tbl}.{$col}";
            }
        }

        $requestedCols = array_map(
            'trim',
            explode(',', $columns)
        );

        foreach ($requestedCols as $col) {

            if (
                preg_match('/^\w+\s*\(/i', $col)
            ) {
                continue;
            }

            if (!in_array($col, $allAllowedColumns)) {
                return [
                    'error' => "Column {$col} not allowed."
                ];
            }
        }

        if (!empty($where)) {

            $blocked = [
                'DROP',
                'DELETE',
                'INSERT',
                'UPDATE',
                'ALTER',
                'TRUNCATE',
                '--',
                ';'
            ];

            foreach ($blocked as $bad) {
                if (
                    stripos($where, $bad) !== false
                ) {
                    return [
                        'error' => 'Invalid where clause'
                    ];
                }
            }
        }

        $safeLimit = min(
            $limit,
            $this->maxRows
        );

        $sql = "SELECT {$columns} FROM {$table}";

        if ($joins) {
            $sql .= " {$joins}";
        }

        if ($where) {
            $sql .= " WHERE {$where}";
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $sql .= " LIMIT {$safeLimit}";

        $cacheKey = 'agentis_' . md5($sql);

        if (
            $this->cacheTtl &&
            Cache::has($cacheKey)
        ) {
            $cached = Cache::get($cacheKey);
            $cached['cached'] = true;
            return $cached;
        }

        try {

            $start = microtime(true);

            $rows = DB::select($sql);

            $duration = round(
                (microtime(true) - $start) * 1000,
                2
            );

            $rows = array_map(
                fn($r) => (array)$r,
                $rows
            );


            $rows = $this->normalizeImageUrls(
                $table,
                $rows
            );

            $result = [
                'success' => true,
                'sql' => $sql,
                'table' => $table,
                'count' => count($rows),
                'rows' => $rows,
                'explanation' => $explanation,
                'duration_ms' => $duration,
                'cached' => false
            ];

            if ($this->cacheTtl) {
                Cache::put(
                    $cacheKey,
                    $result,
                    $this->cacheTtl
                );
            }

            return $result;
        } catch (\Exception $e) {

            return [
                'error' => $e->getMessage(),
                'sql' => $sql
            ];
        }
    }



    protected function normalizeImageUrls(
        string $table,
        array $rows
    ): array {

        $imageColumn =
            $this->tables[$table]['image']
            ?? null;

        if (!$imageColumn) {
            return $rows;
        }

        return array_map(
            function ($row) use ($imageColumn) {

                $row['image_url'] =
                    $row[$imageColumn] ?? null;

                return $row;
            },
            $rows
        );
    }
}
