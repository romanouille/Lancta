<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $title = trim($_POST['title'] ?? '');
  $participants_raw = trim($_POST['participants'] ?? '');
  $content = trim($_POST['content'] ?? '');
  if ($content === '') $errors[] = 'Message vide.';

  $names = array_filter(array_map('trim', explode(',', $participants_raw)));
  $names_canon = array_unique(array_map('canon', $names));

  $ids = [ (int)$_SESSION['user']['id'] ];
  if ($names_canon) {
    $in = implode(',', array_fill(0, count($names_canon), '?'));
    $stmt = $pdo->prepare('SELECT id, username_canonical FROM users WHERE username_canonical IN (' . $in . ')');
    $stmt->execute($names_canon);
    $rowsAll = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $found = array_column($rowsAll, 'id', 'username_canonical');
    foreach ($names_canon as $nc) if (!isset($found[$nc])) $errors[] = 'Utilisateur introuvable : ' . e($nc);
    foreach ($found as $uid) $ids[] = (int)$uid;
  }

  if (!$errors) {
    $pdo->prepare('INSERT INTO conversations (title, created_by, created_at) VALUES (?, ?, ?)')->execute([$title ?: null, $_SESSION['user']['id'], date('c')]);
    $cid = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT OR IGNORE INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)');
    foreach (array_unique($ids) as $uid) $ins->execute([$cid, $uid]);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null; $port = isset($_SERVER['REMOTE_PORT']) ? (int)$_SERVER['REMOTE_PORT'] : null;
    $pdo->prepare('INSERT INTO conversation_messages (conversation_id, user_id, content, created_at, source_ip, source_port) VALUES (?, ?, ?, ?, ?, ?)')->execute([$cid, $_SESSION['user']['id'], $content, date('c'), $ip, $port]);
    foreach (array_unique($ids) as $uid) { if ($uid != $_SESSION['user']['id']) add_notification($pdo, $uid, 'pm', ['conversation_id'=>$cid, 'title'=>$title or 'Conversation', 'by'=>$_SESSION['user']['username']]); }
    redirect('conversation.php?id=' . $cid);
  }
}

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Nouvelle conversation</h1>
<?php if ($errors): ?><div class="mb-3 p-3 bg-red-900/40 text-red-200 rounded"><?php foreach ($errors as $e) echo '<div>'.$e.'</div>'; ?></div><?php endif; ?>
<form method="post" class="bg-slate-800 border border-slate-700 rounded-xl p-4 grid gap-3">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
  <label class="grid gap-1"><span>Titre (optionnel)</span><input name="title" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="Ex: Projet X"/></label>
  <label class="grid gap-1"><span>Participants (pseudos séparés par des virgules)</span><input name="participants" class="border border-slate-600 rounded p-2 bg-slate-900" placeholder="alice, bob, carol"/></label>
  <label class="grid gap-1"><span>Premier message</span><textarea name="content" class="border border-slate-600 rounded p-2 min-h-[140px] bg-slate-900 js-mention-target" required></textarea></label>
  <button class="px-4 py-2 bg-blue-600 text-white rounded">Créer</button>
</form>
<?php include __DIR__ . '/partials/footer.php'; ?>
