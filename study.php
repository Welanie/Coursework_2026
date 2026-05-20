<?php
require_once __DIR__ . '/includes/functions.php';

$allowedModes = ['normal', 'review', 'test'];
$setId = (int) ($_GET['set_id'] ?? $_POST['set_id'] ?? 0);
$mode = $_GET['mode'] ?? $_POST['mode'] ?? 'normal';
if (!in_array($mode, $allowedModes, true)) {
    $mode = 'normal';
}

$set = ensure_set_access($setId);
$user = current_user();
$pageTitle = 'Тренировка';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$sessionKey = 'study_' . $setId . '_' . $mode;
$shouldReset = $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reset']);

if ($shouldReset || empty($_SESSION[$sessionKey]) || ($_SESSION[$sessionKey]['set_id'] ?? 0) !== $setId) {
    $queue = build_study_queue($setId, $mode, $user ? (int) $user['id'] : null);
    if (!$user) {
        $queue = array_slice($queue, 0, DEMO_SET_LIMIT);
    }
    $_SESSION[$sessionKey] = [
        'set_id'        => $setId,
        'mode'          => $mode,
        'queue'         => $queue,
        'initial_total' => count($queue),
        'answered'      => false,
        'last'          => null,
        'score'         => 0,
        'total'         => 0,
    ];
}

$state    = $_SESSION[$sessionKey];
$state['initial_total'] = (int) ($state['initial_total'] ?? max(1, count($state['queue']) + (int) $state['total'] - (!empty($state['answered']) ? 1 : 0)));
$finished = false;
$summary  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'check';

    if ($action === 'check' && !$state['answered'] && !empty($state['queue'])) {
        $submittedCardId = (int) ($_POST['card_id'] ?? 0);
        $queueIds   = array_map('intval', $state['queue']);
        $queueIndex = $submittedCardId > 0 ? array_search($submittedCardId, $queueIds, true) : false;

        if ($submittedCardId <= 0) {
            $submittedCardId = (int) $queueIds[0];
            $queueIndex = 0;
        }

        $card = fetch_card($submittedCardId);
        if (!$card || (int) $card['set_id'] !== $setId) {
            $submittedCardId = (int) $queueIds[0];
            $queueIndex = 0;
            $card = fetch_card($submittedCardId);
        }

        if ($card && $queueIndex === false) {
            array_unshift($state['queue'], (int) $card['id']);
            $queueIndex = 0;
        }

        if ($card) {
            $answer  = trim($_POST['answer'] ?? '');
            $correct = answer_is_correct($answer, $card['name']);
            $state['answered'] = true;
            $state['last'] = [
                'card_id'     => (int) $card['id'],
                'queue_index' => (int) $queueIndex,
                'answer'      => $answer,
                'expected'    => $card['name'],
                'correct'     => $correct,
            ];
            $state['total']++;
            if ($correct) { $state['score']++; }
            if ($user) { update_progress((int) $user['id'], (int) $card['id'], $correct); }
        }
    }

    if ($action === 'next' && $state['answered']) {
        $last        = $state['last'];
        $removeIndex = (int) ($last['queue_index'] ?? 0);
        array_splice($state['queue'], $removeIndex, 1);
        if ($last && !$last['correct'] && $mode !== 'test') {
            $position = min(2, count($state['queue']));
            array_splice($state['queue'], $position, 0, [(int) $last['card_id']]);
        }
        $state['answered'] = false;
        $state['last']     = null;
    }

    if (empty($state['queue']) && $state['total'] > 0) {
        $finished = true;
        $summary  = [
            'score' => (int) $state['score'],
            'total' => (int) $state['total'],
        ];
        if ($user) {
            record_study_session((int) $user['id'], $setId, $mode, $summary['score'], $summary['total']);
        }
        unset($_SESSION[$sessionKey]);
    } else {
        $_SESSION[$sessionKey] = $state;
    }
}

$modeLabel = 'Обычный режим';
if ($mode === 'test') {
    $modeLabel = 'Тест';
} elseif ($mode === 'review') {
    $modeLabel = 'Повторение';
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($finished && $summary): ?>

    <?php $pct = $summary['total'] > 0 ? round($summary['score'] / $summary['total'] * 100) : 0; ?>
    <section class="panel study-card" style="text-align:center;">
        <div class="eyebrow">Тренировка завершена</div>
        <h1 style="font-size:26px;margin-bottom:6px;"><?= h($set['name']) ?></h1>
        <p class="meta" style="margin-bottom:24px;"><?= h($modeLabel) ?></p>

        <div style="display:inline-flex;align-items:baseline;gap:6px;margin-bottom:8px;">
            <span style="font-size:52px;font-weight:700;color:var(--cobalt);line-height:1;"><?= h((string)$summary['score']) ?></span>
            <span style="font-size:24px;color:var(--muted);">/ <?= h((string)$summary['total']) ?></span>
        </div>
        <p class="meta" style="margin-bottom:24px;">
            <?php if ($pct >= 80): ?>
                <span class="badge success">Отлично - <?= $pct ?>%</span>
            <?php elseif ($pct >= 50): ?>
                <span class="badge amber">Неплохо - <?= $pct ?>%</span>
            <?php else: ?>
                <span class="badge danger">Есть над чем поработать - <?= $pct ?>%</span>
            <?php endif; ?>
        </p>

        <?php if (!$user): ?>
            <div class="alert warning" style="text-align:left;">Вы проходили демо-режим. Войдите в аккаунт, чтобы сохранять прогресс.</div>
        <?php endif; ?>

        <div class="actions" style="justify-content:center;margin-top:8px;">
            <a class="button" href="<?= h(app_url('study.php?set_id=' . $setId . '&mode=' . $mode . '&reset=1')) ?>">Повторить</a>
            <a class="button ghost" href="<?= h(app_url('set.php?id=' . $setId)) ?>">К набору</a>
            <?php if ($user): ?>
                <a class="button ghost" href="<?= h(app_url('stats.php')) ?>">Статистика</a>
            <?php endif; ?>
        </div>
    </section>

<?php else: ?>
    <?php
    $state     = $_SESSION[$sessionKey] ?? $state;
    $currentId = (int)(
        ($state['answered'] && !empty($state['last']['card_id']))
            ? $state['last']['card_id']
            : ($state['queue'][0] ?? 0)
    );
    $card       = $currentId ? fetch_card($currentId) : null;
    $initialTotal = max(1, (int) ($state['initial_total'] ?? 1));
    $remainingCards = count($state['queue']);
    if (!empty($state['answered']) && $remainingCards > 0) {
        $remainingCards--;
    }
    $shownTotal = min((int) $state['total'], $initialTotal);
    $progress = min(100, round($shownTotal / $initialTotal * 100));
    ?>

    <?php if (!$card): ?>
        <section class="panel" style="max-width:520px;margin:0 auto;">
            <h1>В наборе нет карточек</h1>
            <p class="meta">Добавьте карточки в этот набор, чтобы начать тренировку.</p>
            <a class="button ghost" href="<?= h(app_url('sets.php')) ?>">К наборам</a>
        </section>
    <?php else: ?>

        <section class="panel study-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div>
                    <div class="eyebrow" style="margin-bottom:2px;"><?= h($set['name']) ?></div>
                    <span class="badge neutral"><?= h($modeLabel) ?></span>
                </div>
                <div class="meta" style="text-align:right;font-size:12px;">
                    Верно:
                    <span style="color:var(--cobalt);font-weight:700;"><?= h((string)$state['score']) ?></span>
                    / <?= h((string)$initialTotal) ?>
                </div>
            </div>

            <!-- progress bar -->
            <div class="progress-bar-wrap">
                <div class="progress-bar" style="width:<?= $progress ?>%;"></div>
            </div>

            <p class="meta" style="text-align:center;margin-bottom:8px;font-size:13px;">Введите название вещества</p>

            <!-- formula display -->
            <div class="formula-backdrop">
                <div class="formula"><?= render_formula($card['formula']) ?></div>
            </div>

            <?php if ($state['answered'] && $state['last']): ?>
                <?php if ($state['last']['correct']): ?>
                    <div class="result-correct">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <circle cx="9" cy="9" r="8" fill="#dcfce7"/>
                            <path d="M5.5 9l2.5 2.5 5-5" stroke="#059669" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Верно: <?= h($state['last']['expected']) ?>
                    </div>
                <?php else: ?>
                    <div class="result-wrong">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <circle cx="9" cy="9" r="8" fill="#fef2f2"/>
                            <path d="M6 6l6 6M12 6l-6 6" stroke="#dc2626" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        Неверно. Правильный ответ: <?= h($state['last']['expected']) ?>
                    </div>
                    <p class="meta" style="font-size:12px;margin-top:-6px;">Ваш ответ: <?= h($state['last']['answer'] ?: 'пустой ответ') ?></p>
                <?php endif; ?>

                <form method="post" style="margin-top:16px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="set_id" value="<?= h((string)$setId) ?>">
                    <input type="hidden" name="mode"   value="<?= h($mode) ?>">
                    <input type="hidden" name="action" value="next">
                    <button type="submit" style="width:100%;justify-content:center;">Следующая карточка</button>
                </form>

            <?php else: ?>
                <form method="post" style="margin-top:16px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="set_id"  value="<?= h((string)$setId) ?>">
                    <input type="hidden" name="mode"    value="<?= h($mode) ?>">
                    <input type="hidden" name="action"  value="check">
                    <input type="hidden" name="card_id" value="<?= h((string)$card['id']) ?>">
                    <div class="field">
                        <label for="answer">Ответ</label>
                        <input id="answer" name="answer" autocomplete="off" autofocus required
                               placeholder="Введите название вещества...">
                    </div>
                    <button type="submit" style="width:100%;justify-content:center;">Проверить</button>
                </form>
            <?php endif; ?>

            <p class="meta" style="margin-top:18px;font-size:12px;display:flex;justify-content:space-between;">
                <span>Осталось в очереди: <strong><?= h((string)$remainingCards) ?></strong></span>
                <span>Прогресс: <?= $progress ?>%</span>
            </p>
        </section>

    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
