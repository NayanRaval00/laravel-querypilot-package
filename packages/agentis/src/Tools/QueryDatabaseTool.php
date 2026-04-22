<?php

namespace Agentis\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryDatabaseTool implements Tool
{
    public function __construct(protected array $tables = []) {}

    public function description(): Stringable|string
    {
        $tableList = implode(', ', array_keys($this->tables));

        return "Query the application database using a safe SQL SELECT statement. "
            . "Available tables: {$tableList}. "
            . "Only SELECT is allowed — never INSERT, UPDATE, DELETE, or DROP.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()
                ->description('A valid read-only SQL SELECT query.')
                ->required(),

            'explanation' => $schema->string()
                ->description('Plain English explanation of what this query does and why.')
                ->required(),
        ];
    }

    /**
     * Called by the AI agent framework — delegates to executeSql().
     */
    public function handle(Request $request): Stringable|string
    {
        return json_encode(
            $this->executeSql(
                (string) $request->string('sql'),
                (string) $request->string('explanation'),
            )
        );
    }

    /**
     * Public method containing all the real logic.
     * Testable directly without needing a Request object.
     */
    public function executeSql(string $sql, string $explanation = ''): array
    {
        $sql = trim($sql);

        // Safety: only SELECT allowed
        if (! preg_match('/^\s*SELECT\s/i', $sql)) {
            return ['error' => 'Only SELECT queries are permitted.'];
        }

        // Safety: block dangerous keywords
        $blocked = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'EXEC', 'GRANT'];
        foreach ($blocked as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                return ['error' => "Keyword '{$keyword}' is not allowed."];
            }
        }

        try {
            $rows = DB::select($sql);
            $rows = array_map(fn($r) => (array) $r, $rows);

            return [
                'success'     => true,
                'count'       => count($rows),
                'rows'        => $rows,
                'sql'         => $sql,
                'explanation' => $explanation,
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'sql'   => $sql,
            ];
        }
    }
}
