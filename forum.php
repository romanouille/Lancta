<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM forums WHERE id = ?');
$stmt->execute([$id]);
$forum = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$forum) { http_response_code(404); die('Forum introuvable'); }

$page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$per = (int)$config['topics_per_page'];
$offset = ($page-1)*$per;

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM topics WHERE forum_id = ? AND deleted_at IS NULL');
$totalStmt->execute([$id]);
$total = (int)$totalStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per));

$topicsStmt = $pdo->prepare('
    SELECT t.*, u.username, u.role
    FROM topics t
    JOIN users u ON u.id = t.user_id
    WHERE t.forum_id = ? AND t.deleted_at IS NULL
    ORDER BY t.pinned DESC, t.created_at DESC
    LIMIT ? OFFSET ?
');
$topicsStmt->bindValue(1, $id, PDO::PARAM_INT);
$topicsStmt->bindValue(2, $per, PDO::PARAM_INT);
$topicsStmt->bindValue(3, $offset, PDO::PARAM_INT);
$topicsStmt->execute();
$topics = $topicsStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-4">
  <div>
    <a href="index.php" class="text-sm text-slate-400">&#8592; Forums</a>
    <h1 class="text-xl sm:text-2xl font-semibold"><?= e($forum['name']) ?></h1>
    <p class="text-slate-300 text-sm sm:text-base"><?= e($forum['description']) ?></p>
  </div>
  <?php if (is_logged_in()): ?><a href="new_topic.php?forum_id=<?= $forum['id'] ?>" class="px-4 py-2 bg-blue-600 text-white rounded w-full sm:w-auto text-center">Nouveau topic</a><?php endif; ?>
</div>

<div class="grid gap-3">
<?php foreach ($topics as $t): ?>
  <a href="view_topic.php?id=<?= $t['id'] ?>" class="block bg-slate-800 border border-slate-700 rounded-xl p-4 hover:shadow">
    <div class="flex items-center gap-2 flex-wrap">
      <?php if ($t['pinned']): ?><span class="text-xs px-2 py-1 bg-amber-200 text-amber-900 rounded-full">Épinglé</span><?php endif; ?>
      <?php if ($t['locked']): ?><span class="text-xs px-2 py-1 bg-slate-200 text-slate-700 rounded-full">Verrouillé</span><?php endif; ?>
    </div>
    <h2 class="text-base sm:text-lg font-bold mt-1"><?= e($t['title']) ?></h2>
    <p class="text-xs sm:text-sm text-slate-400">par <button type="button" data-username="<?= e($t['username']) ?>" class="underline decoration-dotted"><?= render_username($t['username'], $t['role']) ?></button> • <?= e(date('d/m/Y H:i', strtotime($t['created_at']))) ?></p>
  </a>
<?php endforeach; ?>
<?php if (empty($topics)): ?><div class="text-slate-400">Aucun topic pour l’instant.</div><?php endif; ?>
</div>
<?php
$baseUrl = 'forum.php?id=' . $forum['id'];
echo '<nav class="flex flex-wrap gap-2 mt-4">';
if ($page>1) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$baseUrl.'&page='.($page-1).'">Précédent</a>';
for($i=1;$i<=$total_pages;$i++){ $cl = $i==$page ? ' bg-slate-100 text-slate-900' : ''; echo '<a class="px-3 py-1 border border-slate-600 rounded'.$cl.'" href="'.$baseUrl.'&page='.$i.'">'.$i.'</a>'; }
if ($page<$total_pages) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$baseUrl.'&page='.($page+1).'">Suivant</a>';
echo '</nav>';
?>
<?php include __DIR__ . '/partials/footer.php'; ?>
