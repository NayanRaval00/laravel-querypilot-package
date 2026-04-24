<?php

use QueryPilot\QueryPilotAgent;
use QueryPilot\Tools\QueryDatabaseTool;
use Laravel\Ai\Responses\StructuredAgentResponse;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─────────────────────────────────────────
// Helper — fresh tool instance per test
// ─────────────────────────────────────────

function tool(): QueryDatabaseTool
{
    return new QueryDatabaseTool(
        tables: ['users' => ['searchable' => ['id', 'name', 'email', 'created_at']]],
        maxRows: 100,
        cacheTtl: 0,
    );
}

// ─────────────────────────────────────────
// Group 1: Package bootstrap
// ─────────────────────────────────────────

test('agentis service provider is loaded', function () {
    $providers = array_keys(app()->getLoadedProviders());
    expect($providers)->toContain('Agentis\Providers\AgentisServiceProvider');
});

test('agentis config is loaded', function () {
    expect(config('agentis'))->toBeArray();
    expect(config('agentis.tables'))->toBeArray();
    expect(config('agentis.provider'))->toBeString();
});

test('agent is resolved from container', function () {
    expect(app(QueryPilotAgent::class))->toBeInstanceOf(QueryPilotAgent::class);
});

// ─────────────────────────────────────────
// Group 2: Agent setup
// ─────────────────────────────────────────

test('agent instructions contain required keywords', function () {
    $instructions = (string) app(QueryPilotAgent::class)->instructions();

    expect($instructions)
        ->toContain('database assistant')
        ->toContain('query_database');
});

test('agent has query database tool attached', function () {
    $tools = iterator_to_array(app(QueryPilotAgent::class)->tools());

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(QueryDatabaseTool::class);
});

// ─────────────────────────────────────────
// Group 3: Tool safety
// ─────────────────────────────────────────

test('tool blocks DELETE query', function () {
    $result = tool()->executeSql('DELETE FROM users');

    expect($result)->toHaveKey('error');
    expect($result['error'])->toContain('SELECT');
});

test('tool blocks DROP query', function () {
    $result = tool()->executeSql('DROP TABLE users');

    expect($result)->toHaveKey('error');
});

test('tool blocks UPDATE query', function () {
    $result = tool()->executeSql('UPDATE users SET name = "hacked"');

    expect($result)->toHaveKey('error');
});

test('tool blocks INSERT query', function () {
    $result = tool()->executeSql('INSERT INTO users (name) VALUES ("hacker")');

    expect($result)->toHaveKey('error');
});

test('tool blocks TRUNCATE query', function () {
    $result = tool()->executeSql('TRUNCATE TABLE users');

    expect($result)->toHaveKey('error');
});

test('tool blocks ALTER query', function () {
    $result = tool()->executeSql('ALTER TABLE users ADD COLUMN hacked VARCHAR(255)');

    expect($result)->toHaveKey('error');
});

// ─────────────────────────────────────────
// Group 4: Tool executes real DB queries — dynamic counts
// ─────────────────────────────────────────

test('tool executes valid SELECT count query', function () {
    $created = \App\Models\User::factory(3)->create();

    $result = tool()->executeSql(
        'SELECT COUNT(*) as total FROM users',
        'Count all users'
    );

    expect($result['success'])->toBeTrue();
    expect($result['rows'][0]['total'])->toBe($created->count()); // dynamic ✅
    expect($result['sql'])->toContain('SELECT');
    expect($result['explanation'])->toBe('Count all users');
});

test('tool returns all users dynamically', function () {
    $count   = rand(2, 8);
    $created = \App\Models\User::factory($count)->create();

    $result = tool()->executeSql('SELECT id, name, email FROM users');

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe($created->count()); // dynamic ✅
    expect($result['rows'][0])->toHaveKeys(['id', 'name', 'email']);
});

test('tool auto appends LIMIT when missing', function () {
    $result = tool()->executeSql('SELECT id FROM users');

    // LIMIT is auto-added — sql in response should contain it
    expect($result['sql'])->toContain('LIMIT');
});

test('tool preserves existing LIMIT in query', function () {
    $result = tool()->executeSql('SELECT id FROM users LIMIT 5');

    // Should NOT double-add LIMIT
    $limitCount = substr_count(strtoupper($result['sql']), 'LIMIT');
    expect($limitCount)->toBe(1);
});

test('tool returns explanation in response', function () {
    $explanation = 'Count all users in the system';
    $result      = tool()->executeSql(
        'SELECT COUNT(*) as total FROM users',
        $explanation
    );

    // SQL will have LIMIT appended — check explanation separately
    expect($result['explanation'])->toBe($explanation); // ✅ dynamic
    expect($result['success'])->toBeTrue();
});

test('tool returns sql that starts with SELECT', function () {
    $result = tool()->executeSql('SELECT COUNT(*) as total FROM users');

    expect($result['sql'])->toStartWith('SELECT');
});

test('tool handles invalid table gracefully', function () {
    $result = tool()->executeSql('SELECT * FROM nonexistent_table_xyz');

    expect($result)->toHaveKey('error');
});

test('tool returns empty rows for empty table', function () {
    $result = tool()->executeSql('SELECT * FROM users');

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe(0);
    expect($result['rows'])->toBeArray()->toBeEmpty();
});

test('tool includes duration_ms in response', function () {
    $result = tool()->executeSql('SELECT COUNT(*) as total FROM users');

    expect($result)->toHaveKey('duration_ms');
    expect($result['duration_ms'])->toBeFloat()->toBeGreaterThanOrEqual(0);
});

test('tool response has all required keys', function () {
    $result = tool()->executeSql('SELECT COUNT(*) as total FROM users', 'test');

    expect($result)->toHaveKeys(['success', 'count', 'rows', 'sql', 'explanation', 'duration_ms', 'cached']);
});

// ─────────────────────────────────────────
// Group 5: HTTP endpoint — using real AgentResponse
// ─────────────────────────────────────────

test('api endpoint returns 200 with mocked agent', function () {

    // Build a real StructuredAgentResponse using Mockery
    $fakeResponse = Mockery::mock(StructuredAgentResponse::class);
    $fakeResponse->shouldReceive('offsetGet')
        ->andReturnUsing(fn($key) => match ($key) {
            'answer'      => 'There are 5 users.',
            'sql'         => 'SELECT COUNT(*) FROM users LIMIT 100',
            'count'       => 5,
            'explanation' => 'Counted all users.',
            default       => null,
        });
    $fakeResponse->shouldReceive('offsetExists')->andReturn(true);

    // Mock the agent to return the fake response
    $this->mock(QueryPilotAgent::class, function ($mock) use ($fakeResponse) {
        $mock->shouldReceive('prompt')
            ->once()
            ->andReturn($fakeResponse);
    });

    $this->getJson('/api/agentis-test')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'answer',
            'sql',
            'count',
            'explanation',
        ]);
});

test('api endpoint returns correct data structure', function () {
    $fakeResponse = Mockery::mock(StructuredAgentResponse::class);
    $fakeResponse->shouldReceive('offsetGet')
        ->andReturnUsing(fn($key) => match ($key) {
            'answer'      => 'Found 3 products.',
            'sql'         => 'SELECT * FROM products LIMIT 100',
            'count'       => 3,
            'explanation' => 'Fetched products.',
            default       => null,
        });
    $fakeResponse->shouldReceive('offsetExists')->andReturn(true);

    $this->mock(
        QueryPilotAgent::class,
        fn($mock) => $mock
            ->shouldReceive('prompt')
            ->once()
            ->andReturn($fakeResponse)
    );

    $response = $this->getJson('/api/agentis-test');

    $response->assertStatus(200)
        ->assertJsonPath('answer', 'Found 3 products.')
        ->assertJsonPath('count', 3);
});
