<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Пользователи';
$pdo = get_pdo();
$current = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'role') {
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $id]);
        flash('success', 'Роль пользователя обновлена.');
        redirect('admin/users.php');
    }

    if ($action === 'delete' && $id !== (int) $current['id']) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Пользователь удалён.');
        redirect('admin/users.php');
    }
}

$users = $pdo->query(
    'SELECT u.*,
            COUNT(DISTINCT s.id) AS sets_count,
            COUNT(DISTINCT p.id) AS progress_count
     FROM users u
     LEFT JOIN flashcard_sets s ON s.user_id = u.id
     LEFT JOIN user_progress p ON p.user_id = u.id
     GROUP BY u.id
     ORDER BY u.id'
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <h1>Пользователи</h1>
    <p class="lead">Администратор может менять роль и удалять пользователей, кроме собственного аккаунта.</p>
</section>

<section class="table-wrap" style="margin-top: 18px;">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Логин</th>
            <th>Email</th>
            <th>Роль</th>
            <th>Наборы</th>
            <th>Прогресс</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= h((string) $user['id']) ?></td>
                <td><?= h($user['username']) ?></td>
                <td><?= h($user['email']) ?></td>
                <td>
                    <form method="post" class="inline-actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="role">
                        <input type="hidden" name="id" value="<?= h((string) $user['id']) ?>">
                        <select name="role" style="width:120px;">
                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>user</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                        </select>
                        <button type="submit">OK</button>
                    </form>
                </td>
                <td><?= h((string) $user['sets_count']) ?></td>
                <td><?= h((string) $user['progress_count']) ?></td>
                <td>
                    <?php if ((int) $user['id'] !== (int) $current['id']): ?>
                        <form method="post" onsubmit="return confirm('Удалить пользователя и его данные?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= h((string) $user['id']) ?>">
                            <button class="danger" type="submit">Удалить</button>
                        </form>
                    <?php else: ?>
                        <span class="meta">текущий аккаунт</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
