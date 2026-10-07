DROP DATABASE IF EXISTS `beforum_unitest`;
CREATE DATABASE `beforum_unitest` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'beforum_unitest'@'localhost' IDENTIFIED BY 'beforum_unitest';
GRANT ALL ON `beforum_unitest`.* TO 'beforum_unitest'@'localhost';
FLUSH PRIVILEGES;
