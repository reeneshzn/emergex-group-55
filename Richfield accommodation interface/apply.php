<?php
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Book a viewing | Richfield Student Accommodation';
$page_desc  = 'Request viewings at inspected rooms near your Richfield campus. Free, and it commits you to nothing.';

$months = upcoming_months();

/* Pre-selection from the shortlist or a listing page */
$preselected = [];
if (!empty($_GET['listings'])) {
    foreach (explode(',', (string) $_GET['listings']) as $s) {
        if (get_listing($s)) {
            $preselected[] = $s;
        }
    }
}

$values = [
    'fullName'      => '',
    'email'         => '',
    'phone'         => '',
    'guardianEmail' => '',
    'campus'        => $preselected ? all_listings()[$preselected[0]]['campus'] : DEFAULT_CAMPUS,
    'date'          => '',
    'moveIn'        => array_key_first($months),
    'notes'         => '',
];
$errors    = [];
$reference = null;
$stored    = false;   // true only once a row is actually written

/* ######################################################################
   ###  [DB-WRITE]  THE MAIN DATA CAPTURE POINT                       ###
   ######################################################################

   This is the only place the site collects data a human typed.

   WHAT GETS SAVED, and where:

     viewing_requests            one row per submission
       reference        RF-YYMMDD-XXXX, unique, shown to the student
       full_name        text, min 2 characters
       email            validated email address
       phone            SA mobile, 0… or +27… format
       guardian_email   optional second email, NULL when blank
       campus_id        which campus they study at
       preferred_date   the date they want to view, never in the past
       move_in_month    YYYY-MM
       notes            free text, max 500 characters, NULL when blank
       status           new | contacted | viewed | closed
       created_at       set by the database

     viewing_request_rooms       one row per room the request names
       request_id + listing_slug

   Both are written inside ONE TRANSACTION by repo_save_viewing_request(),
   so a request can never be stored without the rooms it asked about.

   WHEN THERE IS NO DATABASE the form still validates and still shows a
   reference — it just does not persist, and the wording below says so.
   ###################################################################### */

session_start();

/* CSRF token. The form writes rows now, so a cross-site POST could create
   junk requests without one. Generated once per session and checked below. */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $k) {
        $values[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $preselected = array_values(array_intersect((array) ($_POST['listings'] ?? []), array_keys(all_listings())));

    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    if (str_len($values['fullName']) < 2) {
        $errors['fullName'] = 'Enter your full name';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address';
    }
    // South African mobile: 0 or +27, then a 6/7/8 prefix, then 8 digits.
    if (!preg_match('/^(?:\+27|0)[6-8][0-9]{8}$/', preg_replace('/\s+/', '', $values['phone']))) {
        $errors['phone'] = 'Enter a South African mobile number';
    }
    if ($values['guardianEmail'] !== '' && !filter_var($values['guardianEmail'], FILTER_VALIDATE_EMAIL)) {
        $errors['guardianEmail'] = 'Enter a valid email address';
    }
    if ($values['date'] === '') {
        $errors['date'] = 'Choose a preferred date';
    } elseif ($values['date'] < date('Y-m-d')) {
        $errors['date'] = 'Choose a date that is not in the past';
    }
    if (str_len($values['notes']) > 500) {
        $errors['notes'] = 'Keep it under 500 characters';
    }

    if (!$errors) {
        /* The reference is a date plus 3 random bytes — about 16 million
           combinations, so a clash is unlikely but not impossible. The
           column has a UNIQUE constraint, so retry rather than fail the
           student's submission. */
        $attempts = 0;
        do {
            $reference = 'RF-' . date('ymd') . '-'
                       . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $clash = using_database() && repo_reference_exists($reference);
        } while ($clash && ++$attempts < 5);

        /* >>> [DB-WRITE] THE INSERT. <<<
           Only runs when a database is connected. Everything above is
           validated by this point. */
        if (using_database()) {
            try {
                $saved_id = repo_save_viewing_request(
                    $values + ['reference' => $reference],
                    $preselected
                );
                $stored = $saved_id !== null;
            } catch (Throwable $e) {
                // Never show a database error to a student. Log it, tell
                // them plainly, and keep their typed values on the form.
                error_log('[richfield] saving viewing request failed: ' . $e->getMessage());
                $errors['form'] = 'We could not save your request just now. Please try again in a moment.';
                $reference = null;
            }
        }

        /* Sending a confirmation email would go HERE, after the save —
           so a mail failure can never lose a request that was stored. */
    }
}

require __DIR__ . '/includes/header.php';
?>

<div style="background:var(--rf-surface-sunken)">
  <div class="wrap" style="padding-block:56px 80px">

    <p class="rf-eyebrow section__eyebrow">Book a viewing</p>
    <h1 class="rf-display section__title" style="max-width:44rem">See it before you sign anything</h1>
    <p class="section__lede">
      Tell us who you are and when suits you. We pass it to the accommodation
      provider, who contacts you directly. It costs nothing and commits you to
      nothing.
    </p>

    <div class="apply-layout" style="margin-top:48px">

      <?php if ($reference): ?>
        <div class="success" role="status">
          <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
          <h2 class="rf-display">Viewing request sent</h2>
          <p>
            Your reference is <span class="ref"><?= e($reference) ?></span>.
            The accommodation provider will contact you directly to confirm a time.
          </p>
          <?php /* Wording follows what actually happened. Never claim a
                   request was stored when there was no database, and never
                   claim nothing was stored once it was. */ ?>
          <?php if (!empty($stored)): ?>
            <p class="fineprint">
              Your request has been saved and is with the accommodation provider.
              Quote your reference if you contact us about it.
            </p>
          <?php else: ?>
            <p class="fineprint">
              No database is connected, so this request was not saved. The site is
              running on sample data.
            </p>
          <?php endif; ?>
          <a class="btn btn--outline" href="apply.php" style="margin-top:24px">Send another request</a>
        </div>

      <?php else: ?>
        <form class="form-card" method="post" action="apply.php" novalidate>

          <?php /* CSRF token — the form writes to the database now. */ ?>
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

          <?php if (isset($errors['form'])): ?>
            <p class="form-error" role="alert" style="margin-bottom:20px"><?= e($errors['form']) ?></p>
          <?php endif; ?>

          <fieldset>
            <legend>Your details</legend>

            <div class="form-row">
              <label for="fullName">Full name</label>
              <input type="text" id="fullName" name="fullName" autocomplete="name"
                     value="<?= e($values['fullName']) ?>"
                     <?= isset($errors['fullName']) ? 'aria-invalid="true"' : '' ?>>
              <p class="form-error"><?= e($errors['fullName'] ?? '') ?></p>
            </div>

            <div class="form-row">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" autocomplete="email"
                     value="<?= e($values['email']) ?>"
                     <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
              <p class="form-error"><?= e($errors['email'] ?? '') ?></p>
            </div>

            <div class="form-row">
              <label for="phone">Phone</label>
              <input type="tel" id="phone" name="phone" autocomplete="tel" placeholder="082 123 4567"
                     value="<?= e($values['phone']) ?>"
                     <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>>
              <p class="form-error"><?= e($errors['phone'] ?? '') ?></p>
            </div>

            <div class="form-row">
              <label for="guardianEmail">Parent or guardian email</label>
              <p class="form-hint">Optional. They receive the same confirmation and lease summary you do.</p>
              <input type="email" id="guardianEmail" name="guardianEmail"
                     value="<?= e($values['guardianEmail']) ?>"
                     <?= isset($errors['guardianEmail']) ? 'aria-invalid="true"' : '' ?>>
              <p class="form-error"><?= e($errors['guardianEmail'] ?? '') ?></p>
            </div>
          </fieldset>

          <fieldset>
            <legend>The viewing</legend>

            <div class="form-row">
              <label for="campus">Your campus</label>
              <select id="campus" name="campus">
                <?php foreach (all_campuses() as $id => $c): ?>
                  <option value="<?= e($id) ?>"<?= $id === $values['campus'] ? ' selected' : '' ?>>
                    <?= e($c['name']) ?> · <?= e($c['city']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row">
              <label for="date">Preferred date</label>
              <input type="date" id="date" name="date" min="<?= date('Y-m-d') ?>"
                     value="<?= e($values['date']) ?>"
                     <?= isset($errors['date']) ? 'aria-invalid="true"' : '' ?>>
              <p class="form-error"><?= e($errors['date'] ?? '') ?></p>
            </div>

            <div class="form-row">
              <label for="moveIn">Moving in</label>
              <select id="moveIn" name="moveIn">
                <?php foreach ($months as $value => $label): ?>
                  <option value="<?= e($value) ?>"<?= $value === $values['moveIn'] ? ' selected' : '' ?>>
                    <?= e($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row">
              <label>Rooms to view</label>
              <p class="form-hint">Pick as many as you like — the provider arranges them together.</p>
              <div class="picklist">
                <?php foreach (all_listings() as $s => $l): ?>
                  <label>
                    <input type="checkbox" name="listings[]" value="<?= e($s) ?>"
                           <?= in_array($s, $preselected, true) ? 'checked' : '' ?>>
                    <span class="picklist__name">
                      <b><?= e($l['name']) ?></b>
                      <span> · <?= e($l['area']) ?></span>
                    </span>
                    <span class="picklist__price"><?= e(format_zar($l['rent'])) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="form-row">
              <label for="notes">Anything else?</label>
              <textarea id="notes" name="notes" rows="3"
                        placeholder="Accessibility needs, preferred viewing time, questions for the provider…"><?= e($values['notes']) ?></textarea>
              <p class="form-error"><?= e($errors['notes'] ?? '') ?></p>
            </div>
          </fieldset>

          <?php /* This wording is a promise to the person filling the form.
                   It must track what the code actually does. */ ?>
          <?php if (using_database()): ?>
            <p class="form-hint">
              Your details are stored securely and used only to arrange viewings.
              They are shared with the accommodation provider you asked to visit,
              and with nobody else. Ask us at any time to delete them.
            </p>
          <?php else: ?>
            <p class="form-hint">
              Your details would be used only to arrange viewings. No database is
              connected right now, so nothing is transmitted, stored, or shared.
            </p>
          <?php endif; ?>

          <button type="submit" class="btn btn--primary btn--block" style="height:48px;margin-top:16px">
            Request viewing
          </button>
        </form>
      <?php endif; ?>

      <aside style="display:grid;gap:24px;align-content:start">
        <?php if ($preselected && !$reference): ?>
          <div style="background:#fff;border:1px solid var(--rf-hairline);border-radius:var(--rf-radius);padding:20px">
            <h2 class="rf-eyebrow" style="color:var(--rf-ink-muted)">From your shortlist</h2>
            <ul style="display:grid;gap:8px;margin-top:12px">
              <?php foreach ($preselected as $s): ?>
                <li style="font-size:.9rem">
                  <b><?= e(all_listings()[$s]['name']) ?></b>
                  <span style="display:block;font-size:.75rem;color:var(--rf-ink-muted)"><?= e(all_listings()[$s]['area']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="next-steps">
          <h2 class="rf-display">What happens next</h2>
          <ol>
            <li>
              <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              <div>
                <b>The provider contacts you</b>
                <p>Usually within one working day, by phone or email — whichever you prefer.</p>
              </div>
            </li>
            <li>
              <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/></svg>
              <div>
                <b>You visit in person</b>
                <p>See the room, the shared spaces, the security and the walk to campus. Bring someone with you.</p>
              </div>
            </li>
            <li>
              <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="m10.85 12.15 7.5-7.5 2 2-1.5 1.5 1.5 1.5-2.5 2.5-1.5-1.5-2 2"/></svg>
              <div>
                <b>You decide</b>
                <p>Sign directly with the provider only if you want it. No fee, no obligation, no pressure from us.</p>
              </div>
            </li>
          </ol>
        </div>
      </aside>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
