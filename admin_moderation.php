<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_admin()) redirect('index.php');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $action = $_POST['action'] ?? '';
  $username = trim($_POST['username'] ?? '');
  $reason = trim($_POST['reason'] ?? '');
  $duration = (int)($_POST['duration'] ?? 0);

  $u = $pdo->prepare('SELECT id, username FROM users WHERE username_canonical=?');
  $u->execute([canon($username)]);
  $user = $u->fetch(PDO::FETCH_ASSOC);
  if (!$user) { $msg = 'Utilisateur introuvable.'; }
  else {
    if ($action==='kick') {
      $until = date('c', time() + $duration*3600);
      $pdo->prepare('INSERT INTO moderation_actions (user_id, type, until, reason, created_by, created_at) VALUES (?, "kick", ?, ?, ?, ?)')->execute([$user['id'], $until, $reason, $_SESSION['user']['id'], date('c')]);
      $msg = 'Kick appliqué jusqu\'à ' . $until;
    } elseif ($action==='ban') {
      $pdo->prepare('INSERT INTO moderation_actions (user_id, type, until, reason, created_by, created_at) VALUES (?, "ban", NULL, ?, ?, ?)')->execute([$user['id'], $reason, $_SESSION['user']['id'], date('c')]);
      $msg = 'Utilisateur banni.';
    } elseif ($action==='nuke') {
      $pdo->prepare('UPDATE replies SET deleted_at = ? WHERE user_id = ? AND deleted_at IS NULL')->execute([date('c'), $user['id']]);
      $pdo->prepare('UPDATE topics SET deleted_at = ? WHERE user_id = ? AND deleted_at IS NULL')->execute([date('c'), $user['id']]);
      $pdo->prepare('INSERT INTO moderation_actions (user_id, type, until, reason, created_by, created_at) VALUES (?, "ban", NULL, ?, ?, ?)')->execute([$user['id'], $reason ?: 'nuke', $_SESSION['user']['id'], date('c')]);
      $msg = 'NUKE effectuée.';
    }
  }
}

$actions = $pdo->query('SELECT m.*, u.username FROM moderation_actions m JOIN users u ON u.id=m.user_id ORDER BY m.created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Modération — Kick / Ban / Nuke</h1>
<?php if ($msg): ?><div class="mb-3 p-3 bg-emerald-900/40 text-emerald-200 rounded"><?= e($msg) ?></div><?php endif; ?>

<div class="grid md:grid-cols-3 gap-4">
  <div class="bg-slate-800 border border-slate-700 rounded p-4">
    <h2 class="font-semibold mb-2">Kick (temporaire)</h2>
    <form method="post" class="grid gap-2">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="action" value="kick"/>
      <input name="username" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="pseudo (insensible à la casse)" required/>
      <input name="duration" type="number" min="1" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="Durée (heures)" required/>
      <input name="reason" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="Raison (optionnel)"/>
      <button class="px-3 py-1 rounded bg-slate-700">Appliquer</button>
    </form>
  </div>
  <div class="bg-slate-800 border border-slate-700 rounded p-4">
    <h2 class="font-semibold mb-2">Ban</h2>
    <form method="post" class="grid gap-2">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="action" value="ban"/>
      <input name="username" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="pseudo" required/>
      <input name="reason" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="Raison (optionnel)"/>
      <button class="px-3 py-1 rounded bg-slate-700">Bannir</button>
    </form>
  </div>
  <div class="bg-slate-800 border border-slate-700 rounded p-4">
    <h2 class="font-semibold mb-2">Nuke (soft-suppr + ban)</h2>
    <form method="post" class="grid gap-2">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="action" value="nuke"/>
      <input name="username" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="pseudo" required/>
      <input name="reason" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="Raison (optionnel)"/>
      <button class="px-3 py-1 rounded bg-red-700 text-white">NUKE</button>
    </form>
  </div>
</div>

<h2 class="text-xl font-semibold mt-6 mb-2">Historique</h2>
<div class="bg-slate-800 border border-slate-700 rounded p-4 overflow-x-auto">
  <table class="w-full text-sm">
    <thead><tr class="text-left border-b border-slate-700"><th class="py-2">Date</th><th>Utilisateur</th><th>Type</th><th>Jusqu'à</th><th>Raison</th></tr></thead>
    <tbody>
    <?php foreach ($actions as $a): ?>
      <tr class="border-b border-slate-700"><td class="py-2"><?= e(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td><td><?= e($a['username']) ?></td><td><?= e($a['type']) ?></td><td><?= e($a['until'] ?? '') ?></td><td><?= e($a['reason'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
