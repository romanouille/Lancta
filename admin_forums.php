<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_admin()) redirect('index.php');

$msg = '';
if (isset($_POST['action']) && $_POST['action']==='add') {
  check_csrf();
  $name = trim($_POST['name'] ?? '');
  $desc = trim($_POST['description'] ?? '');
  if ($name === '') { $msg = 'Le nom est requis.'; }
  else { $pdo->prepare('INSERT INTO forums (name, description, created_at) VALUES (?, ?, ?)')->execute([$name, $desc, date('c')]); $msg = 'Forum ajouté.'; }
}
if (isset($_POST['action']) && $_POST['action']==='delete') {
  check_csrf();
  $fid = (int)($_POST['forum_id'] ?? 0);
  $stmtC = $pdo->prepare('SELECT COUNT(*) FROM topics WHERE forum_id = ?');
  $stmtC->execute([$fid]);
  $has = (int)$stmtC->fetchColumn();
  if ($has > 0) $msg = 'Impossible de supprimer un forum non vide.';
  else { $pdo->prepare('DELETE FROM forums WHERE id = ?')->execute([$fid]); $msg='Forum supprimé.'; }
}
$forums = $pdo->query('SELECT f.*, (SELECT COUNT(*) FROM topics t WHERE t.forum_id=f.id) as topics_count FROM forums f ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Administration — Forums</h1>
<?php if ($msg): ?><div class="mb-3 p-3 bg-emerald-900/40 text-emerald-200 rounded"><?= e($msg) ?></div><?php endif; ?>

<div class="bg-slate-800 border border-slate-700 rounded p-4 mb-6">
  <h2 class="font-semibold mb-3">Ajouter un forum</h2>
  <form method="post" class="grid gap-3 max-w-lg">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
    <input type="hidden" name="action" value="add"/>
    <label class="grid gap-1"><span>Nom</span><input name="name" class="border border-slate-600 rounded p-2 bg-slate-900" required/></label>
    <label class="grid gap-1"><span>Description</span><input name="description" class="border border-slate-600 rounded p-2 bg-slate-900"/></label>
    <button class="px-4 py-2 bg-blue-600 text-white rounded">Créer le forum</button>
  </form>
</div>

<div class="bg-slate-800 border border-slate-700 rounded p-4 overflow-x-auto">
  <table class="w-full text-sm">
    <thead><tr class="text-left border-b border-slate-700"><th class="py-2">ID</th><th>Nom</th><th>Description</th><th>Topics</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($forums as $f): ?>
      <tr class="border-b border-slate-700"><td class="py-2"><?= $f['id'] ?></td><td><?= e($f['name']) ?></td><td><?= e($f['description']) ?></td><td><?= (int)$f['topics_count'] ?></td>
        <td><form method="post" onsubmit="return confirm('Supprimer ce forum ? (autorisé seulement s\'il est vide)')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/><input type="hidden" name="action" value="delete"/><input type="hidden" name="forum_id" value="<?= $f['id'] ?>"/><button class="px-3 py-1 rounded bg-red-700 text-white" <?= $f['topics_count']>0?'disabled title="Forum non vide"':''; ?>>Supprimer</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
