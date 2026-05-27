USE `fbctf`;

INSERT INTO `teams` (id, name, password_hash, admin, protected, logo, visible, active, created_ts)
VALUES (1, 'admin', '$2y$12$B9VMXwMHLnJ0tOcj/W7Wf.GfUhDFOE.gcEhudLciWJf.ve02pOWpS', 1, 1, 'admin', 0, 1, NOW())
ON DUPLICATE KEY UPDATE name=name;
