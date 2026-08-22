# Sonar Duplication Exclusions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Exclude migrations, seeders, and tests from SonarCloud copy-paste detection while retaining duplication analysis for production code.

**Architecture:** Add the Automatic Analysis configuration file `.sonarcloud.properties` at the repository root. Configure only `sonar.cpd.exclusions`, so excluded files remain available to every other supported analyzer.

**Tech Stack:** SonarQube Cloud Automatic Analysis, GitHub, Laravel repository paths.

---

### Task 1: Configure CPD exclusions

**Files:**
- Create: `.sonarcloud.properties`

- [ ] **Step 1: Confirm no Automatic Analysis configuration exists**

Run:

```powershell
Test-Path .sonarcloud.properties
```

Expected: `False`.

- [ ] **Step 2: Add the minimal configuration**

Create `.sonarcloud.properties` with exactly:

```properties
# Exclude structurally repetitive non-production code from copy-paste detection only.
sonar.cpd.exclusions=database/migrations/**/*,database/seeders/**/*,tests/**/*
```

- [ ] **Step 3: Verify the configuration and working-tree scope**

Run:

```powershell
Get-Content .sonarcloud.properties
git diff --check
git status --short
```

Expected: the property contains only the three approved paths; no whitespace errors; unrelated Playwright changes remain unstaged.

- [ ] **Step 4: Commit the configuration**

```bash
git add .sonarcloud.properties docs/superpowers/plans/2026-08-22-sonar-duplication-exclusions.md
git commit -m "Configure Sonar duplication exclusions"
```

Expected: a commit containing only the configuration and this plan.

- [ ] **Step 5: Push and verify SonarCloud**

Run:

```bash
git push origin main
```

Expected: Automatic Analysis processes the new revision. Query `new_duplicated_lines_density`; target is below 3%, with `app/**/*` and `resources/**/*` still included in CPD.
