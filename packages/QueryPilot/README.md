# QueryPilot

AI-powered natural language search for Laravel applications. Connect your database, ask questions in plain English, and let AI handle the queries.

## Installation

```bash
composer require nayanraval/query-pilot
php artisan vendor:publish --tag=querypilot-config
```

## Quick Start

```php
use QueryPilot\Facades\QueryPilot;

$response = QueryPilot::prompt('Show me users who signed up this month');

return response()->json($response['rows']);
```

## Features

- 🤖 Natural language to SQL — no query writing needed
- 🔗 Auto-detects Eloquent relationships and builds JOINs
- 🖼️ Automatically resolves image URLs from storage
- ⚡ Query caching and performance tracking
- 🛡️ Built-in SQL injection protection
- 🎯 Works with Gemini, OpenAI, Anthropic, and more

## License

MIT

## Author
Name : Nayan Raval

Email: ravalnayan029@gmail.com

GitHub: https://github.com/NayanRaval00