# Technical plan: {MODULE_NAME}

> Phase 3 · PLAN — Defines HOW it's built, verified against `research.md`.
> Every technical decision here must be traceable to the specification (spec.md) or to a research finding (research.md).

**Feature ID:** {NNN-slug}
**Based on:** spec.md, research.md

## 1. Technical summary
1-2 paragraphs: chosen architecture approach and why.

## 2. Technology stack (verified with real-time research)
| Component | Choice | Verified version | Source / justification |
|---|---|---|---|
| Language/Framework | {e.g. Python 3.12 + FastAPI} | {current stable version} | research.md #1 |
| Database | {e.g. PostgreSQL} | {version} | research.md #2 |
| Authentication | {e.g. JWT + refresh rotation} | — | research.md #3 |
| Messaging/queues | {if applicable} | | |
| Cache | {if applicable} | | |

⚠️ If any stack choice was NOT validated with real-time research, mark it explicitly:
`[UNVERIFIED — decision based on model knowledge, not research.md]`

## 3. Architecture
Description of components, layers (e.g. controller/service/repository), and how they communicate.
If it helps, describe the diagram in text (layers, end-to-end flow of a typical request).

## 4. Data model (physical schema)
```
{Entity}
- id: uuid (PK)
- field: type, constraints
- ...
```
Relationships, required indexes, and why (based on expected access patterns).

## 5. API contracts
For each endpoint derived from the spec's user stories:

### {Method} {route}
- **Related story:** US-1
- **Request:** {schema/body}
- **Response 200:** {schema}
- **Errors:** {codes and conditions}
- **Auth required:** yes/no, level

## 6. Proposed folder structure
```
src/
├── ...
tests/
├── ...
```

## 7. Testing strategy
- Unit tests: what's covered.
- Integration tests: what end-to-end flows are validated.
- Expected coverage threshold.

## 8. Security and compliance
How the spec's non-functional security requirements are addressed (input validation, rate
limiting, secrets handling, etc.), citing research.md where applicable.

## 9. Risks and open decisions
- **Risk:** {technical risk} → **Mitigation:** {action}
- **Pending decision:** {something requiring user confirmation before implementation}

## 10. Traceability
| Requirement (spec.md) | Covered by (section of this plan) |
|---|---|
| FR-1 | Section 5, endpoint X |
| NFR-Security | Section 8 |
