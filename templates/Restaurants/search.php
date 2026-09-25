<h1><?= __('Search Restaurants') ?></h1>

<?= $this->Form->create(null, ['type' => 'get']) ?>
<?= $this->Form->control('q', ['label' => 'What are you looking for?', 'value' => $query]) ?>
<?= $this->Form->button(__('Search')) ?>
<?= $this->Form->end() ?>

<?php if ($query !== '' && $results === []): ?>
    <p><?= __('No restaurants found for {0}.', h($query)) ?></p>
<?php endif; ?>

<?php if ($results !== []): ?>
    <ul>
        <?php foreach ($results as $restaurant): ?>
            <li>
                <strong><?= h($restaurant->name) ?></strong>
                (<?= h($restaurant->cuisine) ?>) — <?= h($restaurant->address) ?>
                <br>
                <?= h($restaurant->description) ?>
                <br>
                <small><?= __('distance: {0}', h(round($restaurant->distance, 4))) ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
