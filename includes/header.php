<?php
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$activePath  = trim($scriptName, '/');
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

    <nav class="nav" aria-label="Основная навигация">
        <?php
        $navLinks = [
            'sets.php'      => 'Наборы',
            'cards.php'     => 'Карточки',
            'stats.php'     => 'Прогресс',
        ];
        if ($user) {
            $navLinks['my_sets.php']   = 'Мои наборы';
            $navLinks['favorites.php'] = 'Избранное';
        }
        foreach ($navLinks as $href => $label):
            $isActive = substr($activePath, -strlen($href)) === $href;
        ?>
            <a href="<?= h(app_url($href)) ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?= h($label) ?>
            </a>
        <?php endforeach; ?>

        <?php if (is_admin()): ?>
            <a href="<?= h(app_url('admin/index.php')) ?>"
               <?= strpos($activePath, 'admin/') !== false ? 'aria-current="page"' : '' ?>>
                Админка
            </a>
        <?php endif; ?>
    </nav>

    <div class="account">
        <?php if ($user): ?>
            <span><?= h($user['username']) ?></span>
            <a class="button ghost sm" href="<?= h(app_url('logout.php')) ?>">Выйти</a>
        <?php else: ?>
            <a class="button ghost sm" href="<?= h(app_url('login.php')) ?>">Войти</a>
            <a class="button sm" href="<?= h(app_url('register.php')) ?>">Регистрация</a>
        <?php endif; ?>
    </div>
</header>

<main class="container">
    <?php foreach (consume_flash() as $item): ?>
        <div class="alert <?= h($item['type']) ?>"><?= h($item['message']) ?></div>
    <?php endforeach; ?>
