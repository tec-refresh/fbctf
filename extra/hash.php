<?php
if ($argc < 2) {
    echo "Usage: php hash.php <password>\n";
    exit(1);
}
$options = ['cost' => 12];
echo password_hash($argv[1], PASSWORD_DEFAULT, $options);
