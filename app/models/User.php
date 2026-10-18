
<?php

class User
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(
        string $username,
        string $email,
        string $password
    ): int {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "
            INSERT INTO users (
                username,
                email,
                password_hash
            )
            VALUES (
                :username,
                :email,
                :password_hash
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash
        ]);

        return (int) $this->db->lastInsertId();
    }

    
    public function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT id, username, email, password_hash, role
            FROM users
            WHERE email = :email
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();

        return $user ?: null;
    }


    public function authenticate(
        string $email,
        string $password
    ): ?array {
        $user = $this->findByEmail($email);

        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);

        return $user;
    }
    
    public function getAll(
        string $search = '',
        string $role = 'all'
    ): array {
        $sql = "
            SELECT id, username, email, role, created_at
            FROM users
            WHERE 1 = 1
        ";

        $params = [];

        if (trim($search) !== '') {
            $sql .= "
                AND (
                    username LIKE :username_search
                    OR email LIKE :email_search
                )
            ";

            $term = '%' . trim($search) . '%';

            $params['username_search'] = $term;
            $params['email_search'] = $term;
        }

        if (in_array($role, ['user', 'admin'], true)) {
            $sql .= " AND role = :role";
            $params['role'] = $role;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }


}
