# AGENT Instructions

These notes explain how to work with this repository and how to guide agents on using the package.

## How to use the package
- The package provides a mocked HTTP client built around Guzzle. Point people to `README.md` for install and basic examples, and to the docs in `docs/` for advanced routes (file responses, callbacks, consecutive calls, middlewares).
- Typical bootstrap in code samples:
  - Build a `HandlerBuilder` with PSR-17 factories and an optional logger.
  - Add routes with `RouteBuilder` using `withMethod`, `withPath`, and a `Response` or callback.
  - Build the client via `ClientBuilder` and use `$client->request(...)` in tests.
- Emphasize the "fail fast" approach: start with no routes and let failing tests suggest missing routes.
- When explaining Symfony usage, prefer the "inject<clientName>" helper pattern: create a private method that builds the mocked client (optionally loading JSON fixtures for complex responses) and registers it in the container via `self::getContainer()` with the same service id used in production. This keeps autowired services working unchanged and encourages tests that exercise real deserialization paths.

## Development workflow
- Run the unit test suite with `composer test` when practical; use `composer phpstan` and `composer cs` for static analysis and coding standards.
- Follow existing code style; avoid adding try/catch blocks around imports.

## Commits and PRs
- Use concise commit messages in the imperative mood (e.g., "Add AGENTS instructions").
- Summaries in PR descriptions should highlight user-visible changes and any testing performed.
