<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_logged_in()) redirect('login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
check_csrf();

$topic_id = (int)$_POST['topic_id'];
$content = trim($_POST['content'] ?? '');
$captcha = $_POST['captcha'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM topics WHERE id = ?');
$stmt->execute([$topic_id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$topic || $topic['locked'] || $topic['deleted_at']) redirect('view_topic.php?id=' . $topic_id);

$st = user_is_banned_or_kicked($pdo, (int)$_SESSION['user']['id']);
if ($st['banned']) redirect('view_topic.php?id=' . $topic_id);

if ($content !== '' && captcha_check($captcha)) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? null; $port = isset($_SERVER['REMOTE_PORT']) ? (int)$_SERVER['REMOTE_PORT'] : null;
  $stmt = $pdo->prepare('INSERT INTO replies (topic_id, user_id, content, created_at, source_ip, source_port) VALUES (?, ?, ?, ?, ?, ?)');
  $stmt->execute([$topic_id, $_SESSION['user']['id'], $content, date('c'), $ip, $port]);

  if ($topic['user_id'] != $_SESSION['user']['id']) add_notification($pdo, (int)$topic['user_id'], 'reply', ['topic_id'=>$topic['id'], 'title'=>$topic['title'], 'by'=>$_SESSION['user']['username']]);

  $mentions = extract_mentions($pdo, $content);
  foreach ($mentions as $m) { if ($m['id'] != $_SESSION['user']['id']) add_notification($pdo, (int)$m['id'], 'mention', ['topic_id'=>$topic['id'],'by'=>$_SESSION['user']['username'],'title'=>$topic['title']]); }
}
redirect('view_topic.php?id=' . $topic_id);
