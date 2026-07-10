# Hotfix 09B - Release Gate Test Coverage and Audit Visibility

## Purpose

Hotfix 09B hardens the Client Release Gate introduced in Hotfix 09A. It adds automated tests around release approval behavior and improves cockpit audit visibility for recent client release approvals.

## Added coverage

The new `ClientReleaseGateTest` verifies that:

1. A project that passes every technical and commercial gate still requires explicit client release approval.
2. A current approval changes the gate status to `Ready for Client Release`.
3. Open engineering warnings block approval.
4. A changed quotation after approval makes the approval stale.
5. A BOQ grand price below material price blocks the release gate.

## UI improvement

The Project Cockpit now shows the latest release approval history under the Client Release Gate card. This gives reviewers a compact audit trail showing who approved, when it was approved, whether it was revoked, and any approval or revoke notes.

## Scope

This hotfix does not change pricing, BOQ quantity logic, report layout, or PDF rendering behavior. It only strengthens release-gate reliability and visibility.
