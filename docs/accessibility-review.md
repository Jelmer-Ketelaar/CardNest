# MVP accessibility review

This is a baseline engineering review of the current CardNest MVP. It is not a WCAG conformance claim, accessibility
certification, VPAT, or legal approval. A public deployment must record its own review evidence and resolve blockers
for its target audience.

## Review scope and evidence

Reviewed on 2026-08-01 against the current Vue application and its automated test suite:

- registration and login;
- collection search, creation and editing;
- marketplace browsing, cart and checkout;
- buyer and seller order actions, collection import and review creation; and
- binder slot selection and its non-drag interaction.

The review used source inspection, existing Vitest coverage, and the Playwright MVP flows. It does not substitute for
testing with screen readers, zoom, high-contrast settings, real assistive-technology users, or a formal colour
contrast measurement.

## Baseline findings

| Area | Current evidence | Result |
| --- | --- | --- |
| Keyboard-operable controls | Actions use native buttons, links, inputs, selects and checkboxes. Binder placement has a click-to-select path in addition to drag-and-drop. | Baseline present |
| Labels and form semantics | `BaseInput` wraps a native input in a label. Larger forms use visible labels around native controls. Required values use native `required` where applicable. | Baseline present |
| Validation and errors | Field validation uses `aria-invalid` and `aria-describedby`; shared request failures use `ErrorMessage` with `role="alert"`. | Baseline present |
| Loading and success feedback | Loading and success messages use `role="status"` in the reviewed pages. | Baseline present |
| Page structure | Pages use headings, named sections and labelled pagination/navigation where the interface needs them. | Baseline present |
| Modal dialogs | Existing dialogs declare `role="dialog"`, `aria-modal="true"`, and an accessible name, but focus is not consistently moved into, trapped within, or restored after a dialog. | Follow-up required: [#198](https://github.com/Jelmer-Ketelaar/CardNest/issues/198) |
| Visual contrast | The source has no automated contrast measurement or documented human contrast audit. | Release gate: verify against the deployed theme |
| Screen-reader and zoom behaviour | No assistive-technology or 200% zoom session has been recorded. | Release gate: record manual evidence |

## Required release checks

Before presenting the MVP to external users, record the result of each check in the release record:

- [ ] Navigate registration, login, collection creation, checkout, shipping confirmation and review creation with only a keyboard.
- [ ] Confirm focus visibility, logical tab order, and a visible focus target after route changes and failed submissions.
- [ ] Confirm each modal meets the focus-management acceptance criteria in [#198](https://github.com/Jelmer-Ketelaar/CardNest/issues/198), or explicitly record a release decision that excludes modal workflows.
- [ ] Check text, controls, errors, focus indicators and status badges against the deployed colour palette using a contrast tool.
- [ ] Test the same key flows at 200% browser zoom and with a narrow viewport.
- [ ] Run a screen-reader smoke test for headings, form labels, errors, dynamic status messages and dialog names.
- [ ] Add follow-up issues for every finding that changes the intended MVP experience; do not silently treat a failed check as passed.

## Current decision boundary

The existing controls provide a usable semantic and keyboard baseline for internal MVP validation. They are not enough
to claim public accessibility conformance while modal focus management, contrast evidence and assistive-technology
smoke testing remain outstanding. The [release checklist](release-checklist.md) treats those as explicit release
inputs rather than automatically granting approval.
