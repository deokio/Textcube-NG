-- MySQL 5.0+ / MariaDB 5.1+ / MySQL 8.0+ compatible (Textcube-NG php5.5 port, r4-4).
-- GRANT ... IDENTIFIED BY was deprecated in MySQL 5.7 and removed in MySQL 8.0.
-- User creation and privilege grant are now separated.
-- CREATE USER IF NOT EXISTS (5.7.6+) is not used: an existing user (error 1396) is skipped by a CONTINUE handler so GRANT still runs.
USE PlaceholderForDbName;

DROP PROCEDURE IF EXISTS add_user;

CREATE PROCEDURE add_user()
BEGIN
    DECLARE EXIT HANDLER FOR 1044 BEGIN END;
    DECLARE CONTINUE HANDLER FOR 1396 BEGIN END;
    CREATE USER 'PlaceholderForDbUser'@'localhost' IDENTIFIED BY 'PlaceholderForDbPassword';
    GRANT ALL PRIVILEGES ON PlaceholderForDbName.* TO 'PlaceholderForDbUser'@'localhost';
    FLUSH PRIVILEGES;
END
;

CALL add_user();

DROP PROCEDURE IF EXISTS add_user;
