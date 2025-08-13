<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

// Local avatar helper (same as forum.php for consistency)
function render_avatar(?string $avatarPath, string $username, string $classes = 'w-10 h-10') {
  $avatarPath = trim((string)$avatarPath);
  if ($avatarPath) {
    echo '<img src="'.e($avatarPath).'" alt="avatar" class="'.$classes.' rounded-xl object-cover ring-1 ring-white/10 bg-slate-800" />';
  } else {
    $initial = mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'), 'UTF-8');
    echo '<div class="'.$classes.' rounded-xl grid place-items-center bg-gradient-to-br from-slate-700 to-slate-600 text-slate-200 ring-1 ring-white/10">'.e($initial).'</div>';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  captcha_generate();
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('
  SELECT t.*, u.username, u.role, u.avatar,
         f.name  AS forum_name,
         f.id    AS forum_id
  FROM topics t
  JOIN users u ON u.id = t.user_id
  JOIN forums f ON f.id = t.forum_id
  WHERE t.id = ?
');
$stmt->execute([$id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$topic) { http_response_code(404); die('Topic introuvable'); }

$viewerId   = current_user_id();
$viewerRole = $_SESSION['user']['role'] ?? 'user';

if (!topic_is_visible_to_viewer($topic, $viewerId, $viewerRole)) {
  http_response_code(404); die('Topic introuvable');
}

$page   = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$per    = (int)($config['replies_per_page'] ?? 20);
$offset = ($page-1)*$per;

$rst = $pdo->prepare('
  SELECT r.*, u.username, u.role, u.avatar
  FROM replies r
  JOIN users u ON u.id = r.user_id
  WHERE r.topic_id = ?
  ORDER BY r.created_at ASC
  LIMIT ? OFFSET ?
');
$rst->bindValue(1, $id, PDO::PARAM_INT);
$rst->bindValue(2, $per, PDO::PARAM_INT);
$rst->bindValue(3, $offset, PDO::PARAM_INT);
$rst->execute();
$replies = $rst->fetchAll(PDO::FETCH_ASSOC);

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM replies WHERE topic_id = ?');
$totalStmt->execute([$id]);
$total = (int)$totalStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per));

include __DIR__ . '/partials/header.php';
?>
<div class="flex items-center justify-between mb-2">
  <div>
    <a href="forum.php?id=<?= $topic['forum_id'] ?>" class="text-sm text-slate-400">&#8592; <?= e($topic['forum_name']) ?></a>
    <div class="flex items-center gap-2 mt-1 flex-wrap">
      <?php if (!empty($topic['pinned'])): ?>
        <span class="text-xs px-2 py-1 bg-amber-200 text-amber-900 rounded-full">Épinglé</span>
      <?php endif; ?>
      <?php if (!empty($topic['locked'])): ?>
        <span class="text-xs px-2 py-1 bg-slate-200 text-slate-700 rounded-full">Verrouillé</span>
      <?php endif; ?>
      <?php if (!empty($topic['deleted_at'])): ?>
        <span class="text-xs px-2 py-1 bg-red-200 text-red-900 rounded-full">Supprimé</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="flex items-center gap-2 flex-wrap">
    <?php if (is_mod()): ?>
      <form method="post" action="topic_pin.php" class="inline">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="id" value="<?= $topic['id'] ?>"/>
        <button class="text-sm px-3 py-1 rounded bg-amber-600 text-white">
          <?= !empty($topic['pinned']) ? 'Désépingler' : 'Épingler' ?>
        </button>
      </form>
      <form method="post" action="topic_lock.php" class="inline">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="id" value="<?= $topic['id'] ?>"/>
        <button class="text-sm px-3 py-1 rounded bg-slate-700 text-white">
          <?= !empty($topic['locked']) ? 'Déverrouiller' : 'Verrouiller' ?>
        </button>
      </form>
      <?php if (empty($topic['deleted_at'])): ?>
        <form method="post" action="topic_delete.php" class="inline" onsubmit="return confirm('Supprimer ce topic ?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
          <input type="hidden" name="id" value="<?= $topic['id'] ?>"/>
          <button class="text-sm px-3 py-1 rounded bg-red-600 text-white">Supprimer</button>
        </form>
      <?php else: ?>
        <form method="post" action="topic_restore.php" class="inline" onsubmit="return confirm('Restaurer ce topic ?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
          <input type="hidden" name="id" value="<?= $topic['id'] ?>"/>
          <button class="text-sm px-3 py-1 rounded bg-emerald-600 text-white">Restaurer</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (is_logged_in() && (int)$topic['user_id'] === $viewerId && empty($topic['deleted_at'])): ?>
      <form method="post" action="topic_delete_self.php" class="inline" onsubmit="return confirm('Supprimer votre topic ?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
        <input type="hidden" name="id" value="<?= $topic['id'] ?>"/>
        <button class="text-sm px-3 py-1 rounded bg-red-700 text-white">Supprimer (moi)</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<article class="bg-slate-900/60 border border-white/10 rounded-2xl p-4">
  <div class="flex items-start gap-3">
    <div class="shrink-0">
      <?php render_avatar($topic['avatar'] ?? null, $topic['username'] ?? '?', 'w-12 h-12'); ?>
    </div>
    <div class="min-w-0 flex-1">
      <h1 class="text-xl sm:text-2xl font-bold break-words"><?= e($topic['title']) ?></h1>
      <p class="text-xs sm:text-sm text-slate-400 mb-3">
        par <?= render_username($topic['username'], $topic['role']) ?>
        • <?= e(date('d/m/Y H:i', strtotime($topic['created_at']))) ?>
      </p>
      <?php if (!empty($topic['deleted_at'])): ?>
        <div class="p-3 bg-red-900/40 text-red-200 rounded mb-2">
          Ce topic a été supprimé le <?= e(date('d/m/Y H:i', strtotime($topic['deleted_at']))) ?>.
          Son contenu reste visible pour son auteur et le staff, sans possibilité de restauration par l’auteur.
        </div>
      <?php endif; ?>
      <div class="prose prose-invert max-w-none break-words">
        <?= render_with_markdown_and_mentions($topic['content']) ?>
      </div>
    </div>
  </div>
</article>

<section class="mt-6">
  <h2 class="text-lg sm:text-xl font-semibold mb-3">Réponses</h2>

  <div class="grid gap-3">
    <?php foreach ($replies as $r): $visible = reply_is_visible_to_viewer($r, $viewerId, $viewerRole, (int)$topic['user_id']); ?>
      <?php if (!$visible) continue; ?>
      <div id="reply-<?= $r['id'] ?>" class="bg-slate-900/60 border border-white/10 rounded-2xl p-3">
        <div class="flex items-start gap-3">
          <div class="shrink-0">
            <?php render_avatar($r['avatar'] ?? null, $r['username'] ?? '?', 'w-10 h-10'); ?>
          </div>
          <div class="min-w-0 flex-1">
            <div class="text-xs sm:text-sm text-slate-400 mb-1">
              par <?= render_username($r['username'], $r['role']) ?> • <?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?>
            </div>

            <?php if (!empty($r['deleted_at'])): ?>
              <div class="p-2 bg-red-900/40 text-red-200 rounded mb-1">
                Message supprimé le <?= e(date('d/m/Y H:i', strtotime($r['deleted_at']))) ?>.
                Visible pour son auteur et le staff.
              </div>
            <?php endif; ?>

            <div class="break-words">
              <?= render_with_markdown_and_mentions($r['content']) ?>
            </div>

            <div class="mt-2 flex flex-wrap gap-2">
              <button class="text-xs px-2 py-1 bg-slate-700 rounded quote-btn" data-id="<?= $r['id'] ?>" data-user="<?= e($r['username']) ?>">Citer</button>
              <?php if (is_logged_in() && (int)$r['user_id'] === $viewerId && empty($r['deleted_at'])): ?>
                <form method="post" action="reply_delete_self.php" onsubmit="return confirm('Supprimer ce message ?');" class="inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
                  <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                  <button class="text-xs px-2 py-1 bg-red-700 text-white rounded">Supprimer (moi)</button>
                </form>
              <?php endif; ?>
              <?php if (is_mod() && !empty($r['deleted_at'])): ?>
                <form method="post" action="reply_restore.php" class="inline" onsubmit="return confirm('Restaurer ce message ?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
                  <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                  <button class="text-xs px-2 py-1 bg-emerald-700 text-white rounded">Restaurer</button>
                </form>
              <?php elseif (is_mod() && empty($r['deleted_at'])): ?>
                <form method="post" action="reply_delete.php" class="inline" onsubmit="return confirm('Supprimer ce message ?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
                  <input type="hidden" name="id" value="<?= $r['id'] ?>"/>
                  <button class="text-xs px-2 py-1 bg-red-600 text-white rounded">Supprimer</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php
    $baseUrl = 'view_topic.php?id=' . $topic['id'];
    echo '<nav class="flex flex-wrap gap-2 mt-4">';
    if ($page>1) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$baseUrl.'&page='.($page-1).'">Précédent</a>';
    for($i=1;$i<=$total_pages;$i++){
      $cl = $i==$page ? ' bg-slate-100 text-slate-900' : '';
      echo '<a class="px-3 py-1 border border-slate-600 rounded'.$cl.'" href="'.$baseUrl.'&page='.$i.'">'.$i.'</a>';
    }
    if ($page<$total_pages) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$baseUrl.'&page='.($page+1).'">Suivant</a>';
    echo '</nav>';
  ?>

  <?php if (is_logged_in() && empty($topic['locked']) && empty($topic['deleted_at'])): ?>
    <form method="post" action="reply_create.php" class="mt-4 bg-slate-900/60 border border-white/10 rounded-2xl p-4 grid gap-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
      <input type="hidden" name="topic_id" value="<?= $topic['id'] ?>"/>

      <label class="grid gap-1">
        <span>Votre réponse (Markdown + @mentions)</span>
        <textarea id="replyBox" name="content" class="border border-slate-600 rounded p-2 min-h-[120px] bg-slate-900 js-mention-target" required></textarea>
      </label>

      <div class="grid gap-2">
        <span class="text-sm text-slate-300">CAPTCHA</span>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
          <img src="captcha.php?ts=<?= time() ?>" alt="captcha" class="border border-slate-600 rounded max-w-full"/>
          <input name="captcha" autocomplete="off" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="Entrez le texte" required/>
        </div>
      </div>

      <button class="px-4 py-2 bg-blue-600 text-white rounded w-full sm:w-auto">Répondre</button>
    </form>

    <script>
    // Quote rapide
    document.querySelectorAll('.quote-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const user = btn.getAttribute('data-user');
        const el = document.getElementById('reply-'+id);
        const text = el ? el.innerText : '';
        const q = text.split('\\n').map(l => '> ' + l).join('\\n');
        const box = document.getElementById('replyBox');
        box.value = (box.value ? box.value + '\\n\\n' : '') + '> **'+user+'**\\n' + q + '\\n\\n';
        box.focus();
      });
    });
    </script>
  <?php elseif (!empty($topic['locked'])): ?>
    <div class="mt-4 p-3 bg-slate-700 text-slate-200 rounded">Topic verrouillé.</div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
