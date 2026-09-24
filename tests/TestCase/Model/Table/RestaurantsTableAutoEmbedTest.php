<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class RestaurantsTableAutoEmbedTest extends TestCase
{
    protected array $fixtures = ['app.Restaurants'];

    public function testNewRestaurantGetsAnEmbeddingWithoutOneBeingSetExplicitly(): void
    {
        $table = TableRegistry::getTableLocator()->get('Restaurants');

        $entity = $table->newEntity([
            'osm_id' => 7001,
            'name' => 'Manually Added Diner',
            'description' => 'Cozy diner serving pancakes.',
        ]);
        $table->saveOrFail($entity);

        $this->assertNotNull($entity->embedding);
        $this->assertCount(128, $entity->embedding);
    }

    public function testEditingDescriptionRecomputesTheEmbedding(): void
    {
        $table = TableRegistry::getTableLocator()->get('Restaurants');

        $entity = $table->newEntity([
            'osm_id' => 7002,
            'name' => 'Edited Diner',
            'description' => 'Quiet cafe serving coffee.',
        ]);
        $table->saveOrFail($entity);
        $originalEmbedding = $entity->embedding;

        $entity = $table->patchEntity($entity, ['description' => 'Loud sushi bar serving sake.']);
        $table->saveOrFail($entity);

        $this->assertNotEquals($originalEmbedding, $entity->embedding);
    }
}
