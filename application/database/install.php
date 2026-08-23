<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Run this installer from the command line only.\n"); }
$config = require dirname(__DIR__) . '/app/config/config.php';
$db = $config['db'];
$serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);
$pdo = new PDO($serverDsn, $db['user'], $db['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name = preg_replace('/[^a-zA-Z0-9_]/', '', $db['name']);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
$insert = $pdo->prepare('INSERT INTO users (role,first_name,last_name,email,phone,student_number,password_hash,status,verified_at) VALUES (?,?,?,?,?,?,?,"active",?)');
$accounts = [
    ['student','Demo','Student','student@emergex.test','0820000001','400000001','Student@55',null],
    ['landlord','Demo','Landlord','landlord@emergex.test','0820000002',null,'Landlord@55',date('Y-m-d H:i:s')],
    ['admin','System','Administrator','admin@emergex.test','0820000003',null,'Admin@55Secure',date('Y-m-d H:i:s')],
];
foreach ($accounts as $account) {
    [$role,$first,$last,$email,$phone,$studentNumber,$password,$verified] = $account;
    $insert->execute([$role,$first,$last,$email,$phone,$studentNumber,password_hash($password,PASSWORD_DEFAULT),$verified]);
}
$pdo->exec(file_get_contents(__DIR__ . '/seed.sql'));
echo "EMERGEX database installed successfully.\n";
echo "Demo accounts: student@emergex.test / Student@55; landlord@emergex.test / Landlord@55; admin@emergex.test / Admin@55Secure\n";
