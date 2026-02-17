# Instruction Precedence (Non-Negotiable)

The generated `AGENTS.md` file concatenates multiple sources. Each section begins with a header like:

- `=== .ai/... rules ===` — project-specific rules
- `=== ... rules ===` — Boost presets / package rules

Resolve conflicts using this precedence hierarchy:

1. **Project rules win.** Any rule under `=== .ai/` is authoritative for this repository.
2. **Global user rules apply** unless overridden by project rules.
3. **Boost rules are defaults.** Follow them only when they do not conflict with (1) or (2).

If a Boost rule conflicts with a project or global rule, ignore only the conflicting part and apply the higher-priority rule.
