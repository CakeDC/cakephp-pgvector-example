<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class RestaurantsControllerIndexTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Restaurants'];

    public function testIndexListsRestaurants(): void
    {
        $this->get('/restaurants');

        $this->assertResponseOk();
        $this->assertResponseContains('Trattoria Roma');
    }
}
