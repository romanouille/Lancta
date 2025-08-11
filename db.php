<?php
$config = require __DIR__ . '/config.php';

if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0777, true);
if (!is_dir($config['uploads_dir'])) mkdir($config['uploads_dir'], 0777, true);
if (!is_dir($config['avatars_dir'])) mkdir($config['avatars_dir'], 0777, true);

try {
  $pdo = new PDO('sqlite:' . $config['db_path']);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('PRAGMA foreign_keys = ON;');

  $pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    username_canonical TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "user",
    avatar TEXT NULL,
    created_at TEXT NOT NULL
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS forums (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_at TEXT NOT NULL
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS topics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    forum_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    pinned INTEGER NOT NULL DEFAULT 0,
    locked INTEGER NOT NULL DEFAULT 0,
    deleted_at TEXT NULL,
    created_at TEXT NOT NULL,
    source_ip TEXT NULL,
    source_port INTEGER NULL,
    FOREIGN KEY (forum_id) REFERENCES forums(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    topic_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    deleted_at TEXT NULL,
    created_at TEXT NOT NULL,
    source_ip TEXT NULL,
    source_port INTEGER NULL,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    payload TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
  );');
  $pdo->exec('CREATE TABLE IF NOT EXISTS conversation_participants (
    conversation_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    PRIMARY KEY (conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );');
  $pdo->exec('CREATE TABLE IF NOT EXISTS conversation_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    created_at TEXT NOT NULL,
    source_ip TEXT NULL,
    source_port INTEGER NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS moderation_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    until TEXT NULL,
    reason TEXT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
  );');

  $pdo->exec('CREATE TABLE IF NOT EXISTS polls (
    topic_id INTEGER PRIMARY KEY,
    question TEXT NOT NULL,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
  );');
  $pdo->exec('CREATE TABLE IF NOT EXISTS poll_options (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    topic_id INTEGER NOT NULL,
    text TEXT NOT NULL,
    votes INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
  );');
  $pdo->exec('CREATE TABLE IF NOT EXISTS poll_votes (
    option_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    PRIMARY KEY (option_id, user_id),
    FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );');

  // migrations for ip/port
  function addColumnIfMissing($pdo, $table, $column, $type){
    $stmt = $pdo->prepare('PRAGMA table_info(' . $table . ')');
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $names = array_map(function($c){ return $c['name']; }, $cols);
    if (!in_array($column, $names)) {
      $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $type);
    }
  }
  addColumnIfMissing($pdo, 'topics', 'source_ip', 'TEXT');
  addColumnIfMissing($pdo, 'topics', 'source_port', 'INTEGER');
  addColumnIfMissing($pdo, 'replies', 'source_ip', 'TEXT');
  addColumnIfMissing($pdo, 'replies', 'source_port', 'INTEGER');
  addColumnIfMissing($pdo, 'conversation_messages', 'source_ip', 'TEXT');
  addColumnIfMissing($pdo, 'conversation_messages', 'source_port', 'INTEGER');

  // seed
  $count = (int)$pdo->query('SELECT COUNT(*) FROM forums')->fetchColumn();
  if ($count === 0) {
    $stmt = $pdo->prepare('INSERT INTO forums (name, description, created_at) VALUES (?, ?, ?)');
    $stmt->execute(["Général", "Discussions générales", date('c')]);
  }
} catch (Exception $e) {
  die('DB Error: ' . $e->getMessage());
}
