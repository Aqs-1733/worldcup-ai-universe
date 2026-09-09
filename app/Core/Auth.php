<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function attempt(string $usernameOrEmail, string $password): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        return true;
    }

    /** @return array{ok:bool,message:string,user_id?:int} */
    public static function register(array $payload): array
    {
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $displayName = trim((string) ($payload['display_name'] ?? $username));

        if (!preg_match('/^[a-zA-Z0-9_]{3,40}$/', $username)) {
            return ['ok' => false, 'message' => '用户名只能包含字母、数字和下划线，长度 3-40。'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => '邮箱格式不正确。'];
        }
        if (mb_strlen($password) < 6) {
            return ['ok' => false, 'message' => '密码至少 6 位。'];
        }

        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $exists->execute([$username, $email]);
        if ($exists->fetch()) {
            return ['ok' => false, 'message' => '用户名或邮箱已经被注册。'];
        }

        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $displayName]);
        $userId = (int) $pdo->lastInsertId();

        $roleId = self::roleId('user');
        if ($roleId) {
            $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);
        }

        $_SESSION['user_id'] = $userId;
        return ['ok' => true, 'message' => '注册成功。', 'user_id' => $userId];
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            Flash::set('error', '请先登录。');
            redirect('/login');
        }
        return $user;
    }

    public static function isAdmin(?int $userId = null): bool
    {
        $userId ??= self::id();
        if (!$userId) {
            return false;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.name = ?'
        );
        $stmt->execute([$userId, 'admin']);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if (!self::isAdmin((int) $user['id'])) {
            http_response_code(403);
            exit('需要管理员权限。');
        }
        return $user;
    }

    private static function roleId(string $name): ?int
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}
