<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Главная';
$user = current_user();
$pdo = get_pdo();

$counts = [
    'sets' => (int) $pdo->query('SELECT COUNT(*) FROM flashcard_sets WHERE is_public = 1')->fetchColumn(),
    'cards' => (int) $pdo->query('SELECT COUNT(*) FROM flashcards')->fetchColumn(),
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
];

$setsStmt = $pdo->query(
    'SELECT s.id, s.name, s.description, COUNT(f.id) AS cards_count
     FROM flashcard_sets s
     LEFT JOIN flashcards f ON f.set_id = s.id
     WHERE s.is_public = 1
     GROUP BY s.id
     ORDER BY s.id
     LIMIT 3'
);
$sets = $setsStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="panel">
        <h1>Тренажёр химических формул</h1>
        <p class="lead">Пользователь видит формулу, вспоминает название вещества, получает проверку ответа и собирает статистику прогресса по карточкам.</p>
        <div class="actions">
            <a class="button" href="<?= h(app_url('study.php?set_id=1&mode=normal&reset=1')) ?>">Начать тренировку</a>
            <a class="button ghost" href="<?= h(app_url('sets.php')) ?>">Открыть наборы</a>
            <?php if (!$user): ?>
                <a class="button ghost" href="<?= h(app_url('register.php')) ?>">Создать аккаунт</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel soft">
        <h2>Состояние проекта</h2>
        <div class="stats">
            <div class="stat"><strong><?= h((string) $counts['sets']) ?></strong><span>публичных наборов</span></div>
            <div class="stat"><strong><?= h((string) $counts['cards']) ?></strong><span>карточек</span></div>
            <div class="stat"><strong><?= h((string) $counts['users']) ?></strong><span>пользователей</span></div>
        </div>
    </div>
</section>

<section class="grid">
    <?php foreach ($sets as $set): ?>
        <article class="card">
            <span class="badge"><?= h((string) $set['cards_count']) ?> карточек</span>
            <h3><?= h($set['name']) ?></h3>
            <p class="meta"><?= h($set['description']) ?></p>
            <div class="inline-actions">
                <a class="button" href="<?= h(app_url('study.php?set_id=' . $set['id'] . '&mode=normal&reset=1')) ?>">Учить</a>
                <a class="button ghost" href="<?= h(app_url('set.php?id=' . $set['id'])) ?>">Подробнее</a>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($user): ?>
    <section class="panel" style="margin-top: 18px;">
        <h2>Быстрые действия</h2>
        <div class="actions">
            <a class="button" href="<?= h(app_url('stats.php')) ?>">Мой прогресс</a>
            <a class="button ghost" href="<?= h(app_url('my_sets.php')) ?>">Создать свой набор</a>
            <a class="button ghost" href="<?= h(app_url('favorites.php')) ?>">Избранные карточки</a>
        </div>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

