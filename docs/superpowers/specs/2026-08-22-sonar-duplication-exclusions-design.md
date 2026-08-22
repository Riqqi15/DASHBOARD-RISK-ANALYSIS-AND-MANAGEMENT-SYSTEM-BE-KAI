# Sonar Duplication Exclusions Design

## Objective

Reduce SonarCloud duplication on new code below the 3% quality-gate limit without hiding duplication in production application code.

## Scope

Add a root-level `.sonarcloud.properties` file for the repository's Automatic Analysis configuration. Set only `sonar.cpd.exclusions` for these non-production or structurally repetitive paths:

- `database/migrations/**/*`
- `database/seeders/**/*`
- `tests/**/*`

The exclusions apply only to copy-paste detection. SonarCloud continues analyzing these files for supported reliability, maintainability, and security rules. Production paths such as `app/**/*` and `resources/**/*` remain included in duplication analysis.

## Expected Result

The current 208 duplicated database lines and 83 duplicated test lines stop contributing to CPD. Based on the latest SonarCloud measures, new-code duplication should fall from 429 of 5,888 lines (7.29%) to approximately 138 of 5,888 lines (2.34%). SonarCloud will confirm the actual result after analyzing the pushed default-branch commit.

## Verification

1. Confirm `.sonarcloud.properties` contains only the intended CPD exclusions.
2. Confirm unrelated working-tree files are not committed.
3. Push the configuration to `main` to trigger Automatic Analysis.
4. Check the latest SonarCloud analysis revision and `new_duplicated_lines_density`.

## Rollback

Remove `.sonarcloud.properties` if the exclusion scope is rejected by project policy. Automatic Analysis remains enabled; only the repository-level additional configuration is removed.
