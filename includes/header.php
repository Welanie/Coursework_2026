<?php
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$activePath = trim(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(app_url('css/style.css')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= h(app_url('index.php')) ?>">
        <span class="brand-mark">Ch</span>
        <span>
            <strong><?= h(APP_NAME) ?></strong>
            <small><?= h(APP_SUBTITLE) ?></small>
        </span>
    </a>
    <nav class="nav">
        <a href="<?= h(app_url('sets.php')) ?>">Наборы</a>
        <a href="<?= h(app_url('cards.php')) ?>">Карточки</a>
        <a href="<?= h(app_url('stats.php')) ?>">Прогресс</a>
        <?php if ($user): ?>
            <a href="<?= h(app_url('my_sets.php')) ?>">Мои наборы</a>
            <a href="<?= h(app_url('favorites.php')) ?>">Избранное</a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
            <a href="<?= h(app_url('admin/index.php')) ?>">Админка</a>
        <?php endif; ?>
    </nav>
    <div class="account">
        <?php if ($user): ?>
            <span><?= h($user['username']) ?></span>
            <a class="button ghost" href="<?= h(app_url('logout.php')) ?>">Выйти</a>
        <?php else: ?>
            <a class="button ghost" href="<?= h(app_url('login.php')) ?>">Войти</a>
            <a class="button" href="<?= h(app_url('register.php')) ?>">Регистрация</a>
        <?php endif; ?>
    </div>
</header>

<main class="container">
    <?php foreach (consume_flash() as $item): ?>
        <div class="alert <?= h($item['type']) ?>"><?= h($item['message']) ?></div>
    <?php endforeach; ?>

