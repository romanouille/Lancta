<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
include __DIR__ . '/partials/header.php';
$forums = $pdo->query('SELECT f.*, (SELECT COUNT(*) FROM topics t WHERE t.forum_id=f.id AND t.deleted_at IS NULL) as topics_count FROM forums f ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<h1 class="text-xl sm:text-2xl font-semibold mb-4">Forums</h1>
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($forums as $f): ?>
  <a href="forum.php?id=<?= $f['id'] ?>" class="block bg-slate-800 border border-slate-700 rounded-xl p-4 hover:shadow">
    <h2 class="text-base sm:text-lg font-bold"><?= e($f['name']) ?></h2>
    <p class="text-slate-300 text-sm sm:text-base"><?= e($f['description']) ?></p>
    <p class="text-xs sm:text-sm text-slate-400 mt-1"><?= (int)$f['topics_count'] ?> topics</p>
  </a>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
