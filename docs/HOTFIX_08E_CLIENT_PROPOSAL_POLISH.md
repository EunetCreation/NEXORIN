# Hotfix 08E - Client Proposal Polish and Customer-Safe Output

## Purpose

Hotfix 08E polishes the client-facing proposal without changing the internal engineering report structure.

The patch keeps the project name exactly as stored in the database and does not change the financial page title text.

## Changes

1. Client proposal draft watermark is repositioned on the cover so it no longer collides with the footer area.
2. Client proposal now hides internal-heavy financial indicators from the default customer view:
   - NPV
   - ROI
   - LCOE
3. Client proposal now shows customer-safe value metrics:
   - Proposal Value
   - First-Year Saving
   - Simple Payback
   - Annual Energy
   - Release Status
4. Added a Why Hybrid section to explain that the selected configuration prioritizes backup reliability and resilience.
5. Included scope is now ordered in a more customer-friendly sequence:
   - PV Module
   - Mounting & Structure
   - Power Conversion
   - Battery / ESS
   - DC Electrical
   - AC Electrical
   - Monitoring
   - Grounding & Safety
   - Engineering
   - Testing & Commissioning
   - Legal & Permit
   - Logistics
6. Client scope pagination is tightened so the included scope does not create a nearly empty final scope page.
7. The old APAR item label is normalized to Portable Fire Extinguisher and Basic Safety Equipment Allowance in generated reports.

## Install

Extract the changed files into the project root and overwrite existing files.

Then run:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
```

Regenerate BOM/BOQ if you want the database row itself to use the new fire extinguisher label. The report builder also normalizes older stored rows during display.

## Test

1. Regenerate Technical Calculation.
2. Regenerate BOM/BOQ.
3. Apply Pricing.
4. Regenerate Financial Analysis.
5. Regenerate Quotation.
6. Generate Internal Engineering Report.
7. Generate Client Proposal PDF.
8. Confirm that the client proposal watermark does not overlap with the cover footer.
9. Confirm that NPV, ROI, and LCOE are not shown in the default client proposal summary.
10. Confirm that the Why Hybrid section appears.
11. Confirm that APAR no longer appears in the report output.
