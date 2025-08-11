<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

$username = trim($_GET['u'] ?? '');
$stmt = $pdo->prepare('SELECT id, username, role, avatar, created_at FROM users WHERE username_canonical = ?');
$stmt->execute([canon($username)]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { http_response_code(404); die('Utilisateur introuvable'); }

$topics = $pdo->prepare('SELECT id, title, created_at FROM topics WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 20');
$topics->execute([$user['id']]);
$topics = $topics->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<div class="flex items-center gap-4 mb-4">
  <img src="<?= e($user['avatar'] ?? '') ?>" onerror="this.src=''; this.classList.add('hidden')" class="w-16 h-16 rounded-full border border-slate-600" alt="avatar"/>
  <div>
    <h1 class="text-2xl font-semibold mb-1"><?= render_username($user['username'], $user['role']) ?></h1>
    <p class="text-slate-300">Inscription : <?= e(date('d/m/Y', strtotime($user['created_at']))) ?></p>
  </div>
</div>

<?php if (is_logged_in() && $_SESSION['user']['id']==$user['id']): ?>
  <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 mb-6">
    <h2 class="font-semibold mb-2">Mon avatar</h2>
    <form method="post" action="profile_avatar.php" enctype="multipart/form-data" class="grid gap-2">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
      <input type="file" name="avatar" accept="image/png,image/jpeg" required class="text-sm"/>
      <button class="px-3 py-1 rounded bg-slate-700">Uploader</button>
      <p class="text-xs text-slate-400">JPG/PNG, redimensionné à <?= (int)$config['avatar_size'] ?>x<?= (int)$config['avatar_size'] ?>.</p>
    </form>
  </div>
<?php endif; ?>

<h2 class="text-xl font-semibold mb-2">Derniers topics</h2>
<ul class="list-disc pl-5">
<?php foreach ($topics as $t): ?><li><a class="text-blue-400 hover:underline" href="view_topic.php?id=<?= $t['id'] ?>"><?= e($t['title']) ?></a> <span class="text-slate-400 text-sm">(<?= e(date('d/m/Y', strtotime($t['created_at']))) ?>)</span></li><?php endforeach; ?>
<?php if (empty($topics)): ?><li class="text-slate-400">Aucun topic.</li><?php endif; ?>
</ul>
<?php include __DIR__ . '/partials/footer.php'; ?>
