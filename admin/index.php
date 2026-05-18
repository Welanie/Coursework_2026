<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Админ-панель';
$pdo = get_pdo();

$stats = [
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'sets' => (int) $pdo->query('SELECT COUNT(*) FROM flashcard_sets')->fetchColumn(),
    'cards' => (int) $pdo->query('SELECT COUNT(*) FROM flashcards')->fetchColumn(),
    'sessions' => (int) $pdo->query('SELECT COUNT(*) FROM study_sessions')->fetchColumn(),
];

$sessions = $pdo->query(
    'SELECT ss.*, u.username, s.name AS set_name
     FROM study_sessions ss
     JOIN users u ON u.id = ss.user_id
     JOIN flashcard_sets s ON s.id = ss.set_id
     ORDER BY ss.date DESC
     LIMIT 10'
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <h1>Административная панель</h1>
    <div class="stats">
        <div class="stat"><strong><?= h((string) $stats['users']) ?></strong><span>пользователей</span></div>
        <div class="stat"><strong><?= h((string) $stats['sets']) ?></strong><span>наборов</span></div>
        <div class="stat"><strong><?= h((string) $stats['cards']) ?></strong><span>карточек</span></div>
        <div class="stat"><strong><?= h((string) $stats['sessions']) ?></strong><span>сессий</span></div>
    </div>
    <div class="actions" style="margin-top: 18px;">
        <a class="button" href="<?= h(app_url('admin/sets.php')) ?>">Наборы</a>
        <a class="button ghost" href="<?= h(app_url('admin/cards.php')) ?>">Карточки</a>
        <a class="button ghost" href="<?= h(app_url('admin/users.php')) ?>">Пользователи</a>
    </div>
</section>

<section class="table-wrap" style="margin-top: 18px;">
    <table>
        <thead>
        <tr>
            <th>Дата</th>
            <th>Пользователь</th>
            <th>Набор</th>
            <th>Режим</th>
            <th>Результат</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($sessions as $session): ?>
            <tr>
                <td><?= h($session['date']) ?></td>
                <td><?= h($session['username']) ?></td>
                <td><?= h($session['set_name']) ?></td>
                <td><?= h($session['mode']) ?></td>
                <td><?= h((string) $session['score']) ?> / <?= h((string) $session['total']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$sessions): ?>
            <tr><td colspan="5">Сессий пока нет.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
