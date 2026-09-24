# Restaurant Vector Search Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a CakePHP 5 restaurant catalog, seeded from real OpenStreetMap data, with pgvector-powered natural-language search — the companion codebase for a pgvector + CakePHP article.

**Architecture:** A `restaurants` Postgres table with a `pgvector` column, accessed through a custom CakePHP `Type` that marshals PHP float arrays to/from Postgres vector literals. A deterministic local "feature hashing" function stands in for a real embedding API (zero dependencies, zero API keys), used both when importing restaurants from OpenStreetMap and when embedding a user's search query. A console command imports data; a controller action serves search.

**Tech Stack:** CakePHP 5.4, `cakephp/migrations` (Phinx), PostgreSQL 18 with the `vector` extension, PHPUnit, DDEV.

**Spec:** `docs/superpowers/specs/2026-09-24-restaurant-vector-search-design.md`

## Global Constraints

- PHP >= 8.2 (composer.json floor); DDEV runs PHP 8.5 — keep code 8.2-compatible.
- CakePHP `5.4.*`; `cakephp/migrations` for all schema changes (no hand-run SQL outside migrations, except the vector column itself, which Phinx has no native type for).
- PostgreSQL 18 via DDEV; the `vector` extension must exist in **both** the `default` and `test` datasources.
- No external embedding API and no API keys — embeddings are computed locally via deterministic feature hashing at a fixed dimension of 128.
- No auth, no booking/reservation flow, no production-deployment concerns (explicit spec non-goals).
- The Overpass API (`overpass-api.de`) is the only external network dependency, used only by the import command — it must never be called from the test suite (mock it).
- `ARTICLE.md` (final task) must only describe code that actually exists in the repo at that point.

## Review Focus

- Search runs before any data has been imported (empty `restaurants` table) — must render "no results," not an error. Covered in Task 9.
- Re-running `import_restaurants` for a city already imported must not create duplicate rows (upsert by `osm_id`). Covered in Task 7.
- A search query containing quotes/semicolons (e.g. `O'Brien's; DROP TABLE`) must not break the raw SQL vector-distance query. Covered in Task 9.
- OpenStreetMap nodes missing a `name` tag must be skipped by the importer, not saved with a null name. Covered in Task 6.
- Saving or fetching a `Restaurant` entity whose `embedding` is `null` (not yet computed) must round-trip safely through `VectorType`, not error. Covered in Task 3.

---

## File Structure

- `config/Migrations/20260924120000_CreateRestaurants.php` — creates the `vector` extension and `restaurants` table.
- `config/bootstrap.php` — registers the `vector` CakePHP database type (modify existing file).
- `src/Database/Type/VectorType.php` — marshals PHP `float[]` ⇄ Postgres `vector` literal.
- `src/Model/Table/RestaurantsTable.php`, `src/Model/Entity/Restaurant.php` — baked, then hand-extended with the `vector` schema override and `findSimilarTo` finder.
- `src/Controller/RestaurantsController.php`, `templates/Restaurants/*.php` — baked, then hand-extended with `search()`.
- `src/Vector/EmbeddingGenerator.php` — deterministic feature-hashing embedding function.
- `src/Vector/DescriptionBuilder.php` — turns raw OSM tags into a natural-language sentence.
- `src/Vector/OverpassClient.php` — fetches and parses restaurant nodes from the Overpass API.
- `src/Command/ImportRestaurantsCommand.php` — ties the above three together into `bin/cake import_restaurants`.
- `tests/Fixture/RestaurantsFixture.php` and one test file per class above.
- `ARTICLE.md` — the article deliverable (Task 11).

---

### Task 1: DDEV environment, datasources, and the `restaurants` table

**Files:**
- Modify: `config/app_local.php`
- Create: `config/Migrations/20260924120000_CreateRestaurants.php`

**Interfaces:**
- Produces: a `restaurants` table (columns: `id`, `osm_id` bigint unique, `name`, `cuisine`, `city`, `address`, `tags` json, `description` text, `embedding` vector(128), `created`, `modified`) in both the `default` and `test` Postgres databases, with the `vector` extension enabled in each.

- [ ] **Step 1: Start DDEV and install dependencies**

Run:
```bash
ddev start
ddev composer install
```
Expected: both commands exit 0; `config/app_local.php` exists (composer's post-install copies it from `config/app_local.example.php`).

- [ ] **Step 2: Point the `default` and `test` datasources at DDEV's Postgres service**

Edit `config/app_local.php`, replacing the `'Datasources'` array with:

```php
'Datasources' => [
    'default' => [
        'className' => Cake\Database\Connection::class,
        'driver' => Cake\Database\Driver\Postgres::class,
        'persistent' => false,
        'host' => 'db',
        'port' => 5432,
        'username' => 'db',
        'password' => 'db',
        'database' => 'db',
        'encoding' => 'utf8',
        'timezone' => 'UTC',
        'flags' => [],
        'cacheMetadata' => true,
        'log' => false,
        'quoteIdentifiers' => false,
        'url' => env('DATABASE_URL', null),
    ],
    'test' => [
        'className' => Cake\Database\Connection::class,
        'driver' => Cake\Database\Driver\Postgres::class,
        'persistent' => false,
        'host' => 'db',
        'port' => 5432,
        'username' => 'db',
        'password' => 'db',
        'database' => 'test',
        'encoding' => 'utf8',
        'timezone' => 'UTC',
        'flags' => [],
        'cacheMetadata' => true,
        'quoteIdentifiers' => false,
        'url' => env('DATABASE_TEST_URL', null),
    ],
],
```

- [ ] **Step 3: Create the `test` database**

Run:
```bash
ddev postgres -e "CREATE DATABASE test;"
```
Expected: `CREATE DATABASE` printed, exit 0. If DDEV rejects the `-e` flag on your DDEV version, use `ddev exec -- psql -U db -d db -c "CREATE DATABASE test;"` instead.

- [ ] **Step 4: Verify both connections**

Run:
```bash
ddev cake schema_cache clear
ddev exec -- php -r "require 'config/bootstrap.php'; var_dump(\Cake\Datasource\ConnectionManager::get('default')->getDriver()->connect()); var_dump(\Cake\Datasource\ConnectionManager::get('test')->getDriver()->connect());"
```
Expected: both `var_dump` calls print `bool(true)`.

- [ ] **Step 5: Write the migration**

Create `config/Migrations/20260924120000_CreateRestaurants.php`:

```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateRestaurants extends AbstractMigration
{
    public function change(): void
    {
        $this->execute('CREATE EXTENSION IF NOT EXISTS vector');

        $table = $this->table('restaurants');
        $table
            ->addColumn('osm_id', 'biginteger', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('cuisine', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('address', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('tags', 'json', ['null' => true])
            ->addColumn('description', 'text', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['osm_id'], ['unique' => true])
            ->create();

        // Phinx has no native pgvector column type, so add it with raw SQL.
        $this->execute('ALTER TABLE restaurants ADD COLUMN embedding vector(128)');
    }
}
```

- [ ] **Step 6: Run the migration against both connections**

Run:
```bash
ddev cake migrations migrate
ddev cake migrations migrate --connection test
```
Expected: both commands report the `CreateRestaurants` migration as migrated, exit 0.

- [ ] **Step 7: Verify the schema**

Run:
```bash
ddev postgres -e "\d restaurants"
```
Expected: output lists all columns from Step 5, including `embedding | vector(128)`.

- [ ] **Step 8: Commit**

```bash
git add config/app_local.php config/Migrations/20260924120000_CreateRestaurants.php
git commit -m "Add restaurants table with pgvector column"
```
(Note: `config/app_local.php` is gitignored by default — if `git add` reports it as ignored, that's correct; skip it and only commit the migration. Confirm with `git check-ignore config/app_local.php` before committing.)

---

### Task 2: Bake CRUD scaffolding for Restaurants

**Files:**
- Create: `src/Model/Table/RestaurantsTable.php`, `src/Model/Entity/Restaurant.php`, `src/Controller/RestaurantsController.php`
- Create: `templates/Restaurants/index.php`, `templates/Restaurants/view.php`, `templates/Restaurants/add.php`, `templates/Restaurants/edit.php`
- Test: `tests/TestCase/Controller/RestaurantsControllerIndexTest.php`
- Test: `tests/Fixture/RestaurantsFixture.php`

**Interfaces:**
- Consumes: the `restaurants` table from Task 1.
- Produces: `RestaurantsTable` (registry alias `Restaurants`), `Restaurant` entity, `RestaurantsController` with baked `index`/`view`/`add`/`edit`/`delete` actions.

- [ ] **Step 1: Bake the scaffolding**

Run:
```bash
ddev cake bake all Restaurants
```
Expected: creates the Table, Entity, Controller, and template files listed above, exit 0.

- [ ] **Step 2: Trim system-managed fields out of the add/edit forms**

Edit `templates/Restaurants/add.php` and `templates/Restaurants/edit.php` so the form only exposes fields a human should hand-edit. Replace the generated `<fieldset>` contents with:

```php
<fieldset>
    <legend><?= __('Add Restaurant') ?></legend>
    <?php
        echo $this->Form->control('osm_id');
        echo $this->Form->control('name');
        echo $this->Form->control('cuisine');
        echo $this->Form->control('city');
        echo $this->Form->control('address');
        echo $this->Form->control('description');
    ?>
</fieldset>
```

(`tags` and `embedding` are written only by the import command in Task 7 — they stay out of the manual forms entirely.)

- [ ] **Step 3: Create the fixture**

Create `tests/Fixture/RestaurantsFixture.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RestaurantsFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'osm_id' => 1001,
                'name' => 'Trattoria Roma',
                'cuisine' => 'italian',
                'city' => 'Brooklyn',
                'address' => '1 Pizza St',
                'tags' => '{}',
                'description' => 'Italian restaurant in Brooklyn serving wine, with outdoor seating.',
                'embedding' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
```

- [ ] **Step 4: Write the smoke test**

Create `tests/TestCase/Controller/RestaurantsControllerIndexTest.php`:

```php
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
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `ddev cake test --filter RestaurantsControllerIndexTest`
Expected: FAIL or ERROR (route/action not wired to the fixture's data yet, or fixture load error) — confirm the failure is meaningful, not a typo.

- [ ] **Step 6: Fix until it passes**

Baked `index.php` already renders the `name` column by default — if the test fails for another reason (e.g. datasource/fixture wiring), fix that specific cause; the baked code should not need behavioral changes.

- [ ] **Step 7: Run the test to verify it passes**

Run: `ddev cake test --filter RestaurantsControllerIndexTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/Model src/Controller/RestaurantsController.php templates/Restaurants tests/Fixture/RestaurantsFixture.php tests/TestCase/Controller/RestaurantsControllerIndexTest.php
git commit -m "Bake Restaurants CRUD scaffolding"
```

---

### Task 3: `VectorType` — marshal pgvector columns to/from PHP arrays

**Files:**
- Create: `src/Database/Type/VectorType.php`
- Modify: `config/bootstrap.php`
- Modify: `src/Model/Table/RestaurantsTable.php`
- Test: `tests/TestCase/Database/Type/VectorTypeTest.php`
- Test: `tests/TestCase/Model/Table/RestaurantsTableEmbeddingRoundTripTest.php`

**Interfaces:**
- Produces: `App\Database\Type\VectorType` with `toDatabase(?array $value, Driver $driver): ?string` and `toPHP(?string $value, Driver $driver): ?array`; registered under the type name `'vector'`.
- Produces: `RestaurantsTable::_initializeSchema()` marking the `embedding` column as type `'vector'`.

- [ ] **Step 1: Write the failing unit test**

Create `tests/TestCase/Database/Type/VectorTypeTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Database\Type;

use App\Database\Type\VectorType;
use Cake\Database\Driver\Postgres;
use PHPUnit\Framework\TestCase;

class VectorTypeTest extends TestCase
{
    public function testToDatabaseConvertsArrayToVectorLiteral(): void
    {
        $type = new VectorType('vector');
        $this->assertSame('[1,0.5,0]', $type->toDatabase([1.0, 0.5, 0.0], new Postgres()));
    }

    public function testToDatabaseConvertsNullToNull(): void
    {
        $type = new VectorType('vector');
        $this->assertNull($type->toDatabase(null, new Postgres()));
    }

    public function testToPhpConvertsVectorLiteralToArray(): void
    {
        $type = new VectorType('vector');
        $this->assertSame([1.0, 0.5, 0.0], $type->toPHP('[1,0.5,0]', new Postgres()));
    }

    public function testToPhpConvertsNullToNull(): void
    {
        $type = new VectorType('vector');
        $this->assertNull($type->toPHP(null, new Postgres()));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev cake test --filter VectorTypeTest`
Expected: FAIL with "Class App\Database\Type\VectorType not found".

- [ ] **Step 3: Implement `VectorType`**

Create `src/Database/Type/VectorType.php`:

```php
<?php
declare(strict_types=1);

namespace App\Database\Type;

use Cake\Database\Driver;
use Cake\Database\Type\BaseType;
use InvalidArgumentException;
use PDO;

class VectorType extends BaseType
{
    public function toDatabase(mixed $value, Driver $driver): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('VectorType expects an array of floats or null.');
        }

        return '[' . implode(',', array_map(
            static fn (float $v): string => rtrim(rtrim(sprintf('%.10f', $v), '0'), '.') ?: '0',
            array_map('floatval', $value)
        )) . ']';
    }

    public function toPHP(mixed $value, Driver $driver): ?array
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string)$value, '[]');
        if ($trimmed === '') {
            return [];
        }

        return array_map('floatval', explode(',', $trimmed));
    }

    public function toStatement(mixed $value, Driver $driver): int
    {
        return PDO::PARAM_STR;
    }

    public function marshal(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map('floatval', $value);
        }

        return $value;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev cake test --filter VectorTypeTest`
Expected: PASS.

- [ ] **Step 5: Register the type and wire it into the table's schema**

In `config/bootstrap.php`, add near the other bootstrap configuration (after the `require CONFIG . 'app.php'` block):

```php
\Cake\Database\TypeFactory::map('vector', \App\Database\Type\VectorType::class);
```

In `src/Model/Table/RestaurantsTable.php`, add (imports plus method, inside the class):

```php
use Cake\Database\Schema\TableSchemaInterface;

// ...

protected function _initializeSchema(TableSchemaInterface $schema): TableSchemaInterface
{
    $schema->setColumnType('embedding', 'vector');

    return $schema;
}
```

- [ ] **Step 6: Write the round-trip integration test**

Create `tests/TestCase/Model/Table/RestaurantsTableEmbeddingRoundTripTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class RestaurantsTableEmbeddingRoundTripTest extends TestCase
{
    protected array $fixtures = ['app.Restaurants'];

    public function testEmbeddingArrayRoundTripsThroughSave(): void
    {
        $table = TableRegistry::getTableLocator()->get('Restaurants');

        $entity = $table->newEntity([
            'osm_id' => 5005,
            'name' => 'Roundtrip Diner',
            'description' => 'Test description.',
            'embedding' => [0.1, 0.2, 0.3],
        ]);
        $table->saveOrFail($entity);

        $fetched = $table->get($entity->id);

        $this->assertEqualsWithDelta([0.1, 0.2, 0.3], $fetched->embedding, 0.0001);
    }

    public function testNullEmbeddingRoundTripsAsNull(): void
    {
        $table = TableRegistry::getTableLocator()->get('Restaurants');

        $entity = $table->newEntity([
            'osm_id' => 5006,
            'name' => 'No Embedding Yet',
            'description' => 'Test description.',
            'embedding' => null,
        ]);
        $table->saveOrFail($entity);

        $fetched = $table->get($entity->id);

        $this->assertNull($fetched->embedding);
    }
}
```

- [ ] **Step 7: Run test to verify it fails, then passes**

Run: `ddev cake test --filter RestaurantsTableEmbeddingRoundTripTest`
Expected: first run may FAIL if the schema override isn't picked up (clear schema cache with `ddev cake schema_cache clear` and re-run); after that, PASS.

- [ ] **Step 8: Commit**

```bash
git add src/Database/Type/VectorType.php config/bootstrap.php src/Model/Table/RestaurantsTable.php tests/TestCase/Database/Type/VectorTypeTest.php tests/TestCase/Model/Table/RestaurantsTableEmbeddingRoundTripTest.php
git commit -m "Add VectorType to marshal pgvector columns"
```

---

### Task 4: `EmbeddingGenerator` — deterministic feature-hashing embeddings

**Files:**
- Create: `src/Vector/EmbeddingGenerator.php`
- Test: `tests/TestCase/Vector/EmbeddingGeneratorTest.php`

**Interfaces:**
- Produces: `App\Vector\EmbeddingGenerator::embed(string $text): array` — always returns a 128-element `float[]`, L2-normalized (or all zeros if the input has no tokens).

- [ ] **Step 1: Write the failing tests**

Create `tests/TestCase/Vector/EmbeddingGeneratorTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Vector;

use App\Vector\EmbeddingGenerator;
use PHPUnit\Framework\TestCase;

class EmbeddingGeneratorTest extends TestCase
{
    public function testSameInputProducesSameVector(): void
    {
        $generator = new EmbeddingGenerator();
        $this->assertSame($generator->embed('Italian restaurant'), $generator->embed('Italian restaurant'));
    }

    public function testVectorHas128Dimensions(): void
    {
        $generator = new EmbeddingGenerator();
        $this->assertCount(128, $generator->embed('any text at all'));
    }

    public function testVectorIsL2Normalized(): void
    {
        $generator = new EmbeddingGenerator();
        $vector = $generator->embed('Italian restaurant with outdoor seating');
        $magnitude = sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $vector)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    public function testDifferentInputsProduceDifferentVectors(): void
    {
        $generator = new EmbeddingGenerator();
        $this->assertNotSame($generator->embed('Italian restaurant'), $generator->embed('Sushi bar'));
    }

    public function testEmptyStringProducesAllZeroVector(): void
    {
        $generator = new EmbeddingGenerator();
        $this->assertSame(array_fill(0, 128, 0.0), $generator->embed(''));
    }

    public function testLongQueryDoesNotError(): void
    {
        $generator = new EmbeddingGenerator();
        $longText = str_repeat('quiet outdoor seating vegan wine ', 500);
        $vector = $generator->embed($longText);
        $this->assertCount(128, $vector);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev cake test --filter EmbeddingGeneratorTest`
Expected: FAIL with "Class App\Vector\EmbeddingGenerator not found".

- [ ] **Step 3: Implement `EmbeddingGenerator`**

Create `src/Vector/EmbeddingGenerator.php`:

```php
<?php
declare(strict_types=1);

namespace App\Vector;

class EmbeddingGenerator
{
    private const DIMENSIONS = 128;

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);

        $normalized = strtolower($text);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';
        $tokens = array_filter(explode(' ', $normalized), static fn (string $t): bool => $t !== '');

        foreach ($tokens as $token) {
            $bucket = crc32($token) % self::DIMENSIONS;
            $vector[$bucket] += 1.0;
        }

        $magnitude = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));
        if ($magnitude > 0.0) {
            $vector = array_map(static fn (float $v): float => $v / $magnitude, $vector);
        }

        return $vector;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev cake test --filter EmbeddingGeneratorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Vector/EmbeddingGenerator.php tests/TestCase/Vector/EmbeddingGeneratorTest.php
git commit -m "Add deterministic feature-hashing EmbeddingGenerator"
```

---

### Task 5: `DescriptionBuilder` — synthesize a sentence from OSM tags

**Files:**
- Create: `src/Vector/DescriptionBuilder.php`
- Test: `tests/TestCase/Vector/DescriptionBuilderTest.php`

**Interfaces:**
- Produces: `App\Vector\DescriptionBuilder::build(array $tags): string`.

- [ ] **Step 1: Write the failing tests**

Create `tests/TestCase/Vector/DescriptionBuilderTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Vector;

use App\Vector\DescriptionBuilder;
use PHPUnit\Framework\TestCase;

class DescriptionBuilderTest extends TestCase
{
    public function testFullTagSetProducesFullSentence(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build([
            'name' => 'Vinegar Hill House',
            'cuisine' => 'american',
            'addr:city' => 'Brooklyn',
            'cocktails' => 'yes',
            'drink:wine' => 'yes',
            'outdoor_seating' => 'yes',
            'opening_hours' => 'Mo-Th 17:30-21:30; Fr 17:30-22:00',
        ]);

        $this->assertStringContainsString('American restaurant in Brooklyn', $description);
        $this->assertStringContainsString('cocktails and wine', $description);
        $this->assertStringContainsString('outdoor seating', $description);
        $this->assertStringContainsString('open late', $description);
    }

    public function testMissingTagsAreOmittedNotPlaceholders(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build(['name' => 'Mystery Place']);

        $this->assertSame('Restaurant.', $description);
    }

    public function testVeganPreferredOverVegetarianWhenBothPresent(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build([
            'diet:vegan' => 'yes',
            'diet:vegetarian' => 'yes',
        ]);

        $this->assertStringContainsString('vegan options', $description);
        $this->assertStringNotContainsString('vegetarian options', $description);
    }

    public function testEarlyClosingTimeIsNotOpenLate(): void
    {
        $builder = new DescriptionBuilder();
        $description = $builder->build(['opening_hours' => 'Mo-Su 08:00-18:00']);

        $this->assertStringNotContainsString('open late', $description);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev cake test --filter DescriptionBuilderTest`
Expected: FAIL with "Class App\Vector\DescriptionBuilder not found".

- [ ] **Step 3: Implement `DescriptionBuilder`**

Create `src/Vector/DescriptionBuilder.php`:

```php
<?php
declare(strict_types=1);

namespace App\Vector;

class DescriptionBuilder
{
    /**
     * @param array<string, string> $tags
     */
    public function build(array $tags): string
    {
        $parts = [];

        $cuisine = $tags['cuisine'] ?? null;
        $city = $tags['addr:city'] ?? null;

        $subject = $cuisine ? (ucfirst($cuisine) . ' restaurant') : 'Restaurant';
        if ($city) {
            $subject .= ' in ' . $city;
        }
        $parts[] = $subject;

        $drinks = [];
        if (($tags['cocktails'] ?? null) === 'yes') {
            $drinks[] = 'cocktails';
        }
        foreach (['wine', 'beer', 'coffee'] as $drink) {
            if (($tags['drink:' . $drink] ?? null) === 'yes') {
                $drinks[] = $drink;
            }
        }
        if ($drinks !== []) {
            $parts[] = 'serving ' . implode(' and ', $drinks);
        }

        $amenities = [];
        if (($tags['outdoor_seating'] ?? null) === 'yes') {
            $amenities[] = 'outdoor seating';
        }
        if (($tags['diet:vegan'] ?? null) === 'yes') {
            $amenities[] = 'vegan options';
        } elseif (($tags['diet:vegetarian'] ?? null) === 'yes') {
            $amenities[] = 'vegetarian options';
        }
        if ($amenities !== []) {
            $parts[] = 'with ' . implode(', ', $amenities);
        }

        if ($this->isOpenLate($tags['opening_hours'] ?? null)) {
            $parts[] = 'open late';
        }

        return implode(', ', $parts) . '.';
    }

    private function isOpenLate(?string $openingHours): bool
    {
        if ($openingHours === null) {
            return false;
        }
        if (preg_match('/(\d{2}):\d{2}(?!.*\d{2}:\d{2})/', $openingHours, $matches) !== 1) {
            return false;
        }

        return (int)$matches[1] >= 22;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev cake test --filter DescriptionBuilderTest`
Expected: PASS. If `testFullTagSetProducesFullSentence` fails specifically on the "open late" assertion, check the regex against the exact `opening_hours` string in the test — adjust the regex to capture the last `HH:MM` occurrence in the string.

- [ ] **Step 5: Commit**

```bash
git add src/Vector/DescriptionBuilder.php tests/TestCase/Vector/DescriptionBuilderTest.php
git commit -m "Add DescriptionBuilder to synthesize sentences from OSM tags"
```

---

### Task 6: `OverpassClient` — fetch and parse restaurant nodes

**Files:**
- Create: `src/Vector/OverpassClient.php`
- Test: `tests/TestCase/Vector/OverpassClientTest.php`

**Interfaces:**
- Produces: `App\Vector\OverpassClient::__construct(?\Cake\Http\Client $client = null)` and `fetchRestaurants(string $bbox): array` returning a list of `['osm_id' => int, 'tags' => array<string,string>]`, skipping any node without a `name` tag, throwing `\RuntimeException` on a failed request or unexpected response shape.

- [ ] **Step 1: Write the failing tests**

Create `tests/TestCase/Vector/OverpassClientTest.php`:

```php
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

        $response = $this->createMock(Response::class);
        $response->method('isOk')->willReturn(true);
        $response->method('getJson')->willReturn($body);

        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($response);

        $overpass = new OverpassClient($client);
        $result = $overpass->fetchRestaurants('40.7,-74.02,40.72,-73.98');

        $this->assertCount(1, $result);
        $this->assertSame(111, $result[0]['osm_id']);
        $this->assertSame('Vinegar Hill House', $result[0]['tags']['name']);
    }

    public function testFetchRestaurantsThrowsOnFailedRequest(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('isOk')->willReturn(false);
        $response->method('getStatusCode')->willReturn(500);

        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($response);

        $overpass = new OverpassClient($client);

        $this->expectException(RuntimeException::class);
        $overpass->fetchRestaurants('40.7,-74.02,40.72,-73.98');
    }

    public function testFetchRestaurantsThrowsOnUnexpectedShape(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('isOk')->willReturn(true);
        $response->method('getJson')->willReturn(['unexpected' => true]);

        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn($response);

        $overpass = new OverpassClient($client);

        $this->expectException(RuntimeException::class);
        $overpass->fetchRestaurants('40.7,-74.02,40.72,-73.98');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev cake test --filter OverpassClientTest`
Expected: FAIL with "Class App\Vector\OverpassClient not found".

- [ ] **Step 3: Implement `OverpassClient`**

Create `src/Vector/OverpassClient.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev cake test --filter OverpassClientTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Vector/OverpassClient.php tests/TestCase/Vector/OverpassClientTest.php
git commit -m "Add OverpassClient to fetch restaurant nodes from OpenStreetMap"
```

---

### Task 7: `ImportRestaurantsCommand`

**Files:**
- Create: `src/Command/ImportRestaurantsCommand.php`
- Test: `tests/TestCase/Command/ImportRestaurantsCommandTest.php`

**Interfaces:**
- Consumes: `OverpassClient::fetchRestaurants()` (Task 6), `DescriptionBuilder::build()` (Task 5), `EmbeddingGenerator::embed()` (Task 4), the `Restaurants` table (Task 3).
- Produces: `bin/cake import_restaurants [--city=brooklyn|madrid] [--bbox="lat1,lon1,lat2,lon2"]`, upserting rows by `osm_id`.

- [ ] **Step 1: Write the failing tests**

Create `tests/TestCase/Command/ImportRestaurantsCommandTest.php`:

```php
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
        $overpass = $this->createMock(OverpassClient::class);
        $overpass->method('fetchRestaurants')->willReturn($overpassResult);

        return new ImportRestaurantsCommand($overpass, new DescriptionBuilder(), new EmbeddingGenerator());
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev cake test --filter ImportRestaurantsCommandTest`
Expected: FAIL with "Class App\Command\ImportRestaurantsCommand not found".

- [ ] **Step 3: Implement `ImportRestaurantsCommand`**

Create `src/Command/ImportRestaurantsCommand.php`:

```php
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

    private OverpassClient $overpass;
    private DescriptionBuilder $descriptionBuilder;
    private EmbeddingGenerator $embeddingGenerator;

    public function __construct(
        ?OverpassClient $overpass = null,
        ?DescriptionBuilder $descriptionBuilder = null,
        ?EmbeddingGenerator $embeddingGenerator = null
    ) {
        parent::__construct();
        $this->overpass = $overpass ?? new OverpassClient();
        $this->descriptionBuilder = $descriptionBuilder ?? new DescriptionBuilder();
        $this->embeddingGenerator = $embeddingGenerator ?? new EmbeddingGenerator();
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
            $restaurants = $this->overpass->fetchRestaurants($bbox);
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
            $description = $this->descriptionBuilder->build($tags);
            $embedding = $this->embeddingGenerator->embed($description);

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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev cake test --filter ImportRestaurantsCommandTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Command/ImportRestaurantsCommand.php tests/TestCase/Command/ImportRestaurantsCommandTest.php
git commit -m "Add import_restaurants command"
```

---

### Task 8: `findSimilarTo` custom finder

**Files:**
- Modify: `src/Model/Table/RestaurantsTable.php`
- Modify: `tests/Fixture/RestaurantsFixture.php` (add more records with real embeddings)
- Test: `tests/TestCase/Model/Table/RestaurantsTableFindSimilarToTest.php`

**Interfaces:**
- Produces: `RestaurantsTable::findSimilarTo(SelectQuery $query, array $vector): SelectQuery`, callable as `$table->find('similarTo', vector: $vector)`, adding a `distance` computed column and ordering ascending by it (closest first).

- [ ] **Step 1: Extend the fixture with vectors for ranking**

Modify `tests/Fixture/RestaurantsFixture.php` so `init()` builds three records with distinguishable 128-dim embeddings:

```php
<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RestaurantsFixture extends TestFixture
{
    public function init(): void
    {
        $this->records = [
            $this->record(1, 1001, 'Trattoria Roma', 'italian', 0),
            $this->record(2, 1002, 'Sushi Sato', 'japanese', 1),
            $this->record(3, 1003, 'No Embedding Cafe', 'cafe', null),
        ];

        parent::init();
    }

    private function record(int $id, int $osmId, string $name, string $cuisine, ?int $hotIndex): array
    {
        return [
            'id' => $id,
            'osm_id' => $osmId,
            'name' => $name,
            'cuisine' => $cuisine,
            'city' => 'Brooklyn',
            'address' => '1 Test St',
            'tags' => '{}',
            'description' => ucfirst($cuisine) . ' restaurant in Brooklyn.',
            'embedding' => $hotIndex === null ? null : $this->vectorLiteral($hotIndex),
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ];
    }

    private function vectorLiteral(int $hotIndex): string
    {
        $vector = array_fill(0, 128, 0.0);
        $vector[$hotIndex] = 1.0;

        return '[' . implode(',', $vector) . ']';
    }
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/TestCase/Model/Table/RestaurantsTableFindSimilarToTest.php`:

```php
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
```

- [ ] **Step 3: Run test to verify it fails**

Run: `ddev cake test --filter RestaurantsTableFindSimilarToTest`
Expected: FAIL — either "call to undefined method findSimilarTo" or a DB error, depending on PHPUnit's error surfacing.

- [ ] **Step 4: Implement the finder**

Add to `src/Model/Table/RestaurantsTable.php` (imports plus method, inside the class):

```php
use Cake\ORM\Query\SelectQuery;

// ...

/**
 * @param array<int, float> $vector
 */
public function findSimilarTo(SelectQuery $query, array $vector): SelectQuery
{
    $literal = '[' . implode(',', array_map('floatval', $vector)) . ']';
    $distanceSql = sprintf("embedding <=> '%s'", $literal);

    return $query
        ->where(['embedding IS NOT' => null])
        ->select(['distance' => $query->newExpr($distanceSql)])
        ->orderBy([$query->newExpr($distanceSql) => 'ASC']);
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev cake test --filter RestaurantsTableFindSimilarToTest`
Expected: PASS. If the `orderBy` call signature errors, use `->order([$distanceSql => 'ASC'])` instead — check `vendor/cakephp/cakephp/src/ORM/Query/SelectQuery.php` for the exact method name/signature in the installed version.

- [ ] **Step 6: Commit**

```bash
git add src/Model/Table/RestaurantsTable.php tests/Fixture/RestaurantsFixture.php tests/TestCase/Model/Table/RestaurantsTableFindSimilarToTest.php
git commit -m "Add findSimilarTo pgvector finder"
```

---

### Task 9: Search controller action and template

**Files:**
- Modify: `src/Controller/RestaurantsController.php`
- Create: `templates/Restaurants/search.php`
- Test: `tests/TestCase/Controller/RestaurantsControllerSearchTest.php`

**Interfaces:**
- Consumes: `EmbeddingGenerator::embed()` (Task 4), `RestaurantsTable::find('similarTo', ...)` (Task 8).
- Produces: `GET /restaurants/search?q=...` — empty `q` renders the form only; non-empty `q` renders ranked results or a "no results" message.

- [ ] **Step 1: Write the failing tests**

Create `tests/TestCase/Controller/RestaurantsControllerSearchTest.php`:

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

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
        $this->get('/restaurants/search?q=' . urlencode('italian trattoria'));

        $this->assertResponseOk();
        $this->assertResponseContains('Trattoria Roma');
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
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev cake test --filter RestaurantsControllerSearchTest`
Expected: FAIL — no `search` action exists yet (404 or missing-method error).

- [ ] **Step 3: Implement the controller action**

Add to `src/Controller/RestaurantsController.php` (import plus method, inside the class):

```php
use App\Vector\EmbeddingGenerator;

// ...

public function search(): void
{
    $query = trim((string)$this->request->getQuery('q', ''));
    $results = [];

    if ($query !== '') {
        $embedding = (new EmbeddingGenerator())->embed($query);
        $results = $this->Restaurants->find('similarTo', vector: $embedding)->limit(10)->toArray();
    }

    $this->set(compact('query', 'results'));
}
```

- [ ] **Step 4: Create the template**

Create `templates/Restaurants/search.php`:

```php
<h1><?= __('Search Restaurants') ?></h1>

<?= $this->Form->create(null, ['type' => 'get']) ?>
<?= $this->Form->control('q', ['label' => 'What are you looking for?', 'value' => $query]) ?>
<?= $this->Form->button(__('Search')) ?>
<?= $this->Form->end() ?>

<?php if ($query !== '' && $results === []): ?>
    <p><?= __('No restaurants found for {0}.', h($query)) ?></p>
<?php endif; ?>

<?php if ($results !== []): ?>
    <ul>
        <?php foreach ($results as $restaurant): ?>
            <li>
                <strong><?= h($restaurant->name) ?></strong>
                (<?= h($restaurant->cuisine) ?>) — <?= h($restaurant->address) ?>
                <br>
                <?= h($restaurant->description) ?>
                <br>
                <small><?= __('distance: {0}', h(round($restaurant->distance, 4))) ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev cake test --filter RestaurantsControllerSearchTest`
Expected: PASS. Default CakePHP routing (`$routes->fallbacks()` in `config/routes.php`) already maps `/restaurants/search` to `RestaurantsController::search()` — if it 404s instead, check `config/routes.php` for a fallback route and add one if missing.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/RestaurantsController.php templates/Restaurants/search.php tests/TestCase/Controller/RestaurantsControllerSearchTest.php
git commit -m "Add natural-language search endpoint"
```

---

### Task 10: Import real data and manually verify search

**Files:** none (verification-only task; no source changes).

**Interfaces:**
- Consumes: `bin/cake import_restaurants` (Task 7), `/restaurants/search` (Task 9).

- [ ] **Step 1: Run the full test suite**

Run: `ddev cake test`
Expected: all tests PASS (this is the first point every task's tests have run together — confirm no cross-task regressions).

- [ ] **Step 2: Import real data**

Run:
```bash
ddev cake import_restaurants --city=brooklyn
```
Expected: "Imported N restaurants." with N roughly in the 50–300 range (Brooklyn's Overpass bounding box). If it errors, the failure message from Task 7's error handling should say why (Overpass request failure or unknown city) — resolve based on that message before continuing.

- [ ] **Step 3: Re-run to confirm idempotency against real data**

Run:
```bash
ddev cake import_restaurants --city=brooklyn
ddev postgres -e "SELECT count(*) FROM restaurants;"
```
Expected: the row count after the second run equals the count after the first run (no duplicates).

- [ ] **Step 4: Manually verify search quality**

Run:
```bash
ddev postgres -e "SELECT name, description FROM restaurants LIMIT 5;"
```
Pick a phrase that should match one of the returned descriptions (e.g. if a row mentions "outdoor seating" and "wine", search for that). Then:
```bash
curl -s "https://cakephp-pgvector.ddev.site/restaurants/search?q=outdoor+seating+wine" | grep -A2 '<li>' | head -20
```
Expected: the restaurant whose description most closely matches the query phrase (by shared vocabulary) appears at or near the top of the results.

- [ ] **Step 5: Commit**

No file changes expected from this task. If Step 2–4 surfaced a fix (e.g. a bounding box adjustment), commit that fix with an appropriate message. Otherwise, skip committing — this task's purpose is verification, not code.

---

### Task 11: Write `ARTICLE.md`

**Files:**
- Create: `ARTICLE.md`

**Interfaces:** none (documentation only).

- [ ] **Step 1: Draft the article**

Create `ARTICLE.md` at the project root covering, in order:

1. **Why pgvector + CakePHP** — one paragraph motivating semantic search over exact-match filters (reuse the "vocabulary mismatch / fuzzy concepts / ranking vs. filtering" points from the design's motivation), and why Postgres+pgvector is a low-friction way to add it to an existing CakePHP app without a separate vector database.
2. **The schema** — walk through `config/Migrations/20260924120000_CreateRestaurants.php`, explaining the `CREATE EXTENSION vector` step and why the `embedding vector(128)` column needed raw SQL (Phinx has no native pgvector column type).
3. **Teaching CakePHP to speak `vector`** — walk through `src/Database/Type/VectorType.php` and the `_initializeSchema()` override in `src/Model/Table/RestaurantsTable.php`, explaining the PHP `float[]` ⇄ Postgres literal marshaling.
4. **The embedding function, and its trade-offs** — walk through `src/Vector/EmbeddingGenerator.php`'s feature-hashing approach; explicitly show a "swap one function" example of what `embed()` would look like calling a real embedding API (e.g. OpenAI's `text-embedding-3-small`) instead, without needing to change anything downstream (`VectorType`, the finder, the search action all only see `float[]`).
5. **Turning open data into descriptions** — walk through `src/Vector/OverpassClient.php` and `src/Vector/DescriptionBuilder.php`, and `bin/cake import_restaurants` (`src/Command/ImportRestaurantsCommand.php`).
6. **The search query** — walk through `RestaurantsTable::findSimilarTo()` in `src/Model/Table/RestaurantsTable.php` and `RestaurantsController::search()`, explaining pgvector's `<=>` cosine-distance operator.
7. **Next steps** — mention adding an IVFFlat/HNSW index once the dataset grows past a few thousand rows, hybrid full-text + vector search (Postgres `tsvector` combined with `<=>`), and swapping in a real embedding API per section 4.

Every code excerpt quoted in the article must be copied verbatim from the actual file at that point in the repo — do not paraphrase or reconstruct from memory.

- [ ] **Step 2: Verify every referenced path and symbol exists**

Run:
```bash
grep -oE '`[a-zA-Z0-9_/.]+\.php`' ARTICLE.md | tr -d '`' | sort -u | while read -r f; do
  test -f "$f" && echo "OK: $f" || echo "MISSING: $f"
done
```
Expected: every line printed is `OK:` — fix any `MISSING:` line before continuing (either the path was mistyped, or the article references a file this plan never created).

- [ ] **Step 3: Commit**

```bash
git add ARTICLE.md
git commit -m "Write pgvector + CakePHP article"
```
