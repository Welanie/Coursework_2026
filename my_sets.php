<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$pageTitle = 'Мои наборы';
$user = current_user();
$pdo = get_pdo();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM flashcard_sets WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        flash('success', 'Набор удалён.');
        redirect('my_sets.php');
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if ($name === '') {
            $errors[] = 'Введите название набора.';
        }

        if (!$errors) {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE flashcard_sets SET name = ?, description = ?, is_public = ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$name, $description, $isPublic, $id, $user['id']]);
                flash('success', 'Набор обновлён.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO flashcard_sets (user_id, name, description, is_public) VALUES (?, ?, ?, ?)');
                $stmt->execute([$user['id'], $name, $description, $isPublic]);
                flash('success', 'Набор создан.');
            }
            redirect('my_sets.php');
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM flashcard_sets WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['edit'], $user['id']]);
    $edit = $stmt->fetch() ?: null;
}

$stmt = $pdo->prepare(
    'SELECT s.*, COUNT(f.id) AS cards_count
     FROM flashcard_sets s
     LEFT JOIN flashcards f ON f.set_id = s.id
     WHERE s.user_id = ?
     GROUP BY s.id
     ORDER BY s.created_at DESC'
);
$stmt->execute([$user['id']]);
$sets = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="form-panel">
    <h1><?= $edit ? 'Редактировать набор' : 'Создать свой набор' ?></h1>
    <?php foreach ($errors as $error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">
        <div class="field">
            <label for="name">Название</label>
            <input id="name" name="name" value="<?= h($edit['name'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label for="description">Описание</label>
            <textarea id="description" name="description"><?= h($edit['description'] ?? '') ?></textarea>
        </div>
        <label><input type="checkbox" name="is_public" value="1" <?= ($edit['is_public'] ?? 0) ? 'checked' : '' ?> style="width:auto;min-height:auto;"> Доступен по ссылке и в общем списке</label>
        <div class="actions" style="margin-top: 16px;">
            <button type="submit">Сохранить</button>
            <?php if ($edit): ?><a class="button ghost" href="<?= h(app_url('my_sets.php')) ?>">Отмена</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="grid" style="margin-top: 18px;">
    <?php foreach ($sets as $set): ?>
        <article class="card">
            <span class="badge"><?= $set['is_public'] ? 'публичный' : 'личный' ?></span>
            <h3><?= h($set['name']) ?></h3>
            <p class="meta"><?= h($set['description']) ?></p>
            <p class="meta">Карточек: <?= h((string) $set['cards_count']) ?></p>
            <div class="inline-actions">
                <a class="button" href="<?= h(app_url('my_cards.php?set_id=' . $set['id'])) ?>">Карточки</a>
                <a class="button ghost" href="<?= h(app_url('set.php?id=' . $set['id'])) ?>">Ссылка</a>
                <a class="button ghost" href="<?= h(app_url('my_sets.php?edit=' . $set['id'])) ?>">Изменить</a>
                <form method="post" onsubmit="return confirm('Удалить набор?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= h((string) $set['id']) ?>">
                    <button class="danger" type="submit">Удалить</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$sets): ?>
        <div class="card">У вас пока нет собственных наборов.</div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

