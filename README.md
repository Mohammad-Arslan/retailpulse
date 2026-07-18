<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## RetailPulse Documentation

RetailPulse is a multi-branch retail ERP built on this Laravel base. Key docs:

- [docs/srs.md](docs/srs.md) — full requirements specification
- [docs/phases/](docs/phases/README.md) — phase-by-phase delivery roadmap
- [docs/architecture/](docs/architecture/README.md) — **authoritative architecture decision records.** Read this before making any architectural change (new modules, tenancy, events, API surface, frontend patterns) — it takes precedence over ad hoc implementation choices.
- [docs/implementation-status.md](docs/implementation-status.md) — current build status by phase
- `CLAUDE.md` (repo root) — command reference and condensed architecture summary for AI coding agents

## Local AI (Ollama)

RetailPulse can call a **local** Ollama model through the Laravel AI SDK (`laravel/ai`). The browser never talks to Ollama directly — only the Laravel backend does.

### Prerequisites

1. Install [Ollama](https://ollama.com/) for Windows.
2. Pull and run the model:

```bash
ollama pull qwen2.5-coder:7b
ollama run qwen2.5-coder:7b
```

3. Confirm Ollama is reachable at `http://127.0.0.1:11434`.

### Configure Laravel

In `.env` (do **not** commit secrets):

```ini
APP_ENV=local
AI_PROVIDER=ollama
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=qwen2.5-coder:7b
```

Start the app as usual (Laragon / `composer dev` / `php artisan serve`).

### Test endpoint (local only)

`POST /api/dev/ai/ask` is registered with middleware that returns **404** unless `APP_ENV=local`.

**PowerShell:**

```powershell
Invoke-RestMethod -Method Post -Uri "http://retailpulse.test/api/dev/ai/ask" `
  -ContentType "application/json" `
  -Body '{"prompt":"Explain Laravel service container in simple words"}'
```

**curl:**

```bash
curl -X POST http://retailpulse.test/api/dev/ai/ask \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"prompt\":\"Explain Laravel service container in simple words\"}"
```

Expected shape:

```json
{
  "success": true,
  "answer": "..."
}
```

### Troubleshooting

| Symptom | Likely cause | Fix |
|--------|--------------|-----|
| Connection refused / “Could not connect…” | Ollama not running | Start Ollama; verify `http://127.0.0.1:11434` |
| Model not found | Model not pulled | `ollama pull qwen2.5-coder:7b` then match `OLLAMA_MODEL` |
| 404 on `/api/dev/ai/ask` | Not local | Set `APP_ENV=local` (endpoint is blocked in staging/production) |
| Timeouts on first request | Cold model load | Retry; first generate can be slow on CPU |

Switch providers later by changing `AI_PROVIDER` (and provider credentials) in `.env` — no code change required for the default text provider used by `LocalAiService`.
