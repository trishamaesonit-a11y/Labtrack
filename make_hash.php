<?php
// Usage (CLI): php tools/make_hash.php mypassword
echo password_hash($argv[1] ?? "password", PASSWORD_DEFAULT), PHP_EOL;
