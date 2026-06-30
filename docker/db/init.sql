-- Seed schema used by the integration scenarios. The `person` table starts
-- with three rows on the origin database so a successful sync can be asserted
-- by row count on the target.
CREATE TABLE IF NOT EXISTS person (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

INSERT INTO person (name) VALUES ('Alice'), ('Bob'), ('Carol');
