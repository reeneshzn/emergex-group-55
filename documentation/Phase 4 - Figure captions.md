# Phase 4 — screen capture figures

Captions for section 4.7.3, ready to paste into Word. Insert each image
yourself, then paste the caption beneath it.

Image files are in `Screenshots of Application/`.

Set every image to **16 cm wide** (or 7.5 cm for the four landing-page
panels) so the figures look consistent.

Figures 4.1 to 4.8 are the diagrams already in the document — architecture,
modules, sequence, hardware, network, class model, ERD and navigation.
These carry on from there.

---

## Figure 4.9 — Landing page

**Files:** `01a-home-hero.png`, `01b-home-campuses.png`,
`01c-home-howitworks.png`, `01d-home-faq.png`

**Layout:** 2 × 2 borderless table, 7.5 cm wide each, labelled (a) to (d).

**Caption:**

> Figure 4.9 — Landing page (index.php), shown in four panels: (a) the
> sample-data banner, navigation and hero with computed statistics; (b) the
> eight campuses with confirmation badges; (c) how the process works and the
> cost estimator; (d) frequently asked questions and the not-affiliated
> footer notice.

**Worth knowing if asked:** panel (a) shows 822 beds, 8 campuses, 13
inspected listings and a 15-minute median walk. Every one of those is
calculated from the data on each page load, never written into the page —
the "derive, do not hardcode" principle in Table 4.2.2.

---

## Figure 4.10 — Search page

**File:** `02-search.png`

**Caption:**

> Figure 4.10 — Search page (search.php), realising FR2, FR3 and FR4.
> Filters on the left, results sorted closest-on-foot first, and the Leaflet
> map on the right.

---

## Figure 4.11 — Empty state

**File:** `06-search-empty.png`

**Caption:**

> Figure 4.11 — Empty state, reached by filtering Polokwane to R 2 000 with
> three required amenities. The design rule in Table 4.7.3 requires guidance
> and a route out ("Clear filters"), never a blank area.

**Worth knowing if asked:** amenities are AND, not OR — the interface says
"Must include", so a listing must have every amenity ticked. That is the
HAVING COUNT clause in the search query at 4.6.2.

---

## Figure 4.12 — Listing detail

**File:** `03-listing.png`

**Caption:**

> Figure 4.12 — Listing detail (listing.php?slug=glen-ashley-house),
> realising FR3. Rent, room type, amenities, inspection status and both
> travel times. An unknown slug returns HTTP 404 rather than an empty page.

---

## Figure 4.13 — Viewing-request form

**File:** `04-apply-form.png`

**Caption:**

> Figure 4.13 — Viewing-request form (apply.php), the system's only
> data-capture point. Every field in Table 4.7.2 appears here, each with a
> visible label bound by for/id.

---

## Figure 4.14 — Validation errors

**File:** `07-apply-errors.png` — crop to the four red error messages

**Caption:**

> Figure 4.14 — Server-side validation after an empty submission (FR7).
> Errors appear beside each field, the field carries aria-invalid, and the
> student's entries are preserved. One documented defect is visible here:
> the guardian hint promises a confirmation email that the system does not
> send (NFR5, corrected wording specified in 4.7.2).

---

## Figure 4.15 — Rooms to view, no error

**File:** `08-apply-rooms.png`

**Caption:**

> Figure 4.15 — The "Rooms to view" section of the same empty submission.
> No error appears, although no room is selected. This is Phase 3 finding 1
> — BR-06 is not enforced — and is the defect corrected by the room-count
> check in the validation sequence at 4.6.1.

**This is the most valuable figure in the section.** It is photographic
evidence that the Phase 3 analysis was correct, sitting beside the Phase 4
design that fixes it. If a marker asks you to point at where your phases
connect, point here.

---

## Figure 4.16 — Confirmation

**File:** `05-apply-confirmation.png`

**Caption:**

> Figure 4.16 — Confirmation output, showing the generated reference
> RF-261024-A97E in the BR-10 format. The fine print reads "Your request has
> been saved", which the system prints only when a row was actually written
> — when no database is connected it says so instead (Table 4.7.3).

---

## Checklist

- [ ] All eight diagrams (4.1–4.8) survived the conversion to Word
- [ ] Eight screen figures inserted, 4.9 to 4.16
- [ ] Image widths consistent — 16 cm, or 7.5 cm for the four panels
- [ ] Captions below each figure, not above
- [ ] No figure split across two pages
- [ ] Page numbers added (Insert → Page Number → Bottom of Page)
- [ ] Body text Times New Roman 12 pt, line spacing 1.5
