<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_mod()) redirect('index.php');
check_csrf();
$id=(int)($_POST['id']??0);
$pdo->prepare('UPDATE topics SET locked=CASE WHEN locked=1 THEN 0 ELSE 1 END WHERE id=?')->execute([$id]);
redirect('view_topic.php?id='.$id);
