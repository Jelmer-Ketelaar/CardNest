# Backup and restore expectations

This document defines the operational expectations for a future CardNest deployment. It does **not** prove that a
production backup, restore job, encryption key, retention policy or recovery owner is currently configured.

## Backup scope

| Asset | Why it matters | Expected protection |
| --- | --- | --- |
| PostgreSQL database | Source of truth for accounts, collections, listings, orders, payments, storage placement and reviews | Encrypted, access-controlled backups with point-in-time/recovery capability selected by operations |
| Object storage, when introduced | User photos, uploaded evidence and generated assets will not live in PostgreSQL | Versioned and encrypted backup/replication strategy with access controls matching the source bucket |
| Runtime configuration and secrets | Required to restore service behaviour but must never be copied into Git | Managed secret store and documented recovery access, separate from database backups |
| Deployment artifacts and migrations | Required to restore a compatible application version | Immutable build artifacts plus the Git history and reviewed migration set |

Redis and Messenger queues are not the durable source of truth for an order or collection. The operational plan must
state whether queued work can be replayed from the database or requires an additional durable strategy.

## Required operating decisions

Before a public deployment, name an accountable operations role and record:

- recovery-point and recovery-time objectives appropriate to the service;
- backup frequency, retention, region and encryption/key-management policy;
- access approval and audit requirements for backup operators;
- the authoritative backup location for PostgreSQL and future object storage;
- how failed jobs, backup integrity checks and capacity alerts are monitored; and
- an incident escalation and communication path.

Do not place connection strings, passwords, encryption keys or backup URLs in this repository or a release record.

## Restore verification runbook

Run the following in an isolated environment with non-production credentials and no public traffic:

1. Select a known backup and record its identifier, timestamp and operator in the restricted operations record.
2. Restore PostgreSQL to an isolated instance; restore the compatible object-storage snapshot when that capability
   exists.
3. Deploy the matching application artifact and inject only environment-managed configuration.
4. Run `php bin/console doctrine:migrations:migrate --no-interaction` only after confirming migration compatibility
   with the selected data snapshot.
5. Run `php bin/console doctrine:schema:validate --skip-sync` and the `/api/v1/health` smoke check.
6. Verify a minimal non-sensitive data path: public catalog read, authenticated account read, private owner-scoped
   collection read, and a worker/queue health check appropriate to the deployment.
7. Record duration, outcome, deviations and follow-up actions. Do not paste personal data or credentials into the
   record.
8. Tear down the isolated restore environment according to the approved data-handling process.

## Cadence

Perform and record a restore drill before the first public release and at a cadence chosen by operations. Any drill
failure is a release risk: create a tracked issue, identify the affected backup scope and do not claim recoverability
until a subsequent successful drill is recorded.
