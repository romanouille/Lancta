<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');

$stmt = $pdo->prepare('
    SELECT c.*, 
      (SELECT content FROM conversation_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_content,
      (SELECT created_at FROM conversation_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_date
    FROM conversations c
    JOIN conversation_participants p ON p.conversation_id = c.id
    WHERE p.user_id = ?
    ORDER BY COALESCE(last_date, c.created_at) DESC
');
$stmt->execute([$_SESSION['user']['id']]);
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-semibold">Messages privés</h1>
  <a href="message_new.php" class="px-4 py-2 bg-blue-600 text-white rounded">Nouvelle conversation</a>
</div>
<div class="grid gap-2">
<?php foreach ($convs as $c): ?>
  <a href="conversation.php?id=<?= $c['id'] ?>" class="block bg-slate-800 border border-slate-700 rounded-xl p-3 hover:shadow">
    <div class="font-semibold"><?= e($c['title'] ?: 'Conversation #'.$c['id']) ?></div>
    <div class="text-sm text-slate-300 line-clamp-1"><?= e($c['last_content'] ?? '') ?></div>
    <div class="text-xs text-slate-400"><?= e(date('d/m/Y H:i', strtotime($c['last_date'] ?? $c['created_at']))) ?></div>
  </a>
<?php endforeach; ?>
<?php if (empty($convs)): ?><div class="text-slate-400">Aucune conversation.</div><?php endif; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
