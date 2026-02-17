# Agent Guidance

## Boost Documentation Boundary

- In this Laravel Boost project, keep project-specific agent guidance in `.ai/guidelines/**`.
- Do not add or modify project guidance in `AGENTS.md` or `CLAUDE.md`.
- Keep `README.md` user/operator-facing; keep agent-only implementation contract details in `.ai/guidelines/**`.

## Instruction Precedence (Non-Negotiable)

The generated `AGENTS.md` file concatenates multiple sources. Each section begins with a header like:

- `=== .ai/... rules ===` — project-specific rules
- `=== ... rules ===` — Boost presets / package rules

Resolve conflicts using this precedence hierarchy:

1. **Project rules win.** Any rule under `=== .ai/` is authoritative for this repository.
2. **Global user rules apply** unless overridden by project rules.
3. **Boost rules are defaults.** Follow them only when they do not conflict with (1) or (2).

If a Boost rule conflicts with a project or global rule, ignore only the conflicting part and apply the higher-priority rule.

## Agent Guidance Maintenance

- After meaningful implementation changes, review `.ai/guidelines/**` and update it when agent behavior or project constraints should change.
- Keep guidance changes general, reusable, and scoped to rules the agent should follow in future tasks.
