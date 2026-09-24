<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRestaurants extends BaseMigration
{
    public function up(): void
    {
        $this->execute('CREATE EXTENSION IF NOT EXISTS vector');

        $table = $this->table('restaurants');
        $table
            ->addColumn('osm_id', 'biginteger', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('cuisine', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('address', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('tags', 'json', ['null' => true])
            ->addColumn('description', 'text', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['osm_id'], ['unique' => true])
            ->create();

        // Phinx has no native pgvector column type, so add it with raw SQL.
        $this->execute('ALTER TABLE restaurants ADD COLUMN embedding vector(128)');
    }

    public function down(): void
    {
        $this->table('restaurants')->drop()->save();
    }
}
