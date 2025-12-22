<?php

require __DIR__ . '/lib/db.php';

$pdo = db();
$error = '';
$success = '';
$editType = null;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($action === 'create') {
        if ($name === '' || $slug === '') {
            $error = 'Name and slug are required.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO notification_types (slug, name, description) VALUES (:slug, :name, :description)');
            $stmt->execute([
                ':slug' => $slug,
                ':name' => $name,
                ':description' => $description ?: null,
            ]);
            $success = 'Notification type created.';
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === 0 || $name === '' || $slug === '') {
            $error = 'Name and slug are required.';
        } else {
            $stmt = $pdo->prepare('UPDATE notification_types SET slug = :slug, name = :name, description = :description WHERE id = :id');
            $stmt->execute([
                ':slug' => $slug,
                ':name' => $name,
                ':description' => $description ?: null,
                ':id' => $id,
            ]);
            $success = 'Notification type updated.';
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM notification_types WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $success = 'Notification type deleted.';
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT id, slug, name, description FROM notification_types WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $editType = $stmt->fetch();
}

$types = $pdo->query('SELECT id, slug, name, description FROM notification_types ORDER BY name')->fetchAll();

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BeanPrepared Admin - Event Types</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  >
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="/Web/beanprepared/backend/admin_events.php">BeanPrepared Admin</a>
      <div class="navbar-nav">
        <a class="nav-link" href="/Web/beanprepared/backend/admin_submissions.php">Submissions</a>
        <a class="nav-link" href="/Web/beanprepared/backend/admin_events.php">Events</a>
        <a class="nav-link active" href="/Web/beanprepared/backend/admin_types.php">Event Types</a>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <div class="row g-4">
      <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-3"><?php echo $editType ? 'Edit Type' : 'Add Type'; ?></h5>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php elseif ($success !== ''): ?>
              <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="action" value="<?php echo $editType ? 'update' : 'create'; ?>">
              <?php if ($editType): ?>
                <input type="hidden" name="id" value="<?php echo h((string) $editType['id']); ?>">
              <?php endif; ?>

              <div class="mb-3">
                <label class="form-label">Name *</label>
                <input class="form-control" name="name" required value="<?php echo h($editType['name'] ?? ''); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Slug *</label>
                <input class="form-control" name="slug" required value="<?php echo h($editType['slug'] ?? ''); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"><?php echo h($editType['description'] ?? ''); ?></textarea>
              </div>

              <button type="submit" class="btn btn-warning fw-semibold">
                <?php echo $editType ? 'Update Type' : 'Create Type'; ?>
              </button>
              <?php if ($editType): ?>
                <a class="btn btn-link" href="admin_types.php">Cancel</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-3">Event Types</h5>
            <?php if (count($types) === 0): ?>
              <div class="alert alert-secondary">No types defined.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Slug</th>
                      <th>Description</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($types as $type): ?>
                      <tr>
                        <td><?php echo h($type['name']); ?></td>
                        <td><?php echo h($type['slug']); ?></td>
                        <td><?php echo h($type['description'] ?? ''); ?></td>
                        <td>
                          <a class="btn btn-sm btn-outline-primary" href="admin_types.php?edit=<?php echo h((string) $type['id']); ?>">Edit</a>
                          <form method="post" style="display:inline-block" onsubmit="return confirm('Delete this type?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo h((string) $type['id']); ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
