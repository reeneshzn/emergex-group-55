# Richfield Student Accommodation — website interface

A Richfield-branded student accommodation portal. Plain PHP, HTML, CSS and
JavaScript — no build step, no `npm install`, no dependencies to download.

> **This is not an official Richfield product.** It carries a real
> institution's identity without their approval. Keep it private until
> Richfield signs off. Every page is set to `noindex` and the footer carries a
> not-affiliated disclaimer — do not remove either before approval.

---

## Running it locally

Requires **PHP 8.0 or newer** (the payment calculator uses `match`). Nothing
else — no `npm install`, no build step, no dependencies to download.

There are three ways to run it. Pick one.

### 1. Fastest — built-in server, no database

Two commands, about ten seconds. The site runs on the sample data in
`includes/data.php`; the booking form validates but saves nothing.

In **PowerShell**, note the `;` — Windows PowerShell 5.1 does not support `&&`:

```powershell
cd "C:\path\to\Richfield accommodation interface"
php -S localhost:8000
```

Open **http://localhost:8000**. `Ctrl+C` stops it.

> If `php` is not recognised, PHP is not on your PATH. Install it with
> `winget install PHP.PHP.8.4` and open a **new** terminal.

### 2. With a database, still no server — SQLite

Same as above but data is real and the booking form saves. The whole database
is a single file.

```powershell
cd "C:\path\to\Richfield accommodation interface"
copy includes\config.sample.php includes\config.php
```

Open `includes/config.php` and change one line:

```php
'driver' => 'sqlite',
```

Then:

```powershell
php database/seed.php
php -S localhost:8000
```

`seed.php` creates the tables and loads the campuses, listings, amenities and
FAQs. It prints what it wrote. Submit the booking form and the row lands in
`database/richfield.sqlite`.

> **PDO drivers.** A stock PHP install ships the extension DLLs but no
> `php.ini`, so none load. If `seed.php` reports no driver, create a `php.ini`
> next to `php.exe` containing `extension_dir = "ext"` and
> `extension = pdo_sqlite` (add `pdo_mysql` and `mbstring` too). XAMPP already
> has all of these enabled.

### 3. XAMPP — closest to the real deployment

1. Start **Apache** and **MySQL** in the XAMPP control panel
2. Copy the folder into `xampp\htdocs\`
3. Open http://localhost/phpmyadmin and import `database/schema.mysql.sql`
   (it creates the database itself)
4. `copy includes\config.sample.php includes\config.php` — leave the driver as
   `mysql`, and edit the username/password only if yours is not `root` with a
   blank password
5. `php database/seed.php`
6. Open `http://localhost/Richfield%20accommodation%20interface/`

> **Port 3306 already in use?** Something else on the machine — another
> MySQL/MariaDB install, a different dev stack — can already be listening on
> the default port, which makes XAMPP's own MySQL fail to start (check
> `C:\xampp\mysql\data\mysql_error.log` for `Can't start server: Bind on
> TCP/IP port`). Rather than touching whatever else is running, move XAMPP's
> MySQL to a different port: change **both** `port=` lines in
> `C:\xampp\mysql\bin\my.ini` to `3307`, restart MySQL from the XAMPP control
> panel, and set `'port' => 3307` in `includes/config.php` to match.

### Checking which source is live

```powershell
php -r "require 'includes/functions.php'; echo using_database() ? 'database: '.db_driver() : 'sample data';"
```

The booking form also tells you: it says *"stored securely"* when a database is
connected and *"no database is connected"* when there is not.

### Pages

| URL | Page |
| --- | --- |
| `/` | Landing page — all ten sections |
| `/search.php` | Search with map and filters |
| `/listing.php?slug=acacia-court-bryanston` | Room detail |
| `/apply.php` | Book-a-viewing form |

### Offline

The only external resources are Google Fonts and Leaflet, both from CDNs. With
no internet the page still works — it falls back to system fonts and the map
area stays empty.

---

## All 19 files

```
index.php                  landing page
search.php                 search + filters + map
listing.php                room detail (?slug=)
apply.php                  viewing request form + validation + save
README.md                  this file
.gitignore                 blocks credentials and database dumps

includes/
  db.php                   THE DATABASE CONNECTOR — the only `new PDO`
  repository.php           every SQL statement in the project
  config.sample.php        credentials template; copy to config.php
  data.php                 sample campuses, listings, amenities, FAQs
  functions.php            distance, ZAR maths, formatting, lookups
  header.php               shared page shell + nav
  footer.php               shared footer + shortlist drawer
  listing-card.php         reusable card partial

database/
  schema.mysql.sql         tables for MySQL / MariaDB (XAMPP)
  schema.sqlite.sql        tables for SQLite
  seed.php                 creates the tables and loads the data

assets/
  css/style.css            the entire design system
  js/app.js                shortlist, calculator, map, reveals
```

`includes/config.php` and `database/*.sqlite` are created when you set up a
database. Both are git-ignored — they hold credentials and personal data.

---

## The commercial argument

Three facts, verified from live sources on 2026-09-02:

1. **Richfield does not operate residences.** Campuses refer students to
   independent providers ad hoc. That referral phone number is the gap this
   fills.
2. **Richfield students are not NSFAS-eligible.** NSFAS is for public
   universities and TVET colleges; Richfield is private. Every generic South
   African student-housing template gets this wrong.
3. **richfield.ac.za runs Next.js + Tailwind v4.** Their brand tokens were
   sampled from their own production stylesheet, so the colours are exact
   rather than eyeballed.

Sources: [richfield.ac.za](https://www.richfield.ac.za/),
[campuses](https://www.richfield.ac.za/campuses),
[FAQ](https://www.richfield.ac.za/faq),
[NSFAS providers](https://www.nsfas.org.za/content/accommodationprovider.html).

---

## Hard constraints

Breaking one of these damages the pitch.

- **No NSFAS badge, filter or claim anywhere.** The only permitted mention is
  the FAQ answer in `data.php` explaining ineligibility.
- **No fabricated real-world entities.** Sample listings use invented building
  names and suburb-level areas only — never real landlords, businesses or
  street addresses.
- **No invented accreditation.** No DHET seal, no "approved by" that was not
  granted. The "Inspected" badge describes a *proposed* programme — confirm
  with the client before presenting.
- **Do not publish publicly** under Richfield's identity until approved.

---

## Swap points

| What | Where |
| --- | --- |
| **Brand colours, fonts, radii** | The `:root` block at the top of `assets/css/style.css`. No other file contains a literal hex. |
| **Logo** | The `.wordmark` block in `includes/header.php` — nothing else references it. |
| **Listing photography** | `listing_image()` in `includes/functions.php`. Replace the generated panel with an `<img>`. |
| **Campuses / listings / FAQs** | `includes/data.php` |
| **Sample-data banner** | Set `SAMPLE_DATA` to `false` in `includes/data.php` |

Re-skinning for another institution means editing the `:root` block, the
wordmark, and `data.php`. Nothing else.

---

## Accessibility

Two brand colours needed accessible derivatives, both documented in the CSS
with their measured ratios:

- White on the brand red `#de473c` measures **4.14:1** and fails AA for normal
  text. `--rf-red-ink` (`#d43a2f`, **4.72:1**) is used wherever white text sits
  on red; `--rf-red` stays for decoration.
- `--rf-gray-light` (`#d9d9d9`) on white measures **1.41:1**, under the 3:1
  large-text minimum. `--rf-numeral` (`#7d8896`, **3.60:1**) is used for
  display numerals and icons on white.

Also: skip-to-content link, `prefers-reduced-motion` honoured, the map has a
keyboard-navigable listing list as its accessible equivalent, and no horizontal
scroll at any width (verified at 360, 390, 414, 768, 1024, 1280 and 1920px).

---

## What was verified and how

Everything below was executed and confirmed on PHP 8.4.24, not just reviewed.

**PHP**

- All 9 PHP files pass `php -l` with no syntax errors
- Every route returns the right status: `/`, `/search.php`, `/listing.php`,
  `/apply.php` all 200; an unknown `?slug=` correctly returns **404**
- All **18** listing pages render 200
- Search filters compute correctly server-side — Pretoria returns 3 rooms, and
  1 under a R3 000 cap; Bryanston returns 2 with backup power; impossible
  filters return the empty state
- Form validation fires on every path: empty fields, a non-South-African
  number, a past date; a valid submit returns a reference like `RF-260903-F111`
  and `+27…` numbers are accepted
- `<script>` in a form field comes back escaped as `&lt;script&gt;`
- A fresh run of every page produces **zero** warnings, notices or deprecations

**Browser**

- Shortlist saves, survives a reload, caps at 4, and clears
- Payment calculator: **R 9 950** due now, **R 4 500** instalment, **R 59 450**
  total on the defaults; upfront applies a **R 2 700** discount; termly splits
  12 months into **4** instalments
- Leaflet renders OpenStreetMap tiles with campus and listing pins
- Drawer opens and closes on Escape
- Zero horizontal overflow at 360, 390, 414, 768, 1024, 1280 and 1920px
- No JavaScript console errors

**Portability note:** the code deliberately avoids `mb_strlen()`. mbstring is an
optional extension and is *not* present in a stock PHP build — calling it made
the form fatal on submit. `str_len()` in `functions.php` uses mbstring when it
exists and falls back to `strlen()` when it doesn't, so the form works on any
PHP 8 install regardless of how XAMPP is configured.

---

## Database

The site has a working database layer. It runs **with or without** one:

| State | What happens |
| --- | --- |
| No `includes/config.php` | Runs on the sample arrays in `data.php`. Nothing saves. |
| Config present, server down | Same fallback, and the failure is logged. Set `'strict' => true` to make it throw instead. |
| Connected, schema not imported | Same fallback. |
| Connected with tables | Reads and writes the database. |

That is deliberate. This repo is shared — a teammate without MySQL running
should still be able to open the site.

### Setting it up with XAMPP

1. Start **Apache** and **MySQL** in the XAMPP control panel
2. Open http://localhost/phpmyadmin and import `database/schema.mysql.sql`
   (it creates the database itself)
3. `copy includes\config.sample.php includes\config.php`
4. Edit `config.php` if your MySQL user is not `root` with a blank password
5. `php database/seed.php`

Prefer no server at all? Set `'driver' => 'sqlite'` in `config.php` and run
step 5 — the whole database becomes one file under `database/`.

`seed.php` is safe to re-run: it updates existing rows rather than duplicating
them, and it never touches submitted viewing requests. Pass `--fresh` to drop
the reference tables and start clean.

### Where the connectors are

Every marker is greppable:

```bash
grep -rn "\[DB-" .
```

| Marker | File | What |
| --- | --- | --- |
| **DB-CONNECTOR** | `includes/db.php` | **The connector.** The only place `new PDO` appears. Connection, helpers, transactions. |
| **DB-QUERIES** | `includes/repository.php` | Every SQL statement in the project. Add new queries here. |
| **DB-WRITE** | `includes/repository.php`, `apply.php` | Saving a viewing request. |
| **DB-SHORTLIST** | `includes/repository.php` | Saved rooms — unused until logins exist. |
| **DB-1** | `includes/data.php` | The sample arrays, now the fallback and the seed source. |
| **DB-2** | `includes/functions.php` | Where the connector is included. |
| **DB-3** | `includes/functions.php` | The read seam — DB or sample, one branch. |
| **DB-4** | `includes/functions.php` | Trust-strip aggregates. |
| **DB-5** | `search.php` | Filtering, in SQL when connected. |
| **DB-7** | `assets/js/app.js` | Shortlist persistence, if accounts get added. |
| **DB-A** | `includes/footer.php` | Payload-size warning as the catalogue grows. |

### What kind of data it stores

**Reference data** — read by the site, maintained by staff:

| Table | Holds |
| --- | --- |
| `campuses` | The 8 campuses, coordinates, bed counts, the `confirmed` flag |
| `room_types` | single / sharing-2 / sharing-3 / studio / en-suite |
| `amenities` | Wi-Fi, backup power, biometric access, and the rest |
| `listings` | Accommodation: name, area, rent, beds, `verified`, blurb, optional `image_url` |
| `listing_amenities` | Which amenities each listing has |
| `faqs` | The home-page questions and answers |

**Captured data** — written by students using the site:

| Table | Holds |
| --- | --- |
| `viewing_requests` | Name, email, phone, optional guardian email, campus, preferred date, move-in month, notes, status, timestamp |
| `viewing_request_rooms` | Which rooms each request named |
| `shortlists` | Saved rooms — only used once students have logins |

### Adding a query

Put it in `includes/repository.php`, use `db_all()` / `db_one()` /
`db_insert()` from `db.php`, and return the same array shape the rest of the
site expects. No page should ever contain SQL.

### Security as built

- **Prepared statements everywhere.** Injection attempts against every
  URL parameter were tested; the tables survive intact.
- **CSRF token** on the viewing form, checked with `hash_equals()`.
- **Credentials git-ignored.** `config.php` never reaches the repo;
  `config.sample.php` is the committed template.
- **Output escaped** through `e()` on every dynamic value.
- **Errors logged, not shown.** A database failure tells the student to try
  again rather than leaking a stack trace.

### Still to do before this is public

- **Rate limiting** on the viewing form. A public form that writes rows needs
  it; even a per-IP hourly cap.
- **POPIA.** Names, emails, phone numbers and guardian emails are regulated
  personal information. Agree a retention period and a deletion route.
  Never commit a dump of `viewing_requests` — `.gitignore` blocks the obvious
  filenames, but it cannot stop a rename.
- **An admin view.** Requests land in the database with a `status` column
  (`new` / `contacted` / `viewed` / `closed`) but nothing reads them yet.

---

## Open items for the client

1. **Confirm five campus locations.** Bryanston, Pretoria and Umhlanga are
   confirmed on their site. Newtown Junction, Centurion, Musgrave, Cape Town
   and Polokwane came from a third-party directory and render a
   "Location to confirm" badge until verified in `includes/data.php`.
2. **Supply the official logo** and confirm the sampled brand tokens.
3. **Supply real photography.** The highest-value addition before pitching.
4. **Confirm funding wording** against current Richfield policy.
5. **Confirm the "Inspected" programme** exists or is being proposed.
6. **Campus coordinates are suburb-level approximations**, not surveyed
   building positions.

---

## Notes for whoever maintains this

- `apply.php` validates on the server and **sends nothing** — no mail, no
  database, no third-party request. The privacy line above the submit button
  says so; update both together if that changes.
- All output goes through `e()` (`htmlspecialchars`) to prevent XSS.
- Distances are straight-line (haversine), not street routing. The UI labels
  them as estimates.
- The OpenStreetMap attribution in the map and footer is a licence condition.
  Do not remove it.
