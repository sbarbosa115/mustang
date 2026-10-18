-- The test suite's database (Doctrine's dbname_suffix: _test, plus a suffix per parallel worker if one is added).
GRANT ALL PRIVILEGES ON `mustang\_test%`.* TO 'mustang'@'%';
CREATE DATABASE IF NOT EXISTS `mustang_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
FLUSH PRIVILEGES;
