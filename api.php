<?php
// api.php
// Bayero University Kano — Examination Incident Management System (BUK EIMS)
declare(strict_types=1);

// --- Secure Session Configuration ---
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();
date_default_timezone_set('UTC');

// --- 1. DB Connection (PostgreSQL / Neon) — see db.php ---
require __DIR__ . '/db.php';
$pdo = get_pdo();

// --- 2. Middleware & Security Helpers ---
function sendJson($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function auditLog(PDO $pdo, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?::jsonb, ?)");
        $stmt->execute([$userId, $action, $entityType, $entityId, json_encode($details), $ip]);
    } catch (Throwable $e) {
        error_log("Audit log failure: " . $e->getMessage());
    }
}

function generateCSRF(PDO $pdo): string {
    $token = bin2hex(random_bytes(32));
    $sid = session_id();
    $stmt = $pdo->prepare("INSERT INTO csrf_tokens (session_id, token, expires_at, used) VALUES (?, ?, NOW() + INTERVAL '4 hours', FALSE)");
    $stmt->execute([$sid, $token]);
    return $token;
}

function validateCSRF(PDO $pdo): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!$token && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'x-csrf-token') { 
                $token = $v; 
                break; 
            }
        }
    }
    
    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    }

    if (!$token) {
        sendJson(['success' => false, 'message' => 'CSRF Token Missing'], 403);
    }
    
    $sid = session_id();
    // Enforce binding to active session_id and prevent reuse of used tokens
    $stmt = $pdo->prepare("SELECT id FROM csrf_tokens WHERE token = ? AND session_id = ? AND expires_at > NOW() AND used = FALSE");
    $stmt->execute([$token, $sid]);
    $row = $stmt->fetch();
    
    if (!$row) {
        sendJson(['success' => false, 'message' => 'Invalid or Expired CSRF Token'], 403);
    }

    // Invalidate token after validation to prevent replay
    $pdo->prepare("UPDATE csrf_tokens SET used = TRUE WHERE id = ?")->execute([$row['id']]);
}

function requireRole(array $roles = []): array {
    if (!isset($_SESSION['user'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles, true)) {
        sendJson(['success' => false, 'message' => 'Forbidden: insufficient role privileges'], 403);
    }
    return $_SESSION['user'];
}

function notify(PDO $pdo, int $user_id, string $type, string $title, string $message, ?string $link = null): void {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $title, $message, $link]);
}

// --- 3. Request Router ---
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($input)) {
    $input = $_POST;
}

try {
    switch ($action) {
        // --- Authentication & Profile ---
        case 'check_auth':
            if (isset($_SESSION['user'])) {
                sendJson(['isAuthenticated' => true, 'user' => $_SESSION['user'], 'csrf_token' => generateCSRF($pdo)]);
            }
            sendJson(['isAuthenticated' => false, 'csrf_token' => generateCSRF($pdo)]);
            break;

        case 'login':
            validateCSRF($pdo);
            $email = strtolower(trim((string)($input['email'] ?? '')));
            $password = (string)($input['password'] ?? '');

            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? AND is_active = TRUE");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'staff_id' => $user['staff_id'],
                    'role' => $user['role'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'faculty_id' => $user['faculty_id'] ? (int)$user['faculty_id'] : null,
                    'department_id' => $user['department_id'] ? (int)$user['department_id'] : null
                ];
                auditLog($pdo, (int)$user['id'], 'user_login', 'users', (int)$user['id'], ['email' => $user['email']]);
                sendJson(['success' => true, 'data' => $_SESSION['user']]);
            }
            auditLog($pdo, null, 'user_login_failed', 'users', null, ['email' => $email]);
            sendJson(['success' => false, 'message' => 'Invalid credentials or inactive account'], 401);
            break;

        case 'logout':
            if (isset($_SESSION['user']['id'])) {
                auditLog($pdo, (int)$_SESSION['user']['id'], 'user_logout', 'users', (int)$_SESSION['user']['id']);
            }
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            sendJson(['success' => true, 'message' => 'Logged out successfully']);
            break;

        case 'update_profile':
            $user = requireRole();
            validateCSRF($pdo);
            $fullName = trim((string)($input['full_name'] ?? ''));
            $phone = trim((string)($input['phone'] ?? ''));
            if ($fullName === '') sendJson(['success' => false, 'message' => 'Full name cannot be empty'], 422);

            if (!empty($input['password'])) {
                if (strlen($input['password']) < 8) sendJson(['success' => false, 'message' => 'Password must be at least 8 characters'], 422);
                $hash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, password_hash = ? WHERE id = ?")->execute([$fullName, $phone ?: null, $hash, $user['id']]);
            } else {
                $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")->execute([$fullName, $phone ?: null, $user['id']]);
            }
            $_SESSION['user']['full_name'] = $fullName;
            auditLog($pdo, $user['id'], 'profile_updated', 'users', $user['id']);
            sendJson(['success' => true, 'message' => 'Profile updated successfully']);
            break;

        case 'get_notifications':
            $user = requireRole();
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$user['id']]);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        // --- Staff Account Requests ---
        case 'request_account':
            validateCSRF($pdo);
            $role = $input['role_requested'] ?? 'invigilator';
            $allowedRoles = ['exam_officer', 'invigilator', 'hod', 'committee'];
            if (!in_array($role, $allowedRoles, true)) {
                sendJson(['success' => false, 'message' => 'Select a valid staff role'], 422);
            }
            $staffId = trim((string)($input['staff_id'] ?? ''));
            $fullName = trim((string)($input['full_name'] ?? ''));
            $email = strtolower(trim((string)($input['email'] ?? '')));
            $password = (string)($input['password'] ?? '');
            if ($staffId === '' || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                sendJson(['success' => false, 'message' => 'Provide a valid staff ID, name, BUK email, and password of at least 8 characters'], 422);
            }
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) OR staff_id = ?");
            $stmt->execute([$email, $staffId]);
            if ($stmt->fetch()) {
                sendJson(['success' => false, 'message' => 'An account already exists for this email or staff ID'], 409);
            }
            $stmt = $pdo->prepare("SELECT id FROM account_requests WHERE LOWER(email) = LOWER(?) AND status = 'pending'");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                sendJson(['success' => false, 'message' => 'A pending account request already exists for this email'], 409);
            }
            $stmt = $pdo->prepare("INSERT INTO account_requests (staff_id, full_name, email, phone, password_hash, role_requested, faculty_id, department_id, justification) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$staffId, $fullName, $email, $input['phone'] ?? null, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $role, !empty($input['faculty_id']) ? $input['faculty_id'] : null, !empty($input['department_id']) ? $input['department_id'] : null, $input['justification'] ?? null]);
            auditLog($pdo, null, 'account_requested', 'account_requests', null, ['email' => $email, 'staff_id' => $staffId, 'role' => $role]);
            sendJson(['success' => true, 'message' => 'Account request submitted for administrator approval']);
            break;

        case 'get_account_requests':
            requireRole(['admin']);
            $status = $input['status'] ?? 'pending';
            if ($status === 'all') {
                $stmt = $pdo->query("SELECT ar.*, reviewer.full_name AS reviewer_name, f.name AS faculty_name, d.name AS department_name FROM account_requests ar LEFT JOIN users reviewer ON reviewer.id=ar.reviewed_by LEFT JOIN faculties f ON f.id=ar.faculty_id LEFT JOIN departments d ON d.id=ar.department_id ORDER BY ar.created_at DESC");
            } else {
                $stmt = $pdo->prepare("SELECT ar.*, reviewer.full_name AS reviewer_name, f.name AS faculty_name, d.name AS department_name FROM account_requests ar LEFT JOIN users reviewer ON reviewer.id=ar.reviewed_by LEFT JOIN faculties f ON f.id=ar.faculty_id LEFT JOIN departments d ON d.id=ar.department_id WHERE ar.status=? ORDER BY ar.created_at DESC");
                $stmt->execute([$status]);
            }
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) unset($row['password_hash']);
            sendJson(['success' => true, 'data' => $rows]);
            break;

        case 'approve_account_request':
            $admin = requireRole(['admin']);
            validateCSRF($pdo);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT * FROM account_requests WHERE id=? AND status='pending' FOR UPDATE");
                $stmt->execute([$input['id']]);
                $request = $stmt->fetch();
                if (!$request) sendJson(['success' => false, 'message' => 'Pending account request not found'], 404);
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?) OR staff_id=?");
                $stmt->execute([$request['email'], $request['staff_id']]);
                if ($stmt->fetch()) sendJson(['success' => false, 'message' => 'A user with this email or staff ID already exists'], 409);
                
                $stmt = $pdo->prepare("INSERT INTO users (staff_id, full_name, email, password_hash, role, faculty_id, department_id, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$request['staff_id'], $request['full_name'], $request['email'], $request['password_hash'], $request['role_requested'], $request['faculty_id'], $request['department_id'], $request['phone']]);
                $newUserId = (int)$pdo->lastInsertId();
                
                $stmt = $pdo->prepare("UPDATE account_requests SET status='approved', reviewed_by=?, reviewed_at=NOW(), decision_note=? WHERE id=?");
                $stmt->execute([$admin['id'], $input['decision_note'] ?? 'Approved by administrator', $request['id']]);
                $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?) ON CONFLICT (user_id) DO NOTHING")->execute([$newUserId]);
                $pdo->commit();

                auditLog($pdo, $admin['id'], 'account_request_approved', 'users', $newUserId, ['approved_user_email' => $request['email'], 'role' => $request['role_requested']]);
                sendJson(['success' => true, 'message' => 'Account approved and created']);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            break;

        case 'reject_account_request':
            $admin = requireRole(['admin']);
            validateCSRF($pdo);
            $stmt = $pdo->prepare("UPDATE account_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW(), decision_note=? WHERE id=? AND status='pending'");
            $stmt->execute([$admin['id'], $input['decision_note'] ?? 'Request declined by administrator', $input['id']]);
            auditLog($pdo, $admin['id'], 'account_request_rejected', 'account_requests', (int)$input['id']);
            sendJson(['success' => true, 'message' => 'Account request declined']);
            break;

        // --- Admin: User Management ---
        case 'get_users':
            requireRole(['admin']);
            $sql = "SELECT u.id, u.staff_id, u.full_name, u.email, u.role, u.is_active, u.last_login, d.name as dept_name, f.name as faculty_name 
                    FROM users u 
                    LEFT JOIN departments d ON u.department_id = d.id 
                    LEFT JOIN faculties f ON u.faculty_id = f.id 
                    ORDER BY u.created_at DESC";
            $rows = $pdo->query($sql)->fetchAll();
            foreach ($rows as &$row) { 
                $row['is_active'] = ($row['is_active'] === true || $row['is_active'] === 't' || $row['is_active'] === '1'); 
            }
            sendJson(['success' => true, 'data' => $rows]);
            break;

        case 'create_user':
            $admin = requireRole(['admin']);
            validateCSRF($pdo);
            $hash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (staff_id, full_name, email, password_hash, role, department_id, faculty_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$input['staff_id'], $input['full_name'], $input['email'], $hash, $input['role'], $input['department_id'] ?: null, $input['faculty_id'] ?: null]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?) ON CONFLICT (user_id) DO NOTHING")->execute([$newId]);
            auditLog($pdo, $admin['id'], 'user_created', 'users', $newId, ['email' => $input['email'], 'role' => $input['role']]);
            sendJson(['success' => true, 'message' => 'User created successfully']);
            break;

        case 'update_user':
            $admin = requireRole(['admin']);
            validateCSRF($pdo);
            $id = (int)$input['id'];
            $dept_id = !empty($input['department_id']) ? $input['department_id'] : null;
            $fac_id = !empty($input['faculty_id']) ? $input['faculty_id'] : null;

            if (!empty($input['password'])) {
                $hash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("UPDATE users SET staff_id=?, full_name=?, email=?, role=?, faculty_id=?, department_id=?, password_hash=? WHERE id=?");
                $stmt->execute([$input['staff_id'], $input['full_name'], $input['email'], $input['role'], $fac_id, $dept_id, $hash, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET staff_id=?, full_name=?, email=?, role=?, faculty_id=?, department_id=? WHERE id=?");
                $stmt->execute([$input['staff_id'], $input['full_name'], $input['email'], $input['role'], $fac_id, $dept_id, $id]);
            }
            auditLog($pdo, $admin['id'], 'user_updated', 'users', $id);
            sendJson(['success' => true, 'message' => 'User updated successfully']);
            break;

        case 'toggle_user':
            $admin = requireRole(['admin']);
            validateCSRF($pdo);
            if ($input['id'] == $_SESSION['user']['id']) {
                sendJson(['success' => false, 'message' => 'Cannot disable your own account'], 400);
            }
            $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$input['is_active'], $input['id']]);
            auditLog($pdo, $admin['id'], 'user_status_toggled', 'users', (int)$input['id'], ['is_active' => $input['is_active']]);
            sendJson(['success' => true, 'message' => 'User access updated']);
            break;

        case 'delete_user':
            $admin = requireRole(['admin']);
            validateCSRF($pdo);
            if ($input['id'] == $_SESSION['user']['id']) {
                sendJson(['success' => false, 'message' => 'Cannot delete your own account'], 400);
            }
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$input['id']]);
            auditLog($pdo, $admin['id'], 'user_deleted', 'users', (int)$input['id']);
            sendJson(['success' => true, 'message' => 'User deleted permanently']);
            break;

        // --- Dashboards & Analytics ---
        case 'get_admin_dashboard':
            requireRole(['admin']);
            $stats = [
                'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                'incidents' => (int)$pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
                'active_cases' => (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE stage NOT IN ('resolved', 'dismissed')")->fetchColumn(),
                'resolved_cases' => (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE stage IN ('resolved', 'dismissed')")->fetchColumn()
            ];
            sendJson(['success' => true, 'data' => $stats]);
            break;

        case 'get_eo_dashboard':
            requireRole(['admin', 'exam_officer']);
            $stats = [
                'total_incidents' => (int)$pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
                'open_cases' => (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE stage NOT IN ('resolved', 'dismissed')")->fetchColumn(),
                'under_review' => (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE stage = 'under_review'")->fetchColumn(),
                'resolved_this_month' => (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE stage IN ('resolved', 'dismissed') AND EXTRACT(MONTH FROM resolved_at) = EXTRACT(MONTH FROM NOW()) AND EXTRACT(YEAR FROM resolved_at) = EXTRACT(YEAR FROM NOW())")->fetchColumn(),
                'pending_incidents' => (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'pending'")->fetchColumn()
            ];
            sendJson(['success' => true, 'data' => $stats]);
            break;

        case 'get_user_dashboard':
            $user = requireRole(['invigilator', 'hod', 'committee']);
            $stats = [];
            if ($user['role'] === 'invigilator') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reported_by = ?");
                $stmt->execute([$user['id']]); 
                $stats['reports'] = (int)$stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reported_by = ? AND status = 'pending'");
                $stmt->execute([$user['id']]); 
                $stats['pending'] = (int)$stmt->fetchColumn();
            } elseif ($user['role'] === 'hod') {
                $dept = $user['department_id'];
                $fac = $user['faculty_id'];
                if ($dept) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE student_dept_id = ?");
                    $stmt->execute([$dept]); $stats['dept_incidents'] = (int)$stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases c JOIN incidents i ON c.incident_id = i.id WHERE i.student_dept_id = ? AND c.stage NOT IN ('resolved', 'dismissed')");
                    $stmt->execute([$dept]); $stats['active_dept_cases'] = (int)$stmt->fetchColumn();
                } elseif ($fac) {
                    $stmt = $pdo->prepare("SELECT COUNT(i.id) FROM incidents i JOIN departments d ON i.student_dept_id = d.id WHERE d.faculty_id = ?");
                    $stmt->execute([$fac]); $stats['dept_incidents'] = (int)$stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(c.id) FROM cases c JOIN incidents i ON c.incident_id = i.id JOIN departments d ON i.student_dept_id = d.id WHERE d.faculty_id = ? AND c.stage NOT IN ('resolved', 'dismissed')");
                    $stmt->execute([$fac]); $stats['active_dept_cases'] = (int)$stmt->fetchColumn();
                } else {
                    $stats['dept_incidents'] = 0; $stats['active_dept_cases'] = 0;
                }
            } elseif ($user['role'] === 'committee') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE assigned_committee = ?");
                $stmt->execute([$user['id']]); $stats['assigned'] = (int)$stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE assigned_committee = ? AND stage IN ('investigation', 'hearing')");
                $stmt->execute([$user['id']]); $stats['pending_hearings'] = (int)$stmt->fetchColumn();
            }
            sendJson(['success' => true, 'data' => $stats]);
            break;

        case 'get_analytics':
            requireRole(['admin', 'exam_officer', 'hod']);
            $stagesRaw = $pdo->query("SELECT stage, COUNT(*) as count FROM cases GROUP BY stage")->fetchAll();
            $stages = [];
            foreach($stagesRaw as $row) { $stages[ucfirst(str_replace('_', ' ', $row['stage']))] = (int)$row['count']; }
            
            $monthlyRaw = $pdo->query("SELECT TO_CHAR(created_at, 'Mon') as month, EXTRACT(MONTH FROM created_at) as month_num, COUNT(*) as count FROM incidents WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM NOW()) GROUP BY month, month_num ORDER BY month_num")->fetchAll();
            $monthly = [];
            foreach($monthlyRaw as $row) { $monthly[$row['month']] = (int)$row['count']; }

            if (empty($stages)) $stages = ['Reported' => 0];
            if (empty($monthly)) $monthly = [date('M') => 0];

            sendJson(['success' => true, 'data' => ['stages' => $stages, 'monthly' => $monthly]]);
            break;

        // --- Data Fetching (Incidents, Cases & Details) ---
        case 'get_incidents':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod']);
            $sql = "SELECT i.*, u.full_name as reporter_name, c.code as course_code, d.name as department_name, f.name as faculty_name 
                    FROM incidents i 
                    JOIN users u ON i.reported_by = u.id 
                    JOIN courses c ON i.course_id = c.id
                    LEFT JOIN departments d ON i.student_dept_id = d.id
                    LEFT JOIN faculties f ON d.faculty_id = f.id";
            $params = [];
            
            if ($user['role'] === 'invigilator') {
                $sql .= " WHERE i.reported_by = ?"; 
                $params[] = $user['id'];
            } elseif ($user['role'] === 'hod') {
                if (!empty($user['department_id'])) {
                    $sql .= " WHERE i.student_dept_id = ?"; 
                    $params[] = $user['department_id'];
                } elseif (!empty($user['faculty_id'])) {
                    $sql .= " WHERE d.faculty_id = ?"; 
                    $params[] = $user['faculty_id'];
                }
            }
            $sql .= " ORDER BY i.created_at DESC";
            
            $stmt = $pdo->prepare($sql); 
            $stmt->execute($params);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'get_incident_details':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $incId = (int)($input['incident_id'] ?? 0);
            if ($incId <= 0) sendJson(['success' => false, 'message' => 'Invalid incident ID'], 400);

            $stmt = $pdo->prepare("SELECT i.*, u.full_name as reporter_name, c.code as course_code, c.title as course_title, d.name as department_name, f.name as faculty_name 
                                  FROM incidents i 
                                  JOIN users u ON i.reported_by = u.id 
                                  JOIN courses c ON i.course_id = c.id
                                  LEFT JOIN departments d ON i.student_dept_id = d.id
                                  LEFT JOIN faculties f ON d.faculty_id = f.id
                                  WHERE i.id = ?");
            $stmt->execute([$incId]);
            $incident = $stmt->fetch();
            if (!$incident) sendJson(['success' => false, 'message' => 'Incident record not found'], 404);

            // Scope check for invigilators and HODs
            if ($user['role'] === 'invigilator' && $incident['reported_by'] != $user['id']) {
                sendJson(['success' => false, 'message' => 'Forbidden'], 403);
            }
            if ($user['role'] === 'hod') {
                if (!empty($user['department_id']) && $incident['student_dept_id'] != $user['department_id']) sendJson(['success' => false, 'message' => 'Forbidden'], 403);
            }

            $evidenceStmt = $pdo->prepare("SELECT id, original_name, mime_type, file_size, uploaded_at FROM incident_evidence WHERE incident_id = ?");
            $evidenceStmt->execute([$incId]);
            $evidence = $evidenceStmt->fetchAll();

            $caseStmt = $pdo->prepare("SELECT id, case_no, stage FROM cases WHERE incident_id = ?");
            $caseStmt->execute([$incId]);
            $case = $caseStmt->fetch();

            sendJson(['success' => true, 'data' => ['incident' => $incident, 'evidence' => $evidence, 'case' => $case]]);
            break;

        case 'get_cases':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $sql = "SELECT c.*, i.reference_no, i.student_name, i.student_matric, i.offence_type, co.code as course_code,
                           comm.full_name as committee_member_name, off.full_name as officer_name
                    FROM cases c 
                    JOIN incidents i ON c.incident_id = i.id
                    LEFT JOIN courses co ON i.course_id = co.id
                    LEFT JOIN departments d ON i.student_dept_id = d.id
                    LEFT JOIN users comm ON c.assigned_committee = comm.id
                    LEFT JOIN users off ON c.assigned_officer = off.id";
            
            $params = [];
            if ($user['role'] === 'invigilator') {
                $sql .= " WHERE i.reported_by = ?"; 
                $params[] = $user['id'];
            } elseif ($user['role'] === 'hod') {
                if (!empty($user['department_id'])) {
                    $sql .= " WHERE i.student_dept_id = ?"; 
                    $params[] = $user['department_id'];
                } elseif (!empty($user['faculty_id'])) {
                    $sql .= " WHERE d.faculty_id = ?"; 
                    $params[] = $user['faculty_id'];
                }
            } elseif ($user['role'] === 'committee') {
                $sql .= " WHERE c.assigned_committee = ?"; 
                $params[] = $user['id'];
            }
            $sql .= " ORDER BY c.updated_at DESC";
            
            $stmt = $pdo->prepare($sql); 
            $stmt->execute($params);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'get_case_details':
            $user = requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $case_id = (int)($input['case_id'] ?? 0);
            if ($case_id <= 0) sendJson(['success' => false, 'message' => 'Invalid case ID'], 400);
            
            $case = $pdo->prepare("SELECT c.*, comm.full_name as committee_name, off.full_name as officer_name 
                                  FROM cases c 
                                  LEFT JOIN users comm ON c.assigned_committee = comm.id
                                  LEFT JOIN users off ON c.assigned_officer = off.id
                                  WHERE c.id = ?"); 
            $case->execute([$case_id]);
            $caseData = $case->fetch();
            
            if (!$caseData) sendJson(['success' => false, 'message' => 'Case not found'], 404);

            $incident = $pdo->prepare("SELECT i.*, c.code as course_code, c.title as course_title, d.name as department_name, d.faculty_id 
                                      FROM incidents i 
                                      JOIN courses c ON i.course_id = c.id 
                                      LEFT JOIN departments d ON i.student_dept_id = d.id
                                      WHERE i.id = ?"); 
            $incident->execute([$caseData['incident_id']]);
            $incidentData = $incident->fetch();

            // Authorization check
            if ($user['role'] === 'hod') {
                if (!empty($user['department_id']) && $incidentData['student_dept_id'] != $user['department_id']) {
                    sendJson(['success' => false, 'message' => 'Forbidden: case belongs to another department'], 403);
                }
                if (!empty($user['faculty_id']) && $incidentData['faculty_id'] != $user['faculty_id']) {
                    sendJson(['success' => false, 'message' => 'Forbidden: case belongs to another faculty'], 403);
                }
            } elseif ($user['role'] === 'committee') {
                if ($caseData['assigned_committee'] != $user['id']) {
                    sendJson(['success' => false, 'message' => 'Forbidden: you are not assigned to this case'], 403);
                }
            }
            
            $logs = $pdo->prepare("SELECT l.*, u.full_name as actor_name FROM case_logs l JOIN users u ON l.actor_id = u.id WHERE l.case_id = ? ORDER BY l.logged_at ASC");
            $logs->execute([$case_id]);

            $evidence = $pdo->prepare("SELECT id, incident_id, original_name, mime_type, file_size, file_hash, uploaded_at FROM incident_evidence WHERE incident_id = ?");
            $evidence->execute([$caseData['incident_id']]);

            $hearings = $pdo->prepare("SELECT * FROM hearings WHERE case_id = ? ORDER BY scheduled_date DESC");
            $hearings->execute([$case_id]);

            $sanction = $pdo->prepare("SELECT s.*, u.full_name as recorded_by_name FROM sanctions s JOIN users u ON s.recorded_by = u.id WHERE s.case_id = ?");
            $sanction->execute([$case_id]);
            
            $commentsStmt = $pdo->prepare("SELECT cc.*, u.full_name as author_name FROM case_comments cc JOIN users u ON cc.author_id = u.id WHERE cc.case_id = ? ORDER BY cc.created_at ASC"); 
            $commentsStmt->execute([$case_id]);
            
            $appealsStmt = $pdo->prepare("SELECT a.*, u.full_name as submitter_name, d.full_name as decider_name FROM appeals a JOIN users u ON a.submitted_by = u.id LEFT JOIN users d ON a.decided_by = d.id WHERE a.case_id = ? ORDER BY a.created_at DESC"); 
            $appealsStmt->execute([$case_id]);

            $lockersStmt = $pdo->prepare("SELECT el.*, u.full_name as custodian_name FROM evidence_lockers el LEFT JOIN users u ON el.custodian_id = u.id WHERE el.incident_id = ? ORDER BY el.created_at DESC");
            $lockersStmt->execute([$caseData['incident_id']]);

            sendJson(['success' => true, 'data' => [
                'case' => $caseData,
                'incident' => $incidentData,
                'logs' => $logs->fetchAll(),
                'evidence' => $evidence->fetchAll(),
                'hearings' => $hearings->fetchAll(),
                'sanction' => $sanction->fetch(),
                'comments' => $commentsStmt->fetchAll(),
                'appeals' => $appealsStmt->fetchAll(),
                'lockers' => $lockersStmt->fetchAll()
            ]]);
            break;

        case 'get_sanctions_report':
            requireRole(['admin', 'exam_officer', 'hod']);
            
            $sql = "SELECT c.id, c.case_no, c.resolution_summary, c.resolved_at, i.student_name, i.student_matric, i.offence_type, co.code as course_code, s.sanction_type, s.duration, s.description as sanction_description, s.effective_date 
                    FROM cases c 
                    JOIN incidents i ON c.incident_id = i.id 
                    JOIN courses co ON i.course_id = co.id
                    LEFT JOIN departments d ON i.student_dept_id = d.id
                    LEFT JOIN sanctions s ON c.id = s.case_id 
                    WHERE c.stage IN ('resolved', 'dismissed')";
            
            $params = [];
            if (!empty($input['academic_session'])) {
                $sql .= " AND i.academic_session = ?";
                $params[] = $input['academic_session'];
            }
            if (!empty($input['semester'])) {
                $sql .= " AND i.semester = ?";
                $params[] = $input['semester'];
            }
            if (!empty($input['department_id'])) {
                $sql .= " AND i.student_dept_id = ?";
                $params[] = $input['department_id'];
            } elseif (!empty($input['faculty_id'])) {
                $sql .= " AND d.faculty_id = ?";
                $params[] = $input['faculty_id'];
            }
            if (!empty($input['timeframe']) && $input['timeframe'] === 'recent') {
                $sql .= " AND c.resolved_at >= NOW() - INTERVAL '30 days'";
            }
            
            $sql .= " ORDER BY c.resolved_at DESC NULLS LAST, c.updated_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        // --- Authenticated Secure Evidence Gateway ---
        case 'download_evidence':
            $user = requireRole();
            $evidenceId = (int)($input['id'] ?? $_GET['id'] ?? 0);
            if ($evidenceId <= 0) sendJson(['success' => false, 'message' => 'Invalid evidence ID'], 400);

            $stmt = $pdo->prepare("SELECT e.*, i.student_dept_id, d.faculty_id, i.reported_by FROM incident_evidence e JOIN incidents i ON e.incident_id = i.id JOIN departments d ON i.student_dept_id = d.id WHERE e.id = ?");
            $stmt->execute([$evidenceId]);
            $ev = $stmt->fetch();
            if (!$ev) sendJson(['success' => false, 'message' => 'Evidence record not found'], 404);

            if ($user['role'] === 'invigilator' && $user['id'] != $ev['reported_by']) {
                sendJson(['success' => false, 'message' => 'Forbidden'], 403);
            }
            if ($user['role'] === 'hod') {
                if (!empty($user['department_id']) && $user['department_id'] != $ev['student_dept_id']) sendJson(['success' => false, 'message' => 'Forbidden'], 403);
                if (!empty($user['faculty_id']) && $user['faculty_id'] != $ev['faculty_id']) sendJson(['success' => false, 'message' => 'Forbidden'], 403);
            }

            $filePath = __DIR__ . '/uploads/' . $ev['stored_name'];
            if (!file_exists($filePath)) {
                sendJson(['success' => false, 'message' => 'Evidence file not found on disk'], 404);
            }

            auditLog($pdo, $user['id'], 'evidence_downloaded', 'incident_evidence', $evidenceId, ['filename' => $ev['original_name']]);

            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($ev['mime_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . addslashes($ev['original_name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;

        // --- Standard System Workflows ---
        case 'report_incident':
            $user = requireRole(['invigilator', 'exam_officer', 'admin']);
            validateCSRF($pdo);
            
            $evidence = ''; $uploaded_size = 0; $mime_type = ''; $original_name = ''; $file_hash = '';
            
            if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
                $rawName = $_FILES['evidence']['name'];
                $ext = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt'];
                
                if (!in_array($ext, $allowedExts, true)) {
                    sendJson(['success' => false, 'message' => 'Invalid evidence file type. Allowed: JPG, PNG, WEBP, PDF, DOC, DOCX, TXT.'], 422);
                }
                
                // Inspect real MIME type
                $detectedMime = 'application/octet-stream';
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detectedMime = finfo_file($finfo, $_FILES['evidence']['tmp_name']) ?: 'application/octet-stream';
                    finfo_close($finfo);
                }

                $allowedMimes = [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain', 'application/octet-stream'
                ];
                
                if (!in_array($detectedMime, $allowedMimes, true)) {
                    sendJson(['success' => false, 'message' => 'Uploaded evidence content does not match allowed file formats.'], 422);
                }
                
                $evidence = time() . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $original_name = basename($rawName);
                $mime_type = $detectedMime;
                $uploaded_size = (int)$_FILES['evidence']['size'];
                $file_hash = hash_file('sha256', $_FILES['evidence']['tmp_name']);
                
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                move_uploaded_file($_FILES['evidence']['tmp_name'], $uploadDir . '/' . $evidence);
            }
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO incidents (reference_no, reported_by, course_id, exam_date, exam_time, venue, semester, academic_session, offence_type, description, student_name, student_matric, student_dept_id, student_level) VALUES ('TEMP', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
            $stmt->execute([
                $user['id'], 
                $input['course_id'], 
                $input['exam_date'], 
                $input['exam_time'], 
                $input['venue'], 
                $input['semester'], 
                $input['academic_session'], 
                $input['offence_type'], 
                $input['description'], 
                $input['student_name'], 
                $input['student_matric'], 
                $input['student_dept_id'], 
                $input['student_level']
            ]);
            $inc_id = (int)$stmt->fetchColumn();
            
            $ref = 'INC-' . date('Y') . '-' . str_pad((string)$inc_id, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE incidents SET reference_no = ? WHERE id = ?")->execute([$ref, $inc_id]);
            
            if ($evidence) {
                $pdo->prepare("INSERT INTO incident_evidence (incident_id, uploaded_by, original_name, stored_name, file_path, mime_type, file_size, file_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$inc_id, $user['id'], $original_name, $evidence, 'uploads/' . $evidence, $mime_type, $uploaded_size, $file_hash]);
            }

            // Sync with students table
            $pdo->prepare("INSERT INTO students (matric_no, full_name, department_id, level) VALUES (?, ?, ?, ?) ON CONFLICT (matric_no) DO UPDATE SET full_name = EXCLUDED.full_name, level = EXCLUDED.level")
                ->execute([$input['student_matric'], $input['student_name'], $input['student_dept_id'], $input['student_level']]);
            
            $officers = $pdo->query("SELECT id FROM users WHERE role = 'exam_officer' AND is_active = TRUE")->fetchAll();
            foreach($officers as $off) {
                notify($pdo, (int)$off['id'], 'incident_reported', 'New Incident Alert', "Incident {$ref} was reported by {$user['full_name']}.");
                $pdo->prepare("INSERT INTO tasks (assigned_to, incident_id, title, description, task_type, priority, due_date) VALUES (?, ?, ?, ?, 'triage', 'normal', NOW() + INTERVAL '2 days')")
                    ->execute([(int)$off['id'], $inc_id, "Review {$ref}", 'Triage the newly submitted examination incident report.']);
            }
            
            $pdo->commit();
            auditLog($pdo, $user['id'], 'incident_reported', 'incidents', $inc_id, ['ref' => $ref, 'student_matric' => $input['student_matric']]);
            sendJson(['success' => true, 'reference_no' => $ref, 'message' => 'Incident reported successfully']);
            break;

        case 'open_case':
            $user = requireRole(['admin', 'exam_officer']);
            validateCSRF($pdo);
            $inc_id = (int)$input['incident_id'];
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO cases (case_no, incident_id, opened_by, stage) VALUES ('TEMP', ?, ?, 'reported') RETURNING id");
            $stmt->execute([$inc_id, $user['id']]);
            $case_id = (int)$stmt->fetchColumn();
            
            $case_no = 'CASE-' . date('Y') . '-' . str_pad((string)$case_id, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE cases SET case_no = ? WHERE id = ?")->execute([$case_no, $case_id]);
            $pdo->prepare("UPDATE incidents SET status = 'case_opened' WHERE id = ?")->execute([$inc_id]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, to_stage, note) VALUES (?, ?, 'case_opened', 'reported', 'Case initiated from incident review.')")->execute([$case_id, $user['id']]);
            
            $inc = $pdo->prepare("SELECT reported_by, reference_no FROM incidents WHERE id = ?"); 
            $inc->execute([$inc_id]); 
            $inc_data = $inc->fetch();
            if ($inc_data) {
                notify($pdo, (int)$inc_data['reported_by'], 'case_opened', 'Case Opened', "Case {$case_no} was opened for your incident report ({$inc_data['reference_no']}).");
            }
            
            $pdo->commit();
            auditLog($pdo, $user['id'], 'case_opened', 'cases', $case_id, ['case_no' => $case_no, 'incident_id' => $inc_id]);
            sendJson(['success' => true, 'case_no' => $case_no, 'message' => 'Case opened successfully']);
            break;

        case 'advance_case_stage':
            $user = requireRole(['admin', 'exam_officer', 'committee']);
            validateCSRF($pdo);
            $caseId = (int)$input['case_id'];
            $newStage = trim((string)$input['stage']);
            $allowedStages = ['reported', 'under_review', 'investigation', 'hearing', 'resolved', 'dismissed'];
            if (!in_array($newStage, $allowedStages, true)) {
                sendJson(['success' => false, 'message' => 'Invalid stage transition requested'], 422);
            }
            
            $stmt = $pdo->prepare("SELECT stage, case_no, assigned_committee FROM cases WHERE id = ?"); 
            $stmt->execute([$caseId]); 
            $case = $stmt->fetch();
            if (!$case) sendJson(['success' => false, 'message' => 'Case not found'], 404);
            
            $allowed = false;
            if (in_array($user['role'], ['admin', 'exam_officer'], true)) {
                $allowed = true;
            } elseif ($user['role'] === 'committee') {
                if ($case['assigned_committee'] == $user['id']) {
                    if (in_array($case['stage'], ['investigation', 'hearing'], true) && in_array($newStage, ['investigation', 'hearing', 'resolved', 'dismissed'], true)) {
                        $allowed = true;
                    }
                }
            }
            if (!$allowed) sendJson(['success' => false, 'message' => 'Unauthorized stage transition.'], 403);
            
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE cases SET stage = ?, updated_at = NOW() WHERE id = ?")->execute([$newStage, $caseId]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, from_stage, to_stage, note) VALUES (?, ?, 'stage_advanced', ?, ?, ?)")
                ->execute([$caseId, $user['id'], $case['stage'], $newStage, $input['note'] ?? 'Stage advanced.']);
            $pdo->commit();
            auditLog($pdo, $user['id'], 'case_stage_advanced', 'cases', $caseId, ['from' => $case['stage'], 'to' => $newStage]);
            sendJson(['success' => true, 'message' => "Case stage advanced to {$newStage}"]);
            break;

        case 'assign_committee':
            $user = requireRole(['admin', 'exam_officer']);
            validateCSRF($pdo);
            $caseId = (int)$input['case_id'];
            $committeeId = !empty($input['committee_id']) ? (int)$input['committee_id'] : null;

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE cases SET assigned_committee = ?, updated_at = NOW() WHERE id = ?")->execute([$committeeId, $caseId]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'committee_assigned', ?)")
                ->execute([$caseId, $user['id'], $committeeId ? 'Committee member assigned.' : 'Committee assignment cleared.']);
            
            if ($committeeId) {
                // Also link in panel members junction
                $pdo->prepare("INSERT INTO case_panel_members (case_id, user_id, panel_role) VALUES (?, ?, 'member') ON CONFLICT (case_id, user_id) DO NOTHING")
                    ->execute([$caseId, $committeeId]);

                $case_no = $pdo->prepare("SELECT case_no FROM cases WHERE id = ?"); 
                $case_no->execute([$caseId]);
                notify($pdo, $committeeId, 'system', 'Case Assignment', "You have been assigned to handle {$case_no->fetchColumn()}.");
            }
            
            $pdo->commit();
            auditLog($pdo, $user['id'], 'committee_assigned', 'cases', $caseId, ['committee_id' => $committeeId]);
            sendJson(['success' => true, 'message' => 'Committee assignment updated successfully']);
            break;

        case 'add_case_note':
            $user = requireRole(['hod', 'committee', 'exam_officer', 'admin']);
            validateCSRF($pdo);
            $caseId = (int)$input['case_id'];
            $note = trim((string)($input['note'] ?? ''));
            if ($note === '') sendJson(['success' => false, 'message' => 'Note cannot be empty'], 422);

            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'note_added', ?)")
                ->execute([$caseId, $user['id'], $note]);
            auditLog($pdo, $user['id'], 'case_note_added', 'cases', $caseId);
            sendJson(['success' => true, 'message' => 'Note added successfully']);
            break;

        case 'schedule_hearing':
            $user = requireRole(['committee', 'admin', 'exam_officer']);
            validateCSRF($pdo);
            $caseId = (int)$input['case_id'];
            $date = trim((string)($input['date'] ?? ''));
            $venue = trim((string)($input['venue'] ?? ''));
            if ($date === '' || $venue === '') sendJson(['success' => false, 'message' => 'Hearing date and venue are required'], 422);

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO hearings (case_id, scheduled_by, scheduled_date, venue) VALUES (?, ?, ?, ?)")->execute([$caseId, $user['id'], $date, $venue]);
            $pdo->prepare("UPDATE cases SET stage = 'hearing', updated_at = NOW() WHERE id = ?")->execute([$caseId]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, to_stage, note) VALUES (?, ?, 'hearing_scheduled', 'hearing', ?)")
                ->execute([$caseId, $user['id'], "Hearing officially scheduled at {$venue} on {$date}"]);
            $pdo->commit();
            auditLog($pdo, $user['id'], 'hearing_scheduled', 'cases', $caseId, ['venue' => $venue, 'date' => $date]);
            sendJson(['success' => true, 'message' => 'Hearing scheduled successfully']);
            break;

        case 'resolve_case':
            $user = requireRole(['committee', 'admin']);
            validateCSRF($pdo);
            $caseId = (int)$input['case_id'];
            $status = trim((string)($input['status'] ?? 'resolved'));
            if (!in_array($status, ['resolved', 'dismissed'], true)) sendJson(['success' => false, 'message' => 'Invalid resolution status'], 422);

            $stmt = $pdo->prepare("SELECT * FROM cases WHERE id = ?");
            $stmt->execute([$caseId]);
            $case = $stmt->fetch();
            if (!$case) sendJson(['success' => false, 'message' => 'Case not found'], 404);

            if ($user['role'] === 'committee' && $case['assigned_committee'] != $user['id']) {
                sendJson(['success' => false, 'message' => 'Forbidden: only assigned committee member or admin can resolve this case'], 403);
            }
            
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE cases SET stage = ?, resolution_summary = ?, resolved_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([$status, $input['resolution_summary'], $caseId]);
            
            if ($status === 'resolved') {
                $sanctionType = $input['sanction_type'] ?? 'warning';
                $pdo->prepare("INSERT INTO sanctions (case_id, recorded_by, sanction_type, duration, description, effective_date) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (case_id) DO UPDATE SET sanction_type = EXCLUDED.sanction_type, duration = EXCLUDED.duration, description = EXCLUDED.description, effective_date = EXCLUDED.effective_date")
                    ->execute([$caseId, $user['id'], $sanctionType, $input['duration'] ?? null, $input['description'] ?? 'Case resolved with official sanction.', $input['effective_date'] ?? date('Y-m-d')]);
            }
            
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, to_stage, note) VALUES (?, ?, ?, ?, ?)")
                ->execute([$caseId, $user['id'], $status === 'resolved' ? 'case_resolved' : 'case_dismissed', $status, $input['resolution_summary']]);
            $pdo->commit();
            
            auditLog($pdo, $user['id'], $status === 'resolved' ? 'case_resolved' : 'case_dismissed', 'cases', $caseId, ['status' => $status]);
            sendJson(['success' => true, 'message' => 'Case officially closed and recorded']);
            break;

        case 'get_lookups':
            $activeAlertsCount = 0;
            try {
                $activeAlertsCount = (int)$pdo->query("SELECT COUNT(*) FROM hall_security_alerts WHERE status = 'active'")->fetchColumn();
            } catch (Throwable $e) {}

            $schedules = [];
            try {
                $schedules = $pdo->query("SELECT es.*, c.code as course_code, c.title as course_title, d.name as department_name, f.name as faculty_name FROM exam_schedules es LEFT JOIN courses c ON es.course_id = c.id LEFT JOIN departments d ON es.department_id = d.id LEFT JOIN faculties f ON es.faculty_id = f.id ORDER BY es.exam_date DESC, es.session_time ASC LIMIT 60")->fetchAll();
            } catch (Throwable $e) {}

            sendJson(['success' => true, 'data' => [
                'departments' => $pdo->query("SELECT id, name, code, faculty_id FROM departments ORDER BY name")->fetchAll(), 
                'faculties' => $pdo->query("SELECT id, name, code FROM faculties ORDER BY name")->fetchAll(), 
                'courses' => $pdo->query("SELECT id, code, title, department_id, level, semester FROM courses ORDER BY code")->fetchAll(),
                'committee_members' => $pdo->query("SELECT id, full_name, staff_id FROM users WHERE role = 'committee' AND is_active = TRUE ORDER BY full_name")->fetchAll(),
                'staff_members' => $pdo->query("SELECT id, full_name, staff_id, role FROM users WHERE is_active = TRUE ORDER BY full_name")->fetchAll(),
                'sessions' => $pdo->query("SELECT * FROM academic_sessions ORDER BY start_date DESC")->fetchAll(),
                'venues' => $pdo->query("SELECT * FROM venues WHERE is_active = TRUE ORDER BY name")->fetchAll(),
                'students' => $pdo->query("SELECT s.matric_no, s.full_name, s.department_id, s.level, d.name as department_name FROM students s LEFT JOIN departments d ON s.department_id = d.id ORDER BY s.matric_no LIMIT 100")->fetchAll(),
                'schedules' => $schedules,
                'active_alerts_count' => $activeAlertsCount
            ]]);
            break;

        // --- BUK EIMS Workspace & Productivity ---
        case 'get_workspace_data':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $uid = (int)$user['id'];
            $role = $user['role'];
            $stats = [];
            if ($role === 'admin' || $role === 'exam_officer') {
                $stats['incidents'] = (int)$pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
                $stats['open_cases'] = (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE stage NOT IN ('resolved','dismissed')")->fetchColumn();
                $stats['resolved_cases'] = (int)$pdo->query("SELECT COUNT(*) FROM cases WHERE stage IN ('resolved','dismissed')")->fetchColumn();
                $stats['overdue_tasks'] = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('open','in_progress') AND due_date < NOW()")->fetchColumn();
            } elseif ($role === 'invigilator') {
                $s = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reported_by = ?"); $s->execute([$uid]); $stats['incidents'] = (int)$s->fetchColumn();
                $s = $pdo->prepare("SELECT COUNT(*) FROM incident_drafts WHERE owner_id = ?"); $s->execute([$uid]); $stats['drafts'] = (int)$s->fetchColumn();
                $s = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reported_by = ? AND status = 'pending'"); $s->execute([$uid]); $stats['pending'] = (int)$s->fetchColumn();
                $stats['open_cases'] = 0;
            } else {
                $s = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('open','in_progress')"); $s->execute([$uid]); $stats['open_tasks'] = (int)$s->fetchColumn();
                $s = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE assigned_committee = ? AND stage NOT IN ('resolved','dismissed')"); $s->execute([$uid]); $stats['open_cases'] = (int)$s->fetchColumn();
                $stats['incidents'] = 0;
            }
            $stmt = $pdo->prepare("SELECT id, title, description, task_type, priority, status, due_date, case_id, incident_id FROM tasks WHERE assigned_to = ? AND status IN ('open','in_progress') ORDER BY due_date NULLS LAST, CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END LIMIT 12");
            $stmt->execute([$uid]); $tasks = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT id, type, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
            $stmt->execute([$uid]); $notifications = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT id, title, body, audience_role, created_at FROM announcements WHERE is_published = TRUE AND (audience_role IS NULL OR audience_role = '' OR audience_role = ?) AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$role]); $announcements = $stmt->fetchAll();
            
            $hearingsSql = "SELECT h.id, h.scheduled_date, h.venue, h.status, c.id as case_id, c.case_no, i.student_name FROM hearings h JOIN cases c ON h.case_id = c.id JOIN incidents i ON c.incident_id = i.id WHERE h.scheduled_date >= NOW() - INTERVAL '1 day'";
            $hearingsParams = [];
            if ($role === 'committee') { 
                $hearingsSql .= " AND c.assigned_committee = ?"; 
                $hearingsParams[] = $uid; 
            }
            $hearingsSql .= " ORDER BY h.scheduled_date ASC LIMIT 10";
            $stmt = $pdo->prepare($hearingsSql); 
            $stmt->execute($hearingsParams); 
            $hearings = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT l.id, l.case_id, l.action, l.note, l.logged_at, u.full_name as actor_name, c.case_no FROM case_logs l JOIN users u ON l.actor_id = u.id JOIN cases c ON l.case_id = c.id ORDER BY l.logged_at DESC LIMIT 10"); 
            $stmt->execute(); 
            $activity = $stmt->fetchAll();
            sendJson(['success' => true, 'data' => compact('stats','tasks','notifications','announcements','hearings','activity')]);
            break;

        case 'get_global_search':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $q = trim((string)($input['q'] ?? ''));
            if (strlen($q) < 2) sendJson(['success' => true, 'data' => []]);
            $like = '%' . $q . '%';
            
            $sql = "SELECT 'incident' as result_type, i.id, i.reference_no as ref, i.student_name as title, i.status as meta, i.created_at FROM incidents i JOIN users u ON i.reported_by = u.id WHERE i.reference_no ILIKE ? OR i.student_name ILIKE ? OR i.student_matric ILIKE ? OR i.venue ILIKE ? OR u.full_name ILIKE ? ORDER BY i.created_at DESC LIMIT 12";
            $stmt = $pdo->prepare($sql); $stmt->execute([$like,$like,$like,$like,$like]); $rows = $stmt->fetchAll();
            
            $sql = "SELECT 'case' as result_type, c.id, c.case_no as ref, i.student_name as title, c.stage as meta, c.updated_at as created_at FROM cases c JOIN incidents i ON c.incident_id = i.id WHERE c.case_no ILIKE ? OR i.student_name ILIKE ? OR i.student_matric ILIKE ? ORDER BY c.updated_at DESC LIMIT 12";
            $stmt = $pdo->prepare($sql); $stmt->execute([$like,$like,$like]); $rows = array_merge($rows, $stmt->fetchAll());
            sendJson(['success' => true, 'data' => array_slice($rows, 0, 20)]);
            break;

        case 'get_tasks':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $stmt = $pdo->prepare("SELECT t.*, c.case_no, i.reference_no FROM tasks t LEFT JOIN cases c ON t.case_id = c.id LEFT JOIN incidents i ON t.incident_id = i.id WHERE t.assigned_to = ? ORDER BY CASE t.status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END, t.due_date NULLS LAST, t.created_at DESC");
            $stmt->execute([(int)$user['id']]); 
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'update_task_status':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']); 
            validateCSRF($pdo);
            $status = $input['status'] ?? 'in_progress';
            if (!in_array($status, ['open','in_progress','completed','dismissed'], true)) sendJson(['success' => false, 'message' => 'Invalid task status'], 422);
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END, updated_at = NOW() WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$status, $status, $input['task_id'], $user['id']]); 
            sendJson(['success' => true, 'message' => 'Task updated']);
            break;

        case 'mark_notification_read':
            $user = requireRole(); 
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?"); 
            $stmt->execute([$input['id'], $user['id']]); 
            sendJson(['success' => true]);
            break;

        case 'mark_all_notifications_read':
            $user = requireRole(); 
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?"); 
            $stmt->execute([$user['id']]); 
            sendJson(['success' => true]);
            break;

        case 'get_case_timeline':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $caseId = (int)($input['case_id'] ?? 0);
            if ($caseId <= 0) sendJson(['success' => false, 'message' => 'Invalid case ID'], 400);

            $stmt = $pdo->prepare("SELECT c.*, i.reference_no, i.student_name, i.student_matric, i.description, i.offence_type, i.exam_date, i.venue, co.code as course_code, comm.full_name as committee_name, off.full_name as officer_name 
                                  FROM cases c 
                                  JOIN incidents i ON c.incident_id = i.id 
                                  JOIN courses co ON i.course_id = co.id 
                                  LEFT JOIN users comm ON c.assigned_committee = comm.id
                                  LEFT JOIN users off ON c.assigned_officer = off.id
                                  WHERE c.id = ?"); 
            $stmt->execute([$caseId]); 
            $case = $stmt->fetch();
            if (!$case) sendJson(['success' => false, 'message' => 'Case not found'], 404);
            
            $stmt = $pdo->prepare("SELECT l.*, u.full_name as actor_name FROM case_logs l JOIN users u ON l.actor_id = u.id WHERE l.case_id = ? ORDER BY l.logged_at ASC"); 
            $stmt->execute([$caseId]); $logs = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT cc.*, u.full_name as author_name FROM case_comments cc JOIN users u ON cc.author_id = u.id WHERE cc.case_id = ? ORDER BY cc.created_at ASC"); 
            $stmt->execute([$caseId]); $comments = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT a.*, u.full_name as submitter_name, d.full_name as decider_name FROM appeals a JOIN users u ON a.submitted_by = u.id LEFT JOIN users d ON a.decided_by = d.id WHERE a.case_id = ? ORDER BY a.created_at DESC"); 
            $stmt->execute([$caseId]); $appeals = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT * FROM hearings WHERE case_id = ? ORDER BY scheduled_date DESC"); 
            $stmt->execute([$caseId]); $hearings = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT id, incident_id, original_name, mime_type, file_size, file_hash, uploaded_at FROM incident_evidence WHERE incident_id = ?");
            $stmt->execute([$case['incident_id']]); $evidence = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT s.*, u.full_name as recorded_by_name FROM sanctions s JOIN users u ON s.recorded_by = u.id WHERE s.case_id = ?");
            $stmt->execute([$caseId]); $sanction = $stmt->fetch();

            sendJson(['success' => true, 'data' => compact('case','logs','comments','appeals','hearings','evidence','sanction')]);
            break;

        case 'add_case_comment':
            $user = requireRole(['admin', 'exam_officer', 'hod', 'committee']); 
            validateCSRF($pdo);
            $caseId = (int)$input['case_id'];
            $body = trim((string)$input['body']);
            if ($body === '') sendJson(['success' => false, 'message' => 'Comment body cannot be empty'], 422);

            $stmt = $pdo->prepare("INSERT INTO case_comments (case_id, author_id, body, visibility) VALUES (?, ?, ?, ?)"); 
            $stmt->execute([$caseId, $user['id'], $body, $input['visibility'] ?? 'internal']);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'note_added', ?)")
                ->execute([$caseId, $user['id'], $body]); 
            sendJson(['success' => true, 'message' => 'Comment added']);
            break;

        case 'submit_appeal':
            $user = requireRole(['admin', 'exam_officer', 'hod', 'committee', 'invigilator']); 
            validateCSRF($pdo);
            $grounds = trim((string)$input['grounds']);
            if ($grounds === '') sendJson(['success' => false, 'message' => 'Grounds for appeal are required'], 422);

            $stmt = $pdo->prepare("INSERT INTO appeals (case_id, submitted_by, grounds) VALUES (?, ?, ?)"); 
            $stmt->execute([(int)$input['case_id'], $user['id'], $grounds]);
            auditLog($pdo, $user['id'], 'appeal_submitted', 'cases', (int)$input['case_id']);
            sendJson(['success' => true, 'message' => 'Appeal submitted successfully']);
            break;

        case 'review_appeal':
            $user = requireRole(['admin', 'exam_officer', 'committee']); 
            validateCSRF($pdo);
            $status = $input['status'] ?? 'under_review'; 
            if (!in_array($status, ['under_review','approved','rejected','withdrawn'], true)) sendJson(['success'=>false,'message'=>'Invalid appeal status'],422);
            $stmt = $pdo->prepare("UPDATE appeals SET status = ?, decision = ?, decided_by = ?, decided_at = CASE WHEN ? IN ('approved','rejected') THEN NOW() ELSE NULL END, updated_at = NOW() WHERE id = ?"); 
            $stmt->execute([$status, $input['decision'] ?? null, $user['id'], $status, $input['appeal_id']]); 
            auditLog($pdo, $user['id'], 'appeal_reviewed', 'appeals', (int)$input['appeal_id'], ['status' => $status]);
            sendJson(['success' => true, 'message' => 'Appeal decision updated']);
            break;

        case 'get_calendar':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $stmt = $pdo->prepare("SELECT h.id, 'hearing' as event_type, h.scheduled_date as starts_at, h.venue as location, c.case_no as title, h.status FROM hearings h JOIN cases c ON h.case_id = c.id WHERE h.scheduled_date >= NOW() - INTERVAL '30 days' ORDER BY h.scheduled_date ASC LIMIT 50"); 
            $stmt->execute(); 
            $events = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT id, 'task' as event_type, due_date as starts_at, NULL as location, title, status FROM tasks WHERE assigned_to = ? AND due_date IS NOT NULL ORDER BY due_date ASC LIMIT 50"); 
            $stmt->execute([$user['id']]); 
            $events = array_merge($events, $stmt->fetchAll());
            
            $stmt = $pdo->query("SELECT id, 'session' as event_type, start_date as starts_at, NULL as location, CONCAT(name, ' — ', semester, ' Semester') as title, CASE WHEN is_active THEN 'active' ELSE 'planned' END as status FROM academic_sessions ORDER BY start_date DESC LIMIT 20"); 
            $events = array_merge($events, $stmt->fetchAll());
            sendJson(['success' => true, 'data' => $events]);
            break;

        case 'get_drafts':
            $user = requireRole(['invigilator', 'exam_officer', 'admin']); 
            $stmt = $pdo->prepare("SELECT id, title, progress, created_at, updated_at, payload FROM incident_drafts WHERE owner_id = ? ORDER BY updated_at DESC"); 
            $stmt->execute([$user['id']]); 
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'save_draft':
            $user = requireRole(['invigilator', 'exam_officer', 'admin']); 
            validateCSRF($pdo); 
            $payload = json_encode($input['payload'] ?? []);
            if (!empty($input['draft_id'])) { 
                $stmt = $pdo->prepare("UPDATE incident_drafts SET title = ?, payload = ?::jsonb, progress = ?, updated_at = NOW() WHERE id = ? AND owner_id = ?"); 
                $stmt->execute([$input['title'] ?? 'Untitled incident draft', $payload, $input['progress'] ?? 0, $input['draft_id'], $user['id']]); 
                $id = (int)$input['draft_id']; 
            } else { 
                $stmt = $pdo->prepare("INSERT INTO incident_drafts (owner_id, title, payload, progress) VALUES (?, ?, ?::jsonb, ?) RETURNING id"); 
                $stmt->execute([$user['id'], $input['title'] ?? 'Untitled incident draft', $payload, $input['progress'] ?? 0]); 
                $id = (int)$stmt->fetchColumn(); 
            }
            sendJson(['success' => true, 'id' => $id, 'message' => 'Draft saved successfully']);
            break;

        case 'delete_draft':
            $user = requireRole(['invigilator', 'exam_officer', 'admin']); 
            validateCSRF($pdo); 
            $stmt = $pdo->prepare("DELETE FROM incident_drafts WHERE id = ? AND owner_id = ?"); 
            $stmt->execute([$input['draft_id'], $user['id']]); 
            sendJson(['success' => true, 'message' => 'Draft deleted']);
            break;

        case 'get_preferences':
            $user = requireRole(); 
            $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?"); 
            $stmt->execute([$user['id']]); 
            $pref = $stmt->fetch(); 
            sendJson(['success' => true, 'data' => $pref ?: ['default_view'=>'overview','notify_in_app'=>true,'notify_email'=>true,'compact_mode'=>false]]);
            break;

        case 'save_preferences':
            $user = requireRole(); 
            validateCSRF($pdo); 
            $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, default_view, notify_in_app, notify_email, compact_mode) VALUES (?, ?, ?, ?, ?) ON CONFLICT (user_id) DO UPDATE SET default_view=EXCLUDED.default_view, notify_in_app=EXCLUDED.notify_in_app, notify_email=EXCLUDED.notify_email, compact_mode=EXCLUDED.compact_mode, updated_at=NOW()"); 
            $stmt->execute([$user['id'], $input['default_view'] ?? 'overview', !empty($input['notify_in_app']) ? 1 : 0, !empty($input['notify_email']) ? 1 : 0, !empty($input['compact_mode']) ? 1 : 0]); 
            sendJson(['success' => true, 'message' => 'Preferences saved']);
            break;

        case 'create_announcement':
            $user = requireRole(['admin']); 
            validateCSRF($pdo); 
            $stmt = $pdo->prepare("INSERT INTO announcements (title, body, audience_role, created_by) VALUES (?, ?, ?, ?)"); 
            $stmt->execute([$input['title'], $input['body'], $input['audience_role'] ?: null, $user['id']]); 
            auditLog($pdo, $user['id'], 'announcement_created', 'announcements');
            sendJson(['success' => true, 'message' => 'Announcement published']);
            break;

        case 'save_academic_session':
            $user = requireRole(['admin']); 
            validateCSRF($pdo); 
            $pdo->beginTransaction(); 
            if (!empty($input['is_active'])) {
                $pdo->exec("UPDATE academic_sessions SET is_active = FALSE"); 
            }
            $stmt = $pdo->prepare("INSERT INTO academic_sessions (name, semester, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?) ON CONFLICT (name, semester) DO UPDATE SET start_date=EXCLUDED.start_date, end_date=EXCLUDED.end_date, is_active=EXCLUDED.is_active"); 
            $stmt->execute([$input['name'], $input['semester'], $input['start_date'], $input['end_date'], !empty($input['is_active'])]); 
            $pdo->commit(); 
            auditLog($pdo, $user['id'], 'academic_session_saved', 'academic_sessions', null, ['name' => $input['name'], 'semester' => $input['semester']]);
            sendJson(['success' => true, 'message' => 'Academic session saved']);
            break;

        case 'review_incident':
            $user = requireRole(['admin', 'exam_officer']); 
            validateCSRF($pdo);
            $decision = $input['decision'] ?? 'reviewed';
            $allowed = ['reviewed','returned','duplicate','closed','escalated'];
            if (!in_array($decision, $allowed, true)) sendJson(['success' => false, 'message' => 'Invalid triage decision'], 422);
            $status = in_array($decision, ['closed','duplicate'], true) ? 'closed' : 'reviewed';
            
            $stmt = $pdo->prepare("UPDATE incidents SET status = ?, updated_at = NOW() WHERE id = ?"); 
            $stmt->execute([$status, $input['incident_id']]);
            
            $refStmt = $pdo->prepare("SELECT reference_no, reported_by FROM incidents WHERE id = ?"); 
            $refStmt->execute([$input['incident_id']]); 
            $inc = $refStmt->fetch();
            if ($inc) { 
                notify($pdo, (int)$inc['reported_by'], 'system', 'Incident review updated', "{$inc['reference_no']} was marked {$decision}." . (!empty($input['note']) ? ' ' . $input['note'] : '')); 
            }
            auditLog($pdo, $user['id'], 'incident_triaged', 'incidents', (int)$input['incident_id'], ['decision' => $decision]);
            sendJson(['success' => true, 'message' => 'Triage decision saved']);
            break;

        case 'assign_case_officer':
            $user = requireRole(['admin','exam_officer']); 
            validateCSRF($pdo);
            $officerId = !empty($input['officer_id']) ? (int)$input['officer_id'] : null;
            $caseId = (int)$input['case_id'];

            $stmt = $pdo->prepare("UPDATE cases SET assigned_officer = ?, updated_at = NOW() WHERE id = ?"); 
            $stmt->execute([$officerId, $caseId]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'officer_assigned', ?)")
                ->execute([$caseId, $user['id'], $officerId ? 'Case officer assigned.' : 'Case officer assignment cleared.']);

            if ($officerId) { 
                $caseStmt = $pdo->prepare("SELECT case_no FROM cases WHERE id = ?"); 
                $caseStmt->execute([$caseId]); 
                notify($pdo, $officerId, 'system', 'Case assignment', "You have been assigned {$caseStmt->fetchColumn()}."); 
            }
            auditLog($pdo, $user['id'], 'case_officer_assigned', 'cases', $caseId, ['officer_id' => $officerId]);
            sendJson(['success' => true, 'message' => 'Case officer assignment updated']);
            break;

        // --- Senate Working Papers & Ratification Engine ---
        case 'get_senate_memos':
            $user = requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $session = trim((string)($_GET['session'] ?? ''));
            $status = trim((string)($_GET['status'] ?? ''));
            $sql = "SELECT sm.*, u.full_name as created_by_name,
                    (SELECT COUNT(*) FROM cases c WHERE c.senate_memo_id = sm.id) as case_count
                    FROM senate_memos sm
                    LEFT JOIN users u ON sm.created_by = u.id
                    WHERE 1=1";
            $params = [];
            if ($session !== '') {
                $sql .= " AND sm.academic_session = ?";
                $params[] = $session;
            }
            if ($status !== '') {
                $sql .= " AND sm.status = ?";
                $params[] = $status;
            }
            $sql .= " ORDER BY sm.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'create_senate_memo':
            $user = requireRole(['admin', 'exam_officer']);
            validateCSRF($pdo);
            $academicSession = trim((string)($input['academic_session'] ?? ''));
            $meetingNumber = trim((string)($input['meeting_number'] ?? ''));
            $title = trim((string)($input['title'] ?? ''));
            $caseIds = $input['case_ids'] ?? [];

            if ($academicSession === '' || $title === '') {
                sendJson(['success' => false, 'message' => 'Academic session and title are required'], 422);
            }
            if (!is_array($caseIds) || empty($caseIds)) {
                sendJson(['success' => false, 'message' => 'Please select at least one resolved case to compile into this memo'], 422);
            }

            $caseIds = array_map('intval', $caseIds);
            $count = (int)$pdo->query("SELECT COUNT(*) FROM senate_memos WHERE academic_session = " . $pdo->quote($academicSession))->fetchColumn() + 1;
            $memoNumber = "BUK/SEN/" . str_replace('/', '-', $academicSession) . "/M" . str_pad((string)$count, 3, '0', STR_PAD_LEFT);

            $inClause = implode(',', $caseIds);
            $statsStmt = $pdo->query("SELECT s.sanction_type, COUNT(*) as qty 
                                      FROM sanctions s 
                                      WHERE s.case_id IN ($inClause) 
                                      GROUP BY s.sanction_type");
            $bySanction = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $facStmt = $pdo->query("SELECT f.name, COUNT(*) as qty 
                                    FROM cases c 
                                    JOIN incidents i ON c.incident_id = i.id 
                                    JOIN departments d ON i.student_dept_id = d.id 
                                    JOIN faculties f ON d.faculty_id = f.id 
                                    WHERE c.id IN ($inClause) 
                                    GROUP BY f.name");
            $byFaculty = $facStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $summaryStats = [
                'total_cases' => count($caseIds),
                'by_sanction' => $bySanction,
                'by_faculty' => $byFaculty,
                'compiled_at' => date('Y-m-d H:i:s')
            ];

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO senate_memos (memo_number, academic_session, meeting_number, title, status, summary_statistics, created_by) VALUES (?, ?, ?, ?, 'draft', ?::jsonb, ?) RETURNING id");
            $stmt->execute([$memoNumber, $academicSession, $meetingNumber ?: null, $title, json_encode($summaryStats), $user['id']]);
            $memoId = $stmt->fetchColumn();

            $upStmt = $pdo->prepare("UPDATE cases SET senate_memo_id = ?, updated_at = NOW() WHERE id = ?");
            foreach ($caseIds as $cid) {
                $upStmt->execute([$memoId, $cid]);
                $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'senate_memo_compiled', ?)")
                    ->execute([$cid, $user['id'], "Compiled into Senate Working Paper: {$memoNumber}"]);
            }
            $pdo->commit();

            auditLog($pdo, $user['id'], 'senate_memo_created', 'senate_memos', (int)$memoId, ['memo_number' => $memoNumber, 'cases_count' => count($caseIds)]);
            sendJson(['success' => true, 'message' => "Senate Working Paper {$memoNumber} compiled successfully", 'data' => ['id' => $memoId, 'memo_number' => $memoNumber]]);
            break;

        case 'get_senate_memo_details':
            $user = requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $id = (int)($input['memo_id'] ?? $_GET['memo_id'] ?? 0);
            if ($id <= 0) sendJson(['success' => false, 'message' => 'Invalid memo ID'], 400);

            $stmt = $pdo->prepare("SELECT sm.*, u.full_name as created_by_name FROM senate_memos sm LEFT JOIN users u ON sm.created_by = u.id WHERE sm.id = ?");
            $stmt->execute([$id]);
            $memo = $stmt->fetch();
            if (!$memo) sendJson(['success' => false, 'message' => 'Senate Memo not found'], 404);

            if (is_string($memo['summary_statistics'])) {
                $memo['summary_statistics'] = json_decode($memo['summary_statistics'], true);
            }

            $cStmt = $pdo->prepare("SELECT c.id, c.case_no, c.stage, c.case_tier, c.result_status, c.resolution_summary,
                                    i.reference_no, i.student_name, i.student_matric, i.offense_category, i.incident_summary,
                                    d.name as department_name, f.name as faculty_name, cr.code as course_code,
                                    s.sanction_type, s.duration_semesters, s.effective_session, s.notes as sanction_notes
                                    FROM cases c
                                    JOIN incidents i ON c.incident_id = i.id
                                    LEFT JOIN departments d ON i.student_dept_id = d.id
                                    LEFT JOIN faculties f ON d.faculty_id = f.id
                                    LEFT JOIN courses cr ON i.course_id = cr.id
                                    LEFT JOIN sanctions s ON s.case_id = c.id
                                    WHERE c.senate_memo_id = ?
                                    ORDER BY f.name, d.name, c.case_no");
            $cStmt->execute([$id]);
            $cases = $cStmt->fetchAll();

            sendJson(['success' => true, 'data' => ['memo' => $memo, 'cases' => $cases]]);
            break;

        case 'update_senate_ratification':
            $user = requireRole(['admin', 'exam_officer']);
            validateCSRF($pdo);
            $id = (int)$input['memo_id'];
            $status = trim((string)($input['status'] ?? ''));
            $meetingNumber = trim((string)($input['meeting_number'] ?? ''));
            $ratificationDate = !empty($input['ratification_date']) ? $input['ratification_date'] : date('Y-m-d');
            $notes = trim((string)($input['ratification_notes'] ?? ''));

            $validStatuses = ['draft', 'recommended_to_senate', 'senate_approved', 'senate_amended', 'senate_rejected'];
            if (!in_array($status, $validStatuses, true)) {
                sendJson(['success' => false, 'message' => 'Invalid ratification status'], 422);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE senate_memos SET status = ?, meeting_number = COALESCE(?, meeting_number), ratification_date = ?, ratification_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $meetingNumber ?: null, $status !== 'draft' ? $ratificationDate : null, $notes ?: null, $id]);

            if ($status === 'senate_approved') {
                $cStmt = $pdo->prepare("SELECT c.id, s.sanction_type, s.duration_semesters 
                                        FROM cases c 
                                        LEFT JOIN sanctions s ON s.case_id = c.id 
                                        WHERE c.senate_memo_id = ?");
                $cStmt->execute([$id]);
                $linked = $cStmt->fetchAll();
                foreach ($linked as $row) {
                    $rStatus = 'nullified';
                    if ($row['sanction_type'] === 'warning') {
                        $rStatus = 'released';
                    } elseif ($row['sanction_type'] === 'suspension') {
                        $rStatus = 'held';
                    }
                    $pdo->prepare("UPDATE cases SET result_status = ?, updated_at = NOW() WHERE id = ?")->execute([$rStatus, $row['id']]);
                    $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'senate_ratified', ?)")
                        ->execute([$row['id'], $user['id'], "Senate Ratification (Meeting {$meetingNumber}): Result status updated to {$rStatus}."]);
                }
            }
            $pdo->commit();

            auditLog($pdo, $user['id'], 'senate_memo_ratified', 'senate_memos', $id, ['status' => $status, 'meeting_number' => $meetingNumber]);
            sendJson(['success' => true, 'message' => "Senate memo ratification status updated to " . str_replace('_', ' ', $status)]);
            break;

        // --- Multi-Tier Disciplinary Jurisdiction & Result Status ---
        case 'escalate_case_tier':
            $user = requireRole(['admin', 'exam_officer', 'hod', 'committee']);
            validateCSRF($pdo);
            $caseId = (int)$input['case_id'];
            $newTier = trim((string)($input['case_tier'] ?? ''));
            $transferNotes = trim((string)($input['transfer_notes'] ?? ''));

            $validTiers = ['departmental', 'faculty', 'central'];
            if (!in_array($newTier, $validTiers, true)) {
                sendJson(['success' => false, 'message' => 'Invalid case tier'], 422);
            }

            $cStmt = $pdo->prepare("SELECT c.id, c.case_no, c.case_tier, i.reference_no FROM cases c JOIN incidents i ON c.incident_id = i.id WHERE c.id = ?");
            $cStmt->execute([$caseId]);
            $c = $cStmt->fetch();
            if (!$c) sendJson(['success' => false, 'message' => 'Case not found'], 404);

            $oldTier = $c['case_tier'] ?? 'central';
            $pdo->prepare("UPDATE cases SET case_tier = ?, updated_at = NOW() WHERE id = ?")->execute([$newTier, $caseId]);

            $logNote = "Jurisdiction transferred from " . strtoupper($oldTier) . " to " . strtoupper($newTier) . ($transferNotes ? ": {$transferNotes}" : "");
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'tier_escalated', ?)")
                ->execute([$caseId, $user['id'], $logNote]);

            $officers = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'exam_officer') AND is_active = TRUE")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($officers as $offId) {
                notify($pdo, (int)$offId, 'system', "Case Escalated ({$c['case_no']})", "Case {$c['case_no']} was escalated to {$newTier} panel.");
            }

            auditLog($pdo, $user['id'], 'case_tier_escalated', 'cases', $caseId, ['from' => $oldTier, 'to' => $newTier, 'notes' => $transferNotes]);
            sendJson(['success' => true, 'message' => "Case jurisdiction transferred to " . ucfirst($newTier) . " Panel"]);
            break;

        case 'update_result_status':
            $user = requireRole(['admin', 'exam_officer']);
            validateCSRF($pdo);
            $caseId = (int)$input['case_id'];
            $status = trim((string)($input['result_status'] ?? 'normal'));
            $lockoutStart = !empty($input['lockout_start_date']) ? $input['lockout_start_date'] : null;
            $lockoutEnd = !empty($input['lockout_end_date']) ? $input['lockout_end_date'] : null;

            if (!in_array($status, ['normal', 'held', 'nullified'], true)) {
                sendJson(['success' => false, 'message' => 'Invalid result status'], 422);
            }

            $stmt = $pdo->prepare("UPDATE cases SET result_status = ?, lockout_start_date = ?, lockout_end_date = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $lockoutStart, $lockoutEnd, $caseId]);

            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'result_status_updated', ?)")
                ->execute([$caseId, $user['id'], "Result status updated to {$status}." . ($lockoutStart ? " Lockout: {$lockoutStart} to {$lockoutEnd}" : "")]);

            auditLog($pdo, $user['id'], 'case_result_status_updated', 'cases', $caseId, ['result_status' => $status, 'lockout_start' => $lockoutStart, 'lockout_end' => $lockoutEnd]);
            sendJson(['success' => true, 'message' => "Result status updated to {$status}"]);
            break;

        // --- Hall Security & Panic Alerts ---
        case 'trigger_security_alert':
            $user = requireRole(['invigilator', 'exam_officer', 'admin', 'hod', 'committee']);
            validateCSRF($pdo);
            $venue = trim((string)($input['venue'] ?? ''));
            $sessionTime = trim((string)($input['session_time'] ?? ''));
            $reason = trim((string)($input['reason'] ?? ''));
            $urgency = trim((string)($input['urgency'] ?? 'critical'));

            if ($venue === '' || $reason === '') {
                sendJson(['success' => false, 'message' => 'Exam hall venue and reason are required'], 422);
            }
            if (!in_array($urgency, ['high', 'critical'], true)) $urgency = 'critical';

            $stmt = $pdo->prepare("INSERT INTO hall_security_alerts (venue, invigilator_id, session_time, reason, urgency, status) VALUES (?, ?, ?, ?, ?, 'active') RETURNING id");
            $stmt->execute([$venue, $user['id'], $sessionTime ?: null, $reason, $urgency]);
            $alertId = $stmt->fetchColumn();

            $recipients = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'exam_officer') AND is_active = TRUE")->fetchAll(PDO::FETCH_COLUMN);
            $title = "EMERGENCY: Hall Security Alert at {$venue}";
            $msg = "Urgent security assistance requested by {$user['full_name']} at {$venue} ({$sessionTime}): {$reason}";
            foreach ($recipients as $recId) {
                notify($pdo, (int)$recId, 'urgent', $title, $msg);
            }

            auditLog($pdo, $user['id'], 'security_alert_triggered', 'hall_security_alerts', (int)$alertId, ['venue' => $venue, 'urgency' => $urgency]);
            sendJson(['success' => true, 'message' => "Hall Security Emergency Alert dispatched to Central Security & Chief Invigilator", 'data' => ['alert_id' => $alertId]]);
            break;

        case 'get_security_alerts':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $stmt = $pdo->query("SELECT hsa.*, u.full_name as invigilator_name, ru.full_name as resolved_by_name
                                 FROM hall_security_alerts hsa
                                 LEFT JOIN users u ON hsa.invigilator_id = u.id
                                 LEFT JOIN users ru ON hsa.resolved_by = ru.id
                                 ORDER BY (CASE WHEN hsa.status = 'active' THEN 0 ELSE 1 END), hsa.created_at DESC
                                 LIMIT 40");
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'resolve_security_alert':
            $user = requireRole(['admin', 'exam_officer']);
            validateCSRF($pdo);
            $alertId = (int)$input['alert_id'];
            $notes = trim((string)($input['resolution_notes'] ?? ''));

            $stmt = $pdo->prepare("UPDATE hall_security_alerts SET status = 'resolved', resolved_by = ?, resolved_at = NOW(), resolution_notes = ? WHERE id = ?");
            $stmt->execute([$user['id'], $notes ?: 'Resolved by Chief Invigilator/Security Command.', $alertId]);

            auditLog($pdo, $user['id'], 'security_alert_resolved', 'hall_security_alerts', $alertId, ['notes' => $notes]);
            sendJson(['success' => true, 'message' => 'Security alert marked as resolved']);
            break;

        // --- Physical Evidence Locker & Chain-of-Custody ---
        case 'get_evidence_lockers':
            $user = requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $stmt = $pdo->query("SELECT el.*, u.full_name as custodian_name, i.reference_no, i.student_name, i.student_matric,
                                 (SELECT COUNT(*) FROM evidence_custody_transfers ect WHERE ect.evidence_locker_id = el.id) as transfer_count
                                 FROM evidence_lockers el
                                 LEFT JOIN users u ON el.custodian_id = u.id
                                 JOIN incidents i ON el.incident_id = i.id
                                 ORDER BY el.created_at DESC");
            $lockers = $stmt->fetchAll();
            
            $unassigned = $pdo->query("SELECT i.id, i.reference_no, i.student_name, i.student_matric, i.offense_category, i.created_at
                                      FROM incidents i
                                      WHERE i.status != 'closed'
                                      AND i.id NOT IN (SELECT incident_id FROM evidence_lockers)
                                      ORDER BY i.created_at DESC LIMIT 30")->fetchAll();

            sendJson(['success' => true, 'data' => ['lockers' => $lockers, 'unassigned_incidents' => $unassigned]]);
            break;

        case 'tag_evidence_locker':
            $user = requireRole(['admin', 'exam_officer', 'committee']);
            validateCSRF($pdo);
            $incidentId = (int)$input['incident_id'];
            $lockerIdentifier = trim((string)($input['locker_identifier'] ?? 'Locker-A1'));
            $shelfDrawer = trim((string)($input['shelf_drawer'] ?? 'Shelf 1, Bin 1'));
            $description = trim((string)($input['description'] ?? 'Physical Malpractice Evidence'));
            $barcodeTag = trim((string)($input['barcode_tag'] ?? ''));
            $custodianId = !empty($input['custodian_id']) ? (int)$input['custodian_id'] : (int)$user['id'];

            if ($incidentId <= 0) sendJson(['success' => false, 'message' => 'Invalid incident ID'], 422);

            if ($barcodeTag === '') {
                $barcodeTag = 'BUK-EV-' . date('Y') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO evidence_lockers (incident_id, locker_identifier, shelf_drawer, barcode_tag, description, custodian_id, status) VALUES (?, ?, ?, ?, ?, ?, 'secured') RETURNING id");
            $stmt->execute([$incidentId, $lockerIdentifier, $shelfDrawer, $barcodeTag, $description, $custodianId]);
            $lockerId = $stmt->fetchColumn();

            $pdo->prepare("INSERT INTO evidence_custody_transfers (evidence_locker_id, from_custodian_id, to_custodian_id, reason, notes) VALUES (?, ?, ?, 'initial_intake', 'Initial lodge into physical evidence safe.')")
                ->execute([$lockerId, $user['id'], $custodianId]);

            $pdo->commit();
            auditLog($pdo, $user['id'], 'evidence_locker_tagged', 'evidence_lockers', (int)$lockerId, ['barcode' => $barcodeTag, 'locker' => $lockerIdentifier]);
            sendJson(['success' => true, 'message' => "Evidence lodged into {$lockerIdentifier} with barcode {$barcodeTag}", 'data' => ['id' => $lockerId, 'barcode_tag' => $barcodeTag]]);
            break;

        case 'transfer_evidence_custody':
            $user = requireRole(['admin', 'exam_officer', 'committee']);
            validateCSRF($pdo);
            $lockerId = (int)$input['evidence_locker_id'];
            $toCustodianId = (int)$input['to_custodian_id'];
            $reason = trim((string)($input['reason'] ?? 'committee_hearing'));
            $notes = trim((string)($input['notes'] ?? ''));

            if ($lockerId <= 0 || $toCustodianId <= 0) {
                sendJson(['success' => false, 'message' => 'Locker ID and receiving custodian are required'], 422);
            }

            $curStmt = $pdo->prepare("SELECT * FROM evidence_lockers WHERE id = ?");
            $curStmt->execute([$lockerId]);
            $locker = $curStmt->fetch();
            if (!$locker) sendJson(['success' => false, 'message' => 'Evidence locker record not found'], 404);

            $fromCustodianId = $locker['custodian_id'] ?: $user['id'];

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE evidence_lockers SET custodian_id = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$toCustodianId, $lockerId]);

            $pdo->prepare("INSERT INTO evidence_custody_transfers (evidence_locker_id, from_custodian_id, to_custodian_id, reason, notes) VALUES (?, ?, ?, ?, ?)")
                ->execute([$lockerId, $fromCustodianId, $toCustodianId, $reason, $notes ?: 'Custody transfer recorded.']);
            $pdo->commit();

            notify($pdo, $toCustodianId, 'system', 'Evidence Custody Transfer', "Physical custody of evidence {$locker['barcode_tag']} has been transferred to you.");
            auditLog($pdo, $user['id'], 'evidence_custody_transferred', 'evidence_lockers', $lockerId, ['from' => $fromCustodianId, 'to' => $toCustodianId, 'reason' => $reason]);
            sendJson(['success' => true, 'message' => 'Chain-of-custody transfer successfully recorded']);
            break;

        case 'get_evidence_transfers':
            $user = requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $lockerId = (int)($input['evidence_locker_id'] ?? $_GET['evidence_locker_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT ect.*, fu.full_name as from_custodian_name, tu.full_name as to_custodian_name 
                                   FROM evidence_custody_transfers ect 
                                   LEFT JOIN users fu ON ect.from_custodian_id = fu.id 
                                   LEFT JOIN users tu ON ect.to_custodian_id = tu.id 
                                   WHERE ect.evidence_locker_id = ? 
                                   ORDER BY ect.transferred_at ASC");
            $stmt->execute([$lockerId]);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        // --- Timetable Examination Schedules ---
        case 'get_scheduled_exams':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $query = trim((string)($_GET['q'] ?? ''));
            $sql = "SELECT es.*, c.code as course_code, c.title as course_title, d.name as department_name, f.name as faculty_name 
                    FROM exam_schedules es 
                    LEFT JOIN courses c ON es.course_id = c.id 
                    LEFT JOIN departments d ON es.department_id = d.id 
                    LEFT JOIN faculties f ON es.faculty_id = f.id";
            $params = [];
            if ($query !== '') {
                $sql .= " WHERE c.code ILIKE ? OR c.title ILIKE ? OR es.venue ILIKE ? OR es.academic_session ILIKE ?";
                $like = "%{$query}%";
                $params = [$like, $like, $like, $like];
            }
            $sql .= " ORDER BY es.exam_date DESC, es.session_time ASC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        // --- Repeat Offender Radar & Academic Disciplinary Profile ---
        case 'get_student_disciplinary_history':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $matricNo = trim((string)($input['matric_no'] ?? $_GET['matric_no'] ?? ''));
            if ($matricNo === '') sendJson(['success' => false, 'message' => 'Matriculation number is required'], 422);

            $sStmt = $pdo->prepare("SELECT s.*, d.name as department_name, f.name as faculty_name 
                                    FROM students s 
                                    LEFT JOIN departments d ON s.department_id = d.id 
                                    LEFT JOIN faculties f ON d.faculty_id = f.id 
                                    WHERE LOWER(s.matric_no) = LOWER(?)");
            $sStmt->execute([$matricNo]);
            $student = $sStmt->fetch();

            $iStmt = $pdo->prepare("SELECT i.*, c.code as course_code, c.title as course_title, cs.case_no, cs.stage as case_stage, cs.case_tier, cs.result_status,
                                    s.sanction_type, s.duration_semesters, s.effective_session
                                    FROM incidents i
                                    LEFT JOIN courses c ON i.course_id = c.id
                                    LEFT JOIN cases cs ON cs.incident_id = i.id
                                    LEFT JOIN sanctions s ON s.case_id = cs.id
                                    WHERE LOWER(i.student_matric) = LOWER(?)
                                    ORDER BY i.incident_date DESC, i.created_at DESC");
            $iStmt->execute([$matricNo]);
            $incidents = $iStmt->fetchAll();

            $totalIncidents = count($incidents);
            $activeSanctions = 0;
            foreach ($incidents as $inc) {
                if (!empty($inc['sanction_type'])) $activeSanctions++;
            }
            $isRepeatOffender = $totalIncidents > 1;

            sendJson(['success' => true, 'data' => [
                'student' => $student,
                'matric_no' => $matricNo,
                'total_incidents' => $totalIncidents,
                'active_sanctions' => $activeSanctions,
                'is_repeat_offender' => $isRepeatOffender,
                'risk_badge' => $totalIncidents >= 3 ? 'High Risk' : ($totalIncidents === 2 ? 'Medium Risk / Repeat Offender' : ($totalIncidents === 1 ? 'First Offense' : 'Clean Record')),
                'incidents' => $incidents
            ]]);
            break;

        // --- Visual Collusion & Seating Network ---
        case 'get_collusion_network':
            $user = requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $stmt = $pdo->query("SELECT i.venue, i.incident_date, i.session_time, c.code as course_code, COUNT(i.id) as cluster_size,
                                 json_agg(json_build_object(
                                     'id', i.id,
                                     'reference_no', i.reference_no,
                                     'student_name', i.student_name,
                                     'student_matric', i.student_matric,
                                     'seat_number', i.seat_number,
                                     'offense_category', i.offense_category,
                                     'status', i.status
                                 )) as co_accused
                                 FROM incidents i
                                 JOIN courses c ON i.course_id = c.id
                                 GROUP BY i.venue, i.incident_date, i.session_time, c.code
                                 HAVING COUNT(i.id) > 1
                                 ORDER BY cluster_size DESC, i.incident_date DESC LIMIT 25");
            $clusters = $stmt->fetchAll();
            foreach ($clusters as &$cl) {
                if (is_string($cl['co_accused'])) {
                    $cl['co_accused'] = json_decode($cl['co_accused'], true);
                }
            }
            sendJson(['success' => true, 'data' => $clusters]);
            break;

        // --- Institutional Risk Heatmap ---
        case 'get_risk_heatmap':
            $user = requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $byFaculty = $pdo->query("SELECT f.id, f.name, f.code, COUNT(i.id) as incident_count,
                                      COUNT(CASE WHEN cs.id IS NOT NULL THEN 1 END) as case_count,
                                      COUNT(CASE WHEN s.id IS NOT NULL THEN 1 END) as sanction_count
                                      FROM faculties f
                                      LEFT JOIN departments d ON d.faculty_id = f.id
                                      LEFT JOIN incidents i ON i.student_dept_id = d.id
                                      LEFT JOIN cases cs ON cs.incident_id = i.id
                                      LEFT JOIN sanctions s ON s.case_id = cs.id
                                      GROUP BY f.id, f.name, f.code
                                      ORDER BY incident_count DESC")->fetchAll();

            $byVenue = $pdo->query("SELECT i.venue, COUNT(i.id) as incident_count,
                                    COUNT(DISTINCT i.course_id) as course_count
                                    FROM incidents i
                                    WHERE i.venue IS NOT NULL AND i.venue != ''
                                    GROUP BY i.venue
                                    ORDER BY incident_count DESC LIMIT 15")->fetchAll();

            $byCategory = $pdo->query("SELECT i.offense_category, COUNT(i.id) as incident_count
                                       FROM incidents i
                                       GROUP BY i.offense_category
                                       ORDER BY incident_count DESC")->fetchAll();

            $byCourse = $pdo->query("SELECT c.code, c.title, COUNT(i.id) as incident_count
                                     FROM incidents i
                                     JOIN courses c ON i.course_id = c.id
                                     GROUP BY c.code, c.title
                                     ORDER BY incident_count DESC LIMIT 10")->fetchAll();

            sendJson(['success' => true, 'data' => [
                'by_faculty' => $byFaculty,
                'by_venue' => $byVenue,
                'by_category' => $byCategory,
                'by_course' => $byCourse
            ]]);
            break;

        // --- Official Letterhead & Legal Document Generator ---
        case 'get_official_letter':
            $user = requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $caseId = (int)($input['case_id'] ?? $_GET['case_id'] ?? 0);
            $type = trim((string)($input['type'] ?? $_GET['type'] ?? 'query_letter'));

            $validTypes = ['query_letter', 'summons_subpoena', 'hearing_notice', 'senate_order'];
            if (!in_array($type, $validTypes, true)) {
                sendJson(['success' => false, 'message' => 'Invalid letter type requested'], 422);
            }

            $cStmt = $pdo->prepare("SELECT c.*, i.reference_no, i.student_name, i.student_matric, i.venue, i.incident_date, i.session_time, i.incident_summary, i.offense_category,
                                    c_course.code as course_code, c_course.title as course_title,
                                    d.name as department_name, f.name as faculty_name,
                                    comm.full_name as committee_chair,
                                    s.sanction_type, s.duration_semesters, s.effective_session, s.notes as sanction_notes,
                                    sm.meeting_number as senate_meeting, sm.memo_number as senate_memo_ref
                                    FROM cases c
                                    JOIN incidents i ON c.incident_id = i.id
                                    LEFT JOIN courses c_course ON i.course_id = c_course.id
                                    LEFT JOIN departments d ON i.student_dept_id = d.id
                                    LEFT JOIN faculties f ON d.faculty_id = f.id
                                    LEFT JOIN users comm ON c.assigned_committee = comm.id
                                    LEFT JOIN sanctions s ON s.case_id = c.id
                                    LEFT JOIN senate_memos sm ON c.senate_memo_id = sm.id
                                    WHERE c.id = ?");
            $cStmt->execute([$caseId]);
            $data = $cStmt->fetch();
            if (!$data) sendJson(['success' => false, 'message' => 'Case record not found'], 404);

            $hStmt = $pdo->prepare("SELECT * FROM hearings WHERE case_id = ? ORDER BY scheduled_date DESC LIMIT 1");
            $hStmt->execute([$caseId]);
            $hearing = $hStmt->fetch();

            $today = date('jS F, Y');
            $letter = [];

            switch ($type) {
                case 'query_letter':
                    $letter = [
                        'type' => 'query_letter',
                        'doc_title' => 'FORMAL QUERY ON ALLEGED EXAMINATION MALPRACTICE',
                        'ref_no' => 'BUK/MISC/QRY/' . date('Y') . '/' . $data['case_no'],
                        'date' => $today,
                        'recipient' => [
                            'name' => $data['student_name'],
                            'matric' => $data['student_matric'],
                            'dept' => $data['department_name'],
                            'faculty' => $data['faculty_name']
                        ],
                        'subject' => "RE: QUERY REGARDING ALLEGED EXAMINATION IRREGULARITY IN " . strtoupper($data['course_code']),
                        'body' => [
                            "It has been formally reported to the Central Examination Misconduct Committee (CEMC) that on {$data['incident_date']} during the conduct of {$data['course_code']} ({$data['course_title']}) in {$data['venue']}, you were allegedly found committing an infraction categorized under: " . strtoupper(str_replace('_', ' ', $data['offense_category'])) . ".",
                            "Summary of Allegation: \"" . ($data['incident_summary'] ?: 'Possession of unauthorized materials / unauthorized communication') . "\".",
                            "This conduct constitutes a prima facie violation of Section 8.3 of the Bayero University Kano General Regulations Governing Examinations.",
                            "You are hereby directed to submit a written explanation, addressing each item of the allegation, within forty-eight (48) hours of the receipt of this query to the Office of the Examination Officer / Secretary, Central Examination Misconduct Committee.",
                            "Take notice that failure to respond within the stipulated time shall be construed as an admission of the allegation and the Committee shall proceed to adjudicate on the matter ex-parte."
                        ],
                        'signatory' => [
                            'name' => 'Dr. A. B. Danbatta',
                            'title' => 'Secretary, Central Examination Misconduct Committee',
                            'institution' => 'Bayero University, Kano'
                        ]
                    ];
                    break;

                case 'hearing_notice':
                case 'summons_subpoena':
                    $hDate = $hearing ? date('l, jS F, Y \a\t g:i A', strtotime($hearing['scheduled_date'])) : 'To Be Announced';
                    $hVenue = $hearing['venue'] ?? 'Senate Committee Room B, New Campus';
                    $letter = [
                        'type' => $type,
                        'doc_title' => 'NOTICE OF SUMMONS TO APPEAR BEFORE EXAMINATION MISCONDUCT COMMITTEE',
                        'ref_no' => 'BUK/CEMC/SUM/' . date('Y') . '/' . $data['case_no'],
                        'date' => $today,
                        'recipient' => [
                            'name' => $data['student_name'],
                            'matric' => $data['student_matric'],
                            'dept' => $data['department_name'],
                            'faculty' => $data['faculty_name']
                        ],
                        'subject' => "SUMMONS TO APPEAR BEFORE THE COMMITTEE - CASE NO: {$data['case_no']}",
                        'body' => [
                            "You are hereby summoned in accordance with the Bayero University Kano Regulations to appear in person before the Central Examination Misconduct Committee (CEMC) regarding Case Ref: {$data['case_no']} arising from Incident Ref: {$data['reference_no']}.",
                            "The hearing session has been formally slated as follows:\n- Hearing Date & Time: {$hDate}\n- Venue: {$hVenue}",
                            "You are entitled to bring along any documentary evidence, written testimony, or call witnesses on your behalf who have firsthand knowledge of the occurrence.",
                            "Please be advised that appearance is mandatory. Punctuality is strictly required."
                        ],
                        'signatory' => [
                            'name' => $data['committee_chair'] ?: 'Prof. M. S. Haruna',
                            'title' => 'Chairman, Central Examination Misconduct Committee',
                            'institution' => 'Bayero University, Kano'
                        ]
                    ];
                    break;

                case 'senate_order':
                    $letter = [
                        'type' => 'senate_order',
                        'doc_title' => 'EXTRACT OF SENATE RATIFIED DISCIPLINARY SANCTION ORDER',
                        'ref_no' => 'BUK/SEN/ORD/' . date('Y') . '/' . $data['case_no'],
                        'date' => $today,
                        'recipient' => [
                            'name' => $data['student_name'],
                            'matric' => $data['student_matric'],
                            'dept' => $data['department_name'],
                            'faculty' => $data['faculty_name']
                        ],
                        'subject' => "OFFICIAL COMMUNIQUE: RATIFICATION OF EXAMINATION MISCONDUCT SANCTION",
                        'body' => [
                            "The Senate of Bayero University Kano, at its " . ($data['senate_meeting'] ? "{$data['senate_meeting']}th Regular Meeting" : "Statutory Regular Meeting") . ", considered the report and recommendations of the Central Examination Misconduct Committee (CEMC) vide Senate Working Paper Ref: " . ($data['senate_memo_ref'] ?: 'BUK/SEN/MAL/RAT') . ".",
                            "After due consideration of the evidence, witness testimonies, and findings regarding Case No: {$data['case_no']} (Incident: {$data['reference_no']}) in respect of {$data['course_code']}, Senate RATIFIED and APPROVED the following disciplinary sanction:",
                            "SANCTION DECISION: " . strtoupper(str_replace('_', ' ', $data['sanction_type'] ?: 'EXPULSION / SUSPENSION')) .
                            ($data['duration_semesters'] ? " FOR A DURATION OF {$data['duration_semesters']} SEMESTER(S)" : "") .
                            ($data['effective_session'] ? " (EFFECTIVE: {$data['effective_session']})" : "") . ".",
                            "RESULT DISPOSITION: The student's examination result in {$data['course_code']} has been NULLIFIED in accordance with University Regulations.",
                            "You are by this order directed to surrender all University property, identity credentials, and vacate student residence halls forthwith where applicable."
                        ],
                        'signatory' => [
                            'name' => 'Malam Jamil Ahmad Salim',
                            'title' => 'Registrar & Secretary to Senate',
                            'institution' => 'Bayero University, Kano'
                        ]
                    ];
                    break;
            }

            sendJson(['success' => true, 'data' => $letter]);
            break;

        default:
            sendJson(['success' => false, 'message' => 'Invalid API Action'], 404);
    }
} catch (PDOException $e) {
    error_log("Database error in api.php: " . $e->getMessage());
    $code = substr((string)$e->getCode(), 0, 2) === '23' ? 422 : 500;
    $msg = $code === 422 ? 'Data constraint violation. Please verify input fields.' : 'System processing error. Please contact the administrator.';
    sendJson(['success' => false, 'message' => $msg], $code);
} catch (Throwable $e) {
    error_log("Application error in api.php: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Internal server error occurred.'], 500);
}
?>