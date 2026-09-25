<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Restaurant> $restaurants
 */
?>
<div class="restaurants index content">
    <?= $this->Html->link(__('New Restaurant'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Restaurants') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('osm_id') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= $this->Paginator->sort('cuisine') ?></th>
                    <th><?= $this->Paginator->sort('city') ?></th>
                    <th><?= $this->Paginator->sort('address') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($restaurants as $restaurant): ?>
                <tr>
                    <td><?= $this->Number->format($restaurant->id) ?></td>
                    <td><?= $this->Number->format($restaurant->osm_id) ?></td>
                    <td><?= h($restaurant->name) ?></td>
                    <td><?= h($restaurant->cuisine) ?></td>
                    <td><?= h($restaurant->city) ?></td>
                    <td><?= h($restaurant->address) ?></td>
                    <td><?= h($restaurant->created) ?></td>
                    <td><?= h($restaurant->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $restaurant->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $restaurant->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $restaurant->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $restaurant->id),
                            ]
                        ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
    </div>
</div>