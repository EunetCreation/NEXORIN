# APP67I Electrical Rule Checking

## Purpose

APP67I adds deterministic electrical design-rule checking to the NEXORIN SLD workflow. It evaluates the drawing topology against the canonical APP67H symbol and port registry. The checker supports drafting review, controlled submission, and controlled export. It does not replace project-specific conductor calculations, protection coordination, manufacturer instructions, local regulations, or engineer approval.

## Architecture

The implementation uses one rule registry and two matching evaluators:

- `resources/data/sld-electrical-rules.json` defines severity, compatible networks, symbol profiles, and rule codes.
- `app/Services/Sld/SldElectricalRuleChecker.php` is the authoritative server evaluator.
- `resources/js/sld-electrical-rule-checker.js` provides immediate browser feedback while drafting.
- `SldGraphBuilder` evaluates newly generated revisions.
- `ProjectSldController` re-evaluates saved editor state, submission, approval, SVG export, and PDF export.
- The editor Checks panel lists issues and can locate the affected object or wire.

The saved validation schema is:

```text
nexorin.sld.electrical-validation.v1
```

The SLD graph schema is:

```text
nexorin-sld-v1.4.0
```

## Severity model

| Severity | Meaning | Workflow effect |
|---|---|---|
| Error | Invalid or unsafe represented topology | Blocks submission, approval, SVG, and PDF |
| Warning | Incomplete engineering representation | Save remains available. Engineer review is required |
| Notice | Missing supporting metadata | Does not block workflow |
| Info | Informational result | Does not block workflow |

## Implemented rules

| Code | Severity | Check |
|---|---|---|
| E001 | Error | Missing connection endpoint |
| E002 | Error | Unknown terminal reference |
| E003 | Error | Incompatible electrical networks |
| E004 | Error | Invalid terminal direction |
| E005 | Error | Terminal connection limit exceeded |
| E006 | Error or profile-defined | Required symbol port is not connected |
| E007 | Error | Duplicate equipment tag |
| E008 | Error | Unclassified electrical network |
| E009 | Error | Electrical self-loop |
| E010 | Error | PV source bypasses DC protection |
| E011 | Error | Unknown symbol outside the APP67H registry |
| E012 | Error | Inverter AC output bypasses AC protection |
| W001 | Warning | Protective or functional earth is missing |
| W002 | Warning | Manual power cable specification is incomplete |
| W003 | Warning | Duplicate terminal connection |
| W004 | Warning | Equipment tag is missing |
| W005 | Warning | Manual engineering object is isolated |
| W006 | Warning | Manual protection rating is incomplete |
| N001 | Notice | Cable voltage class is not recorded |

## Network separation

The checker distinguishes these engineering networks:

- DC positive
- DC negative
- generic DC power
- battery positive
- battery negative
- generic battery DC
- AC power
- neutral
- protective earth
- functional earth
- communication
- control
- measurement
- generic power
- unclassified

A valid connection requires registered endpoints, compatible port networks, compatible connection metadata, and an output-to-input or bidirectional direction contract.

## Editor workflow

The editor evaluates the current drawing after topology or relevant metadata changes. The user can also select **Run check** from the Manage ribbon.

The Checks panel provides:

- pass, review, or fail status;
- error, warning, notice, and total counts;
- severity filters;
- issue code, title, message, and recommendation;
- click-to-locate behavior;
- drawing highlights for error and warning objects.

Save remains available when errors exist so the user can preserve work. Controlled submission, approval, SVG export, and PDF export remain blocked until every error is resolved.

## Server authority

Browser results are advisory and immediate. The server always recalculates the graph before it accepts a controlled workflow action. Client-supplied validation data is not trusted.

Server validation runs during:

1. SLD generation;
2. editor save;
3. submission for review;
4. engineer approval;
5. SVG export;
6. PDF export.

The current status and error/warning counts are stamped into the controlled drawing title block.

## Validation commands

```powershell
npm run validate:sld-registry
npm run validate:sld-rules
npm run test:sld-rules:js
php scripts/test-sld-rules.php
npm run build:sld
```

Run APP67I browser regression on Windows:

```powershell
$env:NEXORIN_PHP_BINARY = "C:\Users\USER\.config\herd\bin\php84\php.exe"
$env:NEXORIN_SLD_E2E_ONLY = "electrical-rules"
npm run test:sld
Remove-Item Env:NEXORIN_SLD_E2E_ONLY
```

Expected APP67I result:

```text
4 passed
APP67I PASS · 4/4 tests · 1/1 suites
```

Run the complete SLD regression after the APP67I suite passes:

```powershell
npm run test:sld
```

Expected complete result:

```text
APP67I PASS · 41/41 tests · 13/13 suites
```

## Current limits

APP67I checks represented topology and required metadata. It does not yet calculate fault current, breaker selectivity, voltage drop, cable ampacity, MPPT operating envelopes, thermal derating, or project-specific grounding performance. Those checks require APP67K engineering-controller data and verified calculation inputs.
