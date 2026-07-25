# APP67J.2.3 · Explicit Incompatible PORT Feedback

## Purpose

APP67J.2.2 correctly limited engineering PORT OSNAP to Wire, but it removed incompatible ports from the Wire hit-test candidate list. A click on an incompatible canonical port therefore produced no rejection event and left the previous `SOURCE LOCKED` toast visible.

## Corrected contract

- Every canonical engineering port remains detectable by Wire.
- Valid ports keep normal cyan PORT feedback.
- Invalid ports use a compact red PORT marker and red engineering card.
- The card explains the exact reason, such as `DC is not compatible with AC`.
- Clicking the invalid port runs the normal connection validator.
- The attempt produces `CONNECTION REJECTED`.
- No dangling edge or connection record is created.
- When overlapping ports share the same coordinate, a valid candidate wins deterministically.
- PORT remains exclusive to Wire. Drafting tools still use geometric OSNAP only.

## Asset version

`67j6`
