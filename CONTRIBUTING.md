# Contributing

## Development

- Target PHP 8.1+ and Laravel 9 through 13.
- Keep public configuration keys and Artisan commands backward-compatible.
- Add focused tests for every behavior change, including queue and database edge cases.
- Run `php -l` on modified PHP files and `composer validate --no-check-publish` before opening a pull request.

## Pull Requests

- Keep each pull request scoped to one change.
- Update `README.md` and `CHANGELOG.md` for user-facing changes.
- Do not include unrelated formatting or generated dependency updates.
- Explain database-driver assumptions and any migration or upgrade impact.
