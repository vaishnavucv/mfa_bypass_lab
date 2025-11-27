<?php
// Generate proper password hashes for the application

echo "Password Hash Generator\n";
echo "======================\n\n";

$passwords = [
    'Admin@123' => 'Admin password',
    'User@123' => 'User password',
];

foreach ($passwords as $password => $description) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "$description ($password):\n";
    echo "$hash\n\n";
}
?>
