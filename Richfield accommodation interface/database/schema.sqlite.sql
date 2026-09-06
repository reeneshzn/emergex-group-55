-- ===========================================================================
-- Richfield Student Accommodation — SQLite schema
-- ===========================================================================
--
-- Same tables as schema.mysql.sql, in SQLite dialect. Use this when you want
-- a real database without running a server — the whole thing is one file.
--
--   1. Set 'driver' => 'sqlite' in includes/config.php
--   2. php database/seed.php        (imports this schema and loads the data)
--
-- MySQL is the production target under XAMPP. This exists so the site can be
-- developed and tested without one.
-- ===========================================================================

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS campuses (
  id         TEXT    NOT NULL PRIMARY KEY,
  name       TEXT    NOT NULL,
  city       TEXT    NOT NULL,
  province   TEXT    NOT NULL,
  lat        REAL    NOT NULL,
  lng        REAL    NOT NULL,
  confirmed  INTEGER NOT NULL DEFAULT 0,  -- drives the "Location to confirm" badge
  beds       INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS room_types (
  id         TEXT    NOT NULL PRIMARY KEY,
  label      TEXT    NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS amenities (
  id         TEXT    NOT NULL PRIMARY KEY,
  short      TEXT    NOT NULL,
  label      TEXT    NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS listings (
  slug       TEXT    NOT NULL PRIMARY KEY,
  name       TEXT    NOT NULL,
  area       TEXT    NOT NULL,
  campus_id  TEXT    NOT NULL REFERENCES campuses(id) ON UPDATE CASCADE,
  room_type  TEXT    NOT NULL REFERENCES room_types(id) ON UPDATE CASCADE,
  lat        REAL    NOT NULL,
  lng        REAL    NOT NULL,
  rent       INTEGER NOT NULL,           -- whole rand, never cents
  beds       INTEGER NOT NULL DEFAULT 0,
  verified   INTEGER NOT NULL DEFAULT 0, -- drives the "Inspected" badge
  seed       TEXT    NOT NULL,
  image_url  TEXT,
  blurb      TEXT    NOT NULL,
  is_active  INTEGER NOT NULL DEFAULT 1,
  created_at TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_listings_campus ON listings(campus_id);
CREATE INDEX IF NOT EXISTS idx_listings_rent   ON listings(rent);
CREATE INDEX IF NOT EXISTS idx_listings_active ON listings(is_active);

CREATE TABLE IF NOT EXISTS listing_amenities (
  listing_slug TEXT NOT NULL REFERENCES listings(slug) ON DELETE CASCADE ON UPDATE CASCADE,
  amenity_id   TEXT NOT NULL REFERENCES amenities(id)  ON DELETE CASCADE ON UPDATE CASCADE,
  PRIMARY KEY (listing_slug, amenity_id)
);

CREATE INDEX IF NOT EXISTS idx_la_amenity ON listing_amenities(amenity_id);

CREATE TABLE IF NOT EXISTS faqs (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  question   TEXT    NOT NULL,
  answer     TEXT    NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0,
  is_active  INTEGER NOT NULL DEFAULT 1
);

-- ---------------------------------------------------------------------------
-- Captured data. PERSONAL INFORMATION — POPIA applies. See schema.mysql.sql.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS viewing_requests (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  reference      TEXT    NOT NULL UNIQUE,
  full_name      TEXT    NOT NULL,
  email          TEXT    NOT NULL,
  phone          TEXT    NOT NULL,
  guardian_email TEXT,
  campus_id      TEXT    NOT NULL REFERENCES campuses(id) ON UPDATE CASCADE,
  preferred_date TEXT    NOT NULL,
  move_in_month  TEXT    NOT NULL,
  notes          TEXT,
  status         TEXT    NOT NULL DEFAULT 'new',
  created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_vr_campus  ON viewing_requests(campus_id);
CREATE INDEX IF NOT EXISTS idx_vr_status  ON viewing_requests(status);
CREATE INDEX IF NOT EXISTS idx_vr_created ON viewing_requests(created_at);

CREATE TABLE IF NOT EXISTS viewing_request_rooms (
  request_id   INTEGER NOT NULL REFERENCES viewing_requests(id) ON DELETE CASCADE,
  listing_slug TEXT    NOT NULL REFERENCES listings(slug) ON DELETE CASCADE ON UPDATE CASCADE,
  PRIMARY KEY (request_id, listing_slug)
);

CREATE TABLE IF NOT EXISTS shortlists (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  owner_key    TEXT NOT NULL,
  listing_slug TEXT NOT NULL REFERENCES listings(slug) ON DELETE CASCADE ON UPDATE CASCADE,
  created_at   TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (owner_key, listing_slug)
);
