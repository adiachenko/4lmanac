# Laravel Guidelines

- Use `laravel-database-migrations` skill for migration rules and foreign key delete policy.
- Seeders should be simple and deterministic. Seed only the initial data required for the application to function. Avoid seeders that append, upsert, or contain complex logic.
- When using polymorphic associations you MUST define a morph map in the boot method of the service provider to avoid hardcoded class names in the database.
- For form request validation, always specify multiple rules for a field using array syntax rather than the `|`-delimited string notation.
- Models are unguarded by default. Pass only validated data or an explicit allowlist of fields to `create()`, `update()`, or `fill()`—never raw or unvalidated request input.
- Do not use the `config()` helper within configuration files.
- Define route handlers with controllers (prefer single-action controllers) and do not use inline route callbacks.
