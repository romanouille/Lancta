<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

$q = trim($_GET['q'] ?? '');
$viewerId = current_user_id();
$viewerRole = $_SESSION['user']['role'] ?? 'user';
$results = ['topics'=>[], 'users'=>[], 'messages'=>[]];

if ($q !== '') {
  $like = '%' . $q . '%';

  // Topics: include if not deleted, or if viewer is staff, or viewer is author
  if (in_array($viewerRole, ['mod','admin'])) {
    $st = $pdo->prepare('SELECT id, title FROM topics WHERE (title LIKE ? OR content LIKE ?) ORDER BY created_at DESC LIMIT 100');
    $st->execute([$like,$like]);
  } else if ($viewerId) {
    $st = $pdo->prepare('SELECT id, title FROM topics WHERE (title LIKE ? OR content LIKE ?) AND (deleted_at IS NULL OR user_id = ?) ORDER BY created_at DESC LIMIT 100');
    $st->execute([$like,$like,$viewerId]);
  } else {
    $st = $pdo->prepare('SELECT id, title FROM topics WHERE (title LIKE ? OR content LIKE ?) AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 100');
    $st->execute([$like,$like]);
  }
  $results['topics'] = $st->fetchAll(PDO::FETCH_ASSOC);

  // Users
  $su = $pdo->prepare('SELECT username FROM users WHERE username LIKE ? COLLATE NOCASE ORDER BY username LIMIT 50');
  $su->execute([$like]);
  $results['users'] = $su->fetchAll(PDO::FETCH_COLUMN);

  // Messages: show replies if not deleted, or if viewer is staff, or if viewer is reply author or topic author
  if (in_array($viewerRole, ['mod','admin'])) {
    $sm = $pdo->prepare('SELECT r.id, r.topic_id, substr(r.content,1,120) as snippet FROM replies r WHERE r.content LIKE ? ORDER BY r.created_at DESC LIMIT 200');
    $sm->execute([$like]);
  } else if ($viewerId) {
    $sm = $pdo->prepare('
      SELECT r.id, r.topic_id, substr(r.content,1,120) as snippet
      FROM replies r
      JOIN topics t ON t.id = r.topic_id
      WHERE r.content LIKE ? AND (r.deleted_at IS NULL OR r.user_id = ? OR t.user_id = ?)
      ORDER BY r.created_at DESC LIMIT 200
    ');
    $sm->execute([$like, $viewerId, $viewerId]);
  } else {
    $sm = $pdo->prepare('SELECT r.id, r.topic_id, substr(r.content,1,120) as snippet FROM replies r WHERE r.content LIKE ? AND r.deleted_at IS NULL ORDER BY r.created_at DESC LIMIT 200');
    $sm->execute([$like]);
  }
  $results['messages'] = $sm->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Recherche</h1>
<form method="get" class="mb-4">
  <input name="q" value="<?= e($q) ?>" class="px-3 py-2 rounded bg-slate-900 border border-slate-700 w-full max-w-xl" placeholder="Titre, auteur, message..."/>
</form>
<?php if ($q===''): ?>
  <div class="text-slate-400">Tapez une requête.</div>
<?php else: ?>
  <div class="grid md:grid-cols-3 gap-4">
    <div class="bg-slate-800 border border-slate-700 rounded p-3">
      <h2 class="font-semibold mb-2">Topics</h2>
      <ul class="space-y-1">
        <?php foreach ($results['topics'] as $t): ?><li><a class="text-blue-400 hover:underline" href="view_topic.php?id=<?= $t['id'] ?>"><?= e($t['title']) ?></a></li><?php endforeach; ?>
        <?php if (empty($results['topics'])): ?><li class="text-slate-400">Aucun résultat</li><?php endif; ?>
      </ul>
    </div>
    <div class="bg-slate-800 border border-slate-700 rounded p-3">
      <h2 class="font-semibold mb-2">Utilisateurs</h2>
      <ul class="space-y-1">
        <?php foreach ($results['users'] as $u): ?><li><a class="text-blue-400 hover:underline" href="profile.php?u=<?= e($u) ?>"><?= e($u) ?></a></li><?php endforeach; ?>
        <?php if (empty($results['users'])): ?><li class="text-slate-400">Aucun résultat</li><?php endif; ?>
      </ul>
    </div>
    <div class="bg-slate-800 border border-slate-700 rounded p-3">
      <h2 class="font-semibold mb-2">Messages</h2>
      <ul class="space-y-1">
        <?php foreach ($results['messages'] as $m): ?><li><a class="text-blue-400 hover:underline" href="view_topic.php?id=<?= $m['topic_id'] ?>#reply-<?= $m['id'] ?>"><?= e($m['snippet']) ?>…</a></li><?php endforeach; ?>
        <?php if (empty($results['messages'])): ?><li class="text-slate-400">Aucun résultat</li><?php endif; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
