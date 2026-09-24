<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class RestaurantsTableFindSimilarToTest extends TestCase
{
    protected array $fixtures = ['app.Restaurants'];

    public function testFindSimilarToOrdersByClosestVectorFirst(): void
    {
        $table = TableRegistry::getTableLocator()->get('Restaurants');

        $vector = array_fill(0, 128, 0.0);
        $vector[0] = 1.0; // exact match for "Trattoria Roma"

        $results = $table->find('similarTo', vector: $vector)->limit(2)->toArray();

        $this->assertSame('Trattoria Roma', $results[0]->name);
        $this->assertEqualsWithDelta(0.0, $results[0]->distance, 0.0001);
        $this->assertSame('Sushi Sato', $results[1]->name);
    }

    public function testFindSimilarToExcludesRowsWithNullEmbedding(): void
    {
        $table = TableRegistry::getTableLocator()->get('Restaurants');

        $vector = array_fill(0, 128, 0.0);
        $vector[0] = 1.0;

        $results = $table->find('similarTo', vector: $vector)->toArray();
        $names = array_map(static fn ($r) => $r->name, $results);

        $this->assertNotContains('No Embedding Cafe', $names);
    }
}
