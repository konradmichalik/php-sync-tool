-- Target database starts with an empty `person` table (zero rows) so a sync
-- from the origin (three rows) is observable.
CREATE TABLE IF NOT EXISTS person (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);
