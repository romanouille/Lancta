<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_admin()) redirect('index.php');

$username = trim($_GET['u'] ?? '');
$user = null;
$topics = $replies = $pms = [];
if ($username !== '') {
  $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username_canonical = ?');
  $stmt->execute([canon($username)]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($user) {
    $t = $pdo->prepare('SELECT id, title, created_at, source_ip, source_port FROM topics WHERE user_id = ? ORDER BY created_at DESC LIMIT 500');
    $t->execute([$user['id']]);
    $topics = $t->fetchAll(PDO::FETCH_ASSOC);

    $r = $pdo->prepare('SELECT id, topic_id, created_at, source_ip, source_port, substr(content,1,120) as snippet FROM replies WHERE user_id = ? ORDER BY created_at DESC LIMIT 1000');
    $r->execute([$user['id']]);
    $replies = $r->fetchAll(PDO::FETCH_ASSOC);

    $m = $pdo->prepare('SELECT id, conversation_id, created_at, source_ip, source_port, substr(content,1,120) as snippet FROM conversation_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 1000');
    $m->execute([$user['id']]);
    $pms = $m->fetchAll(PDO::FETCH_ASSOC);
  }
}

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Audit IP / Ports</h1>

<form method="get" class="mb-4 flex gap-2">
  <input name="u" value="<?= e($username) ?>" placeholder="Pseudo à auditer" class="px-3 py-2 rounded bg-slate-900 border border-slate-700 w-64"/>
  <button class="px-3 py-2 rounded bg-slate-700">Voir</button>
</form>

<?php if ($username !== '' && !$user): ?>
  <div class="p-3 bg-red-900/40 text-red-200 rounded">Utilisateur introuvable.</div>
<?php endif; ?>

<?php if ($user): ?>
  <div class="mb-6">Utilisateur : <?= render_username($user['username'], $user['role']) ?></div>

  <div class="grid md:grid-cols-3 gap-4">
    <div class="bg-slate-800 border border-slate-700 rounded p-3 overflow-x-auto">
      <h2 class="font-semibold mb-2">Topics (max 500)</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left border-b border-slate-700"><th>Créé</th><th>Titre</th><th>IP</th><th>Port</th></tr></thead>
        <tbody>
        <?php foreach ($topics as $t): ?>
          <tr class="border-b border-slate-800"><td class="py-1"><?= e(date('d/m/Y H:i', strtotime($t['created_at']))) ?></td><td><a class="text-blue-400 underline" href="view_topic.php?id=<?= $t['id'] ?>"><?= e($t['title']) ?></a></td><td><?= e($t['source_ip']) ?></td><td><?= e($t['source_port']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($topics)): ?><tr><td colspan="4" class="py-2 text-slate-400">—</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="bg-slate-800 border border-slate-700 rounded p-3 overflow-x-auto">
      <h2 class="font-semibold mb-2">Réponses (max 1000)</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left border-b border-slate-700"><th>Créé</th><th>Topic</th><th>Excerpt</th><th>IP</th><th>Port</th></tr></thead>
        <tbody>
        <?php foreach ($replies as $r): ?>
          <tr class="border-b border-slate-800"><td class="py-1"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td><td><a class="text-blue-400 underline" href="view_topic.php?id=<?= $r['topic_id'] ?>#reply-<?= $r['id'] ?>">#<?= $r['topic_id'] ?></a></td><td><?= e($r['snippet']) ?>…</td><td><?= e($r['source_ip']) ?></td><td><?= e($r['source_port']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($replies)): ?><tr><td colspan="5" class="py-2 text-slate-400">—</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="bg-slate-800 border border-slate-700 rounded p-3 overflow-x-auto">
      <h2 class="font-semibold mb-2">Messages privés (max 1000)</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left border-b border-slate-700"><th>Créé</th><th>Conv.</th><th>Excerpt</th><th>IP</th><th>Port</th></tr></thead>
        <tbody>
        <?php foreach ($pms as $m): ?>
          <tr class="border-b border-slate-800"><td class="py-1"><?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></td><td><a class="text-blue-400 underline" href="conversation.php?id=<?= $m['conversation_id'] ?>">#<?= $m['conversation_id'] ?></a></td><td><?= e($m['snippet']) ?>…</td><td><?= e($m['source_ip']) ?></td><td><?= e($m['source_port']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($pms)): ?><tr><td colspan="5" class="py-2 text-slate-400">—</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
