<?php
// Script to generate a valid bcrypt hash for a password
$password = '123456';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h1>Password Hash Generator</h1>";
echo "<p><strong>Password:</strong> $password</p>";
echo "<p><strong>Hash:</strong> $hash</p>";
echo "<hr>";
echo "<p>Copy this hash and update your 'users' table in the database:</p>";
echo "<code style='background: #f4f4f4; padding: 10px; display: block;'>UPDATE users SET password_hash = '$hash' WHERE email = 'admin@company.com';</code>";
?>