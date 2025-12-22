<?php

require __DIR__ . '/../lib/db.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $startsAt = trim($_POST['starts_at'] ?? '');
    $isOrganizer = ($_POST['is_organizer'] ?? '') === 'yes';
    $description = trim($_POST['description'] ?? '');
    $website = trim($_POST['website'] ?? '');

    if ($name === '' || $email === '' || $startsAt === '' || $description === '') {
        $error = 'Please fill all required fields.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO event_submissions (name, email, phone, starts_at, is_organizer, description, website)
             VALUES (:name, :email, :phone, :starts_at, :is_organizer, :description, :website)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone ?: null,
            ':starts_at' => $startsAt,
            ':is_organizer' => $isOrganizer ? 1 : 0,
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
  <title>OnTheRock - Submit Event</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  >
  <style>
    body {
      background: #f7f4ee;
    }
    .brand-subtitle {
      color: #6a6256;
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="index.php">OnTheRock</a>
      <div class="navbar-nav">
        <a class="nav-link" href="index.php">Upcoming Events</a>
        <a class="nav-link active" href="submit.php">Submit Event</a>
      </div>
    </div>
  </nav>
  <main class="container py-4">
    <div class="mb-4">
      <h1 class="mb-1">Submit Event</h1>
      <p class="brand-subtitle">Share a new event request for OnTheRock.</p>
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
          <label for="starts_at" class="form-label">Date/Time of Event *</label>
          <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" required value="<?php echo h($_POST['starts_at'] ?? ''); ?>">
        </div>
        <div class="col-12">
          <label for="is_organizer" class="form-label">Are you running this event? *</label>
          <select id="is_organizer" name="is_organizer" class="form-select" required>
            <option value="" disabled <?php echo empty($_POST['is_organizer']) ? 'selected' : ''; ?>>Select...</option>
            <option value="yes" <?php echo ($_POST['is_organizer'] ?? '') === 'yes' ? 'selected' : ''; ?>>Yes</option>
            <option value="no" <?php echo ($_POST['is_organizer'] ?? '') === 'no' ? 'selected' : ''; ?>>No</option>
          </select>
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
</body>
</html>
