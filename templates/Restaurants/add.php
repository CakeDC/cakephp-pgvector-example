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
            <?= $this->Html->link(__('List Restaurants'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="restaurants form content">
            <?= $this->Form->create($restaurant) ?>
            <fieldset>
                <legend><?= __('Add Restaurant') ?></legend>
                <?php
                    echo $this->Form->control('osm_id');
                    echo $this->Form->control('name');
                    echo $this->Form->control('cuisine');
                    echo $this->Form->control('city');
                    echo $this->Form->control('address');
                    echo $this->Form->control('description');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
