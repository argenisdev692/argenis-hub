# Specification: {MODULE_NAME}

> Phase 1 · SPECIFY — Defines WHAT is built and WHY. No technical stack here.

**Feature ID:** {NNN-slug}
**Date:** {date}
**Status:** Draft | In review | Approved

## 1. Summary
2-3 sentence description of what this backend module does and what problem it solves.

## 2. Motivation / Business context
Why this module is needed now. What happens if it doesn't exist.

## 3. Actors
- **{Actor 1}**: who interacts with this module and for what purpose.
- **{Actor 2}**: ...

## 4. User stories
Each story must be independent, prioritizable, and independently verifiable.

### US-1: {story title} (Priority: High/Medium/Low)
**As a** {actor}, **I want** {action}, **so that** {benefit}.

**Acceptance criteria:**
- [ ] Given {context}, when {action}, then {expected result}.
- [ ] Given {context}, when {action}, then {expected result}.

### US-2: {story title}
...

## 5. Functional requirements
- **FR-1**: The system MUST {observable behavior, no technology mentioned}.
- **FR-2**: The system MUST ...
- **FR-3**: The system MUST NOT ...

## 6. Non-functional requirements
- **Performance**: e.g. target p95 latency, expected throughput.
- **Security**: sensitive data involved, required authentication/authorization level.
- **Availability**: expected SLA, fault tolerance.
- **Scalability**: expected data/user volume over the next 6-12 months.
- **Compliance**: applicable regulations (GDPR, PCI-DSS, LGPD, etc.) if relevant.

## 7. Data entities (conceptual, not a physical schema)
- **{Entity A}**: key attributes and relationship to other entities.
- **{Entity B}**: ...

## 8. Out of scope
What this version of the module explicitly does NOT cover.

## 9. Assumptions and open decisions
- [NEEDS CLARIFICATION] {specific question blocking the design, if applicable}
- Assumption: {reasonable assumption taken to move forward}

## 10. Success criteria (measurable)
- {Target metric, e.g. "reduces checkout time by 30%"}
