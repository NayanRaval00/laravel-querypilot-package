<?php

namespace QueryPilot\Facades;

use QueryPilot\QueryPilotAgent;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Ai\AgentResponse prompt(string $message, ?string $provider = null)
 */
class QueryPilot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return QueryPilotAgent::class;
    }
}
