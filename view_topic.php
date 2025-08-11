<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  captcha_generate();
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT t.*, u.username, u.role, f.name as forum_name, f.id as forum_id FROM topics t JOIN users u ON u.id = t.user_id JOIN forums f ON f.id = t.forum_id WHERE t.id = ?');
$stmt->execute([$id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$topic) { http_response_code(404); die('Topic introuvable'); }

$page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$per = (int)$config['replies_per_page'];
$offset = ($page-1)*$per;

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM replies WHERE topic_id = ?');
$totalStmt->execute([$id]);
$total = (int)$totalStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per));

$repliesStmt = $pdo->prepare('
    SELECT r.*, u.username, u.role
    FROM replies r
    JOIN users u ON u.id = r.user_id
    WHERE r.topic_id = ?
    ORDER BY r.created_at ASC
    LIMIT ? OFFSET ?
');
$repliesStmt->bindValue(1, $id, PDO::PARAM_INT);
$repliesStmt->bindValue(2, $per, PDO::PARAM_INT);
$repliesStmt->bindValue(3, $offset, PDO::PARAM_INT);
$repliesStmt->execute();
$replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);

// Poll
$poll = $pdo->prepare('SELECT * FROM polls WHERE topic_id=?');
$poll->execute([$id]);
$poll = $poll->fetch(PDO::FETCH_ASSOC);
$options = [];
if ($poll) {
  $stmtOpt = $pdo->prepare('SELECT * FROM poll_options WHERE topic_id=?');
  $stmtOpt->execute([$id]);
  $options = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/partials/header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
  <div>
    <a href="forum.php?id=<?= $topic['forum_id'] ?>" class="text-sm text-slate-400">&#8592; <?= e($topic['forum_name']) ?></a>
    <div class="flex items-center gap-2 mt-1 flex-wrap">
      <?php if ($topic['pinned']): ?><span class="text-xs px-2 py-1 bg-amber-200 text-amber-900 rounded-full">Épinglé</span><?php endif; ?>
      <?php if ($topic['locked']): ?><span class="text-xs px-2 py-1 bg-slate-200 text-slate-700 rounded-full">Verrouillé</span><?php endif; ?>
    </div>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <?php if (is_mod()): ?>
      <form method="post" action="topic_pin.php" class="inline w-full xs:w-auto sm:w-auto"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/><input type="hidden" name="id" value="<?= $topic['id'] ?>"/><button class="text-sm px-3 py-1 rounded bg-amber-600 text-white w-full sm:w-auto"><?= $topic['pinned'] ? 'Désépingler' : 'Épingler' ?></button></form>
      <form method="post" action="topic_lock.php" class="inline w-full xs:w-auto sm:w-auto"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/><input type="hidden" name="id" value="<?= $topic['id'] ?>"/><button class="text-sm px-3 py-1 rounded bg-slate-700 text-white w-full sm:w-auto"><?= $topic['locked'] ? 'Déverrouiller' : 'Verrouiller' ?></button></form>
      <form method="post" action="topic_delete.php" class="inline w-full xs:w-auto sm:w-auto"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/><input type="hidden" name="id" value="<?= $topic['id'] ?>"/><button class="text-sm px-3 py-1 rounded bg-red-600 text-white w-full sm:w-auto"><?= $topic['deleted_at'] ? 'Supprimé' : 'Supprimer' ?></button></form>
      <?php if ($topic['deleted_at']): ?><form method="post" action="topic_restore.php" class="inline w-full xs:w-auto sm:w-auto"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/><input type="hidden" name="id" value="<?= $topic['id'] ?>"/><button class="text-sm px-3 py-1 rounded bg-emerald-600 text-white w-full sm:w-auto">Restaurer</button></form><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<article class="bg-slate-800 border border-slate-700 rounded-xl p-4">
  <h1 class="text-xl sm:text-2xl font-bold break-words"><?= e($topic['title']) ?></h1>
  <p class="text-xs sm:text-sm text-slate-400 mb-4">par <button type="button" data-username="<?= e($topic['username']) ?>" class="underline decoration-dotted"><?= render_username($topic['username'], $topic['role']) ?></button> • <?= e(date('d/m/Y H:i', strtotime($topic['created_at']))) ?></p>
  <?php if ($topic['deleted_at']): ?><div class="p-3 bg-red-900/40 text-red-200 rounded mb-2">Ce topic a été supprimé (soft) le <?= e(date('d/m/Y H:i', strtotime($topic['deleted_at']))) ?>.</div><?php endif; ?>
  <div class="prose prose-invert max-w-none break-words"><?= render_with_markdown_and_mentions($topic['content']) ?></div>
</article>

<?php if ($poll): ?>
<section class="mt-4 bg-slate-800 border border-slate-700 rounded-xl p-4">
  <h2 class="text-lg sm:text-xl font-semibold mb-2">Sondage : <?= e($poll['question']) ?></h2>
  <form method="post" action="poll_vote.php" class="grid gap-2">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
    <input type="hidden" name="topic_id" value="<?= $topic['id'] ?>"/>
    <?php foreach ($options as $o): ?>
      <label class="flex items-center gap-2"><input type="radio" name="option_id" value="<?= $o['id'] ?>" required/><span><?= e($o['text']) ?></span></label>
    <?php endforeach; ?>
    <?php if (is_logged_in()): ?><button class="px-3 py-1 rounded bg-blue-600 text-white w-max">Voter</button><?php else: ?><div class="text-slate-400 text-sm">Connectez-vous pour voter.</div><?php endif; ?>
  </form>
  <div class="mt-3 text-sm text-slate-300">
    <?php $sum = 0; foreach ($options as $o) $sum += (int)$o['votes']; ?>
    <?php foreach ($options as $o): $pct = $sum ? round($o['votes']*100/$sum) : 0; ?>
      <div class="mb-1"><?= e($o['text']) ?> — <?= (int)$o['votes'] ?> (<?= $pct ?>%)<div class="h-2 bg-slate-700 rounded"><div class="h-2 bg-blue-500 rounded" style="width: <?= $pct ?>%"></div></div></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="mt-6">
  <h2 class="text-lg sm:text-xl font-semibold mb-3">Réponses</h2>
  <div class="grid gap-3">
    <?php foreach ($replies as $r): ?>
      <div class="bg-slate-800 border border-slate-700 rounded-xl p-3">
        <div class="text-xs sm:text-sm text-slate-400 mb-1">par <button type="button" data-username="<?= e($r['username']) ?>" class="underline decoration-dotted"><?= render_username($r['username'], $r['role']) ?></button> • <?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></div>
        <?php if ($r['deleted_at']): ?>
          <div class="p-2 bg-red-900/40 text-red-200 rounded mb-1">Réponse supprimée (soft) le <?= e(date('d/m/Y H:i', strtotime($r['deleted_at']))) ?>.</div>
        <?php else: ?>
          <div id="reply-<?= $r['id'] ?>" class="break-words"><?= render_with_markdown_and_mentions($r['content']) ?></div>
          <div class="mt-2"><button class="text-xs px-2 py-1 bg-slate-700 rounded quote-btn" data-id="<?= $r['id'] ?>" data-user="<?= e($r['username']) ?>">Citer</button></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php
    $baseUrl = 'view_topic.php?id=' . $topic['id'];
    echo '<nav class="flex flex-wrap gap-2 mt-4">';
    if ($page>1) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$baseUrl.'&page='.($page-1).'">Précédent</a>';
    for($i=1;$i<=$total_pages;$i++){ $cl = $i==$page ? ' bg-slate-100 text-slate-900' : ''; echo '<a class="px-3 py-1 border border-slate-600 rounded'.$cl.'" href="'.$baseUrl.'&page='.$i.'">'.$i.'</a>'; }
    if ($page<$total_pages) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$baseUrl.'&page='.($page+1).'">Suivant</a>';
    echo '</nav>';
  ?>

  <?php if (is_logged_in() && !$topic['locked'] && !$topic['deleted_at']): ?>
    <form method="post" action="reply_create.php" class="mt-4 bg-slate-800 border border-slate-700 rounded-xl p-4 grid gap-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="topic_id" value="<?= $topic['id'] ?>"/>
      <label class="grid gap-1"><span>Votre réponse (Markdown + @mentions)</span><textarea id="replyBox" name="content" class="border border-slate-600 rounded p-2 min-h-[120px] bg-slate-900 js-mention-target" required></textarea></label>
      <label class="grid gap-1"><span>CAPTCHA : <code><?= e($_SESSION['captcha_text'] ?? '') ?></code></span><div class="flex flex-col sm:flex-row sm:items-center gap-2"><img src="captcha.php" class="border border-slate-600 rounded max-w-full"/><input name="captcha" class="border border-slate-600 rounded p-2 bg-slate-900" required/></div></label>
      <button class="px-4 py-2 bg-blue-600 text-white rounded w-full sm:w-auto">Répondre</button>
    </form>
    <script>
    document.querySelectorAll('.quote-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const user = btn.getAttribute('data-user');
        const el = document.getElementById('reply-'+id);
        const text = el ? el.innerText : '';
        const q = text.split('\n').map(l => '> ' + l).join('\n');
        const box = document.getElementById('replyBox');
        box.value = (box.value ? box.value + '\n\n' : '') + '> **'+user+'**\n' + q + '\n\n';
        box.focus();
      });
    });
    </script>
  <?php elseif ($topic['locked']): ?>
    <div class="mt-4 p-3 bg-slate-700 text-slate-200 rounded">Topic verrouillé.</div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
