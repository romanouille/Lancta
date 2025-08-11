<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT c.* FROM conversations c JOIN conversation_participants p ON p.conversation_id=c.id WHERE c.id=? AND p.user_id=?');
$stmt->execute([$id, $_SESSION['user']['id']]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conv) { http_response_code(403); die('Accès refusé'); }

$parts = $pdo->prepare('SELECT u.id, u.username, u.role FROM conversation_participants p JOIN users u ON u.id=p.user_id WHERE p.conversation_id=?');
$parts->execute([$id]);
$participants = $parts->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
  check_csrf();
  $content = trim($_POST['content'] ?? '');
  if ($content === '') $errors[] = 'Message vide.';
  if (!$errors) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null; $port = isset($_SERVER['REMOTE_PORT']) ? (int)$_SERVER['REMOTE_PORT'] : null;
    $pdo->prepare('INSERT INTO conversation_messages (conversation_id, user_id, content, created_at, source_ip, source_port) VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $_SESSION['user']['id'], $content, date('c'), $ip, $port]);
    foreach ($participants as $p) { if ($p['id'] != $_SESSION['user']['id']) add_notification($pdo, (int)$p['id'], 'pm', ['conversation_id'=>$id, 'title'=>$conv['title'] ?: 'Conversation', 'by'=>$_SESSION['user']['username']]); }
    redirect('conversation.php?id=' . $id);
  }
}

$messages = $pdo->prepare('SELECT m.*, u.username, u.role FROM conversation_messages m JOIN users u ON u.id = m.user_id WHERE m.conversation_id=? ORDER BY m.created_at ASC');
$messages->execute([$id]);
$messages = $messages->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<div class="mb-4">
  <a href="messages.php" class="text-sm text-slate-400">&#8592; Conversations</a>
  <h1 class="text-2xl font-semibold"><?= e($conv['title'] ?: 'Conversation #'.$conv['id']) ?></h1>
</div>

<div class="mb-4 bg-slate-800 border border-slate-700 rounded-xl p-3">
  <div class="text-sm text-slate-300">Participants :
    <?php foreach ($participants as $p): ?><button type="button" data-username="<?= e($p['username']) ?>" class="inline-block px-2 py-0.5 border border-slate-600 rounded-full mr-1 my-1"><?= render_username($p['username'], $p['role']) ?></button><?php endforeach; ?>
  </div>
</div>

<?php if ($errors): ?><div class="mb-3 p-3 bg-red-900/40 text-red-200 rounded"><?php foreach ($errors as $e) echo '<div>'.$e.'</div>'; ?></div><?php endif; ?>

<div class="grid gap-2">
<?php foreach ($messages as $m): ?>
  <div class="bg-slate-800 border border-slate-700 rounded-xl p-3">
    <div class="text-sm text-slate-400 mb-1">par <button type="button" data-username="<?= e($m['username']) ?>" class="underline decoration-dotted"><?= render_username($m['username'], $m['role']) ?></button> • <?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></div>
    <div><?= render_with_markdown_and_mentions($m['content']) ?></div>
  </div>
<?php endforeach; ?>
<?php if (empty($messages)): ?><div class="text-slate-400">Aucun message.</div><?php endif; ?>
</div>

<form method="post" class="mt-4 bg-slate-800 border border-slate-700 rounded-xl p-4 grid gap-3">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
  <label class="grid gap-1"><span>Votre message (Markdown + @mentions)</span><textarea name="content" class="border border-slate-600 rounded p-2 min-h-[120px] bg-slate-900 js-mention-target" required></textarea></label>
  <button class="px-4 py-2 bg-blue-600 text-white rounded">Envoyer</button>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
