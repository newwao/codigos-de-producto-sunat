<?php
// Copy as config.php. Generate a hash: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
return [
    'dsn' => 'mysql:host=localhost;dbname=codigo_claro;charset=utf8mb4',
    'db_user' => 'codigo_claro',
    'db_password' => 'CHANGE_ME',
    'username' => 'administrador',
    'password_hash' => 'REPLACE_WITH_PASSWORD_HASH',
    // Set false only for localhost development over HTTP.
    'require_https' => true,
];
