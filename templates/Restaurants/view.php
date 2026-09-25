<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Restaurant $restaurant
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Restaurant'), ['action' => 'edit', $restaurant->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Restaurant'), ['action' => 'delete', $restaurant->id], ['confirm' => __('Are you sure you want to delete # {0}?', $restaurant->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Restaurants'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Restaurant'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="restaurants view content">
            <h3><?= h($restaurant->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($restaurant->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Cuisine') ?></th>
                    <td><?= h($restaurant->cuisine) ?></td>
                </tr>
                <tr>
                    <th><?= __('City') ?></th>
                    <td><?= h($restaurant->city) ?></td>
                </tr>
                <tr>
                    <th><?= __('Address') ?></th>
                    <td><?= h($restaurant->address) ?></td>
                </tr>
                <tr>
                    <th><?= __('Tags') ?></th>
                    <td><?= h(json_encode($restaurant->tags)) ?></td>
                </tr>
                <tr>
                    <th><?= __('Embedding') ?></th>
                    <td><?= $restaurant->embedding === null ? __('not computed yet') : __('{0}-dimensional vector', count($restaurant->embedding)) ?></td>
                </tr>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= $this->Number->format($restaurant->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Osm Id') ?></th>
                    <td><?= $this->Number->format($restaurant->osm_id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($restaurant->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($restaurant->modified) ?></td>
                </tr>
            </table>
            <div class="text">
                <strong><?= __('Description') ?></strong>
                <blockquote>
                    <?= $this->Text->autoParagraph(h($restaurant->description)); ?>
                </blockquote>
            </div>
        </div>
    </div>
</div>