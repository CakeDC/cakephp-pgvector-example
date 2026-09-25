<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Exercises `bin/cake import_restaurants` through CakePHP's real console
 * instantiation path (Cake\Console\CommandFactory), unlike
 * ImportRestaurantsCommandTest, which constructs the command directly and
 * so would not have caught the command's original constructor-injection
 * incompatibility with CommandFactory::create() (see the plan's ledger,
 * Task 10).
 */
class ImportRestaurantsCommandConsoleTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function testUnknownCityErrorsCleanlyThroughTheRealConsoleRunner(): void
    {
        $this->exec('import_restaurants --city=atlantis');

        $this->assertExitError();
        $this->assertErrorContains('Unknown city');
    }
}
