-- Target database starts with an empty `person` table (zero rows) so a sync
-- from the origin (three rows) is observable.
CREATE TABLE IF NOT EXISTS person (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

-- Second table for the anonymization scenario: plaintext values that masking
-- must have replaced after a sync.
CREATE TABLE IF NOT EXISTS account (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    notes VARCHAR(255) NULL
);
