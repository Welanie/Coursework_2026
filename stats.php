<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Прогресс';
if (!is_logged_in()) {
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="panel">
        <h1>Прогресс</h1>
        <p class="lead">Статистика сохраняется только для авторизованных пользователей.</p>
        <div class="actions">
            <a class="button" href="<?= h(app_url('login.php')) ?>">Войти</a>
            <a class="button ghost" href="<?= h(app_url('register.php')) ?>">Зарегистрироваться</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$user = current_user();
$pdo = get_pdo();

$summaryStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(correct_count), 0) AS correct_answers,
            COALESCE(SUM(total_count), 0) AS total_answers,
            COUNT(*) AS learned_cards
     FROM user_progress
     WHERE user_id = ?'
);
$summaryStmt->execute([$user['id']]);
$summary = $summaryStmt->fetch();

$sessionsStmt = $pdo->prepare(
    'SELECT DATE(date) AS day, SUM(score) AS score, SUM(total) AS total
     FROM study_sessions
     WHERE user_id = ?
     GROUP BY DATE(date)
     ORDER BY day'
);
$sessionsStmt->execute([$user['id']]);
$sessions = $sessionsStmt->fetchAll();

$weakStmt = $pdo->prepare(
    'SELECT f.id, f.formula, f.name, c.name AS category,
            p.correct_count, p.total_count, p.rating
     FROM user_progress p
     JOIN flashcards f ON f.id = p.flashcard_id
     JOIN categories c ON c.id = f.category_id
     WHERE p.user_id = ? AND p.total_count > 0
     ORDER BY (p.correct_count / p.total_count) ASC, p.rating ASC
     LIMIT 8'
);
$weakStmt->execute([$user['id']]);
$weakCards = $weakStmt->fetchAll();

$chartLabels = [];
$chartValues = [];
foreach ($sessions as $session) {
    $chartLabels[] = $session['day'];
    $chartValues[] = (int) $session['total'] > 0 ? round(((int) $session['score'] / (int) $session['total']) * 100) : 0;
}

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <h1>Мой прогресс</h1>
    <div class="stats">
        <div class="stat"><strong><?= h((string) $summary['learned_cards']) ?></strong><span>карточек с попытками</span></div>
        <div class="stat"><strong><?= h((string) $summary['correct_answers']) ?></strong><span>правильных ответов</span></div>
        <div class="stat"><strong><?= h((string) $summary['total_answers']) ?></strong><span>всего ответов</span></div>
    </div>
</section>

<section class="panel" style="margin-top: 18px;">
    <h2>График правильных ответов по дням</h2>
    <canvas class="progress-chart" id="progressChart" width="900" height="260"></canvas>
</section>

<section class="table-wrap" style="margin-top: 18px;">
    <table>
        <thead>
        <tr>
            <th>Формула</th>
            <th>Название</th>
            <th>Категория</th>
            <th>Правильно</th>
            <th>Рейтинг</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($weakCards as $card): ?>
            <tr>
                <td class="formula" style="font-size: 24px;"><?= render_formula($card['formula']) ?></td>
                <td><a href="<?= h(app_url('card.php?id=' . $card['id'])) ?>"><?= h($card['name']) ?></a></td>
                <td><?= h($card['category']) ?></td>
                <td><?= h((string) $card['correct_count']) ?> / <?= h((string) $card['total_count']) ?></td>
                <td><?= h((string) $card['rating']) ?>/5</td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$weakCards): ?>
            <tr><td colspan="5">После первой тренировки здесь появятся сложные карточки.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<script>
(function () {
    var labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    var values = <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE) ?>;
    var canvas = document.getElementById('progressChart');
    var ctx = canvas.getContext('2d');
    var width = canvas.width;
    var height = canvas.height;
    var padding = 34;
    ctx.clearRect(0, 0, width, height);
    ctx.strokeStyle = '#d9e2ec';
    ctx.lineWidth = 1;
    for (var i = 0; i <= 4; i++) {
        var y = padding + (height - padding * 2) * i / 4;
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
    }
    if (!values.length) {
        ctx.fillStyle = '#667085';
        ctx.font = '16px Segoe UI, Arial';
        ctx.fillText('Нет данных для графика', padding, height / 2);
        return;
    }
    var step = values.length === 1 ? 0 : (width - padding * 2) / (values.length - 1);
    ctx.strokeStyle = '#1f7a6d';
    ctx.lineWidth = 3;
    ctx.beginPath();
    values.forEach(function (value, index) {
        var x = padding + step * index;
        var y = height - padding - ((height - padding * 2) * value / 100);
        if (index === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    });
    ctx.stroke();
    values.forEach(function (value, index) {
        var x = padding + step * index;
        var y = height - padding - ((height - padding * 2) * value / 100);
        ctx.fillStyle = '#b64b2a';
        ctx.beginPath();
        ctx.arc(x, y, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#1d2433';
        ctx.font = '12px Segoe UI, Arial';
        ctx.fillText(value + '%', x - 10, y - 10);
        ctx.fillText(labels[index].slice(5), x - 18, height - 10);
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

