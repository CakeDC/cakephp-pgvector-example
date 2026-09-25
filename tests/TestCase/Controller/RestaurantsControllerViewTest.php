<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class RestaurantsControllerViewTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Restaurants'];

    public function testViewRendersRestaurantWithEmbedding(): void
    {
        $table = $this->getTableLocator()->get('Restaurants');
        $restaurant = $table->find()->where(['name' => 'Trattoria Roma'])->firstOrFail();

        $this->get('/restaurants/view/' . $restaurant->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Trattoria Roma');
        $this->assertResponseContains('128-dimensional vector');
    }

    public function testViewRendersRestaurantWithNullEmbedding(): void
    {
        $table = $this->getTableLocator()->get('Restaurants');
        $restaurant = $table->find()->where(['name' => 'No Embedding Cafe'])->firstOrFail();

        $this->get('/restaurants/view/' . $restaurant->id);

        $this->assertResponseOk();
        $this->assertResponseContains('not computed yet');
    }
}
