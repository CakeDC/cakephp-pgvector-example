<?php
declare(strict_types=1);

namespace App\Command;

use App\Vector\DescriptionBuilder;
use App\Vector\EmbeddingGenerator;
use App\Vector\OverpassClient;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;

class ImportRestaurantsCommand extends Command
{
    use LocatorAwareTrait;

    private const CITY_BBOXES = [
        'brooklyn' => '40.65,-74.02,40.70,-73.95',
        'madrid' => '40.40,-3.72,40.43,-3.68',
    ];

    private ?OverpassClient $overpass = null;
    private ?DescriptionBuilder $descriptionBuilder = null;
    private ?EmbeddingGenerator $embeddingGenerator = null;

    public function setOverpassClient(OverpassClient $overpass): static
    {
        $this->overpass = $overpass;

        return $this;
    }

    public function setDescriptionBuilder(DescriptionBuilder $descriptionBuilder): static
    {
        $this->descriptionBuilder = $descriptionBuilder;

        return $this;
    }

    public function setEmbeddingGenerator(EmbeddingGenerator $embeddingGenerator): static
    {
        $this->embeddingGenerator = $embeddingGenerator;

        return $this;
    }

    public static function defaultName(): string
    {
        return 'import_restaurants';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('city', [
                'help' => 'Demo city to import: ' . implode(', ', array_keys(self::CITY_BBOXES)),
                'default' => 'brooklyn',
            ])
            ->addOption('bbox', [
                'help' => 'Explicit bounding box "lat1,lon1,lat2,lon2", overrides --city.',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $overpass = $this->overpass ?? new OverpassClient();
        $descriptionBuilder = $this->descriptionBuilder ?? new DescriptionBuilder();
        $embeddingGenerator = $this->embeddingGenerator ?? new EmbeddingGenerator();

        $bbox = $args->getOption('bbox');
        if (!$bbox) {
            $city = strtolower((string)$args->getOption('city'));
            if (!isset(self::CITY_BBOXES[$city])) {
                $io->error(sprintf(
                    'Unknown city "%s". Known cities: %s',
                    $city,
                    implode(', ', array_keys(self::CITY_BBOXES))
                ));

                return static::CODE_ERROR;
            }
            $bbox = self::CITY_BBOXES[$city];
        }

        try {
            $restaurants = $overpass->fetchRestaurants($bbox);
        } catch (RuntimeException $e) {
            $io->error('Overpass import failed: ' . $e->getMessage());

            return static::CODE_ERROR;
        }

        if ($restaurants === []) {
            $io->error('Overpass returned no restaurants for this bounding box.');

            return static::CODE_ERROR;
        }

        $table = $this->getTableLocator()->get('Restaurants');

        foreach ($restaurants as $restaurant) {
            $tags = $restaurant['tags'];
            $description = $descriptionBuilder->build($tags);
            $embedding = $embeddingGenerator->embed($description);

            $entity = $table->find()->where(['osm_id' => $restaurant['osm_id']])->first();
            $entity ??= $table->newEmptyEntity();

            $entity = $table->patchEntity($entity, [
                'osm_id' => $restaurant['osm_id'],
                'name' => $tags['name'],
                'cuisine' => $tags['cuisine'] ?? null,
                'city' => $tags['addr:city'] ?? null,
                'address' => $this->formatAddress($tags),
                'tags' => $tags,
                'description' => $description,
                'embedding' => $embedding,
            ]);

            $table->saveOrFail($entity);
        }

        $io->success(sprintf('Imported %d restaurants.', count($restaurants)));

        return static::CODE_SUCCESS;
    }

    /**
     * @param array<string, string> $tags
     */
    private function formatAddress(array $tags): ?string
    {
        $parts = array_filter([$tags['addr:housenumber'] ?? null, $tags['addr:street'] ?? null]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
