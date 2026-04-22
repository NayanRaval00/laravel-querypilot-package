<?php

namespace Agentis;

use Agentis\Tools\QueryDatabaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse; // ← add this import
use Stringable;

class AgentisAgent implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    /**
     * System instructions given to the AI.
     */
    public function instructions(): Stringable|string
    {
        $tables       = config('agentis.tables', []);
        $schemaLines  = [];

        foreach ($tables as $table => $def) {
            $cols          = implode(', ', $def['searchable'] ?? []);
            $label         = $def['label'] ?? $table;
            $schemaLines[] = "- `{$table}` ({$label}): columns [{$cols}]";
        }

        $schema = empty($schemaLines)
            ? 'No tables configured. Ask the developer to add tables to config/agentis.php.'
            : implode("\n", $schemaLines);

        return <<<INSTRUCTIONS
        You are an intelligent database assistant for a Laravel application.

        Use the query_database tool to retrieve data, then give a clear, concise answer.
        Always call the tool before answering — never guess or fabricate data.
        Only query the tables and columns listed below.

        Available data:
        {$schema}
        INSTRUCTIONS;
    }

    /**
     * Tools the AI can use during this conversation.
     */
    public function tools(): iterable
    {
        return [
            new QueryDatabaseTool(config('agentis.tables', [])),
        ];
    }

    /**
     * Structured output schema — what the final JSON response looks like.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'answer'      => $schema->string()->description('Plain English answer to the user query.')->required(),
            'sql'         => $schema->string()->description('The SQL query that was executed.')->nullable(),
            'count'       => $schema->integer()->description('Number of rows returned.')->nullable(),
            'explanation' => $schema->string()->description('What the query did.')->nullable(),
        ];
    }
}
