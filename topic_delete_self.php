<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
check_csrf();
$id = (int)($_POST['id'] ?? 0);
$uid = current_user_id();

$stmt = $pdo->prepare('SELECT id, user_id, deleted_at FROM topics WHERE id=?');
$stmt->execute([$id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$topic) redirect('index.php');

if ((int)$topic['user_id'] !== $uid && !is_mod()) redirect('view_topic.php?id='.$id);

// Only set deleted_at if not already deleted
if (empty($topic['deleted_at'])) {
  $pdo->prepare('UPDATE topics SET deleted_at=? WHERE id=?')->execute([date('c'), $id]);
}
redirect('view_topic.php?id='.$id);
