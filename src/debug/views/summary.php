<?php

declare(strict_types=1);

/**
 * @var int $queryCount
 * @var int $queryTime
 * @var yii\httpclient\debug\HttpClientPanel $panel
 * @var yii\web\View $this
 */
?>
<?php if ($queryCount): ?>
<div class="yii-debug-toolbar__block">
    <a href="<?= $panel->getUrl() ?>" title="Executed <?= $queryCount ?> HTTP Requests which took <?= $queryTime ?>.">
        HTTP Requests <span class="yii-debug-toolbar__label yii-debug-toolbar__label_info"><?= $queryCount ?></span> <span class="yii-debug-toolbar__label"><?= $queryTime ?></span>
    </a>
</div>
<?php endif; ?>
