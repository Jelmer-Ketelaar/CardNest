# CardNest GitHub Project

The private [CardNest Development](https://github.com/users/Jelmer-Ketelaar/projects/7) GitHub Project is the single planning source for MVP delivery. It contains 12 parent epics and concrete, independently testable sub-issues ordered through M0–M8 milestones.

## Views and status flow

The project has a table view named `Backlog` and a board view named `Development Board`. The board uses the `Status` field in this exact order:

1. `Todo`
2. `In Progress`
3. `In Review`
4. `Done`

Every planned item starts in `Todo`. An issue moves to `In Progress` only when a contributor starts it, to `In Review` when its linked pull request is ready for review, and to `Done` once the work is merged/closed and verified.

## Project fields

| Field | Values |
| --- | --- |
| Priority | `P0`, `P1`, `P2`, `P3` |
| Size | `XS`, `S`, `M`, `L`, `XL` |
| Type | `Epic`, `Feature`, `Task`, `Bug`, `Documentation` |
| Module | `Foundation`, `Identity`, `Catalog`, `Collection`, `Storage`, `Binder`, `Wishlist`, `Marketplace`, `Cart`, `Order`, `Payment`, `Shipping`, `Review`, `Moderation` |
| Phase | `MVP`, `Phase 2`, `Future` |
| Target date | GitHub Project date field; leave blank until a delivery date is deliberately agreed |

All current backlog entries have `Phase=MVP`, explicit priority, size, type and module values, and no invented target date.

## Milestones

| Order | Milestone |
| ---: | --- |
| 1 | `M0 — Project setup` |
| 2 | `M1 — Accounts and authentication` |
| 3 | `M2 — Card catalog` |
| 4 | `M3 — Collection management` |
| 5 | `M4 — Storage and binder` |
| 6 | `M5 — Wishlist` |
| 7 | `M6 — Marketplace CRUD` |
| 8 | `M7 — Orders and fake checkout` |
| 9 | `M8 — MVP release` |

Milestones intentionally have no delivery dates. Dates are a product-management decision, not a planning-script default.

## Labels

Labels complement the Project fields for issue search and pull-request triage:

- Types: `type:epic`, `type:feature`, `type:task`, `type:bug`, `type:documentation`.
- Areas: `area:backend`, `area:frontend`, `area:database`, `area:infrastructure`, `area:security`, `area:testing`, `area:ux`.
- Modules: `module:identity`, `module:catalog`, `module:collection`, `module:storage`, `module:binder`, `module:wishlist`, `module:marketplace`, `module:cart`, `module:order`, `module:payment`, `module:shipping`, `module:review`, `module:moderation`.
- Priorities: `priority:p0`, `priority:p1`, `priority:p2`, `priority:p3`.
- Other: `status:blocked`, `good-first-issue`, `needs-discussion`.

Use one type label, at least one relevant area label, a module label where one exists, and one priority label. The Project field remains the canonical structured value when label and field ever disagree.

## Workflow automation and safe manual steps

GitHub Projects exposes built-in workflows in the web UI, but GitHub CLI and the public GraphQL schema do not offer a supported create/update mutation for all workflow rules. The project therefore uses only built-in rules and keeps any unavailable behavior explicit rather than relying on brittle browser automation.

In **Project settings → Workflows**, verify or configure these rules for `Jelmer-Ketelaar/CardNest`:

1. **Auto-add to project**: add new repository issues from `Jelmer-Ketelaar/CardNest` to this project. Use an issue-only filter so pull requests are not added as separate items.
2. **Item added to project**: set `Status` to `Todo`.
3. **Item closed**: set `Status` to `Done`.
4. **Pull request merged**: set the associated item to `Done`.
5. **Pull request linked to issue**: set the associated item to `In Review` only if the UI provides a stable rule for an open linked pull request.

If rule 5 is not available in the GitHub UI, move the item to `In Review` manually in the pull-request opening step of [development-workflow.md](development-workflow.md). Do not install a custom webhook or fragile external automation solely to emulate it.

After creating a new issue, confirm that it has entered the project with `Todo`. If it needs a priority, size, module or phase different from the default, set those fields before work begins. The existing MVP issues were added and populated explicitly so their metadata does not depend on an automation rule.

## Dependencies and epics

Each concrete issue is a GitHub sub-issue of exactly one parent epic. Blocking relationships are represented with GitHub issue dependencies where applicable, including framework → entity → migration/API → frontend, and listing → cart/payment → checkout/shipping flows. Keep these relationships current when splitting or sequencing work; avoid using an epic relationship as a substitute for a real prerequisite.
