<?php
declare(strict_types=1);

namespace App\Vector;

use Cake\Http\Client;
use RuntimeException;

class OverpassClient
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    /**
     * @return array<int, array{osm_id:int,tags:array<string,string>}>
     */
    public function fetchRestaurants(string $bbox): array
    {
        $query = sprintf(
            '[out:json][timeout:25];node["amenity"="restaurant"](%s);out body;',
            $bbox
        );

        $response = $this->client->get('https://overpass-api.de/api/interpreter', ['data' => $query]);

        if (!$response->isOk()) {
            throw new RuntimeException('Overpass API request failed with status ' . $response->getStatusCode());
        }

        $body = $response->getJson();
        if (!is_array($body) || !isset($body['elements']) || !is_array($body['elements'])) {
            throw new RuntimeException('Overpass API returned an unexpected response shape.');
        }

        $restaurants = [];
        foreach ($body['elements'] as $element) {
            if (!isset($element['id'], $element['tags']['name'])) {
                continue;
            }
            $restaurants[] = [
                'osm_id' => (int)$element['id'],
                'tags' => $element['tags'],
            ];
        }

        return $restaurants;
    }
}
