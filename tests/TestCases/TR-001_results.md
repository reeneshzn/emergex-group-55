# TR-001 — results to record

Enter these against test run **TR-001** (1 November 2026).

> **All seven defects below were corrected and verified in TR-002**
> (1 November 2026, 224 passed / 0 failed). See `TEST_RUN_TR-002.md`.
> This file is left as the record of what TR-001 found, so set the defect
> statuses in the tool rather than editing the write-ups here.

| Outcome | Cases |
| --- | ---: |
| Passed | 50 |
| Failed | 7 |
| Blocked | 3 |
| **Total** | **60** |

---

## Failed — 7

Each has a defect to raise against it.

| Case | Title | Defect |
| --- | --- | --- |
| TC-31 | A name longer than the stored column is rejected | DEF-01 |
| TC-37 | A request naming no rooms is rejected | DEF-02 |
| TC-38 | Selected rooms must belong to the selected campus | DEF-03 |
| TC-57 | The move-in month is validated against the permitted range | DEF-04 |
| TC-58 | The campus is validated against the campus table | DEF-05 |
| TC-59 | The status column accepts only its defined values | DEF-06 |
| TC-60 | The guardian email hint does not promise an email that is never sent | DEF-07 |

## Blocked — 3

Not executed in this run. The fallback tests move the application's
configuration file aside, and were left out so an interrupted run could
not leave the site on sample data.

| Case | Title | Reason |
| --- | --- | --- |
| TC-54 | The site works with no database connected | Not executed — opt-in |
| TC-55 | The site falls back when connected to an empty schema | Not executed — opt-in |
| TC-56 | The privacy notice matches what the system actually does | Partly executed; the connected-state half passed |

## Passed — 50

TC-01 to TC-30, TC-32 to TC-36, TC-39 to TC-53.

---

# Defects to raise

## DEF-01 · Medium · A name longer than 160 characters is silently truncated

**Found by** TC-31 · **Rule** FR7 · **Phase 3** finding 6 · **Fix** §4.6.1

`full_name` is `VARCHAR(160)`. A 200-character name is accepted, and the
database stores the first 160 characters. Neither the student nor the
provider is told anything was lost, so the provider calls using a name
that is not the student's.

**Correction:** add a maximum-length check to the validation sequence in
`apply.php`, before the transaction opens.

---

## DEF-02 · High · A viewing request naming no rooms is accepted

**Found by** TC-37 · **Rule** BR-06 · **Phase 3** finding 1 · **Fix** §4.6.1

The form can be submitted with no room selected. The request is stored
and a reference issued. Evidence from the run: reference
**RF-261101-F02E** was created with **0 rows** in
`viewing_request_rooms`.

The result is a request the campus cannot act on — a name, a phone
number and a date, with no indication of what the student wants to see.
The student receives a reference and waits for a call that cannot
usefully be made.

Visible in Phase 4 Figure 4.15: the form shows errors on four fields and
nothing beside "Rooms to view".

**Correction:** reject the submission when no room is selected, with the
error shown beside that section.

---

## DEF-03 · Medium · Rooms from another campus are accepted

**Found by** TC-38 · **Rule** BR-07 · **Phase 3** finding 2 · **Fix** §4.6.1

The rooms named on a request are checked for existence but not for
location. Evidence from the run: a request for campus **cape-town**
naming **acacia-court-bryanston** — roughly 1 400 km away — was
accepted.

The request routes to the wrong campus, and whoever picks it up has to
work out that it is wrong.

**Correction:** resolve each submitted slug and compare its `campus_id`
with the submitted campus.

---

## DEF-04 · Medium · The move-in month is not validated

**Found by** TC-57 · **Rule** FR7 · **Phase 3** finding 3 · **Fix** §4.6.1

`moveIn` is a dropdown offering the next twelve months, and the server
accepts whatever arrives. A value of **1999-01**, posted directly, was
stored unchanged.

Not reachable through the interface, which is why it was found by
posting to the server rather than by using the form. A dropdown
constrains the browser, not the request.

**Correction:** validate the submitted month against the generated
twelve-month list.

---

## DEF-05 · Medium · An invalid campus is refused by the database, not by validation

**Found by** TC-58 · **Rule** FR7, BR-04 · **Phase 3** finding 3 · **Fix** §4.6.1

The campus is not validated. An unknown value reaches the insert, the
foreign key rejects it, the exception is caught, and the student is told
"We could not save your request just now" — with no indication of which
field is wrong or what to change.

The data is protected; the student is not helped. A validation message
beside the campus field is what the rule requires.

**Correction:** validate the campus against the campus table in the
validation sequence, before the transaction opens.

---

## DEF-06 · Low · The status column accepts any value

**Found by** TC-59 · **Rule** FR6 · **Phase 3** finding 4 · **Fix** §4.5.3

`status` is `VARCHAR(20)` and accepted `not-a-real-status`. It is meant
to hold one of: new, contacted, viewed, closed.

Low severity because nothing reads the column yet. It stops being low
the moment an administrative view exists: a request whose status reads
"New" rather than "new" disappears from a filtered list, and nobody
notices a request nobody can see.

**Correction:** change the column to
`ENUM('new','contacted','viewed','closed')`.

---

## DEF-07 · Medium · The guardian hint promises an email that is never sent

**Found by** TC-60 · **Rule** NFR5 · **Phase 3** §3.6.1 · **Fix** §4.7.2

The hint beneath the guardian email field reads:

> "Optional. They receive the same confirmation and lease summary you do."

No email is sent by this release. The address is stored so staff can
include the guardian when following up, and nothing more.

This matters more than its severity suggests. Phase 3 weakness W5 is
that the parent who pays sees the costs last — so a parent told they
will receive a confirmation, and who does not, is failed by the very
feature meant to address that weakness.

The same claim appears in the "For parents" section of the landing page
and should be corrected with it.

**Correction (Phase 4 §4.7.2):**

> "Optional. We record it so the accommodation provider can include them
> when they get in touch."
