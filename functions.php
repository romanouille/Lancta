<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$config = require __DIR__ . '/config.php';

function current_user() { return $_SESSION['user'] ?? null; }
function is_logged_in() { return !!current_user(); }
function is_mod() { return is_logged_in() && in_array(($_SESSION['user']['role'] ?? 'user'), ['mod','admin']); }
function is_admin() { return is_logged_in() && (($_SESSION['user']['role'] ?? 'user') === 'admin'); }

function redirect($path) { header('Location: ' . $path); exit; }
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function now_iso(){ return date('c'); }

function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf() { if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(400); die('CSRF token invalide'); } }

function canon($username){ return mb_strtolower($username, 'UTF-8'); }

function render_username($username, $role){
  $style = '';
  if ($role === 'admin') $style = 'color:#C00';
  else if ($role === 'mod') $style = 'color:green';
  return '<span style="'.$style.'">'.e($username).'</span>';
}

function markdown_safe($text){
  $t = e($text);
  $t = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $t);
  $t = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $t);
  $t = preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', $t);
  $t = preg_replace('/(^|\n)&gt;\s?(.*)/u', '$1<blockquote>$2</blockquote>', $t);
  $t = preg_replace('/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/u', '<a href="$2" class="text-blue-400 underline" rel="nofollow noopener" target="_blank">$1</a>', $t);
  $t = preg_replace('/(^|\n)[\-\*]\s+(.*)/u', '$1<li>$2</li>', $t);
  $t = preg_replace('/(<li>.*<\/li>)/us', '<ul>$1</ul>', $t, 1);
  return nl2br($t);
}

function extract_mentions(PDO $pdo, string $content): array {
  preg_match_all('/@([A-Za-z0-9_]{3,32})/u', $content, $m);
  $usernames = array_unique(array_map('canon', $m[1] ?? []));
  if (!$usernames) return [];
  $in = implode(',', array_fill(0, count($usernames), '?'));
  $stmt = $pdo->prepare('SELECT id, username, username_canonical FROM users WHERE username_canonical IN (' . $in . ')');
  $stmt->execute($usernames);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function render_with_markdown_and_mentions(string $text): string {
  $safe = markdown_safe($text);
  $safe = preg_replace('/@([A-Za-z0-9_]{3,32})/u', '<a href="profile.php?u=$1" class="text-blue-400 hover:underline">@$1</a>', $safe);
  return $safe;
}

function add_notification(PDO $pdo, int $user_id, string $type, array $payload) {
  $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, payload, is_read, created_at) VALUES (?, ?, ?, 0, ?)');
  $stmt->execute([$user_id, $type, json_encode($payload, JSON_UNESCAPED_UNICODE), now_iso()]);
}
function get_unread_count(PDO $pdo, int $user_id): int {
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
  $stmt->execute([$user_id]);
  return (int)$stmt->fetchColumn();
}
function mark_notification_read(PDO $pdo, int $id, int $user_id) {
  $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
  $stmt->execute([$id, $user_id]);
}

// CAPTCHA
function captcha_generate() {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $len = random_int(5,6);
  $s = '';
  for ($i=0;$i<$len;$i++) $s .= $alphabet[random_int(0, strlen($alphabet)-1)];
  $_SESSION['captcha_answer'] = $s;
  $_SESSION['captcha_text'] = $s;
}
function captcha_check($input): bool {
  return isset($_SESSION['captcha_answer']) && strtoupper(trim((string)$input)) === $_SESSION['captcha_answer'];
}

// Moderation state
function user_is_banned_or_kicked(PDO $pdo, int $user_id): array {
  $stmt = $pdo->prepare('SELECT type, until FROM moderation_actions WHERE user_id=? ORDER BY created_at DESC');
  $stmt->execute([$user_id]);
  $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $now = time();
  $state = ['banned'=>false,'until'=>null];
  foreach($actions as $a){
    if ($a['type']==='ban') { $state['banned']=true; return $state; }
    if ($a['type']==='kick'){ if ($a['until'] && strtotime($a['until']) > $now) { $state['banned']=true; $state['until']=$a['until']; return $state; } }
  }
  return $state;
}
