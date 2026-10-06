"""
Direct MySQL access for the integration layer.

A browser test can only prove what a page displays. These helpers prove
what actually reached the database - which is the only way to show that a
viewing request was really stored, and stored correctly.

Every statement here is parameterised. The suite must not model bad
practice while testing for it.

If MySQL is unreachable the helpers raise, and conftest.py skips the
integration layer with an explanatory message rather than reporting
dozens of confusing failures.
"""

from __future__ import annotations

from typing import Any

import pymysql

from . import config


def connect() -> pymysql.connections.Connection:
    """Open a connection. Caller closes it, or uses the `db` fixture."""
    return pymysql.connect(
        host=config.DB_HOST,
        port=config.DB_PORT,
        user=config.DB_USER,
        password=config.DB_PASSWORD,
        database=config.DB_NAME,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def available() -> bool:
    """True when the database is reachable and the schema is imported."""
    try:
        conn = connect()
    except Exception:
        return False
    try:
        with conn.cursor() as cur:
            cur.execute("SHOW TABLES LIKE 'viewing_requests'")
            return cur.fetchone() is not None
    finally:
        conn.close()


# --- Generic helpers ---------------------------------------------------


def query(sql: str, params: tuple = ()) -> list[dict[str, Any]]:
    """Every matching row."""
    conn = connect()
    try:
        with conn.cursor() as cur:
            cur.execute(sql, params)
            return list(cur.fetchall())
    finally:
        conn.close()


def one(sql: str, params: tuple = ()) -> dict[str, Any] | None:
    """The first matching row, or None."""
    rows = query(sql, params)
    return rows[0] if rows else None


def scalar(sql: str, params: tuple = ()) -> Any:
    """The first column of the first row, or None."""
    row = one(sql, params)
    return next(iter(row.values())) if row else None


def execute(sql: str, params: tuple = ()) -> int:
    """Run a write. Returns rows affected."""
    conn = connect()
    try:
        with conn.cursor() as cur:
            return cur.execute(sql, params)
    finally:
        conn.close()


# --- Domain helpers ----------------------------------------------------


def viewing_request(reference: str) -> dict[str, Any] | None:
    """The stored request carrying this reference."""
    return one(
        "SELECT * FROM viewing_requests WHERE reference = %s",
        (reference,),
    )


def rooms_for_request(reference: str) -> list[str]:
    """Listing slugs named by the request, in insertion order."""
    rows = query(
        """
        SELECT r.listing_slug
          FROM viewing_request_rooms r
          JOIN viewing_requests v ON v.id = r.request_id
         WHERE v.reference = %s
        """,
        (reference,),
    )
    return [r["listing_slug"] for r in rows]


def latest_request() -> dict[str, Any] | None:
    """The most recently created request, whatever its reference."""
    return one("SELECT * FROM viewing_requests ORDER BY id DESC LIMIT 1")


def request_count() -> int:
    return int(scalar("SELECT COUNT(*) FROM viewing_requests") or 0)


def listing_count(active_only: bool = True) -> int:
    sql = "SELECT COUNT(*) FROM listings"
    if active_only:
        sql += " WHERE is_active = 1"
    return int(scalar(sql) or 0)


def listing(slug: str) -> dict[str, Any] | None:
    return one("SELECT * FROM listings WHERE slug = %s", (slug,))


def campus(campus_id: str) -> dict[str, Any] | None:
    return one("SELECT * FROM campuses WHERE id = %s", (campus_id,))


def table_exists(name: str) -> bool:
    return bool(query("SHOW TABLES LIKE %s", (name,)))


# --- Cleanup -----------------------------------------------------------


def delete_request(reference: str) -> None:
    """
    Remove a request created by a test, and its room rows.

    Room rows are deleted first and explicitly rather than relying on
    ON DELETE CASCADE, so cleanup still works if that rule is changed -
    which Phase 3 finding 5 recommends it should be.
    """
    row = one("SELECT id FROM viewing_requests WHERE reference = %s", (reference,))
    if not row:
        return
    execute("DELETE FROM viewing_request_rooms WHERE request_id = %s", (row["id"],))
    execute("DELETE FROM viewing_requests WHERE id = %s", (row["id"],))
