# Development workflow

This workflow applies to every CardNest implementation issue. The GitHub Project is the delivery source of truth;
the issue body defines the scope, acceptance criteria, dependencies and testing expectations.

1. Select one unblocked issue from `Todo` in the [CardNest Development project](github-project.md).
2. Assign the issue to the person doing the work.
3. Move the project item to `In Progress`.
4. Create a branch using the correct pattern:
   - `feature/<issue-number>-short-description`
   - `fix/<issue-number>-short-description`
   - `chore/<issue-number>-short-description`
5. Implement only the issue scope; create a follow-up issue instead of expanding the work silently.
6. Run the issue's required tests and the relevant static-analysis or browser checks.
7. Open a pull request against the default branch.
8. Move the project item to `In Review` if the project workflow has not done so automatically.
9. Request or perform review and resolve only review feedback that belongs to the linked issue.
10. Merge after all required automated checks and review requirements pass.
11. Close the issue. A pull request body containing `Closes #<issue-number>` will normally close it on merge.
12. Verify that the project item is in `Done`; move it manually if the configured Project workflow did not.

## Pull-request requirements

Every pull request must:

- link its issue with `Closes #<issue-number>`;
- describe what changed and why it is within the issue scope;
- explain how it was tested, including commands or environments where useful;
- complete the repository checklist;
- avoid unrelated changes; and
- pass automated checks before merging.

## Working with dependencies

Do not start an issue whose blocking GitHub issue is unresolved unless the blocker has been explicitly removed or the
work can be safely split. Keep new cross-module or delivery dependencies in GitHub's native **blocked by** relation,
not only in prose.

## Status conventions

`Todo` means ready for selection but not started. `In Progress` means a single owner is actively working on it.
`In Review` means an open linked pull request is ready for review. `Done` means the issue is closed and its Project
item has been verified. Use the `status:blocked` label only for an actual external or unresolved blocker.
