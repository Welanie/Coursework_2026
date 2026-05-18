<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = current_user();
$setId = (int) ($_GET['set_id'] ?? $_POST['set_id'] ?? 0);
if (!user_owns_set($setId, (int) $user['id'])) {
    http_response_code(403);
    exit('Можно редактировать только свои наборы.');
}

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM flashcard_sets WHERE id = ? AND user_id = ?');
$stmt->execute([$setId, $user['id']]);
$set = $stmt->fetch();
$pageTitle = 'Карточки набора';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM flashcards WHERE id = ? AND set_id = ?');
        $stmt->execute([$id, $setId]);
        flash('success', 'Карточка удалена.');
        redirect('my_cards.php?set_id=' . $setId);
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $formula = trim($_POST['formula'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $difficulty = min(5, max(1, (int) ($_POST['difficulty'] ?? 1)));

        if ($categoryId <= 0 || $formula === '' || $name === '') {
            $errors[] = 'Заполните категорию, формулу и название.';
        }

        if (!$errors) {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE flashcards SET category_id = ?, formula = ?, name = ?, difficulty = ? WHERE id = ? AND set_id = ?');
                $stmt->execute([$categoryId, $formula, $name, $difficulty, $id, $setId]);
                flash('success', 'Карточка обновлена.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO flashcards (set_id, category_id, formula, name, difficulty) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$setId, $categoryId, $formula, $name, $difficulty]);
                flash('success', 'Карточка добавлена.');
            }
            redirect('my_cards.php?set_id=' . $setId);
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM flashcards WHERE id = ? AND set_id = ?');
    $stmt->execute([(int) $_GET['edit'], $setId]);
    $edit = $stmt->fetch() ?: null;
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$stmt = $pdo->prepare(
    'SELECT f.*, c.name AS category
     FROM flashcards f
     JOIN categories c ON c.id = f.category_id
     WHERE f.set_id = ?
     ORDER BY f.id DESC'
);
$stmt->execute([$setId]);
$cards = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <h1><?= h($set['name']) ?></h1>
    <p class="lead">Собственные карточки можно использовать в обычном, тестовом и повторном режиме.</p>
    <div class="actions">
        <a class="button ghost" href="<?= h(app_url('my_sets.php')) ?>">К моим наборам</a>
        <a class="button" href="<?= h(app_url('study.php?set_id=' . $setId . '&mode=normal&reset=1')) ?>">Учить</a>
    </div>
</section>

<section class="form-panel" style="margin-top: 18px;">
    <h2><?= $edit ? 'Редактировать карточку' : 'Добавить карточку' ?></h2>
    <?php foreach ($errors as $error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="set_id" value="<?= h((string) $setId) ?>">
        <input type="hidden" name="id" value="<?= h((string) ($edit['id'] ?? 0)) ?>">
        <div class="field">
            <label for="category_id">Категория</label>
            <select id="category_id" name="category_id" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= h((string) $category['id']) ?>" <?= (int) ($edit['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="formula">Формула</label>
            <input id="formula" name="formula" value="<?= h($edit['formula'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label for="name">Название</label>
            <input id="name" name="name" value="<?= h($edit['name'] ?? '') ?>" required>
        </div>
        <div class="field">
            <label for="difficulty">Сложность 1-5</label>
            <input id="difficulty" type="number" min="1" max="5" name="difficulty" value="<?= h((string) ($edit['difficulty'] ?? 1)) ?>">
        </div>
        <div class="actions">
            <button type="submit">Сохранить</button>
            <?php if ($edit): ?><a class="button ghost" href="<?= h(app_url('my_cards.php?set_id=' . $setId)) ?>">Отмена</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="table-wrap" style="margin-top: 18px;">
    <table>
        <thead>
        <tr>
            <th>Формула</th>
            <th>Название</th>
            <th>Категория</th>
            <th>Сложность</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($cards as $card): ?>
            <tr>
                <td class="formula" style="font-size: 24px;"><?= render_formula($card['formula']) ?></td>
                <td><?= h($card['name']) ?></td>
                <td><?= h($card['category']) ?></td>
                <td><?= h((string) $card['difficulty']) ?>/5</td>
                <td class="inline-actions">
                    <a href="<?= h(app_url('my_cards.php?set_id=' . $setId . '&edit=' . $card['id'])) ?>">Изменить</a>
                    <form method="post" onsubmit="return confirm('Удалить карточку?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="set_id" value="<?= h((string) $setId) ?>">
                        <input type="hidden" name="id" value="<?= h((string) $card['id']) ?>">
                        <button class="danger" type="submit">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$cards): ?>
            <tr><td colspan="5">Добавьте первую карточку в набор.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
