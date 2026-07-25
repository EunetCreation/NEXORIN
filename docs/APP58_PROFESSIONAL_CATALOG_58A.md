# NEXORIN APP58 Professional Catalog 58A

## Purpose

APP58 58A converts Material Catalog from a small demo registry into a governed professional catalog foundation. It retains the locked 42A visual language and does not redesign other NEXORIN modules.

## Delivered scope

- 15 professional root catalog domains and 70 technical subcategories.
- Backward-compatible migration of legacy catalog roots.
- Dynamic technical requirement profiles per catalog domain.
- Manual normalized specification intake for values not yet extracted by the parser.
- Certificate intake, verification, expiry tracking, archive, and audit identity.
- Supporting-document intake for warranties, manuals, protocol maps, drawings, type tests, supplier quotes, service scopes, and other governed evidence.
- Secure evidence source access through company-scoped routes.
- Professional catalog readiness evaluator covering identity, required specifications, parsed datasheet, certificates, supporting documents, expiry, and price reference.
- Governance invalidation when approved evidence or normalized specifications change.
- Directory filters for governance and evidence coverage.
- Domain portfolio board, catalog health indicators, evidence counters, and responsive 42A-aligned UI.

## Catalog domains

1. PV Module
2. On-grid Inverter
3. Hybrid & Off-grid Inverter
4. Battery & ESS
5. DC Electrical
6. AC Electrical
7. Mounting & Structure
8. Monitoring, Metering & Communication
9. Grounding, Lightning & Safety
10. Panel & Distribution
11. Civil & Site Work
12. Fire & HSE
13. Legal, Certification & Compliance
14. Logistics & O&M
15. Supplier Quote & Pricelist

## Installation

Apply the patch over `NEXORIN-APP(58)`, then run:

```bash
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=ProfessionalCatalogTaxonomySeeder
npm ci
npm run build
```

The taxonomy seeder is idempotent. It preserves records and migrates known legacy catalog roots into the active professional taxonomy.

## Governance behavior

- Global reference materials remain read-only for company users.
- Company-specific materials can receive specifications and evidence.
- New or changed evidence moves an approved material back to `needs_review`.
- Expired active evidence blocks approval readiness.
- A positive price-book reference improves commercial readiness, but is not required for technical catalog approval.
- Evidence files remain on private storage and are served through permission-protected routes.

## Deliberate boundaries

This patch does not claim to deliver the final industrial parser, smart product selection, roof map designer, or SLD generator.

Planned continuation:

- APP59: page-aware PDF extraction, OCR fallback, table extraction, provenance, unit normalization, and review workspace.
- APP60: product compatibility, ranking, scenario selection, stock/lead-time/warranty scoring, and explainable component recommendations.
- APP61: roof map, polygons, obstruction/setback drawing, module packing, string and MPPT assignment.
- APP62: editable SLD generation with ratings, tags, revision, approval, SVG, and PDF output.
