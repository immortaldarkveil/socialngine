---
description: Code review and change workflow — engineering preferences and review process
---

# Engineering Preferences (Singular Truth)

These are non-negotiable preferences that guide ALL recommendations, code changes, and reviews:

- **DRY is important** — flag repetition aggressively.
- **Well-tested code is non-negotiable** — err on the side of too many tests, not too few.
- **"Engineered enough"** — not under-engineered (fragile, hacky) and not over-engineered (premature abstraction, unnecessary complexity).
- **Handle more edge cases, not fewer** — thoughtfulness > speed.
- **Bias toward explicit over clever.**

---

# Review Process

## BEFORE STARTING ANY REVIEW

Ask the user which mode they want:

> **1/ BIG CHANGE:** Work through interactively, one section at a time (Architecture → Code Quality → Tests → Performance) with at most **4 top issues** per section.
>
> **2/ SMALL CHANGE:** Work through interactively with **ONE question** per review section.

---

## For Each Review Stage

For each stage, output:
- Explanation of each issue found
- Pros and cons of each option
- **Opinionated recommendation** with reasoning mapped to engineering preferences above
- Then use `AskUserQuestion` before proceeding

**Numbering convention:**
- Issues are numbered: **Issue 1, Issue 2, ...**
- Options are lettered: **Option A, Option B, Option C, ...**
- Always make the recommended option the **1st option (A)**
- When asking for input, clearly label as **Issue NUMBER / Option LETTER**

---

## 1. Architecture Review

Evaluate:
- Overall system design and component boundaries
- Dependency graph and coupling concerns
- Data flow patterns and potential bottlenecks
- Scaling characteristics and single points of failure
- Security architecture (auth, data access, API boundaries)

---

## 2. Code Quality Review

Evaluate:
- Code organization and module structure
- DRY violations — be aggressive here
- Error handling patterns and missing edge cases (call these out explicitly)
- Technical debt hotspots
- Areas that are over-engineered or under-engineered

---

## 3. Test Review

Evaluate:
- Test coverage gaps (unit, integration, e2e)
- Test quality and assertion strength
- Missing edge case coverage — be thorough
- Untested failure modes and error paths

---

## 4. Performance Review

Evaluate:
- N+1 queries and database access patterns
- Memory-usage concerns
- Caching opportunities
- Slow or high-complexity code paths

---

## For Every Specific Issue Found

For every bug, code smell, design concern, or risk:

1. **Describe the problem concretely** — with file and line references.
2. **Present 2–3 options**, including "do nothing" where reasonable.
3. For each option, specify:
   - Implementation effort
   - Risk
   - Impact on other code
   - Maintenance burden
4. **Give the recommended option and why**, mapped to engineering preferences.
5. **Explicitly ask for agreement** before proceeding.

---

## Workflow Rules

- Do **not** assume priorities on timeline or scale.
- After each section, **pause and ask for feedback** before moving on.
- Review this plan thoroughly before making any code changes.
- For every issue, explain concrete tradeoffs, give an opinionated recommendation, and ask for input before assuming a direction.
