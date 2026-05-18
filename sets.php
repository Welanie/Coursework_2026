<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Наборы карточек';
$user = current_user();
$query = trim($_GET['q'] ?? '');
[$accessWhere, $accessParams] = set_accessible_where($user);

$where = [$accessWhere];
$params = $accessParams;
if ($query !== '') {
    $where[] = '(s.name LIKE ? OR s.description LIKE ?)';
    $params[] = '%' . $query . '%';
    $params[] = '%' . $query . '%';
}

$sql = 'SELECT s.*, u.username AS owner_name, COUNT(f.id) AS cards_count
        FROM flashcard_sets s
        LEFT JOIN users u ON u.id = s.user_id
        LEFT JOIN flashcards f ON f.set_id = s.id
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY s.id
        ORDER BY s.is_public DESC, s.created_at DESC';
$stmt = get_pdo()->prepare($sql);
$stmt->execute($params);
$sets = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <h1>Наборы карточек</h1>
    <form class="filters" method="get">
        <div class="field">
            <label for="q">Поиск по названию</label>
            <input id="q" name="q" value="<?= h($query) ?>" placeholder="Например: кислоты">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="submit">Найти</button>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <a class="button ghost" href="<?= h(app_url('sets.php')) ?>">Сбросить</a>
        </div>
    </form>
</section>

<section class="grid" style="margin-top: 18px;">
    <?php foreach ($sets as $set): ?>
        <article class="card">
            <span class="badge"><?= $set['is_public'] ? 'Публичный' : 'Личный' ?></span>
            <h3><?= h($set['name']) ?></h3>
            <p class="meta"><?= h($set['description']) ?></p>
            <p class="meta">Карточек: <?= h((string) $set['cards_count']) ?> · Автор: <?= h($set['owner_name'] ?: 'администратор') ?></p>
            <div class="inline-actions">
                <a class="button" href="<?= h(app_url('study.php?set_id=' . $set['id'] . '&mode=normal&reset=1')) ?>">Учить</a>
                <a class="button ghost" href="<?= h(app_url('study.php?set_id=' . $set['id'] . '&mode=test&reset=1')) ?>">Тест</a>
                <a class="button ghost" href="<?= h(app_url('set.php?id=' . $set['id'])) ?>">Открыть</a>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$sets): ?>
        <div class="card">Наборы не найдены.</div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

