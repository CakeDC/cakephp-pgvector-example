<?php
/**
 * @var \App\View\AppView $this
 */
?>
<div class="content">
    <h2><?= __('Restaurant Catalog') ?></h2>
    <p>
        <?= __(
            'A small CakePHP app showcasing pgvector-powered natural-language search: ' .
            'restaurants are pulled from OpenStreetMap, described in plain English, and ' .
            'ranked by semantic similarity in PostgreSQL rather than exact-match filters. ' .
            'See ARTICLE.md in the project root for a full walkthrough.'
        ) ?>
    </p>

    <ul>
        <li><?= $this->Html->link(__('Browse restaurants'), ['controller' => 'Restaurants', 'action' => 'index']) ?></li>
        <li><?= $this->Html->link(__('Search restaurants'), ['controller' => 'Restaurants', 'action' => 'search']) ?></li>
        <li><?= $this->Html->link(__('Add a restaurant'), ['controller' => 'Restaurants', 'action' => 'add']) ?></li>
    </ul>
</div>
