<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_mod()) redirect('index.php');
check_csrf();
$id=(int)($_POST['id']??0);
$pdo->prepare('UPDATE topics SET deleted_at=CASE WHEN deleted_at IS NULL THEN ? ELSE deleted_at END WHERE id=?')->execute([date('c'), $id]);
redirect('view_topic.php?id='.$id);
