<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) { check_csrf(); mark_notification_read($pdo, (int)$_POST['id'], (int)$_SESSION['user']['id']); redirect('notifications.php'); }

$notifs = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 200');
$notifs->execute([$_SESSION['user']['id']]);
$notifs = $notifs->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Notifications</h1>
<div class="grid gap-2">
<?php foreach ($notifs as $n): $p = json_decode($n['payload'], true) ?: []; ?>
  <div class="bg-slate-800 border border-slate-700 rounded-xl p-3 flex items-center justify-between <?= $n['is_read']? 'opacity-70':'' ?>">
    <div class="text-sm">
      <?php if ($n['type']==='mention'): ?>
        <div><strong>@<?= e($p['by'] ?? 'Un utilisateur') ?></strong> vous a mentionné dans <a class="text-blue-400 underline" href="view_topic.php?id=<?= (int)($p['topic_id'] ?? 0) ?>"><?= e($p['title'] ?? 'un topic') ?></a></div>
      <?php elseif ($n['type']==='reply'): ?>
        <div><strong><?= e($p['by'] ?? 'Un utilisateur') ?></strong> a répondu à votre topic <a class="text-blue-400 underline" href="view_topic.php?id=<?= (int)($p['topic_id'] ?? 0) ?>"><?= e($p['title'] ?? 'topic') ?></a></div>
      <?php elseif ($n['type']==='pm'): ?>
        <div>Nouveau message privé dans <a class="text-blue-400 underline" href="conversation.php?id=<?= (int)($p['conversation_id'] ?? 0) ?>"><?= e($p['title'] ?? 'conversation') ?></a> de <strong><?= e($p['by'] ?? 'Un utilisateur') ?></strong></div>
      <?php else: ?>
        <div>Notification</div>
      <?php endif; ?>
      <div class="text-slate-400"><?= e(date('d/m/Y H:i', strtotime($n['created_at']))) ?></div>
    </div>
    <?php if (!$n['is_read']): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/><input type="hidden" name="id" value="<?= $n['id'] ?>"/><button class="px-3 py-1 rounded bg-slate-700 text-white text-sm">Marquer lu</button></form><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (empty($notifs)): ?><div class="text-slate-400">Aucune notification pour l’instant.</div><?php endif; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
