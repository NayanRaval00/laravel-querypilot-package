<?php

namespace QueryPilot;

use QueryPilot\Tools\QueryDatabaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

class QueryPilotAgent implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        $tables        = config('querypilot.tables', []);
        $relationships = config('querypilot.relationships', []);
        $maxRows       = config('querypilot.max_rows', 100);
        $schemaLines   = [];

        foreach ($tables as $table => $def) {
            $cols          = implode(', ', $def['searchable'] ?? []);
            $label         = $def['label'] ?? $table;
            // $schemaLines[] = "- `{$table}` ({$label}): columns [{$cols}]";
            $related = collect($relationships)
                ->filter(fn($r) => str_contains($r, $table))
                ->implode('; ');

            $schemaLines[] =
                "- {$table} ({$label})
                columns: [{$cols}]
                relations: {$related}";
        }

        $schema = empty($schemaLines)
            ? 'No tables configured.'
            : implode("\n", $schemaLines);

        $relationshipLines = empty($relationships)
            ? 'None defined.'
            : implode("\n", array_map(fn($r) => "- {$r}", $relationships));
        $appName = env('APP_NAME', 'Laravel');

        return <<<INSTRUCTIONS
        You are an intelligent database assistant for a "{$appName}" application.

        Your job is to understand what the user is asking for and use the `query_database` tool to retrieve the right data.

        RULES:
        - Always use the `query_database` tool to fetch data before answering.
        - Never guess or fabricate data.
        - Only query the tables and columns listed below.
        - Use relationships to JOIN tables when needed.
        - Do NOT include SQL in your answer — just describe the data naturally.
        - If image columns exist, mention images are available.
        - Always add LIMIT {$maxRows} to every query unless the user asks for a specific count.
        - Use JOIN when data from multiple tables is needed — prefer a single JOIN query over multiple queries.
        - Always use indexes: prefer filtering by id, sku, email over full-text columns.
        - Never use SELECT * — always name the specific columns you need.
        - If a user asks for a record and "details", "profile", "related data", ALWAYS inspect related tables and use JOINs.

        Available tables:
        {$schema}

        Table relationships (use these for JOINs):
        {$relationshipLines}
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new QueryDatabaseTool(
                tables: config('querypilot.tables', []),
                maxRows: config('querypilot.max_rows', 100),
                cacheTtl: config('querypilot.cache_ttl', 0),
            ),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            // 'sql'         => $schema->string()->description('The SQL query executed.')->nullable(),
            'answer' => $schema->string()
                ->description('Friendly human-readable answer.')
                ->required(),

            'table' => $schema->string()
                ->description('Primary table queried.')
                ->nullable(),

            'count' => $schema->integer()
                ->description('Number of records found.')
                ->nullable(),

            'rows' => $schema->array()
                ->description('The actual data rows returned, each as a JSON object string.')
                ->nullable(),
        ];
    }
}
