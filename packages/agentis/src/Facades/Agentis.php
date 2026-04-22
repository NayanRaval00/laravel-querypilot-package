<?php

namespace Agentis\Facades;

use Agentis\AgentisAgent;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Ai\AgentResponse prompt(string $message, ?string $provider = null)
 */
class Agentis extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AgentisAgent::class;
    }
}
