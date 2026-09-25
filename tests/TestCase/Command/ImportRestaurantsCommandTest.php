<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\ImportRestaurantsCommand;
use App\Vector\DescriptionBuilder;
use App\Vector\EmbeddingGenerator;
use App\Vector\OverpassClient;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class ImportRestaurantsCommandTest extends TestCase
{
    protected array $fixtures = ['app.Restaurants'];

    private function makeCommand(array $overpassResult): ImportRestaurantsCommand
    {
        $overpass = $this->createStub(OverpassClient::class);
        $overpass->method('fetchRestaurants')->willReturn($overpassResult);

        return (new ImportRestaurantsCommand())
            ->setOverpassClient($overpass)
            ->setDescriptionBuilder(new DescriptionBuilder())
            ->setEmbeddingGenerator(new EmbeddingGenerator());
    }

    private function makeIo(): ConsoleIo
    {
        return new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput());
    }

    public function testExecuteImportsNewRestaurant(): void
    {
        $command = $this->makeCommand([
            ['osm_id' => 999, 'tags' => ['name' => 'Test Place', 'cuisine' => 'italian']],
        ]);

        $args = new Arguments([], ['city' => 'brooklyn'], []);
        $result = $command->execute($args, $this->makeIo());

        $this->assertSame(ImportRestaurantsCommand::CODE_SUCCESS, $result);

        $table = TableRegistry::getTableLocator()->get('Restaurants');
        $saved = $table->find()->where(['osm_id' => 999])->firstOrFail();
        $this->assertSame('Test Place', $saved->name);
        $this->assertStringContainsString('Italian restaurant', $saved->description);
        $this->assertNotNull($saved->embedding);
    }

    public function testExecuteIsIdempotentForSameOsmId(): void
    {
        $command = $this->makeCommand([
            ['osm_id' => 999, 'tags' => ['name' => 'Test Place', 'cuisine' => 'italian']],
        ]);

        $args = new Arguments([], ['city' => 'brooklyn'], []);
        $command->execute($args, $this->makeIo());
        $command->execute($args, $this->makeIo());

        $table = TableRegistry::getTableLocator()->get('Restaurants');
        $count = $table->find()->where(['osm_id' => 999])->count();
        $this->assertSame(1, $count);
    }

    public function testExecuteReturnsErrorForUnknownCity(): void
    {
        $command = $this->makeCommand([]);

        $args = new Arguments([], ['city' => 'atlantis'], []);
        $result = $command->execute($args, $this->makeIo());

        $this->assertSame(ImportRestaurantsCommand::CODE_ERROR, $result);
    }
}
