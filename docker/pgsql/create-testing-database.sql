-- Creates the testing database if it doesn't already exist.
-- Runs automatically on first Postgres container boot via docker-entrypoint-initdb.d

SELECT 'CREATE DATABASE dost_testing'
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'dost_testing'
)\gexec

GRANT ALL PRIVILEGES ON DATABASE dost_testing TO dost_user;

