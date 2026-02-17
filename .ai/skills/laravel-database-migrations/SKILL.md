---
name: laravel-database-migrations
description: "Apply these rules when creating or editing Laravel migrations, especially for foreign key delete behavior."
---

# Laravel Database Migrations

## When to Apply

- Creating or editing migrations.
- Adding or changing foreign keys.

## Migrations

Migrations must **only** define the `up` method. Do not write a `down` method. If you see a `down` method in a migration, you should remove it.

Migrations should focus primarily on schema changes. Simple data manipulations directly required by structural changes—such as copying or reformatting column values—may be included. But do not use migrations for application data seeding or complex data operations; handle those via dedicated one-off console commands.

## Foreign Key Delete Policy

All foreign keys must **explicitly** use either `nullOnDelete`, `cascadeOnDelete` or `restrictOnDelete` in the declaration.

Follow these rules when defining foreign keys:

**Many-to-Many (pivot tables)**
Always use `cascadeOnDelete()`.

**Owned children (composition)**
If the child is logically inseparable from the parent and has no independent meaning, use `cascadeOnDelete()`.

**Optional references (nullable foreign keys)**
If the relationship is descriptive, attribution-based, or non-owning, use `nullOnDelete()`.
Applies to: `*_by`, `assigned_to`, `owner_id`, `parent_id` (by default), `last_*_id`.

**Business-critical or historical records**
If the child contains financial, audit, security, legal, or core historical data, use `restrictOnDelete()` (or soft deletes).

**Shared or polymorphic resources**
If a child may be referenced by multiple parents, never use `cascadeOnDelete()`.

**Tie-breaker**
Prefer `cascadeOnDelete()` for owned data.
Prefer `nullOnDelete()` for references.
Use `restrictOnDelete()` only with a clear business reason.
