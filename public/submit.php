<?php

require __DIR__ . '/../backend/lib/db.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $startsAt = trim($_POST['starts_at'] ?? '');
    $endsAt = trim($_POST['ends_at'] ?? '');
    $isOrganizer = ($_POST['is_organizer'] ?? '') === 'yes';
    $contactConsent = ($_POST['contact_consent'] ?? '') === 'yes';
    $isOneOff = ($_POST['is_one_off'] ?? '') === 'yes';
    $repeatInterval = trim($_POST['repeat_interval'] ?? '');
    $repeatUnit = trim($_POST['repeat_unit'] ?? '');
    $repeatUntil = trim($_POST['repeat_until'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $website = trim($_POST['website'] ?? '');

    if ($name === '' || $email === '' || $startsAt === '' || $endsAt === '' || $description === '') {
        $error = 'Please fill all required fields.';
    } elseif ($isOrganizer && empty($_POST['contact_consent'])) {
        $error = 'Please confirm contact consent if you are running this event.';
    } elseif (!$isOneOff && ($repeatInterval === '' || $repeatUnit === '' || $repeatUntil === '')) {
        $error = 'Please complete the repeat schedule details.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO event_submissions (name, email, phone, starts_at, ends_at, is_organizer, contact_consent, is_one_off, repeat_interval, repeat_unit, repeat_until, description, website)
             VALUES (:name, :email, :phone, :starts_at, :ends_at, :is_organizer, :contact_consent, :is_one_off, :repeat_interval, :repeat_unit, :repeat_until, :description, :website)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone ?: null,
            ':starts_at' => $startsAt,
            ':ends_at' => $endsAt,
            ':is_organizer' => $isOrganizer ? 1 : 0,
            ':contact_consent' => $isOrganizer ? ($contactConsent ? 1 : 0) : null,
            ':is_one_off' => $isOneOff ? 1 : 0,
            ':repeat_interval' => $isOneOff ? null : (int) $repeatInterval,
            ':repeat_unit' => $isOneOff ? null : $repeatUnit,
            ':repeat_until' => $isOneOff ? null : $repeatUntil,
            ':description' => $description,
            ':website' => $website ?: null,
        ]);
        $success = true;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BeanPrepared - Submit Event</title>
  <link rel="icon" href="/Web/beanprepared/public/icon-512x512.png" sizes="512x512" type="image/png">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  >
  <link rel="stylesheet" href="/Web/beanprepared/public/css/site.css">
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="home.php">
        <img src="/Web/beanprepared/public/icon.png" alt="BeanPrepared" class="site-logo">
        <span>BeanPrepared</span>
      </a>
      <div class="navbar-nav">
        <a class="nav-link" href="home.php">Home</a>
        <a class="nav-link" href="index.php">Upcoming Events</a>
        <a class="nav-link active" href="submit.php">Submit Event</a>
      </div>
    </div>
  </nav>
  <main class="container py-4">
    <div class="mb-4">
      <h1 class="mb-1">Submit Event</h1>
      <p class="brand-subtitle">Share a new event request for BeanPrepared.</p>
    </div>
    <?php if ($success): ?>
      <div class="alert alert-success">Thanks! Your event has been submitted for review.</div>
    <?php elseif ($error !== ''): ?>
      <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm p-4">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="name" class="form-label">Your Name *</label>
          <input id="name" name="name" class="form-control" required value="<?php echo h($_POST['name'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
          <label for="email" class="form-label">Your Email Address *</label>
          <input id="email" name="email" type="email" class="form-control" required value="<?php echo h($_POST['email'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
          <label for="phone" class="form-label">Your Phone Number</label>
          <input id="phone" name="phone" class="form-control" placeholder="(01534) 123456" value="<?php echo h($_POST['phone'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
          <label for="starts_at" class="form-label">Start Date/Time *</label>
          <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" required value="<?php echo h($_POST['starts_at'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
          <label for="ends_at" class="form-label">End Date/Time *</label>
          <input id="ends_at" name="ends_at" type="datetime-local" class="form-control" required value="<?php echo h($_POST['ends_at'] ?? ''); ?>">
        </div>
        <div class="col-12">
          <label for="is_organizer" class="form-label">Are you running this event? *</label>
          <select id="is_organizer" name="is_organizer" class="form-select" required>
            <option value="" disabled <?php echo empty($_POST['is_organizer']) ? 'selected' : ''; ?>>Select...</option>
            <option value="yes" <?php echo ($_POST['is_organizer'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
            <option value="no" <?php echo ($_POST['is_organizer'] ?? '') === 'no' ? 'selected' : ''; ?>>No</option>
          </select>
        </div>
        <div class="col-12 <?php echo ($_POST['is_organizer'] ?? '') === 'yes' ? '' : 'd-none'; ?>" id="consentSection">
          <label for="contact_consent" class="form-label">Do you consent to making your contact details available to our users so they may contact you about the event? *</label>
          <select id="contact_consent" name="contact_consent" class="form-select" <?php echo ($_POST['is_organizer'] ?? '') === 'yes' ? 'required' : ''; ?>>
            <option value="" disabled <?php echo empty($_POST['contact_consent']) ? 'selected' : ''; ?>>Select...</option>
            <option value="yes" <?php echo ($_POST['contact_consent'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
            <option value="no" <?php echo ($_POST['contact_consent'] ?? '') === 'no' ? 'selected' : ''; ?>>No</option>
          </select>
        </div>
        <div class="col-12">
          <label for="is_one_off" class="form-label">Is this event a one-off? *</label>
          <select id="is_one_off" name="is_one_off" class="form-select" required>
            <option value="" disabled <?php echo empty($_POST['is_one_off']) ? 'selected' : ''; ?>>Select...</option>
            <option value="yes" <?php echo ($_POST['is_one_off'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
            <option value="no" <?php echo ($_POST['is_one_off'] ?? '') === 'no' ? 'selected' : ''; ?>>No</option>
          </select>
        </div>
        <div class="col-12 d-none" id="repeatSection">
          <div class="row g-3">
            <div class="col-md-4">
              <label for="repeat_interval" class="form-label">Every *</label>
              <input id="repeat_interval" name="repeat_interval" type="number" min="1" class="form-control" value="<?php echo h($_POST['repeat_interval'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
              <label for="repeat_unit" class="form-label">Frequency *</label>
              <select id="repeat_unit" name="repeat_unit" class="form-select">
                <option value="" disabled <?php echo empty($_POST['repeat_unit']) ? 'selected' : ''; ?>>Select...</option>
                <option value="daily" <?php echo ($_POST['repeat_unit'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                <option value="weekly" <?php echo ($_POST['repeat_unit'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                <option value="monthly" <?php echo ($_POST['repeat_unit'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="repeat_until" class="form-label">Until *</label>
              <input id="repeat_until" name="repeat_until" type="date" class="form-control" value="<?php echo h($_POST['repeat_until'] ?? ''); ?>">
            </div>
          </div>
        </div>
        <div class="col-12">
          <label for="description" class="form-label">Please give a short description of the event *</label>
          <textarea id="description" name="description" class="form-control" required><?php echo h($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="col-12">
          <label for="website" class="form-label">Website/Link to event</label>
          <input id="website" name="website" type="url" class="form-control" placeholder="https://" value="<?php echo h($_POST['website'] ?? ''); ?>">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-warning fw-semibold">Submit</button>
        </div>
      </div>
    </form>
  </main>
  <script>
    const organizerSelect = document.getElementById('is_organizer');
    const consentSection = document.getElementById('consentSection');
    const consentSelect = document.getElementById('contact_consent');
    const oneOffSelect = document.getElementById('is_one_off');
    const repeatSection = document.getElementById('repeatSection');
    const repeatInterval = document.getElementById('repeat_interval');
    const repeatUnit = document.getElementById('repeat_unit');
    const repeatUntil = document.getElementById('repeat_until');
    if (organizerSelect && consentSection) {
      organizerSelect.addEventListener('change', (event) => {
        const show = event.target.value === 'yes';
        consentSection.classList.toggle('d-none', !show);
        if (!show && consentSelect) {
          consentSelect.value = '';
        }
      });
    }
    if (oneOffSelect && repeatSection) {
      const toggleRepeat = (value) => {
        const show = value === 'no';
        repeatSection.classList.toggle('d-none', !show);
        if (!show) {
          if (repeatInterval) repeatInterval.value = '';
          if (repeatUnit) repeatUnit.value = '';
          if (repeatUntil) repeatUntil.value = '';
        }
        if (repeatInterval) repeatInterval.required = show;
        if (repeatUnit) repeatUnit.required = show;
        if (repeatUntil) repeatUntil.required = show;
      };
      toggleRepeat(oneOffSelect.value);
      oneOffSelect.addEventListener('change', (event) => toggleRepeat(event.target.value));
    }
  </script>
</body>
</html>
