<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../db.php';

// Auto-login via remember-me if applicable
if (function_exists('remember_consume')) { remember_consume($pdo); }

$config = require __DIR__ . '/../config.php';
$unread = is_logged_in() ? get_unread_count($pdo, (int)$_SESSION['user']['id']) : 0;
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($config['site_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'Ubuntu', 'Cantarell', 'Noto Sans', 'Helvetica Neue', 'Arial', 'Apple Color Emoji', 'Segoe UI Emoji']
          },
          boxShadow: {
            'glow': '0 0 0 2px rgb(59 130 246 / 0.25), 0 0 0 8px rgb(59 130 246 / 0.10)',
          },
          backdropBlur: { xs: '2px' }
        }
      }
    };
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <script defer src="assets/ui.js"></script>
</head>
<body class="bg-gradient-to-b from-slate-950 to-slate-900 text-slate-100 min-h-screen selection:bg-blue-600/30 selection:text-slate-100">
  <!-- Topbar -->
  <header class="sticky top-0 z-40 supports-[backdrop-filter]:backdrop-blur bg-slate-900/70 border-b border-slate-800/60">
    <div class="max-w-7xl mx-auto px-3 sm:px-6">
      <div class="h-16 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <button id="navToggle" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/></svg>
          </button>
          <a href="index.php" class="group inline-flex items-center gap-2">
            <span class="inline-block w-7 h-7 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 ring-1 ring-white/20"></span>
            <span class="text-lg sm:text-xl font-extrabold tracking-tight group-hover:text-white/90 transition"><?= e($config['site_name']) ?></span>
          </a>
        </div>

        <form action="search.php" method="get" class="hidden md:flex items-center flex-1 max-w-xl mx-3">
          <div class="relative flex-1">
            <input name="q" placeholder="Rechercher un topic, un message, un auteur…" class="w-full pl-10 pr-3 py-2 rounded-2xl bg-white/5 border border-white/10 outline-none focus:border-blue-500/60 focus:ring-4 focus:ring-blue-600/10 placeholder:text-slate-400" />
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-3.5-3.5M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
          </div>
        </form>

        <nav class="hidden lg:flex items-center gap-2">
          <a href="index.php" class="btn-nav">Forums</a>
          <a href="messages.php" class="btn-nav">Messages</a>
          <?php if (is_admin()): ?><a href="admin_users.php" class="btn-nav">Utilisateurs</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_audit.php" class="btn-nav">Audit IP</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_moderation.php" class="btn-nav">Modération</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_forums.php" class="btn-nav">Forums+</a><?php endif; ?>

          <?php if (is_logged_in()): ?>
            <a href="notifications.php" class="btn-chip relative">
              <span>Notifications</span>
              <?php if ($unread>0): ?>
                <span class="notif-dot"><?= $unread ?></span>
              <?php endif; ?>
            </a>

            <div class="relative">
              <button id="userMenuBtn" class="btn-ghost inline-flex items-center gap-2">
                <?php if (!empty($_SESSION['user']['avatar'])): ?>
                  <img src="<?= e($_SESSION['user']['avatar']) ?>" alt="avatar" class="w-8 h-8 rounded-xl ring-1 ring-white/10 object-cover"/>
                <?php else: ?>
                  <div class="w-8 h-8 rounded-xl bg-slate-700 ring-1 ring-white/10"></div>
                <?php endif; ?>
                <span class="hidden md:block text-sm"><?= render_username($_SESSION['user']['username'], $_SESSION['user']['role']) ?></span>
                <svg class="w-4 h-4 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
              </button>
              <div id="userMenu" class="dropdown hidden">
                <a class="dropdown-item" href="profile.php?u=<?= e($_SESSION['user']['username']) ?>">Profil</a>
                <a class="dropdown-item" href="settings.php">Paramètres</a>
                <div class="dropdown-sep"></div>
                <a class="dropdown-item-danger" href="logout.php">Déconnexion</a>
              </div>
            </div>
          <?php else: ?>
            <a href="register.php" class="btn-primary">Inscription</a>
            <a href="login.php" class="btn-ghost">Connexion</a>
          <?php endif; ?>
        </nav>
      </div>
    </div>

    <!-- Mobile -->
    <div id="mobilePanel" class="lg:hidden hidden border-t border-white/10 bg-slate-900/80 supports-[backdrop-filter]:backdrop-blur">
      <div class="max-w-7xl mx-auto px-3 sm:px-6 py-4 space-y-3">
        <form action="search.php" method="get" class="flex">
          <input name="q" placeholder="Rechercher…" class="w-full px-3 py-2 rounded-2xl bg-white/5 border border-white/10 outline-none focus:border-blue-500/60 focus:ring-4 focus:ring-blue-600/10 placeholder:text-slate-400" />
        </form>
        <div class="grid gap-2">
          <a href="index.php" class="btn-block">Forums</a>
          <a href="messages.php" class="btn-block">Messages</a>
          <?php if (is_admin()): ?><a href="admin_users.php" class="btn-block">Utilisateurs</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_audit.php" class="btn-block">Audit IP</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_moderation.php" class="btn-block">Modération</a><?php endif; ?>
          <?php if (is_admin()): ?><a href="admin_forums.php" class="btn-block">Forums+</a><?php endif; ?>
          <?php if (is_logged_in()): ?>
            <a href="notifications.php" class="btn-block flex items-center justify-between">Notifications <?php if ($unread>0): ?><span class="notif-dot ml-2"><?= $unread ?></span><?php endif; ?></a>
            <a href="profile.php?u=<?= e($_SESSION['user']['username']) ?>" class="btn-block">Profil</a>
            <a href="logout.php" class="btn-block text-red-300 hover:text-red-200">Déconnexion</a>
          <?php else: ?>
            <a href="register.php" class="btn-primary w-full text-center">Inscription</a>
            <a href="login.php" class="btn-ghost w-full text-center">Connexion</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <!-- Page container -->
  <main class="max-w-7xl mx-auto px-3 sm:px-6 py-6 grid gap-6">
