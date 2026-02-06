<?php

declare(strict_types=1);

/** @var array{app: array{env: string, debug: bool}, database: array{path: string}, pagespeed: array{api_key: string, rate_limit_per_second: int, max_retries: int}} $config */
$config = require __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Database\MigrationRunner;
use App\Modules\Auth\Application\Services\UserService;
use App\Modules\Auth\Infrastructure\Repositories\SqliteUserRepository;

$options = getopt('', ['email:', 'password:', 'role:']);

if (!isset($options['email'], $options['password'], $options['role'])) {
    echo "Usage: php cli/create-user.php --email=admin@example.com --password=secret123 --role=admin\n";
    echo "Roles: admin, viewer\n";
    exit(1);
}

/** @var string $email */
$email = $options['email'];
/** @var string $password */
$password = $options['password'];
/** @var string $role */
$role = $options['role'];

$database = new Database($config['database']['path']);
$migrationRunner = new MigrationRunner($database);
$migrationRunner->run();

$userRepository = new SqliteUserRepository($database);
$userService = new UserService($userRepository);

try {
    $user = $userService->create($email, $password, $role);
    echo "User created successfully!\n";
    echo "  ID:    " . $user->getId() . "\n";
    echo "  Email: " . $user->getEmail()->getValue() . "\n";
    echo "  Role:  " . $user->getRole()->label() . "\n";
} catch (\App\Shared\Exceptions\ValidationException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
