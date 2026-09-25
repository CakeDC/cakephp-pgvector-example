# CakePHP + pgvector: semantic search demo

A small CakePHP 5 application that catalogs real restaurants (pulled from
OpenStreetMap) and lets users search them in plain English, e.g. "outdoor
seating and vegetarian options", using [pgvector](https://github.com/pgvector/pgvector)
for the similarity search.

See [this article on the CakeDC website](https://www.cakedc.com/articles/semantic-search-in-cakephp-with-pgvector)
for the full walkthrough: the Postgres schema, the custom `Cake\Database\Type`
that maps a `vector` column to a PHP `float[]`, the toy embedding generator,
and the cosine-distance finder that powers the search.

## Requirements

- [DDEV](https://ddev.com) (bundles PHP 8.5 and PostgreSQL 18 with pgvector
  pre-installed via `.ddev/db-build/Dockerfile`)

## Running it

```bash
ddev start
ddev composer install
ddev cake migrations migrate
ddev cake import_restaurants --city=madrid   # or --city=brooklyn
```

Then visit `/restaurants/search?q=outdoor+seating+wine` (or whatever your
imported city's restaurants mention) on the DDEV-provided URL.

## Restaurant data

The catalog is seeded from real, free, open data pulled from
[OpenStreetMap](https://www.openstreetmap.org) via its
[Overpass API](https://overpass-api.de/), no API key required. Running
`bin/cake import_restaurants --city=<city>` fetches restaurant nodes for that
city's bounding box, builds a description from their OpenStreetMap tags,
embeds it, and upserts the result by OpenStreetMap's node id. See
`src/Vector/OverpassClient.php`, `src/Vector/DescriptionBuilder.php`, and
`src/Command/ImportRestaurantsCommand.php`.

## The embeddings

`src/Vector/EmbeddingGenerator.php` builds vectors with a hashed bag-of-words
scheme (no external API, no API key) so the demo works out of the box. It's a
toy: good enough to show restaurants that share vocabulary landing close
together, not a substitute for a trained embedding model. [The article on the
CakeDC website](https://www.cakedc.com/articles/semantic-search-in-cakephp-with-pgvector)
explains the trade-off and how to swap in a real embedding API.

## Configuration

Read and edit the environment specific `config/app_local.php` and set up the
`'Datasources'` and any other configuration relevant to your setup. Other
environment agnostic settings can be changed in `config/app.php`.
