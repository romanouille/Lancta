<?php
// functions.php — version intégrée avec session persistante + helpers complets
// PHP 8.1+ compatible (types nullable explicites).

// ---------- Session persistante (HttpOnly + Secure/SameSite) ----------
$lifetimeDays = 30;
$lifetime     = 60 * 60 * 24 * $lifetimeDays;
$secure       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$sessionName  = 'mf_sid';
$sameSite     = 'Lax'; // 'Strict' si besoin

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_name($sessionName);
  session_set_cookie_params([
    'lifetime' => $lifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => $sameSite,
  ]);
  ini_set('session.gc_maxlifetime', (string)$lifetime);
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.cookie_httponly', '1');
  if ($secure) ini_set('session.cookie_secure', '1');
  session_start();
  // Expiration glissante (rafraîchi toutes les 15 min d'activité)
  $refreshEvery = 60 * 15;
  $now = time();
  if (!isset($_SESSION['__last'])) {
    $_SESSION['__last'] = $now;
  } elseif (($now - (int)$_SESSION['__last']) > $refreshEvery) {
    $_SESSION['__last'] = $now;
    setcookie(session_name(), session_id(), [
      'expires'  => $now + $lifetime,
      'path'     => '/',
      'domain'   => '',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => $sameSite,
    ]);
  }
}

$config = require __DIR__ . '/config.php';

/** ===== Session / Auth ===== */
function current_user() { return $_SESSION['user'] ?? null; }
function current_user_id() { return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null; }
function is_logged_in() { return !!current_user(); }
function is_mod() { return is_logged_in() && in_array(($_SESSION['user']['role'] ?? 'user'), ['mod','admin'], true); }
function is_admin() { return is_logged_in() && (($_SESSION['user']['role'] ?? 'user') === 'admin'); }

/** ===== Utils ===== */
function redirect($path) { header('Location: ' . $path); exit; }
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
function now_iso(){ return date('c'); }

/** ===== CSRF ===== */
function csrf_token() {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function check_csrf() {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400); die('CSRF token invalide');
  }
}

/** ===== Usernames ===== */
function canon($username){ return mb_strtolower($username, 'UTF-8'); }
function render_username($username, $role){
  $style = '';
  if ($role === 'admin') $style = 'color:#C00';
  else if ($role === 'mod') $style = 'color:green';
  return '<span style="'.$style.'">'.e($username).'</span>';
}

/** ===== Markdown / Mentions (sécurisé) ===== */
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

/** ===== Notifications ===== */
function get_unread_count(PDO $pdo, int $userId): int {
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
  $stmt->execute([$userId]);
  return (int)$stmt->fetchColumn();
}
function add_notification(PDO $pdo, int $user_id, string $type, array $payload) {
  $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, payload, is_read, created_at) VALUES (?, ?, ?, 0, ?)');
  $stmt->execute([$user_id, $type, json_encode($payload, JSON_UNESCAPED_UNICODE), now_iso()]);
}
function mark_notification_read(PDO $pdo, int $id, int $user_id) {
  $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
  $stmt->execute([$id, $user_id]);
}

/** ===== CAPTCHA ===== */
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

/** ===== Visibilité & Ordonnancement ===== */
function can_user_see_deleted_for_ordering(array $row, ?int $viewerId = null, string $viewerRole = 'user'): bool {
  if (in_array($viewerRole, ['mod','admin'], true)) return true;
  if ($viewerId !== null) {
    if (!empty($row['user_id']) && (int)$row['user_id'] === $viewerId) return true; // auteur du post
  }
  return empty($row['deleted_at']); // sinon, seulement si non supprimé
}
function topic_is_visible_to_viewer(array $topic, ?int $viewerId = null, string $viewerRole = 'user'): bool {
  if (in_array($viewerRole, ['mod','admin'], true)) return true;
  if (empty($topic['deleted_at'])) return true;
  if ($viewerId !== null && (int)$topic['user_id'] === $viewerId) return true; // auteur voit son topic supprimé
  return false;
}
function reply_is_visible_to_viewer(array $reply, ?int $viewerId = null, string $viewerRole = 'user', ?int $topicAuthorId = null): bool {
  if (in_array($viewerRole, ['mod','admin'], true)) return true;
  if (empty($reply['deleted_at'])) return true;
  if ($viewerId !== null && (int)$reply['user_id'] === $viewerId) return true; // auteur voit sa propre réponse supprimée
  return false;
}

/** ===== Remember-me tokens (compatible avec header remember_consume) ===== */
function remember_issue_token(PDO $pdo, int $userId, int $days = 60): void {
  $selector  = bin2hex(random_bytes(9));
  $validator = bin2hex(random_bytes(32));
  $hash      = hash('sha256', $validator);
  $expiresTs = time() + 60*60*24*$days;
  $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;

  $stmt = $pdo->prepare('
    INSERT INTO auth_tokens (user_id, selector, validator_hash, user_agent, ip_address, expires_at, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ');
  $stmt->execute([$userId, $selector, $hash, $ua, $ip, date('c', $expiresTs), now_iso()]);

  setcookie('remember', $selector . ':' . $validator, [
    'expires'  => $expiresTs,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
function remember_clear(PDO $pdo, ?int $userId = null): void {
  if (!empty($_COOKIE['remember'])) {
    $parts = explode(':', $_COOKIE['remember'], 2);
    if (count($parts) === 2) {
      $selector = $parts[0];
      if ($userId) {
        $pdo->prepare('DELETE FROM auth_tokens WHERE selector = ? AND user_id = ?')->execute([$selector, $userId]);
      } else {
        $pdo->prepare('DELETE FROM auth_tokens WHERE selector = ?')->execute([$selector]);
      }
    }
    setcookie('remember', '', time() - 3600, '/');
  }
}
function remember_consume(PDO $pdo): void {
  if (is_logged_in()) return;
  if (empty($_COOKIE['remember'])) return;
  $parts = explode(':', $_COOKIE['remember'], 2);
  if (count($parts) !== 2) { setcookie('remember','', time()-3600, '/'); return; }
  [$selector, $validator] = $parts;
  $stmt = $pdo->prepare('SELECT * FROM auth_tokens WHERE selector = ? LIMIT 1');
  $stmt->execute([$selector]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { setcookie('remember','', time()-3600, '/'); return; }
  if (strtotime($row['expires_at']) < time()) { remember_clear($pdo); return; }
  $hash = hash('sha256', $validator);
  if (!hash_equals($row['validator_hash'], $hash)) {
    remember_clear($pdo);
    return;
  }
  $u = $pdo->prepare('SELECT * FROM users WHERE id = ?');
  $u->execute([(int)$row['user_id']]);
  $user = $u->fetch(PDO::FETCH_ASSOC);
  if (!$user) { remember_clear($pdo); return; }

  session_regenerate_id(true);
  $_SESSION['user'] = [
    'id'       => (int)$user['id'],
    'username' => $user['username'],
    'role'     => $user['role'],
    'avatar'   => $user['avatar'] ?? null,
  ];

  $newValidator = bin2hex(random_bytes(32));
  $newHash = hash('sha256', $newValidator);
  $newExpTs = time() + 60*60*24*60;
  $pdo->prepare('UPDATE auth_tokens SET validator_hash=?, expires_at=? WHERE id=?')
      ->execute([$newHash, date('c', $newExpTs), (int)$row['id']]);
  setcookie('remember', $row['selector'] . ':' . $newValidator, [
    'expires'  => $newExpTs,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

/** ===== Moderation state (kick/ban) ===== */
function user_is_banned_or_kicked(PDO $pdo, int $user_id): array {
  if (!$pdo) return ['banned'=>false,'until'=>null];
  if (!$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='moderation_actions'")->fetchColumn()) {
    return ['banned'=>false,'until'=>null];
  }
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
