<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../db.php';
$config = require __DIR__ . '/../config.php';
$unread = is_logged_in() ? get_unread_count($pdo, (int)$_SESSION['user']['id']) : 0;
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />
  <title><?= e($config['site_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { darkMode: 'class' };</script>
</head>
<body class="bg-slate-900 text-slate-100">
  <header class="bg-slate-800 border-b border-slate-700 sticky top-0 z-30">
    <div class="max-w-6xl mx-auto px-3 sm:px-4 py-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <button id="navToggle" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded border border-slate-600 bg-slate-700/70" aria-label="Ouvrir le menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="index.php" class="text-lg sm:text-xl font-bold truncate"><?= e($config['site_name']) ?></a>
      </div>
      <form action="search.php" method="get" class="hidden md:block flex-1 max-w-md">
        <input name="q" placeholder="Rechercher..." class="w-full px-3 py-1.5 rounded bg-slate-700 border border-slate-600 text-slate-100 placeholder-slate-400" />
      </form>
      <nav class="hidden lg:flex items-center gap-3">
        <a href="index.php" class="hover:underline">Forums</a>
        <a href="messages.php" class="hover:underline">Messages</a>
        <?php if (is_admin()): ?><a href="admin_users.php" class="hover:underline">Utilisateurs</a><?php endif; ?>
        <?php if (is_admin()): ?><a href="admin_audit.php" class="hover:underline">Audit IP</a><?php endif; ?>
        <?php if (is_admin()): ?><a href="admin_moderation.php" class="hover:underline">Modération</a><?php endif; ?>
        <?php if (is_admin()): ?><a href="admin_forums.php" class="hover:underline">Gestion Forums</a><?php endif; ?>
        <?php if (is_logged_in()): ?>
          <a href="notifications.php" class="relative inline-flex items-center gap-2 px-3 py-1 rounded bg-slate-700">
            <span>Notifications</span>
            <?php if ($unread>0): ?><span class="absolute -top-2 -right-2 text-xs bg-red-600 text-white rounded-full px-2 py-0.5"><?= $unread ?></span><?php endif; ?>
          </a>
          <a href="profile.php?u=<?= e($_SESSION['user']['username']) ?>" class="hidden md:flex items-center gap-2">
            <?php if (!empty($_SESSION['user']['avatar'])): ?>
              <img src="<?= e($_SESSION['user']['avatar']) ?>" alt="avatar" class="w-6 h-6 rounded-full border border-slate-600"/>
            <?php else: ?>
              <div class="w-6 h-6 rounded-full bg-slate-600"></div>
            <?php endif; ?>
            <span class="text-sm text-slate-300"><?= render_username($_SESSION['user']['username'], $_SESSION['user']['role']) ?> (<?= e($_SESSION['user']['role']) ?>)</span>
          </a>
          <a href="logout.php" class="inline-block px-3 py-1 rounded bg-slate-700">Déconnexion</a>
        <?php else: ?>
          <a href="register.php" class="inline-block px-3 py-1 rounded bg-slate-700">Inscription</a>
          <a href="login.php" class="inline-block px-3 py-1 rounded bg-slate-700">Connexion</a>
        <?php endif; ?>
      </nav>
    </div>

    <!-- Mobile panel -->
    <div id="mobilePanel" class="lg:hidden hidden border-t border-slate-700 bg-slate-800/95 backdrop-blur">
      <div class="max-w-6xl mx-auto px-3 py-3 space-y-3">
        <form action="search.php" method="get">
          <input name="q" placeholder="Rechercher..." class="w-full px-3 py-2 rounded bg-slate-700 border border-slate-600 text-slate-100 placeholder-slate-400" />
        </form>
        <div class="grid gap-2">
          <a href="index.php" class="px-3 py-2 rounded bg-slate-700/60">Forums</a>
          <a href="messages.php" class="px-3 py-2 rounded bg-slate-700/60">Messages</a>
          <?php if (is_admin()): ?><a href="admin_users.php" class="px-3 py-2 rounded bg-slate-700/60">Utilisateurs</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_audit.php" class="px-3 py-2 rounded bg-slate-700/60">Audit IP</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_moderation.php" class="px-3 py-2 rounded bg-slate-700/60">Modération</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_forums.php" class="px-3 py-2 rounded bg-slate-700/60">Gestion Forums</a><?php endif; ?>
          <?php if (is_logged_in()): ?>
            <a href="notifications.php" class="px-3 py-2 rounded bg-slate-700/60 flex items-center justify-between">Notifications <?php if ($unread>0): ?><span class="ml-2 text-xs bg-red-600 text-white rounded-full px-2 py-0.5"><?= $unread ?></span><?php endif; ?></a>
            <a href="profile.php?u=<?= e($_SESSION['user']['username']) ?>" class="px-3 py-2 rounded bg-slate-700/60">Profil</a>
            <a href="logout.php" class="px-3 py-2 rounded bg-slate-700/60">Déconnexion</a>
          <?php else: ?>
            <a href="register.php" class="px-3 py-2 rounded bg-slate-700/60">Inscription</a>
            <a href="login.php" class="px-3 py-2 rounded bg-slate-700/60">Connexion</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-3 sm:px-4 py-5">
<script>
  const navToggle = document.getElementById('navToggle');
  const panel = document.getElementById('mobilePanel');
  if (navToggle && panel) {
    navToggle.addEventListener('click', () => {
      panel.classList.toggle('hidden');
    });
  }
</script>
