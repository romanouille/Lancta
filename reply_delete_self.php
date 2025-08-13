<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
check_csrf();
$id = (int)($_POST['id'] ?? 0);
$uid = current_user_id();

$stmt = $pdo->prepare('SELECT r.id, r.user_id, r.topic_id, r.deleted_at, t.user_id AS topic_author_id FROM replies r JOIN topics t ON t.id=r.topic_id WHERE r.id=?');
$stmt->execute([$id]);
$rep = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rep) redirect('index.php');

if ((int)$rep['user_id'] !== $uid && !is_mod()) redirect('view_topic.php?id='.(int)$rep['topic_id']);

// Only set deleted_at if not already deleted
if (empty($rep['deleted_at'])) {
  $pdo->prepare('UPDATE replies SET deleted_at=? WHERE id=?')->execute([date('c'), $id]);
}
redirect('view_topic.php?id='.(int)$rep['topic_id']);
