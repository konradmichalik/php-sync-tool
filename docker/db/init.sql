-- Seed schema used by the integration scenarios. The `person` table starts
-- with three rows on the origin database so a successful sync can be asserted
-- by row count on the target.
CREATE TABLE IF NOT EXISTS person (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

INSERT INTO person (name) VALUES ('Alice'), ('Bob'), ('Carol');

-- Second table for the anonymization scenario: plaintext values that masking
-- must have replaced after a sync.
CREATE TABLE IF NOT EXISTS account (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    notes VARCHAR(255) NULL
);

INSERT INTO account (email, password, display_name, notes) VALUES
    ('alice@example.com', 'plaintext-a', 'Alice', 'internal note a'),
    ('bob@example.com', 'plaintext-b', 'Bob', 'internal note b');
