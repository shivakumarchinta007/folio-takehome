# Folio

A lightweight PHP + SQLite document-sharing app where staff create documents and share them with recipients via secure one-time links.

---

## Features Implemented

### 1. Scheduled Publishing
Staff can prepare a document in advance and have it go live at a specific date and time.

- Added `publish_at` column to the documents table via schema migration
- Added optional datetime field in the admin UI
- Share links return **HTTP 404** (not 200) before the publish time — recipients cannot tell if a token is wrong or the document is just not live yet
- Admin document list shows **SCHEDULED** or **LIVE** badge per document
- Automated tests cover future scheduling, past scheduling, and access blocking

### 2. Search by Title
Staff can find documents by title instantly from the admin page.

- Substring match using SQLite `LIKE` with `%term%`
- Case-insensitive — matches any part of the title
- Safe from SQL injection via prepared statements
- Automated tests cover match, no-match, and case-insensitivity

### 3. Audit Logging
Every document creation and share action is recorded.

- Logs staff ID, action, entity type, entity ID, and details (including `publish_at`)
- Follows the existing audit pattern in `lib/bootstrap.php` consistently

---

## Design Decisions

### Why HTTP 404 for scheduled documents?
A 200 response with a countdown page leaks that a document exists at that token. Returning 404 keeps it indistinguishable from an invalid token — better privacy for recipients.

### Why keep token-based share links?
Readable IDs are guessable. Secure random tokens protect recipient privacy. Search solves the staff usability problem without compromising the access control model.

### Why SQLite LIKE for search?
The app is intentionally lightweight. Substring matching is built into SQLite, requires zero extra dependencies, and works well at internal staff scale. With more time I would explore SQLite FTS5 for larger document sets.

### Why datetime-local input?
Native browser support keeps the UI simple with no JavaScript dependencies. The T separator from HTML is converted to a space before storing in SQLite (`2026-05-20T09:00` → `2026-05-20 09:00:00`).

---

## Code Observations

- `admin.php` has no authentication — `current_staff()` hardcodes staff ID 1. Any visitor can create documents. A session layer would be needed before production use.
- Timezone is hardcoded to `America/Chicago` in `bootstrap.php`. SQLite's `datetime('now')` defaults to UTC — this could cause subtle scheduling bugs if deployed in a different region.

---

## What I Would Do With More Time

- Add timezone selector on the scheduling form
- Allow editing publish date after document creation
- Add CSRF protection on all forms
- Add pagination and sorting for large document lists
- Add audit log visibility in the admin UI
- Explore SQLite FTS5 for smarter search

---

## Running the Project

Requires Docker with Compose.

```bash
docker compose up
```

Open [http://localhost:8000/admin.php](http://localhost:8000/admin.php)

The first run builds the image (~30 seconds). Each `docker compose up` reseeds the database from scratch so you always start from a known state.

---

## Running the Tests

```bash
docker compose exec app php tests/test.php
```

All tests should show `passed, 0 failed`.

---

## Project Structure

```
lib/
  bootstrap.php     # DB connection, helpers, audit log
  layout.php        # Shared HTML header/footer
public/
  admin.php         # Staff admin — create documents, search, share
  view.php          # Recipient view — enforces scheduling
  share.php         # Share link generation
tests/
  test.php          # Automated tests
schema.sql          # Base database schema
seed.php            # Seeds database on startup
```