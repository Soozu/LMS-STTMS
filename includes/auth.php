<?php
require_once 'config.php';

class Auth {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function login($username, $password, $userType) {
        try {
            // First check if user exists and is active
            $stmt = $this->conn->prepare("
                SELECT u.*, 
                    CASE 
                        WHEN u.user_type = 'admin' THEN a.id
                        WHEN u.user_type = 'teacher' THEN t.id
                        WHEN u.user_type = 'student' THEN s.id
                    END as role_id
                FROM users u
                LEFT JOIN admins a ON u.id = a.user_id AND u.user_type = 'admin'
                LEFT JOIN teachers t ON u.id = t.user_id AND u.user_type = 'teacher'
                LEFT JOIN students s ON u.id = s.user_id AND u.user_type = 'student'
                WHERE u.username = ? 
                AND u.user_type = ?
                AND u.status = 'active'
            ");
            $stmt->execute([$username, $userType]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Update last login
                $stmt = $this->conn->prepare("
                    UPDATE users 
                    SET last_login = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['username'] = $user['username'];

                // Log the login
                $this->logAction($user['id'], 'Login', 'User logged in successfully');

                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logAction($_SESSION['user_id'], 'Logout', 'User logged out');
        }
        
        // Clear all session variables
        $_SESSION = array();
        
        // Destroy the session
        session_destroy();
        
        // Set logout message
        session_start();
        $_SESSION['logout_message'] = 'You have been successfully logged out';
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
    }

    public function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }

    private function logAction($user_id, $action, $description) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO system_logs (user_id, action, description, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (PDOException $e) {
            error_log("Error logging action: " . $e->getMessage());
        }
    }

    // Register student
    public function registerStudent($data) {
        try {
            $this->conn->beginTransaction();

            // Create user account
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, password, user_type)
                VALUES (?, ?, 'student')
            ");
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt->execute([$data['lrn'], $hashedPassword]);
            $userId = $this->conn->lastInsertId();

            // Create student profile
            $stmt = $this->conn->prepare("
                INSERT INTO students (
                    user_id, lrn, first_name, last_name, 
                    grade_level, section, gender, birth_date,
                    contact_number, address, guardian_name, guardian_contact
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $data['lrn'], $data['first_name'], $data['last_name'],
                $data['grade_level'], $data['section'], $data['gender'], $data['birth_date'],
                $data['contact_number'], $data['address'], $data['guardian_name'], $data['guardian_contact']
            ]);

            $this->conn->commit();
            return true;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Student registration error: " . $e->getMessage());
            return false;
        }
    }

    // Register teacher
    public function registerTeacher($data) {
        try {
            $this->conn->beginTransaction();

            // Create user account
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, password, user_type)
                VALUES (?, ?, 'teacher')
            ");
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt->execute([$data['username'], $hashedPassword]);
            $userId = $this->conn->lastInsertId();

            // Create teacher profile
            $stmt = $this->conn->prepare("
                INSERT INTO teachers (
                    user_id, employee_id, first_name, last_name,
                    email, contact_number, specialization
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $data['employee_id'], $data['first_name'], $data['last_name'],
                $data['email'], $data['contact_number'], $data['specialization']
            ]);

            $this->conn->commit();
            return true;
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Teacher registration error: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize Auth class
$auth = new Auth($conn); 