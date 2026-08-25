# Tasks: {MODULE_NAME}

> Phase 4 · BREAK DOWN TASKS — Splits the plan into small, actionable, verifiable steps.
> Each task must be executable in isolation and leave the system in a functional or testable state.
> Mark tasks that can be done in parallel with `[P]` (no shared files/dependencies).

**Feature ID:** {NNN-slug}
**Based on:** plan.md

## Phase A — Foundations
- [ ] T001 Set up project folder structure and dependencies
- [ ] T002 [P] Set up database connection and base migrations
- [ ] T003 [P] Set up testing framework and local CI

## Phase B — Data model
- [ ] T004 Create entity {X} with its migration
- [ ] T005 [P] Create entity {Y} with its migration
- [ ] T006 Write unit tests for the data model

## Phase C — US-1: {story 1 title}
- [ ] T007 Implement repository layer for {X}
- [ ] T008 Implement service logic for {use case}
- [ ] T009 Implement endpoint {method route}
- [ ] T010 Integration tests for the endpoint (success + error cases)
- [ ] T011 Verify against US-1 acceptance criteria in spec.md

## Phase D — US-2: {story 2 title}
- [ ] T012 ...

## Phase E — Cross-cutting
- [ ] T0XX Authentication / authorization
- [ ] T0XX Input validation and consistent error handling
- [ ] T0XX Logging and observability
- [ ] T0XX Rate limiting / security hardening (per research.md)

## Phase F — Closeout
- [ ] T0XX Run full test suite and linters
- [ ] T0XX Traceability review: every FR/story in the spec has code + test
- [ ] T0XX API documentation (README / OpenAPI)

---
**Suggested commit convention:** `feat(module): T0XX short description`
