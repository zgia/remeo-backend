---
name: ai-sdk-development
description: Use when developing with Laravel's official AI SDK (`laravel/ai`). Trigger when the task uses `Laravel\\Ai\\`, `laravel/ai`, Laravel AI SDK Agents, Tools, structured output, conversations, streaming, images, audio, transcription, embeddings, reranking, vector stores, files, provider failover, or AI SDK testing. Also trigger for this project's AI features when they are implemented with Laravel AI SDK. Do not trigger for unrelated AI libraries or generic AI application design.
license: MIT
metadata:
  author: laravel
---

# Developing with the Laravel AI SDK

This Skill is the specialized knowledge base for Laravel's official AI SDK (`laravel/ai`).

It covers the SDK's APIs, capabilities, provider integrations, testing, and implementation patterns.

Project-specific Rules and existing project code remain authoritative for application architecture, tenancy, authorization, storage, error handling, queues, database behavior, and other project concerns.

## Activation

Use this Skill when the task involves:

* `laravel/ai`
* `Laravel\Ai\`
* Laravel AI SDK Agents
* Promptable
* Tools
* Provider Tools
* Structured Output
* Conversations
* Streaming
* Broadcasting
* Queueing through the AI SDK
* Images
* Audio / TTS
* Transcription / STT
* Embeddings
* Reranking
* Files
* Vector Stores
* Provider failover
* AI SDK fakes and testing

Also use it when modifying this project's AI features if those features are implemented using Laravel AI SDK.

Do not use this Skill for:

* OpenAI SDK used directly
* Anthropic SDK used directly
* Other AI SDKs
* Generic LLM architecture questions
* Prompt engineering unrelated to Laravel AI SDK
* Generic Laravel queue/cache/database questions

For mixed tasks, combine this Skill with the relevant project Rules and other Skills.

## Source of Truth

The Laravel AI SDK is a rapidly evolving package.

**Always verify the current documentation before implementing or changing AI SDK APIs. Never guess the API.**

Documentation is the source of truth for:

* Method signatures
* Interfaces
* Traits
* Attributes
* Provider capabilities
* Configuration
* Testing APIs
* Supported features
* SDK behavior

Existing project code is the source of truth for how the project integrates the SDK.

When documentation and existing project code differ, determine whether the code uses:

* the currently installed SDK version;
* a project-specific wrapper;
* legacy API;
* or outdated implementation.

Do not silently migrate existing code to a newer API unless requested.

## Progressive Loading

Treat this Skill as a reference guide, not as mandatory context to load in full.

For a focused task:

1. Identify the SDK capability involved.
2. Read the relevant section of this Skill.
3. Verify the current SDK documentation.
4. Read project-specific Rules and existing implementation.
5. Load `laravel-best-practices` only when the task also involves general Laravel concerns.

## Decision Workflow

Before implementation, identify the SDK capability involved.

* Text generation or chat → Agent + `Promptable`
* Conversation history → `Conversational` or `RemembersConversations`
* Structured output → `HasStructuredOutput`
* Image generation → `Image`
* Audio synthesis → `Audio`
* Transcription → `Transcription`
* Embeddings → `Embeddings`
* Reranking → `Reranking`
* Files → `Document`
* Vector stores → `Stores`

Then verify the current documentation for the installed package version.

## Project Integration

When the AI SDK is used inside the Neo application:

1. Follow `AGENTS.md`.
2. Load applicable project Rules.
3. Use this Skill for Laravel AI SDK-specific knowledge.
4. Inspect existing AI SDK integration before introducing new patterns.
5. Reuse existing Agents, Tools, wrappers, configuration, and provider abstractions where appropriate.
6. Keep tenant, authorization, storage, queue, transaction, and error-handling behavior consistent with project Rules.
7. Do not treat AI SDK examples as project architecture.

The SDK's examples demonstrate framework usage. They do not define how this application should structure its business logic.

## Provider Handling

Provider capabilities are not interchangeable.

Before using a provider-specific capability:

1. Check current documentation.
2. Verify the provider supports the requested capability.
3. Check the application's configured providers/models.
4. Handle unsupported capabilities explicitly.

Prefer the SDK's provider enum or current SDK abstraction over hardcoded provider strings when the installed version supports it.

## Testing

Use the SDK's fake and assertion facilities for tests involving external AI behavior.

Examples include:

* Agent fakes
* Prompt assertions
* Image fakes
* Audio fakes
* Transcription fakes
* Embedding fakes
* Reranking fakes
* File fakes
* Vector store fakes

Combine SDK fakes with the project's testing Rules.

Do not make real external AI requests in normal automated tests unless the test explicitly requires an integration test.

## Common Failure Modes

Pay particular attention to:

* Incorrect `Laravel\Ai\` namespace
* Using an API from a different SDK or version
* Assuming provider capabilities are identical
* Guessing method names or parameters
* Mixing SDK examples from different versions
* Bypassing project wrappers or abstractions
* Making real provider requests from unit/feature tests
* Ignoring project tenancy or authorization rules

## Relationship With Other Skills

### `neo-laravel`

Use `neo-laravel` for:

* Project Laravel workflow
* Project Rules
* Codebase conventions
* Testing workflow
* Project architecture
* Verification

Use this Skill for:

* Laravel AI SDK APIs
* SDK capabilities
* Provider integration
* SDK testing
* SDK-specific implementation details

### `laravel-best-practices`

Use `laravel-best-practices` only when the task also requires general Laravel framework guidance.

For example:

* AI Agent + queue design → this Skill + `queue-jobs.md`
* AI response caching → this Skill + `caching.md`
* AI tool persistence with Eloquent → this Skill + `eloquent.md`
* AI endpoint validation → this Skill + `validation.md`

Project Rules always take precedence.

## Verification

For AI SDK changes:

1. Verify the relevant SDK documentation.
2. Run project formatting / static analysis required by `AGENTS.md`.
3. Run relevant tests.
4. Verify AI SDK calls are faked in normal automated tests.
5. Review the final diff for unintended SDK/API changes.

Do not commit, push, create PRs, or modify real external AI resources unless explicitly requested.
