<?php
declare(strict_types=1);

namespace App\Test\TestCase\Vector;

use App\Vector\OverpassClient;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OverpassClientTest extends TestCase
{
    public function testFetchRestaurantsParsesNamedElements(): void
    {
        $body = [
            'elements' => [
                ['id' => 111, 'tags' => ['name' => 'Vinegar Hill House', 'cuisine' => 'american']],
                ['id' => 222, 'tags' => ['cuisine' => 'sushi']], // no name — must be skipped
            ],
        ];

        $response = $this->createStub(Response::class);
        $response->method('isOk')->willReturn(true);
        $response->method('getJson')->willReturn($body);

        $client = $this->createStub(Client::class);
        $client->method('get')->willReturn($response);

        $overpass = new OverpassClient($client);
        $result = $overpass->fetchRestaurants('40.7,-74.02,40.72,-73.98');

        $this->assertCount(1, $result);
        $this->assertSame(111, $result[0]['osm_id']);
        $this->assertSame('Vinegar Hill House', $result[0]['tags']['name']);
    }

    public function testFetchRestaurantsThrowsOnFailedRequest(): void
    {
        $response = $this->createStub(Response::class);
        $response->method('isOk')->willReturn(false);
        $response->method('getStatusCode')->willReturn(500);

        $client = $this->createStub(Client::class);
        $client->method('get')->willReturn($response);

        $overpass = new OverpassClient($client);

        $this->expectException(RuntimeException::class);
        $overpass->fetchRestaurants('40.7,-74.02,40.72,-73.98');
    }

    public function testFetchRestaurantsThrowsOnUnexpectedShape(): void
    {
        $response = $this->createStub(Response::class);
        $response->method('isOk')->willReturn(true);
        $response->method('getJson')->willReturn(['unexpected' => true]);

        $client = $this->createStub(Client::class);
        $client->method('get')->willReturn($response);

        $overpass = new OverpassClient($client);

        $this->expectException(RuntimeException::class);
        $overpass->fetchRestaurants('40.7,-74.02,40.72,-73.98');
    }
}
