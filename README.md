Here it is as one clean block to paste directly into your README.md file:

Folio Take-Home — Shiva Kumar
A small document-sharing app, extended with scheduled publishing, human-readable slugs, and title search.
Setup
Requires Docker (with Compose).
docker compose up
Open http://localhost:8000. The first run builds the image (~30 seconds); subsequent runs start instantly.
Each docker compose up re-seeds db.sqlite from scratch, so you always start with a known state.
To run the tests:
docker compose exec app php tests/test.php
What I Built
1. Scheduled publishing
Staff can set a publish_at date and time when creating a document. Before that time, recipients hitting the share link see a "Not yet available" message with the publish date. After that time, the document renders normally.
The check happens at view time — no background job needed at this scale.
2. Human-readable slugs
Every document gets a slug auto-generated from its title plus a 4-character random hex suffix — e.g. welcome-packet-a3f1.
Slugs complement the existing hex token rather than replacing it. Recipients still access documents via the unguessable hex token. Slugs are staff-facing identifiers only — shown in the admin table and document view, useful for referencing in conversation or email.
This keeps the existing privacy and link-permanence guarantees intact.
3. Share by name
Staff can search for a document by title on both the admin page and the share page. Search uses SQLite LIKE with a %term% pattern — case-insensitive substring match.
I chose LIKE over fuzzy search because it is predictable and fast enough for an internal tool. At larger scale I would switch to SQLite FTS5 or Postgres tsvector.
Migrations
Schema changes live in migrations/ as plain SQL files, applied in order by seed.php at startup:
migrations/
001_add_publish_at.sql
002_add_slug.sql
I chose flat SQL files over a migration framework — simpler, no dependencies, easy to audit. seed.php runs them automatically on every fresh docker compose up.
Things I noticed worth flagging

The admin has no authentication — anyone can access it
Share tokens never expire and are not truly one-time despite the README describing them that way
No CSRF protection on forms
All queries use PDO prepared statements (no SQL injection risk) and all output uses h() / htmlspecialchars (no XSS risk) — these are done correctly

What I'd do with more time

Token expiry and true one-time enforcement
Authentication with a login page
SQLite FTS5 for better search relevance
Pagination on the document list
Allow rescheduling publish_at after creation

AI workflow
I used Claude to generate boilerplate PHP matched to the existing code style, write migration files, and draft tests.
I pushed back on one suggestion: Claude proposed using slugs as the recipient access mechanism. I rejected that — slugs are short and guessable by enumeration. The hex token stays as the access control layer. Slugs are identification, not authorization.
I read every piece of generated code before saving it and verified all three features manually in the browser before running the test suite.