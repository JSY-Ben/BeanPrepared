<?php

require __DIR__ . '/lib/db.php';

$statusFilter = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes = trim($_POST['admin_notes'] ?? '');
    $statusAllowed = ['pending', 'approved', 'rejected'];

    if ($id > 0 && in_array($status, $statusAllowed, true)) {
        $stmt = db()->prepare('UPDATE event_submissions SET status = :status, admin_notes = :admin_notes WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':admin_notes' => $notes === '' ? null : $notes,
            ':id' => $id,
        ]);
    }

    header('Location: admin_submissions.php?status=' . urlencode($statusFilter));
    exit;
}

$query = 'SELECT id, name, email, phone, starts_at, is_organizer, status, admin_notes, description, website, created_at FROM event_submissions';
$params = [];
if ($statusFilter !== 'all') {
    $query .= ' WHERE status = :status';
    $params[':status'] = $statusFilter;
}
$query .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($query);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OnTheRock Admin - Event Submissions</title>
  <style>
    :root {
      color-scheme: light dark;
    }
    body {
      margin: 0;
      font-family: "Georgia", "Times New Roman", serif;
      background: #f7f4ee;
      color: #2e2a24;
    }
    header {
      padding: 32px 24px 16px;
    }
    h1 {
      margin: 0;
      font-size: 28px;
    }
    p {
      margin: 6px 0 0;
      color: #6a6256;
    }
    main {
      padding: 0 24px 40px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
    }
    th, td {
      padding: 14px 16px;
      border-bottom: 1px solid #efeae0;
      text-align: left;
      vertical-align: top;
      font-size: 14px;
    }
    th {
      background: #f6f0dd;
      font-weight: 600;
    }
    tr:last-child td {
      border-bottom: none;
    }
    .tag {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      background: #f6f0dd;
      font-size: 12px;
      color: #7a5a00;
    }
    .muted {
      color: #6a6256;
      font-size: 12px;
    }
    .empty {
      padding: 24px;
      background: #ffffff;
      border-radius: 12px;
      text-align: center;
      color: #6a6256;
    }
    .filters a {
      margin-right: 10px;
      text-decoration: none;
      color: #7a5a00;
      font-weight: 600;
    }
    .filters .active {
      color: #2e2a24;
    }
    .notes {
      width: 100%;
      min-height: 80px;
      border-radius: 10px;
      border: 1px solid #efeae0;
      padding: 8px;
      font-family: inherit;
      font-size: 13px;
    }
    .status-select {
      padding: 6px 8px;
      border-radius: 8px;
      border: 1px solid #efeae0;
      font-size: 13px;
      margin-bottom: 8px;
    }
    .save-btn {
      background: #c9a227;
      color: #1f1a12;
      border: none;
      padding: 6px 12px;
      border-radius: 999px;
      font-weight: 600;
      cursor: pointer;
      font-size: 12px;
    }
  </style>
</head>
<body>
  <header>
    <h1>Event Submissions</h1>
    <p><?php echo count($submissions); ?> submissions</p>
    <div class="filters">
      <a class="<?php echo $statusFilter === 'all' ? 'active' : ''; ?>" href="?status=all">All</a>
      <a class="<?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="?status=pending">Pending</a>
      <a class="<?php echo $statusFilter === 'approved' ? 'active' : ''; ?>" href="?status=approved">Approved</a>
      <a class="<?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" href="?status=rejected">Rejected</a>
    </div>
  </header>
  <main>
    <?php if (count($submissions) === 0): ?>
      <div class="empty">No submissions yet.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Submitted</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Event Time</th>
            <th>Organizer</th>
            <th>Description</th>
            <th>Website</th>
            <th>Status / Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($submissions as $submission): ?>
            <tr>
              <td>
                <div><?php echo h($submission['created_at']); ?></div>
                <div class="muted">ID: <?php echo h((string) $submission['id']); ?></div>
              </td>
              <td><?php echo h($submission['name']); ?></td>
              <td>
                <div><?php echo h($submission['email']); ?></div>
                <?php if (!empty($submission['phone'])): ?>
                  <div class="muted"><?php echo h($submission['phone']); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo h($submission['starts_at']); ?></td>
              <td>
                <span class="tag">
                  <?php echo $submission['is_organizer'] ? 'Yes' : 'No'; ?>
                </span>
              </td>
              <td><?php echo nl2br(h($submission['description'])); ?></td>
              <td>
                <?php if (!empty($submission['website'])): ?>
                  <a href="<?php echo h($submission['website']); ?>" target="_blank" rel="noreferrer">
                    <?php echo h($submission['website']); ?>
                  </a>
                <?php else: ?>
                  <span class="muted">N/A</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post">
                  <input type="hidden" name="id" value="<?php echo h((string) $submission['id']); ?>">
                  <select class="status-select" name="status">
                    <option value="pending" <?php echo $submission['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $submission['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $submission['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                  </select>
                  <textarea class="notes" name="admin_notes" placeholder="Admin notes"><?php echo h($submission['admin_notes'] ?? ''); ?></textarea>
                  <button class="save-btn" type="submit">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </main>
</body>
</html>
