# APP67G.3 Verification Report

Date: 20 July 2026

## Outcome

**34 of 34 Playwright browser scenarios passed in isolated Chromium shards.**

| Regression shard | Tests | Result | Runtime |
|---|---:|---:|---:|
| Boot contract | 2 | PASS | 4.9 s |
| Drafting core | 2 | PASS | 10.8 s |
| Circle methods | 4 | PASS | 11.1 s |
| Arc methods | 4 | PASS | 11.0 s |
| Save and persistence | 3 | PASS | 14.6 s |
| Ribbon, panels, and view | 6 | PASS | 14.4 s |
| Symbols and Inspector | 7 | PASS | 21.9 s |
| Typed engineering Wire | 3 | PASS | 14.0 s |
| Manage transformations | 3 | PASS | 19.4 s |
| **Total** | **34** | **PASS** | **122.1 s** |

## Build and syntax verification

| Check | Result |
|---|---:|
| Node syntax for config, runner, server, helpers, and all specs | PASS |
| PHP lint for deterministic Blade fixture | PASS |
| Playwright test discovery | PASS, 34 tests |
| Dedicated SLD production build | PASS, 266 modules |
| Full frontend build | PASS |
| Production SLD bundle output | PASS, 595.21 kB |

The SLD build reports the existing direct `eval` warning from `@maxgraph/core/lib/esm/internal/utils.js`. This warning originates in the dependency and does not fail the build.

## Clean patch installation verification

The patch archive was extracted over a fresh copy of `NEXORIN-APP(19).zip`. Node syntax checks, PHP fixture linting, the 266-module SLD build, and the boot regression shard all passed from that clean patched copy.

## Functional coverage

The suite verifies:

- real Blade editor boot and maxGraph initialization;
- stable source-node ports;
- line, rectangle, note, and associative Leader;
- all four Circle methods;
- all four Arc methods;
- command cancellation with Escape;
- save and reload serialization;
- layer visibility and print state;
- dirty-close confirmation;
- ribbon tabs and panel collapse states;
- Clean, Engineering, and Inspection modes;
- Model Space and A3 Paper Space;
- fullscreen, zoom, fit, and pan;
- electrical library search and placement;
- editable and controlled Inspector fields;
- copy, paste, duplicate, cut, undo, redo, lock, and delete;
- block selection and Ctrl+A;
- compatible typed Wire creation;
- incompatible DC-to-AC rejection;
- prevention of dangling Wire;
- cable metadata and RS485 formatting;
- route rebuild after equipment movement;
- alignment, distribution, grouping, stacking, rotation, and mirror operations.

## Defects corrected by the regression work

1. The A3 Paper Space controls existed in the interface but had no JavaScript click binding.
2. Associative Leader selection appeared as unsupported multi-selection in the Inspector.
3. Inspector refresh could overwrite the active field while the user was typing.
4. The editor had no deterministic ready state for browser automation.
5. The test runner used URL pathname conversion that could produce invalid Windows paths.
6. A stalled browser shard had no execution timeout or Windows process-tree cleanup.

## Environment note

The container uses the operating system Chromium binary. One long chained execution stalled after repeated Chromium launches. Every shard was rerun in a fresh Playwright process, and all 34 scenarios passed. The supplied runner now applies a 120-second timeout per shard and a short cooldown. The intended user runtime is the Playwright-managed Chromium already installed on Windows.
