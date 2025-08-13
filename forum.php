<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

// Helper (local) for avatar rendering with initials fallback
function render_avatar(?string $avatarPath, string $username, string $classes = 'w-10 h-10') {
  $avatarPath = trim((string)$avatarPath);
  if ($avatarPath) {
    echo '<img src="'.e($avatarPath).'" alt="avatar" class="'.$classes.' rounded-xl object-cover ring-1 ring-white/10 bg-slate-800" />';
  } else {
    $initial = mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8');
    echo '<div class="'.$classes.' rounded-xl grid place-items-center bg-gradient-to-br from-slate-700 to-slate-600 text-slate-200 ring-1 ring-white/10">'.e($initial).'</div>';
  }
}

$viewer = current_user();
$viewerId = current_user_id();
$viewerRole = $viewer['role'] ?? 'user';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM forums WHERE id = ?');
$stmt->execute([$id]);
$forum = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$forum) { http_response_code(404); die('Forum introuvable'); }

// Fetch topics with author avatar
$tstmt = $pdo->prepare('SELECT t.*, u.username, u.role, u.avatar FROM topics t JOIN users u ON u.id=t.user_id WHERE t.forum_id = ?');
$tstmt->execute([$id]);
$topicsRaw = $tstmt->fetchAll(PDO::FETCH_ASSOC);

// Filter visibility and collect ids
$topics = [];
$topicIds = [];
foreach ($topicsRaw as $t) {
  if (topic_is_visible_to_viewer($t, $viewerId, $viewerRole)) {
    $topics[$t['id']] = $t;
    $topicIds[] = (int)$t['id'];
  }
}

include __DIR__ . '/partials/header.php';

if (!$topics) {
  ?>
  <div class="flex items-center justify-between mb-4">
    <div>
      <a href="index.php" class="text-sm text-slate-400">&#8592; Forums</a>
      <h1 class="text-2xl font-semibold"><?= e($forum['name']) ?></h1>
      <p class="text-slate-300"><?= e($forum['description']) ?></p>
    </div>
    <?php if (is_logged_in()): ?><a href="new_topic.php?forum_id=<?= $forum['id'] ?>" class="px-4 py-2 bg-blue-600 text-white rounded">Nouveau topic</a><?php endif; ?>
  </div>
  <div class="text-slate-400">Aucun topic visible.</div>
  <?php
  include __DIR__ . '/partials/footer.php';
  exit;
}

// Fetch replies for all topics in one go
$in = implode(',', array_fill(0, count($topicIds), '?'));
$rstmt = $pdo->prepare('SELECT r.id, r.topic_id, r.user_id, r.created_at, r.deleted_at FROM replies r WHERE r.topic_id IN ('.$in.')');
$rstmt->execute($topicIds);
$replies = $rstmt->fetchAll(PDO::FETCH_ASSOC);

// Compute last visible activity per topic
$lastTimes = [];
foreach ($topics as $tid => $t) {
  $lastTimes[$tid] = $t['created_at']; // initial topic post counts
}
foreach ($replies as $r) {
  $tid = (int)$r['topic_id'];
  if (!isset($topics[$tid])) continue;
  $row = [
    'user_id' => $r['user_id'],
    'topic_author_id' => $topics[$tid]['user_id'],
    'deleted_at' => $r['deleted_at']
  ];
  if (can_user_see_deleted_for_ordering($row, $viewerId, $viewerRole)) {
    if ($r['created_at'] > $lastTimes[$tid]) $lastTimes[$tid] = $r['created_at'];
  } else {
    if (empty($r['deleted_at']) && $r['created_at'] > $lastTimes[$tid]) $lastTimes[$tid] = $r['created_at'];
  }
}

// Sort topics: pinned DESC, then last visible time DESC
$topicsSorted = array_values($topics);
usort($topicsSorted, function($a, $b) use ($lastTimes) {
  if ((int)$a['pinned'] !== (int)$b['pinned']) return (int)$b['pinned'] - (int)$a['pinned'];
  $ta = $lastTimes[$a['id']] ?? $a['created_at'];
  $tb = $lastTimes[$b['id']] ?? $b['created_at'];
  if ($tb === $ta) return 0;
  return ($tb < $ta) ? -1 : 1;
});

// Pagination after sort
$per = (int)$config['topics_per_page'];
$page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$total = count($topicsSorted);
$total_pages = max(1, (int)ceil($total / $per));
$offset = ($page-1)*$per;
$topicsPage = array_slice($topicsSorted, $offset, $per);
?>
<div class="flex items-center justify-between mb-4">
  <div>
    <a href="index.php" class="text-sm text-slate-400">&#8592; Forums</a>
    <h1 class="text-2xl font-semibold"><?= e($forum['name']) ?></h1>
    <p class="text-slate-300"><?= e($forum['description']) ?></p>
  </div>
  <?php if (is_logged_in()): ?><a href="new_topic.php?forum_id=<?= $forum['id'] ?>" class="px-4 py-2 bg-blue-600 text-white rounded">Nouveau topic</a><?php endif; ?>
</div>

<div class="grid gap-3">
<?php foreach ($topicsPage as $t): ?>
  <a href="view_topic.php?id=<?= $t['id'] ?>" class="block bg-slate-900/60 border border-white/10 rounded-2xl p-4 hover:bg-white/5 transition">
    <div class="flex items-start gap-3">
      <div class="shrink-0">
        <?php render_avatar($t['avatar'] ?? null, $t['username'] ?? '?', 'w-10 h-10'); ?>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
          <?php if (!empty($t['pinned'])): ?><span class="text-[10px] px-2 py-1 bg-amber-200 text-amber-900 rounded-full">Épinglé</span><?php endif; ?>
          <?php if (!empty($t['locked'])): ?><span class="text-[10px] px-2 py-1 bg-slate-200 text-slate-700 rounded-full">Verrouillé</span><?php endif; ?>
          <?php if (!empty($t['deleted_at'])): ?><span class="text-[10px] px-2 py-1 bg-red-200 text-red-900 rounded-full">Supprimé</span><?php endif; ?>
        </div>
        <h2 class="text-base sm:text-lg font-semibold mt-1 truncate"><?= e($t['title']) ?></h2>
        <p class="text-xs sm:text-sm text-slate-400">par <?= render_username($t['username'], $t['role']) ?> • Dernière activité : <?= e(date('d/m/Y H:i', strtotime($lastTimes[$t['id']] ?? $t['created_at']))) ?></p>
      </div>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php
$baseUrl = 'forum.php?id=' . $forum['id'];
echo '<nav class="flex flex-wrap gap-2 mt-4">';
if ($page>1) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$baseUrl.'&page='.($page-1).'">Précédent</a>';
for($i=1;$i<=$total_pages;$i++){ $cl = $i==$page ? ' bg-slate-100 text-slate-900' : ''; echo '<a class="px-3 py-1 border border-slate-600 rounded'.$cl.'" href="'.$baseUrl.'&page='.$i.'">'.$i.'</a>'; }
if ($page<$total_pages) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$baseUrl.'&page='.($page+1).'">Suivant</a>';
echo '</nav>';

include __DIR__ . '/partials/footer.php';
