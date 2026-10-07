DROP ROLE IF EXISTS beforum_unitest;
CREATE ROLE beforum_unitest SUPERUSER;
ALTER ROLE beforum_unitest WITH LOGIN PASSWORD 'beforum_unitest';
