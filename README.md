# Folio Take-Home — Shiva Kumar

A small document-sharing app extended with scheduled publishing, human-readable slugs, and title search.

## Setup

Requires Docker (with Compose).

```bash
docker compose up

Open:

http://localhost:8000

The first run builds the image (~30 seconds); subsequent runs start instantly.

Each docker compose up re-seeds db.sqlite from scratch, so you always start with a known state.

To run the tests:

docker compose exec app php tests/test.php
What I Built
1. Scheduled Publishing

Staff can set a published_at date and time when creating a document.

Before that time, recipients opening the share link see a “Not yet available” message with the publish date. After the scheduled time passes, the document becomes visible automatically.

The visibility check happens at request time, so no background jobs or cron scheduling are required for this scale of application.

2. Human-Readable Slugs

Each document gets a readable slug generated from the title plus a short random hex suffix.

Example:

welcome-packet-a3f1

Slugs complement the existing share-token system rather than replacing it.

Recipients still access documents using the secure hex token, while slugs act as staff-friendly identifiers shown in the admin UI and document pages.

This keeps the existing privacy and link-permanence guarantees intact.

3. Share by Name

Staff can search for documents by title on both the admin page and share page.

Search uses SQLite LIKE queries with a %term% substring pattern for simple case-insensitive matching.

I chose this approach because it is:

simple
predictable
lightweight
fast enough for an internal tool

At larger scale I would likely switch to SQLite FTS5 or Postgres full-text search.

Migrations

The README requested schema changes through migrations instead of directly editing schema.sql.

Schema changes live in migrations/ as plain SQL files and are applied automatically during startup from seed.php.

Current migrations:

migrations/
001_add_published_at.sql
002_add_slug.sql

I chose simple SQL migrations over a migration framework because:

no additional dependencies are required
easy to review and audit
keeps the project lightweight
appropriate for the scale of this exercise
Audit Logging

Document creation, scheduling changes, and share actions continue to use the existing audit_log pattern already present in the project.

Things I Noticed Worth Flagging
The admin page currently has no authentication or authorization
Share tokens do not expire and are not truly one-time use
Forms do not currently implement CSRF protection
The app already uses PDO prepared statements correctly, reducing SQL injection risk
Output rendering consistently uses escaping helpers (h() / htmlspecialchars) which helps prevent XSS vulnerabilities
What I Would Improve With More Time
Add authentication and authorization
Add expiring and true one-time-use share links
Improve search with SQLite FTS5 or full-text indexing
Add pagination for larger document lists
Allow editing and rescheduling published_at
Add better migration tracking and rollback support
AI Workflow

I used AI tools to help brainstorm implementation approaches, validate tradeoffs, and speed up repetitive coding tasks.

I still manually reviewed the code, adjusted implementations to match the project requirements, and avoided suggestions that conflicted with the README constraints — particularly around schema changes and migration handling.