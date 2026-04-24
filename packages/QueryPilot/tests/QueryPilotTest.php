<?php

use QueryPilot\QueryPilotAgent;
use QueryPilot\Tools\QueryDatabaseTool;
use Laravel\Ai\Testing\AgentFake;

beforeEach(function () {
    // Use Laravel AI's built-in fake so we don't hit real Gemini API in tests
    AgentFake::fake([
        QueryPilotAgent::class => [
            'structured' => [
                'answer'      => 'There are 3 users in the database.',
                'sql'         => 'SELECT COUNT(*) as count FROM users',
                'count'       => 3,
                'explanation' => 'Counted all rows in the users table.',
            ],
        ],
    ]);
});

test('agent is bound in container', function () {
    $agent = app(QueryPilotAgent::class);
    expect($agent)->toBeInstanceOf(QueryPilotAgent::class);
});

test('agent has correct instructions', function () {
    $agent        = app(QueryPilotAgent::class);
    $instructions = $agent->instructions();

    expect((string) $instructions)
        ->toContain('database assistant')
        ->toContain('query_database');
});

test('agent has query database tool', function () {
    $agent = app(QueryPilotAgent::class);
    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(QueryDatabaseTool::class);
});

test('query database tool blocks non-select queries', function () {
    $tool = new QueryDatabaseTool(['users' => ['searchable' => ['id', 'name']]]);

    // We test handle() directly — no AI needed
    $request = \Laravel\Ai\Tools\Request::fromArray([
        'sql'         => 'DELETE FROM users',
        'explanation' => 'trying to delete',
    ]);

    $result = json_decode($tool->handle($request), true);

    expect($result['error'])->toBe('Only SELECT queries are permitted.');
});

test('query database tool blocks dangerous keywords', function () {
    $tool = new QueryDatabaseTool(['users' => ['searchable' => ['id']]]);

    $request = \Laravel\Ai\Tools\Request::fromArray([
        'sql'         => 'SELECT * FROM users; DROP TABLE users',
        'explanation' => 'malicious query',
    ]);

    $result = json_decode($tool->handle($request), true);

    expect($result)->toHaveKey('error');
});

test('query database tool executes valid select', function () {
    // This hits the real DB — uses sqlite in-memory for tests
    $tool = new QueryDatabaseTool(['users' => ['searchable' => ['id', 'name', 'email']]]);

    $request = \Laravel\Ai\Tools\Request::fromArray([
        'sql'         => 'SELECT COUNT(*) as total FROM users',
        'explanation' => 'Count all users',
    ]);

    $result = json_decode($tool->handle($request), true);

    expect($result['success'])->toBeTrue();
    expect($result)->toHaveKey('count');
    expect($result)->toHaveKey('rows');
});

test('agent returns structured response', function () {
    $agent    = app(QueryPilotAgent::class);
    $response = $agent->prompt('How many users?', provider: 'gemini');
    $result   = $response->structured();

    expect($result)->toHaveKey('answer');
    expect($result)->toHaveKey('sql');
    expect($result)->toHaveKey('count');
    expect($result['answer'])->toBeString();
});

test('api endpoint returns success response', function () {
    $response = $this->getJson('/api/agentis-test');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'result' => ['answer', 'sql', 'count', 'explanation'],
        ]);
});
