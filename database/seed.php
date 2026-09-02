<?php
$pdo = new PDO(
    "mysql:host=mariadb;dbname=clima_tcc;charset=utf8mb4",
    "clima_user",
    "clima_pass",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$hash = password_hash("123456", PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE gestores SET senha_hash = ? WHERE email = ?");
$stmt->execute([$hash, "gestor@escola.com"]);
echo "Seed concluído.\n";
