
<?php

require_once __DIR__ . '/../models/User.php';

class AuthController
{
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->userModel = new User($db);
    }

    public function register(array $data): array
    {
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        $errors = [];

        if (!is_string($username) ||
            $username === '' ||
            strlen($username) < 3 ||
            strlen($username) > 50) {
            $errors[] = 'Username must be between 3 and 50 characters.';
        }

        if (!is_string($email) ||
            strlen($email) > 255 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (!is_string($password) || strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if (!is_string($confirmPassword) ||
            $password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $this->userModel->create(
                $username,
                $email,
                $password
            );

            return [
                'success' => true,
                'errors' => [],
                'message' => 'Account created successfully! You can now log in.'
            ];
        } catch (PDOException $e) {
            // MySQL error 1062 means a UNIQUE value already exists.
            if ($e->errorInfo[1] === 1062) {
                return [
                    'success' => false,
                    'errors' => [
                        'That username or email is already registered.'
                    ]
                ];
            }

            error_log($e->getMessage());

            return [
                'success' => false,
                'errors' => [
                    'Registration failed. Please try again.'
                ]
            ];
        }
    }
}
