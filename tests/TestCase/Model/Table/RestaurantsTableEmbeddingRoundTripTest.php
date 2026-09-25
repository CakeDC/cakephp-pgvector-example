<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class RestaurantsTableEmbeddingRoundTripTest extends TestCase
{
    protected array $fixtures = ['app.Restaurants'];

    public function testEmbeddingArrayRoundTripsThroughSave(): void
    {
        $table = TableRegistry::getTableLocator()->get('Restaurants');

        // Create first — RestaurantsTable::beforeSave() assigns a real embedding
        // from the description on every new entity (see RestaurantsTableAutoEmbedTest),
        // so to isolate VectorType's array <-> Postgres literal round trip, update
        // only the embedding afterwards, leaving `description` clean.
        $entity = $table->newEntity([
            'osm_id' => 5005,
            'name' => 'Roundtrip Diner',
            'description' => 'Test description.',
        ]);
        $table->saveOrFail($entity);

        $embedding = array_fill(0, 128, 0.0);
        $embedding[0] = 0.1;
        $embedding[1] = 0.2;
        $embedding[2] = 0.3;

        $entity->embedding = $embedding;
        $table->saveOrFail($entity);

        $fetched = $table->get($entity->id);

        $this->assertEqualsWithDelta($embedding, $fetched->embedding, 0.0001);
    }
}
