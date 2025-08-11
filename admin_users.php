<?php
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
if (!is_admin()) redirect('index.php');

// Handle role change
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $uid = (int)($_POST['user_id'] ?? 0);
  $new_role = trim($_POST['role'] ?? 'user');
  if (!in_array($new_role, ['user','mod','admin'], true)) {
    $msg = 'Rôle invalide.';
  } else {
    if ($uid === (int)($_SESSION['user']['id'])) {
      $msg = 'Impossible de changer votre propre rôle ici.';
    } else {
      $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
      $stmt->execute([$new_role, $uid]);
      $msg = 'Rôle mis à jour.';
    }
  }
}

// Filtering & pagination
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;
$offset = ($page - 1) * $per;

$where = '';
$params = [];
if ($q !== '') {
  $where = 'WHERE username LIKE ? OR username_canonical LIKE ?';
  $params = ['%' . $q . '%', '%' . mb_strtolower($q, 'UTF-8') . '%'];
}

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM users ' . $where);
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per));

$listStmt = $pdo->prepare('SELECT id, username, username_canonical, role, created_at FROM users ' . $where . ' ORDER BY created_at DESC LIMIT ? OFFSET ?');
foreach ($params as $i => $p) { $listStmt->bindValue($i+1, $p, PDO::PARAM_STR); }
$listStmt->bindValue(count($params)+1, $per, PDO::PARAM_INT);
$listStmt->bindValue(count($params)+2, $offset, PDO::PARAM_INT);
$listStmt->execute();
$users = $listStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<h1 class="text-2xl font-semibold mb-4">Administration — Utilisateurs (rôles)</h1>
<?php if ($msg): ?><div class="mb-3 p-3 bg-emerald-900/40 text-emerald-200 rounded"><?= e($msg) ?></div><?php endif; ?>

<form method="get" class="mb-4 flex gap-2">
  <input name="q" value="<?= e($q) ?>" placeholder="Recherche par pseudo" class="px-3 py-2 rounded bg-slate-900 border border-slate-700 w-64"/>
  <button class="px-3 py-2 rounded bg-slate-700">Rechercher</button>
</form>

<div class="bg-slate-800 border border-slate-700 rounded p-4 overflow-x-auto">
  <table class="w-full text-sm">
    <thead>
      <tr class="text-left border-b border-slate-700">
        <th class="py-2">ID</th>
        <th>Pseudo</th>
        <th>Rôle actuel</th>
        <th>Inscrit</th>
        <th>Changer le rôle</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr class="border-b border-slate-800">
        <td class="py-2"><?= (int)$u['id'] ?></td>
        <td><a class="text-blue-400 hover:underline" href="profile.php?u=<?= e($u['username']) ?>"><?= render_username($u['username'], $u['role']) ?></a></td>
        <td><?= e($u['role']) ?></td>
        <td><?= e(date('d/m/Y H:i', strtotime($u['created_at']))) ?></td>
        <td>
          <form method="post" class="flex items-center gap-2">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"/>
            <select name="role" class="border border-slate-600 rounded p-1 bg-slate-900">
              <option value="user" <?= $u['role']==='user'?'selected':''; ?>>user</option>
              <option value="mod" <?= $u['role']==='mod'?'selected':''; ?>>mod</option>
              <option value="admin" <?= $u['role']==='admin'?'selected':''; ?>>admin</option>
            </select>
            <button class="px-3 py-1 rounded bg-slate-700">Mettre à jour</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
      <tr><td colspan="5" class="py-3 text-slate-400">Aucun utilisateur.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$base = 'admin_users.php?q=' . urlencode($q);
echo '<nav class="flex gap-2 mt-4">';
if ($page>1) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$base.'&page='.($page-1).'">Précédent</a>';
for($i=1;$i<=$total_pages;$i++){ $cl = $i==$page ? ' bg-slate-100 text-slate-900' : ''; echo '<a class="px-3 py-1 border border-slate-600 rounded'.$cl.'" href="'.$base.'&page='.$i.'">'.$i.'</a>'; }
if ($page<$total_pages) echo '<a class="px-3 py-1 border border-slate-600 rounded" href="'.$base.'&page='.($page+1).'">Suivant</a>';
echo '</nav>';
?>

<?php include __DIR__ . '/partials/footer.php'; ?>
