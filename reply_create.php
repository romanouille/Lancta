<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';

if (!is_logged_in()) { redirect('login.php'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
check_csrf();

$topic_id = (int)($_POST['topic_id'] ?? 0);
$content  = trim((string)($_POST['content'] ?? ''));
$captcha  = (string)($_POST['captcha'] ?? '');

// Validate basics
if ($topic_id <= 0) { redirect('index.php'); }
if ($content === '') {
  header('Location: view_topic.php?id='.$topic_id.'&err=empty', true, 303);
  exit;
}

// CAPTCHA must be checked; captcha_generate() is done in GET on view_topic.php
if (!captcha_check($captcha)) {
  header('Location: view_topic.php?id='.$topic_id.'&err=captcha', true, 303);
  exit;
}

// Check topic status
$stmt = $pdo->prepare('SELECT id, user_id, locked, deleted_at FROM topics WHERE id = ?');
$stmt->execute([$topic_id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$topic) {
  header('Location: index.php', true, 303);
  exit;
}
if (!empty($topic['locked'])) {
  header('Location: view_topic.php?id='.$topic_id.'&err=locked', true, 303);
  exit;
}
if (!empty($topic['deleted_at'])) {
  header('Location: view_topic.php?id='.$topic_id.'&err=deleted', true, 303);
  exit;
}

// Prepare dynamic insert with IP/port if columns exist
function table_has_column(PDO $pdo, string $table, string $column): bool {
  $q = $pdo->prepare("PRAGMA table_info(" . $table . ")");
  $q->execute();
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $col) {
    if (strcasecmp($col['name'], $column) === 0) return true;
  }
  return false;
}

$fields = ['topic_id','user_id','content','created_at'];
$params = [$topic_id, current_user_id(), $content, date('c')];

$has_ip   = table_has_column($pdo, 'replies', 'ip_address');
$has_port = table_has_column($pdo, 'replies', 'ip_port');

if ($has_ip)   { $fields[] = 'ip_address'; $params[] = $_SERVER['REMOTE_ADDR'] ?? null; }
if ($has_port) { $fields[] = 'ip_port';    $params[] = isset($_SERVER['REMOTE_PORT']) ? (int)$_SERVER['REMOTE_PORT'] : null; }

$sql = 'INSERT INTO replies (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
$ins = $pdo->prepare($sql);
$ins->execute($params);
$reply_id = (int)$pdo->lastInsertId();

// Notifications: mentions + topic author (simple version; ignore self)
try {
  $mentions = extract_mentions($pdo, $content);
  foreach ($mentions as $u) {
    if ((int)$u['id'] === current_user_id()) continue;
    if (function_exists('add_notification')) {
      add_notification($pdo, (int)$u['id'], 'mention', ['topic_id'=>$topic_id, 'reply_id'=>$reply_id]);
    }
  }
  if ((int)$topic['user_id'] !== current_user_id()) {
    if (function_exists('add_notification')) {
      add_notification($pdo, (int)$topic['user_id'], 'reply', ['topic_id'=>$topic_id, 'reply_id'=>$reply_id]);
    }
  }
} catch (Throwable $e) {
  // Silencieux pour ne pas bloquer le post en cas d'erreur de notifs
}

// Redirect to the new reply anchor
header('Location: view_topic.php?id=' . $topic_id . '#reply-' . $reply_id, true, 303);
exit;
