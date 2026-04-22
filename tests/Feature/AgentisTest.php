<?php

use Agentis\AgentisAgent;
use Agentis\Tools\QueryDatabaseTool;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─────────────────────────────────────────
// Helper
// ─────────────────────────────────────────

function tool(): QueryDatabaseTool
{
    return new QueryDatabaseTool([
        'users' => ['searchable' => ['id', 'name', 'email', 'created_at']],
    ]);
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
    expect(app(AgentisAgent::class))->toBeInstanceOf(AgentisAgent::class);
});

// ─────────────────────────────────────────
// Group 2: Agent setup
// ─────────────────────────────────────────

test('agent instructions contain required keywords', function () {
    $instructions = (string) app(AgentisAgent::class)->instructions();

    expect($instructions)
        ->toContain('database assistant')
        ->toContain('query_database');
});

test('agent has query database tool attached', function () {
    $tools = iterator_to_array(app(AgentisAgent::class)->tools());

    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(QueryDatabaseTool::class);
});

// ─────────────────────────────────────────
// Group 3: Tool safety — all call executeSql() directly
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
// Group 4: Tool executes real DB queries
// ─────────────────────────────────────────

test('tool executes valid SELECT count query', function () {
    \App\Models\User::factory(3)->create();

    $result = tool()->executeSql(
        'SELECT COUNT(*) as total FROM users',
        'Count all users'
    );

    expect($result['success'])->toBeTrue();
    expect($result['rows'][0]['total'])->toBe(3);
    expect($result['sql'])->toContain('SELECT');
    expect($result['explanation'])->toBe('Count all users');
});

test('tool returns all users', function () {
    \App\Models\User::factory(5)->create();

    $result = tool()->executeSql('SELECT id, name, email FROM users');

    expect($result['success'])->toBeTrue();
    expect($result['count'])->toBe(5);
    expect($result['rows'][0])->toHaveKeys(['id', 'name', 'email']);
});

test('tool returns sql and explanation in response', function () {
    $sql    = 'SELECT COUNT(*) as total FROM users';
    $result = tool()->executeSql($sql, 'Count all users');

    expect($result['sql'])->toBe($sql);
    expect($result['explanation'])->toBe('Count all users');
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

// ─────────────────────────────────────────
// Group 5: HTTP endpoint
// ─────────────────────────────────────────

test('api endpoint returns 200', function () {
    $this->mock(AgentisAgent::class, function ($mock) {
        $mock->shouldReceive('prompt')
            ->once()
            ->andReturn(new class {
                public function offsetGet($key): mixed
                {
                    return match ($key) {
                        'answer'      => 'There are 5 users.',
                        'sql'         => 'SELECT COUNT(*) FROM users',
                        'count'       => 5,
                        'explanation' => 'Counted all users.',
                        default       => null,
                    };
                }
                public function offsetExists($key): bool
                {
                    return true;
                }
            });
    });

    $this->getJson('/api/agentis-test')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});
