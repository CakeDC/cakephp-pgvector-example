## Installs the pgvector Postgres extension (postgresql-18-pgvector) so
## `CREATE EXTENSION vector` works in migrations.
RUN apt-get update && apt-get install -y --no-install-recommends postgresql-18-pgvector \
    && rm -rf /var/lib/apt/lists/*
