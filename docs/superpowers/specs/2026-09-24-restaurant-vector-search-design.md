# Restaurant Catalog with pgvector Semantic Search — Design

- **Date:** 2026-09-24
- **Status:** Approved
- **Author:** Claude (with jorge@hostpro-tech.com)

## Purpose

A small, self-contained CakePHP 5 application demonstrating pgvector
integration end to end, built as the companion codebase for an article about
pgvector + CakePHP. The app is a catalog of real restaurants (pulled from
OpenStreetMap) with natural-language semantic search powered by pgvector.
Simplicity and readability for an article audience take priority over
production concerns (auth, pagination polish, admin UI, etc. are out of
scope).

## Non-goals

- No user accounts, auth, or booking/reservation flow.
- No real embedding API integration (see "Embeddings" below) — the app must
  run fully offline with zero API keys, but the design must make swapping in
  a real embedding provider an obvious, small change (this is called out
  explicitly in the article).
- No production deployment concerns; DDEV is the only supported environment.

## Environment

Existing DDEV config already targets this: `type: cakephp`, PHP 8.5,
Postgres 18, nginx-fpm. No changes needed to `.ddev/config.yaml`.

## Data model

**Migration** creates the `vector` Postgres extension and a `restaurants`
table:

| column       | type          | notes                                         |
|--------------|---------------|------------------------------------------------|
| id           | uuid / int PK | bake default (bigint identity)                |
| osm_id       | bigint        | OpenStreetMap node id; unique, used for upsert |
| name         | string        | required                                       |
| cuisine      | string        | nullable, raw OSM `cuisine` tag                |
| city         | string        | nullable                                       |
| address      | string        | nullable, composed from `addr:*` tags          |
| tags         | json          | raw OSM tags, kept for display/debugging       |
| description  | text          | synthesized natural-language sentence          |
| embedding    | vector(128)   | pgvector column, see Embeddings below           |
| created      | datetime      |                                                 |
| modified     | datetime      |                                                 |

An IVFFlat or HNSW index on `embedding` is out of scope for this small
dataset (a few hundred rows) — sequential `ORDER BY embedding <=> ?` is fast
enough and simpler to explain in the article. The article's "next steps"
section mentions adding an index for larger datasets.

## Components

### 1. `RestaurantsTable` / `Restaurant` entity

Standard bake-generated model. The `embedding` column is stored as a Postgres
`vector` — CakePHP has no native type for it, so a custom
`Cake\Database\Type` (`VectorType`) is added to marshal PHP `float[]` arrays
to/from the Postgres `vector` literal format (`[0.1,0.2,...]`). This is the
one piece of "framework glue" the article centers on.

### 2. `App\Vector\EmbeddingGenerator` (or similar namespace)

Pure function: `embed(string $text): array` (returns a 128-float array,
L2-normalized). Algorithm ("feature hashing" / hashing trick):

1. Lowercase, strip punctuation, split on whitespace → tokens.
2. For each token, `crc32(token) % 128` selects a bucket; increment that
   bucket by 1 (plain counts — no signed hashing, keeps the algorithm
   simple to explain in the article).
3. L2-normalize the resulting 128-dim vector so cosine and L2 distance
   behave consistently.

Deterministic: same input string always yields the same vector — required
for both tests and for re-running the import idempotently. This same
function is used both when embedding a restaurant's `description` at import
time and when embedding the user's search query at request time.

### 3. Description synthesis: `App\Vector\DescriptionBuilder`

Pure function: `build(array $osmTags): string`. Combines `cuisine`,
`addr:city`, `outdoor_seating`, `diet:vegan`/`diet:vegetarian`,
`opening_hours` (simplified to "open late" if closing time ≥ 22:00), and
drink/amenity tags into one natural sentence, e.g.:

> "American restaurant in Brooklyn serving cocktails and wine, with outdoor
> seating, open late."

Missing tags are simply omitted from the sentence — no placeholder text.

### 4. Import console command: `bin/cake import_restaurants`

- Option `--bbox="lat1,lon1,lat2,lon2"` (or `--city` resolved via a small
  hardcoded lookup table of a few bounding boxes for demo cities — avoids
  adding a geocoding dependency).
- Calls Overpass API (`https://overpass-api.de/api/interpreter`) with a
  query for `node[amenity=restaurant]` in the bounding box, `out body`.
- For each returned node: build description, compute embedding, upsert by
  `osm_id`.
- No pagination/rate-limit handling needed for a demo-sized bounding box
  (expect on the order of 100–300 results); a single request is sufficient.

### 5. Search: `RestaurantsController::search()`

- GET `/restaurants/search?q=...`.
- Empty/missing `q` renders the search form only.
- Non-empty `q`: embed the query text via `EmbeddingGenerator::embed()`,
  run a custom finder (`RestaurantsTable::findSimilarTo(array $vector, int
  $limit = 10)`) that executes:
  ```sql
  SELECT *, embedding <=> :query AS distance
  FROM restaurants
  ORDER BY embedding <=> :query
  LIMIT :limit
  ```
  via CakePHP's query builder with a raw expression for the `<=>` operator
  (pgvector's cosine-distance operator), bound as a Postgres vector literal.
- Results rendered in a template showing name, cuisine, address, description,
  and the raw distance value (useful for the article to show relevance
  ordering concretely).
- Normal bake-scaffolded `index`/`view` actions remain for browsing without
  search.

## Error handling

- Import command: if Overpass returns a non-200 or empty result set, print a
  clear error and exit non-zero; no retries (article-scope simplicity).
- Search: empty query string is not an error — it just shows the empty
  form. No results is not an error — render "no matches" in the template.
- `VectorType` throws on malformed vector data on the way out of the
  database (should not happen in practice) rather than silently returning
  nulls.

## Testing

- Unit tests for `EmbeddingGenerator::embed()`: determinism (same input →
  same output), normalization (`|v| == 1.0` within float tolerance),
  differing inputs generally produce differing vectors.
- Unit tests for `DescriptionBuilder::build()`: a handful of tag
  combinations → expected sentence fragments present/absent.
- Integration test for `RestaurantsController::search()` against a small
  fixture set (3–5 restaurants with hand-picked descriptions/embeddings)
  asserting that a query semantically closer to one fixture ranks first.
- Import command is tested against a canned/fixture Overpass JSON response
  (HTTP client mocked/stubbed) — no real network calls in the test suite.

## Article deliverable

`ARTICLE.md` at the project root, written against the actual code produced
here, covering:

1. Why pgvector + CakePHP, and what this example does.
2. Schema/migration walkthrough (the `vector` extension, the `VectorType`).
3. The embedding technique used here and its trade-offs vs. a real embedding
   API — explicit callout that swapping in OpenAI/Voyage/etc. is a one-line
   change to `EmbeddingGenerator::embed()`.
4. The import command and description synthesis.
5. The search query and how pgvector's distance operators work.
6. Next steps: ANN indexes (IVFFlat/HNSW) for larger datasets, hybrid
   full-text + vector search, real embeddings.

## Open questions / assumptions carried into planning

- Exact embedding dimensionality (128) and hashing scheme are a reasonable
  default; can be tuned during implementation if search results look poor
  in manual testing.
- Demo bounding boxes for `--city` will be limited to 2–3 well-known cities
  (e.g. Brooklyn NY, Madrid) with dense OSM restaurant coverage.
