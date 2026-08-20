-- PostgreSQL target database starts with an empty `person` table (zero rows)
-- so a sync from the origin (three rows) is observable.
CREATE TABLE IF NOT EXISTS person (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);
