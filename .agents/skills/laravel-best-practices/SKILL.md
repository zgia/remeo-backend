---
name: laravel-best-practices
description: Use this skill when Laravel-specific framework guidance is needed, especially for Eloquent and query performance, caching, queues, HTTP clients, migrations, routing, validation, testing, security, events, notifications, collections, and other framework-level concerns. Do not activate for ordinary Laravel/PHP changes when project conventions and Rules are sufficient.
license: MIT
metadata:
  author: laravel
---

# Laravel Best Practices

Use this Skill as a **Laravel framework knowledge base**, not as the project's governing policy.

Project-specific conventions are defined by `AGENTS.md` and `.agents/rules/`. Existing codebase patterns, database schema, tests, and business documentation are the source of truth for project behavior.

## When to Use

Use this Skill when the task requires Laravel-specific technical guidance, especially for:

* Eloquent and relationship design
* Query performance and N+1 investigation
* Advanced query patterns
* Caching
* Queue and Job behavior
* HTTP Client
* Routing and Controller patterns
* Validation and Form Requests
* Events, Notifications, and Mail
* Exception and error handling
* Scheduling
* Migrations
* Collections
* Blade and Views
* Laravel security
* Laravel configuration
* Framework-level architecture

Do **not** load or apply this Skill merely because a task happens to contain PHP or Laravel code.

## Priority

When guidance conflicts, use this priority:

1. Explicit user instruction
2. Project-specific `.agents/rules/`
3. Existing project code and established patterns
4. Database schema, tests, and business documentation
5. This Skill
6. General Laravel conventions or personal preference

This Skill provides defaults and framework knowledge. It does not override project policy.

## Consistency First

Before applying a Laravel recommendation, inspect the existing codebase.

Laravel often provides multiple valid approaches. Prefer the approach already established by the project unless there is a concrete reason to change it.

Do not introduce a second implementation pattern merely because this Skill describes another valid approach.

When an existing pattern appears problematic, distinguish between:

* a deliberate project convention;
* an intentional exception;
* historical technical debt;
* an opportunity for improvement.

Do not silently rewrite project conventions.

## Progressive Loading

Do not treat the entire `rules/` directory as required context.

Load only the rules relevant to the current task.

Examples:

* Query performance → `db-performance.md`, optionally `advanced-queries.md`
* Eloquent relationships → `eloquent.md`
* Cache → `caching.md`
* Queue / Job → `queue-jobs.md`
* HTTP integration → `http-client.md`
* Migration → `migrations.md`
* Validation → `validation.md`
* Testing → `testing.md`
* Security → `security.md`
* Routing / Controller → `routing.md`
* Events / Notifications → `events-notifications.md`
* Mail → `mail.md`
* Error handling → `error-handling.md`
* Scheduling → `scheduling.md`
* Collections → `collections.md`
* Configuration → `config.md`
* Blade / View → `blade-views.md`
* General architecture → `architecture.md`
* General style → `style.md`

Multiple rules may be loaded when a task crosses several areas.

## Documentation

For Laravel framework APIs whose exact syntax or behavior may vary by installed version, verify against the current Laravel documentation rather than relying on memory.

Do not guess framework APIs.

## How to Apply

1. Identify the Laravel-specific technical problem.
2. Check the project's existing implementation pattern.
3. Load only the relevant rule files from `rules/`.
4. Compare the recommendation against project-specific Rules.
5. Follow the project's established pattern when applicable.
6. Use current Laravel documentation when exact API behavior matters.
7. Apply the smallest change that solves the actual problem.

## What This Skill Does Not Define

Do not use this Skill as the source of truth for:

* Tenant ownership rules
* Authorization semantics specific to this project
* Project-specific API error conventions
* Project-specific Enum conventions
* Project-specific storage paths
* Project-specific database conventions
* Project-specific localization
* Business state machines
* Business terminology
* Internal architecture decisions
* Deployment or operational policy

Those belong to the project Rules and project source of truth.

## Examples

### Simple Laravel change

A Controller needs a small modification.

Use:

1. `neo-laravel`
2. applicable project Rules

Do not automatically load this Skill.

### Query performance investigation

A page performs hundreds of queries.

Use:

1. `neo-laravel`
2. tenancy / architecture Rules as applicable
3. `db-performance.md`
4. `advanced-queries.md` if needed

### Queue concurrency problem

A Job may be processed more than once.

Use:

1. `neo-laravel`
2. project concurrency Rule
3. `queue-jobs.md`

Project-specific behavior remains authoritative.

### Laravel API question

A task depends on the exact behavior of a Laravel API.

Use the relevant rule in this Skill and verify the installed Laravel version's documentation before implementation.
