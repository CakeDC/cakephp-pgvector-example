<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Vector\EmbeddingGenerator;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class RestaurantsControllerSearchTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Restaurants'];

    public function testEmptyQueryShowsFormOnly(): void
    {
        $this->get('/restaurants/search');

        $this->assertResponseOk();
        $this->assertResponseContains('<form');
        $this->assertResponseNotContains('Trattoria Roma');
    }

    public function testQueryReturnsClosestMatchFirst(): void
    {
        // The fixture's embeddings are synthetic one-hot vectors (for the finder's
        // own ordering test) rather than embeddings of the real descriptions, so
        // re-embed both rows from their actual text here to make this a real
        // "closest match ranks first" assertion rather than a tie every query wins.
        $table = $this->getTableLocator()->get('Restaurants');
        $generator = new EmbeddingGenerator();

        $trattoria = $table->find()->where(['name' => 'Trattoria Roma'])->firstOrFail();
        $trattoria->embedding = $generator->embed($trattoria->description);
        $table->saveOrFail($trattoria);

        $sushi = $table->find()->where(['name' => 'Sushi Sato'])->firstOrFail();
        $sushi->embedding = $generator->embed($sushi->description);
        $table->saveOrFail($sushi);

        $this->get('/restaurants/search?q=' . urlencode($trattoria->description));

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $trattoriaPosition = strpos($body, 'Trattoria Roma');
        $sushiPosition = strpos($body, 'Sushi Sato');

        $this->assertNotFalse($trattoriaPosition);
        $this->assertNotFalse($sushiPosition);
        $this->assertLessThan(
            $sushiPosition,
            $trattoriaPosition,
            'Restaurant matching the query text should rank above an unrelated one.'
        );
    }

    public function testQueryWithNoMatchesShowsNoResultsMessage(): void
    {
        // No rows have a null embedding excluded, but an empty table would also
        // need this path — simulate "no textual match feel" by asserting the
        // no-results branch renders when results are empty via a query that
        // still returns ranked rows (fixture always has >=1 embedded row);
        // this test instead confirms the message renders on a fresh, empty table.
        $this->getTableLocator()->get('Restaurants')->deleteAll(['1 =' => 1]);

        $this->get('/restaurants/search?q=anything');

        $this->assertResponseOk();
        $this->assertResponseContains('No restaurants found');
    }

    public function testQueryWithSpecialCharactersDoesNotError(): void
    {
        $this->get('/restaurants/search?q=' . urlencode("O'Brien's; DROP TABLE restaurants;--"));

        $this->assertResponseOk();
    }

    public function testQueryWithNoHashableTokensShowsNoResultsMessage(): void
    {
        $this->get('/restaurants/search?q=' . urlencode('!!!'));

        $this->assertResponseOk();
        $this->assertResponseContains('No restaurants found');
        $this->assertResponseNotContains('NAN');
        $this->assertResponseNotContains('distance:');
    }
}
