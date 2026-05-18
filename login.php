<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = 'Вход';
$error = '';
$login = trim($_POST['login'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';

    $stmt = get_pdo()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && verify_user_password($password, $user['password_hash'], (int) $user['id'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        flash('success', 'Вы вошли как ' . $user['username'] . '.');
        redirect('index.php');
    }

    $error = 'Неверный логин или пароль.';
}

include __DIR__ . '/includes/header.php';
?>

<section class="form-panel">
    <h1>Вход</h1>
    <p class="lead">Тестовые аккаунты: admin / 123456 и student / 123456.</p>

    <?php if ($error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label for="login">Логин или email</label>
            <input id="login" name="login" value="<?= h($login) ?>" required>
        </div>
        <div class="field">
            <label for="password">Пароль</label>
            <input id="password" type="password" name="password" required>
        </div>
        <button type="submit">Войти</button>
    </form>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

