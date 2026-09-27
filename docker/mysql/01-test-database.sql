-- Local development and CI only; no application domain schema is created here.
CREATE DATABASE IF NOT EXISTS ordely_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON ordely_test.* TO 'ordely'@'%';
