# Future user-data export design

CardNest does not implement a data-export endpoint in the MVP. This document preserves a safe design direction so a
later feature can be built without exposing another user's information or turning an export into an account-takeover
path.

## Intended scope

An authenticated user should eventually be able to request an export of data that is about that user or that they
created. Likely export groups are:

- account profile and settings that exist at the time of implementation;
- owned collections, physical card metadata, purchase fields, storage locations, binder layouts and placement history;
- wishlists and preferences;
- the user's own marketplace listings and their price history;
- orders in which the user is a participant, with the export representation chosen deliberately for counterparty and
  delivery data;
- authored reviews and public reputation inputs; and
- a machine-readable explanation of omitted fields and retention exceptions.

The export must not casually include another user's private collection, authentication artifacts, password hashes,
bearer/recovery tokens, internal exception details, or unredacted counterparty delivery information. Product and
legal decisions are required before defining what transaction history is exportable, retained, anonymized or omitted.

## Proposed flow

1. Require an authenticated user and recent re-authentication or an equivalent account-security check.
2. Create an export request owned by that user; do not accept a user identifier supplied by the client.
3. Rate-limit requests and record minimal audit metadata such as request id, actor id, timestamps and outcome — never
   exported content or tokens.
4. Build the export asynchronously through Symfony Messenger from owner-scoped repositories.
5. Produce a versioned, documented archive with JSON as the canonical format and optional CSV files for tabular
   collection/listing data.
6. Store the archive in private, encrypted object storage and expose only a short-lived, one-time download mechanism.
7. Expire and delete the generated artifact on a documented schedule; notify the user through a reviewed channel.

## Format and compatibility

- Include a manifest with schema version, creation time, account identifier, generated files, fields intentionally
  omitted, and currency convention (`amount` integer minor units plus ISO currency).
- Use UUID strings in exported JSON. Keep catalog information separate from a physical `CollectionItem`, just as in
  the application model.
- Avoid exporting volatile signed URLs. Export an image reference only when licensing, ownership and access rules
  allow it.
- Make format changes additive where possible and keep a migration/compatibility note in the manifest.

## Security and operational requirements

- Export queries must use the same owner/participant authorization boundaries as normal API reads.
- Generated files must not be served from a public path or committed to source control.
- The queue payload should contain a request id, not a complete personal-data snapshot.
- Monitor failed requests without logging the archive contents or delivery URL.
- A cancellation, deletion or account-restriction request needs defined interaction with an in-flight export.

## Deliberately not implemented

This design creates no endpoint, database table, queue handler, object-storage bucket, retention period or legal
policy. Those choices require a scoped implementation issue after product and compliance decisions are available.
