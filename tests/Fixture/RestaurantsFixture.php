<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RestaurantsFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            $this->record(1001, 'Trattoria Roma', 'italian', 0),
            $this->record(1002, 'Sushi Sato', 'japanese', 1),
            $this->record(1003, 'No Embedding Cafe', 'cafe', null),
        ];

        parent::init();
    }

    private function record(int $osmId, string $name, string $cuisine, ?int $hotIndex): array
    {
        return [
            'osm_id' => $osmId,
            'name' => $name,
            'cuisine' => $cuisine,
            'city' => 'Brooklyn',
            'address' => '1 Test St',
            'tags' => '{}',
            'description' => ucfirst($cuisine) . ' restaurant in Brooklyn.',
            'embedding' => $hotIndex === null ? null : $this->vectorArray($hotIndex),
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ];
    }

    /**
     * @return array<int, float>
     */
    private function vectorArray(int $hotIndex): array
    {
        $vector = array_fill(0, 128, 0.0);
        $vector[$hotIndex] = 1.0;

        return $vector;
    }
}
