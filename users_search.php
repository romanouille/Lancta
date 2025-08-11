<?php
require __DIR__ . '/db.php';
$q = trim($_GET['q'] ?? '');
header('Content-Type: application/json');
if ($q === '') { echo json_encode([]); exit; }
$can = mb_strtolower($q, 'UTF-8') . '%';
$stmt = $pdo->prepare('SELECT username FROM users WHERE username_canonical LIKE ? ORDER BY username LIMIT 10');
$stmt->execute([$can]);
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN), JSON_UNESCAPED_UNICODE);
