# Hotfix 08C - Report Mode and Release Readiness

## Purpose

Hotfix 08C improves the premium engineering report after the master material catalog and scope chargeability controls were introduced.

The goal is to keep the report English-first, clearer for internal review, and safer for future client release workflows.

## Changes

1. Replaced the cover label `Report Status` with `Release Status`.
2. Added `release_status` and `report_mode` metadata in the premium report data builder.
3. Renamed the technical hero label from `Status Engineering` to `Calculation Status`.
4. Added a commercial review note when the current design has weak financial return, especially hybrid systems where backup reliability is the main objective.
5. Expanded the scope counters shown in the financial summary and detailed BOM/BOQ page:
   - Base BOQ
   - Catalog Candidates
   - Optional Add-ons
   - Summary Cost
   - Utility Scope
   - Not Applicable
6. Reduced scope-table repetition by creating a combined scope badge, for example:
   - Required · Base BOQ
   - Catalog Candidate · Requires Selection
   - Optional · Add-on
7. Added CSS for commercial review notes, wider scope counters, and compact scope badges.

## Expected PDF behavior

The premium PDF should still show all internal controlled-scope rows, but it should make the difference between base price, catalog candidates, optional add-ons, utility scope, and not-applicable rows easier to read.

The report remains an internal engineering report until the release gate marks it ready for client release.
