<?php
// api.php
declare(strict_types=1);
session_start();
date_default_timezone_set('UTC');

// --- 1. DB Connection & Schema (PostgreSQL / Neon) — see db.php ---
require __DIR__ . '/db.php';
$pdo = get_pdo();

// --- 2. Middleware & Helpers (SRS Section 6.1 & 7.1) ---
function sendJson($data, $code = 200) {
    http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit;
}

function generateCSRF($pdo) {
    $token = bin2hex(random_bytes(32));
    $sid = session_id();
    $stmt = $pdo->prepare("INSERT INTO csrf_tokens (session_id, token, expires_at) VALUES (?, ?, NOW() + INTERVAL '2 hours')");
    $stmt->execute([$sid, $token]);
    return $token;
}

function validateCSRF($pdo) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!$token && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'x-csrf-token') { $token = $v; break; }
        }
    }
    
    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    }

    if (!$token) sendJson(['success' => false, 'message' => 'CSRF Token Missing'], 403);
    
    $stmt = $pdo->prepare("SELECT id FROM csrf_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    if (!$stmt->fetch()) {
        sendJson(['success' => false, 'message' => 'Invalid or Expired CSRF Token'], 403);
    }
}

function requireRole($roles = []) {
    if (!isset($_SESSION['user'])) sendJson(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles)) sendJson(['success' => false, 'message' => 'Forbidden'], 403);
    return $_SESSION['user'];
}

function notify($pdo, $user_id, $type, $title, $message, $link = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $title, $message, $link]);
}

// --- 3. Request Router ---
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($input)) $input = $_POST;

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
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
            $stmt->execute([$input['email']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($input['password'], $user['password_hash'])) {
                session_regenerate_id(true);
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                $_SESSION['user'] = [
                    'id' => $user['id'], 'role' => $user['role'], 'full_name' => $user['full_name'],
                    'faculty_id' => $user['faculty_id'], 'department_id' => $user['department_id']
                ];
                sendJson(['success' => true, 'data' => $_SESSION['user']]);
            }
            sendJson(['success' => false, 'message' => 'Invalid credentials or inactive account'], 401);
            break;

        case 'logout':
            $_SESSION = []; session_destroy();
            sendJson(['success' => true, 'message' => 'Logged out']);
            break;

        case 'update_profile':
            $user = requireRole();
            validateCSRF($pdo);
            if (!empty($input['password'])) {
                $hash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, password_hash = ? WHERE id = ?")->execute([$input['full_name'], $input['phone'] ?? null, $hash, $user['id']]);
            } else {
                $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")->execute([$input['full_name'], $input['phone'] ?? null, $user['id']]);
            }
            $_SESSION['user']['full_name'] = $input['full_name'];
            sendJson(['success' => true, 'message' => 'Profile updated successfully.']);
            break;

        case 'get_notifications':
            $user = requireRole();
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
            $stmt->execute([$user['id']]);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        // --- Staff Account Requests ---
        case 'request_account':
            validateCSRF($pdo);
            $role = $input['role_requested'] ?? 'invigilator';
            $allowedRoles = ['exam_officer','invigilator','hod','committee'];
            if (!in_array($role, $allowedRoles, true)) sendJson(['success'=>false,'message'=>'Select a valid staff role'], 422);
            $staffId = trim((string)($input['staff_id'] ?? ''));
            $fullName = trim((string)($input['full_name'] ?? ''));
            $email = strtolower(trim((string)($input['email'] ?? '')));
            $password = (string)($input['password'] ?? '');
            if ($staffId === '' || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) sendJson(['success'=>false,'message'=>'Provide a valid staff ID, name, BUK email, and password of at least 8 characters'], 422);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?) OR staff_id=?");
            $stmt->execute([$email, $staffId]);
            if ($stmt->fetch()) sendJson(['success'=>false,'message'=>'An account already exists for this email or staff ID'], 409);
            $stmt = $pdo->prepare("SELECT id FROM account_requests WHERE LOWER(email)=LOWER(?) AND status='pending'");
            $stmt->execute([$email]);
            if ($stmt->fetch()) sendJson(['success'=>false,'message'=>'A pending account request already exists for this email'], 409);
            $stmt = $pdo->prepare("INSERT INTO account_requests (staff_id, full_name, email, phone, password_hash, role_requested, faculty_id, department_id, justification) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$staffId, $fullName, $email, $input['phone'] ?? null, password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]), $role, !empty($input['faculty_id']) ? $input['faculty_id'] : null, !empty($input['department_id']) ? $input['department_id'] : null, $input['justification'] ?? null]);
            sendJson(['success'=>true,'message'=>'Account request submitted for administrator approval']);
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
            sendJson(['success'=>true,'data'=>$rows]);
            break;

        case 'approve_account_request':
            $admin = requireRole(['admin']);
            validateCSRF($pdo);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT * FROM account_requests WHERE id=? AND status='pending' FOR UPDATE");
                $stmt->execute([$input['id']]);
                $request = $stmt->fetch();
                if (!$request) sendJson(['success'=>false,'message'=>'Pending account request not found'], 404);
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email)=LOWER(?) OR staff_id=?");
                $stmt->execute([$request['email'], $request['staff_id']]);
                if ($stmt->fetch()) sendJson(['success'=>false,'message'=>'A user with this email or staff ID already exists'], 409);
                $stmt = $pdo->prepare("INSERT INTO users (staff_id, full_name, email, password_hash, role, faculty_id, department_id, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$request['staff_id'],$request['full_name'],$request['email'],$request['password_hash'],$request['role_requested'],$request['faculty_id'],$request['department_id'],$request['phone']]);
                $newUserId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("UPDATE account_requests SET status='approved', reviewed_by=?, reviewed_at=NOW(), decision_note=? WHERE id=?");
                $stmt->execute([$admin['id'], $input['decision_note'] ?? 'Approved by administrator', $request['id']]);
                $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?) ON CONFLICT (user_id) DO NOTHING")->execute([$newUserId]);
                $pdo->commit();
                sendJson(['success'=>true,'message'=>'Account approved and created']);
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
            sendJson(['success'=>true,'message'=>'Account request declined']);
            break;

        // --- Admin: User Management (SRS 5.6) ---
        case 'get_users':
            requireRole(['admin']);
            $sql = "SELECT u.id, u.staff_id, u.full_name, u.email, u.role, u.is_active, u.last_login, d.name as dept_name 
                    FROM users u LEFT JOIN departments d ON u.department_id = d.id ORDER BY u.created_at DESC";
            $rows = $pdo->query($sql)->fetchAll();
            foreach ($rows as &$row) { $row['is_active'] = ($row['is_active'] === true || $row['is_active'] === 't' || $row['is_active'] === '1'); }
            sendJson(['success' => true, 'data' => $rows]);
            break;

        case 'create_user':
            requireRole(['admin']);
            validateCSRF($pdo);
            $hash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (staff_id, full_name, email, password_hash, role, department_id, faculty_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$input['staff_id'], $input['full_name'], $input['email'], $hash, $input['role'], $input['department_id'] ?: null, $input['faculty_id'] ?: null]);
            sendJson(['success' => true, 'message' => 'User created successfully']);
            break;

        case 'update_user':
            requireRole(['admin']);
            validateCSRF($pdo);
            $id = $input['id'];
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
            sendJson(['success' => true, 'message' => 'User updated successfully']);
            break;

        case 'toggle_user':
            requireRole(['admin']);
            validateCSRF($pdo);
            if ($input['id'] == $_SESSION['user']['id']) {
                sendJson(['success' => false, 'message' => 'Cannot disable your own account'], 400);
            }
            $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$input['is_active'], $input['id']]);
            sendJson(['success' => true, 'message' => 'User access updated']);
            break;

        case 'delete_user':
            requireRole(['admin']);
            validateCSRF($pdo);
            if ($input['id'] == $_SESSION['user']['id']) sendJson(['success' => false, 'message' => 'Cannot delete your own account'], 400);
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$input['id']]);
            sendJson(['success' => true, 'message' => 'User deleted permanently']);
            break;

        // --- Dashboards & Analytics ---
        case 'get_admin_dashboard':
            requireRole(['admin']);
            $stats = [
                'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                'incidents' => $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
                'active_cases' => $pdo->query("SELECT COUNT(*) FROM cases WHERE stage NOT IN ('resolved', 'dismissed')")->fetchColumn(),
                'resolved_cases' => $pdo->query("SELECT COUNT(*) FROM cases WHERE stage IN ('resolved', 'dismissed')")->fetchColumn()
            ];
            sendJson(['success' => true, 'data' => $stats]);
            break;

        case 'get_eo_dashboard':
            requireRole(['admin', 'exam_officer']);
            $stats = [
                'total_incidents' => $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(),
                'open_cases' => $pdo->query("SELECT COUNT(*) FROM cases WHERE stage NOT IN ('resolved', 'dismissed')")->fetchColumn(),
                'under_review' => $pdo->query("SELECT COUNT(*) FROM cases WHERE stage = 'under_review'")->fetchColumn(),
                'resolved_this_month' => $pdo->query("SELECT COUNT(*) FROM cases WHERE stage IN ('resolved', 'dismissed') AND EXTRACT(MONTH FROM resolved_at) = EXTRACT(MONTH FROM NOW()) AND EXTRACT(YEAR FROM resolved_at) = EXTRACT(YEAR FROM NOW())")->fetchColumn(),
                'pending_incidents' => $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'pending'")->fetchColumn()
            ];
            sendJson(['success' => true, 'data' => $stats]);
            break;

        case 'get_user_dashboard':
            $user = requireRole(['invigilator', 'hod', 'committee']);
            $stats = [];
            if ($user['role'] === 'invigilator') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reported_by = ?");
                $stmt->execute([$user['id']]); $stats['reports'] = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reported_by = ? AND status = 'pending'");
                $stmt->execute([$user['id']]); $stats['pending'] = $stmt->fetchColumn();
            } elseif ($user['role'] === 'hod') {
                $dept = $user['department_id'];
                $fac = $user['faculty_id'];
                if ($dept) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE student_dept_id = ?");
                    $stmt->execute([$dept]); $stats['dept_incidents'] = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases c JOIN incidents i ON c.incident_id = i.id WHERE i.student_dept_id = ? AND c.stage NOT IN ('resolved', 'dismissed')");
                    $stmt->execute([$dept]); $stats['active_dept_cases'] = $stmt->fetchColumn();
                } elseif ($fac) {
                    $stmt = $pdo->prepare("SELECT COUNT(i.id) FROM incidents i JOIN departments d ON i.student_dept_id = d.id WHERE d.faculty_id = ?");
                    $stmt->execute([$fac]); $stats['dept_incidents'] = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(c.id) FROM cases c JOIN incidents i ON c.incident_id = i.id JOIN departments d ON i.student_dept_id = d.id WHERE d.faculty_id = ? AND c.stage NOT IN ('resolved', 'dismissed')");
                    $stmt->execute([$fac]); $stats['active_dept_cases'] = $stmt->fetchColumn();
                } else {
                    $stats['dept_incidents'] = 0; $stats['active_dept_cases'] = 0;
                }
            } elseif ($user['role'] === 'committee') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE assigned_committee = ?");
                $stmt->execute([$user['id']]); $stats['assigned'] = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE assigned_committee = ? AND stage IN ('investigation', 'hearing')");
                $stmt->execute([$user['id']]); $stats['pending_hearings'] = $stmt->fetchColumn();
            }
            sendJson(['success' => true, 'data' => $stats]);
            break;

        case 'get_analytics':
            requireRole(['admin', 'exam_officer', 'hod']);
            $stagesRaw = $pdo->query("SELECT stage, COUNT(*) as count FROM cases GROUP BY stage")->fetchAll();
            $stages = [];
            foreach($stagesRaw as $row) { $stages[ucfirst(str_replace('_', ' ', $row['stage']))] = $row['count']; }
            
            $monthlyRaw = $pdo->query("SELECT TO_CHAR(created_at, 'Mon') as month, EXTRACT(MONTH FROM created_at) as month_num, COUNT(*) as count FROM incidents WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM NOW()) GROUP BY month, month_num ORDER BY month_num")->fetchAll();
            $monthly = [];
            foreach($monthlyRaw as $row) { $monthly[$row['month']] = $row['count']; }

            if (empty($stages)) $stages = ['Reported' => 0];
            if (empty($monthly)) $monthly = [date('M') => 0];

            sendJson(['success' => true, 'data' => ['stages' => $stages, 'monthly' => $monthly]]);
            break;

        // --- Data Fetching (Incidents, Cases & Details) ---
        case 'get_incidents':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod']);
            $sql = "SELECT i.*, u.full_name as reporter_name, c.code as course_code 
                    FROM incidents i 
                    JOIN users u ON i.reported_by = u.id 
                    JOIN courses c ON i.course_id = c.id";
            $params = [];
            
            if ($user['role'] === 'invigilator') {
                $sql .= " WHERE i.reported_by = ?"; $params[] = $user['id'];
            } elseif ($user['role'] === 'hod') {
                if (!empty($user['department_id'])) {
                    $sql .= " WHERE i.student_dept_id = ?"; $params[] = $user['department_id'];
                } elseif (!empty($user['faculty_id'])) {
                    $sql .= " JOIN departments d ON i.student_dept_id = d.id WHERE d.faculty_id = ?"; $params[] = $user['faculty_id'];
                }
            }
            $sql .= " ORDER BY i.created_at DESC";
            
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'get_cases':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $sql = "SELECT c.*, i.reference_no, i.student_name, i.student_matric, i.offence_type 
                    FROM cases c 
                    JOIN incidents i ON c.incident_id = i.id";
            
            if ($user['role'] === 'hod' && empty($user['department_id']) && !empty($user['faculty_id'])) {
                $sql .= " JOIN departments d ON i.student_dept_id = d.id";
            }
            
            $params = [];
            if ($user['role'] === 'invigilator') {
                $sql .= " WHERE i.reported_by = ?"; $params[] = $user['id'];
            } elseif ($user['role'] === 'hod') {
                if (!empty($user['department_id'])) {
                    $sql .= " WHERE i.student_dept_id = ?"; $params[] = $user['department_id'];
                } elseif (!empty($user['faculty_id'])) {
                    $sql .= " WHERE d.faculty_id = ?"; $params[] = $user['faculty_id'];
                }
            } elseif ($user['role'] === 'committee') {
                $sql .= " WHERE c.assigned_committee = ?"; $params[] = $user['id'];
            }
            $sql .= " ORDER BY c.updated_at DESC";
            
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'get_case_details':
            requireRole(['admin', 'exam_officer', 'committee', 'hod']);
            $case_id = $input['case_id'];
            
            $case = $pdo->prepare("SELECT * FROM cases WHERE id = ?"); $case->execute([$case_id]);
            $caseData = $case->fetch();
            
            if (!$caseData) sendJson(['success' => false, 'message' => 'Case not found'], 404);

            $incident = $pdo->prepare("SELECT i.*, c.code as course_code FROM incidents i JOIN courses c ON i.course_id = c.id WHERE i.id = ?"); 
            $incident->execute([$caseData['incident_id']]);
            
            $logs = $pdo->prepare("SELECT l.*, u.full_name as actor_name FROM case_logs l JOIN users u ON l.actor_id = u.id WHERE l.case_id = ? ORDER BY l.logged_at ASC");
            $logs->execute([$case_id]);

            $evidence = $pdo->prepare("SELECT * FROM incident_evidence WHERE incident_id = ?");
            $evidence->execute([$caseData['incident_id']]);

            $hearings = $pdo->prepare("SELECT * FROM hearings WHERE case_id = ? ORDER BY scheduled_date DESC");
            $hearings->execute([$case_id]);

            $sanction = $pdo->prepare("SELECT * FROM sanctions WHERE case_id = ?");
            $sanction->execute([$case_id]);
            $commentsStmt = $pdo->prepare("SELECT cc.*, u.full_name as author_name FROM case_comments cc JOIN users u ON cc.author_id = u.id WHERE cc.case_id = ? ORDER BY cc.created_at ASC"); $commentsStmt->execute([$case_id]);
            $appealsStmt = $pdo->prepare("SELECT a.*, u.full_name as submitter_name FROM appeals a JOIN users u ON a.submitted_by = u.id WHERE a.case_id = ? ORDER BY a.created_at DESC"); $appealsStmt->execute([$case_id]);

            sendJson(['success' => true, 'data' => [
                'case' => $caseData,
                'incident' => $incident->fetch(),
                'logs' => $logs->fetchAll(),
                'evidence' => $evidence->fetchAll(),
                'hearings' => $hearings->fetchAll(),
                'sanction' => $sanction->fetch(),
                'comments' => $commentsStmt->fetchAll(),
                'appeals' => $appealsStmt->fetchAll()
            ]]);
            break;

        // NEW FILTER-ENABLED REPORT ENDPOINT
        case 'get_sanctions_report':
            requireRole(['admin', 'exam_officer', 'hod']);
            
            $sql = "SELECT c.id, c.case_no, c.resolution_summary, i.student_name, i.student_matric, i.offence_type, co.code as course_code, s.sanction_type, s.duration, s.description as sanction_description 
                    FROM cases c 
                    JOIN incidents i ON c.incident_id = i.id 
                    JOIN courses co ON i.course_id = co.id
                    LEFT JOIN departments d ON i.student_dept_id = d.id
                    LEFT JOIN sanctions s ON c.id = s.case_id 
                    WHERE c.stage IN ('resolved', 'dismissed')";
            
            $params = [];
            
            // Apply Dynamic Filters
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
            
            $sql .= " ORDER BY c.updated_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        // --- Standard System Workflows ---
        case 'report_incident':
            $user = requireRole(['invigilator', 'exam_officer', 'admin']);
            validateCSRF($pdo);
            
            $evidence = ''; $uploaded_size = 0; $mime_type = ''; $original_name = '';
            
            if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION);
                $evidence = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $original_name = $_FILES['evidence']['name']; $mime_type = $_FILES['evidence']['type']; $uploaded_size = $_FILES['evidence']['size'];
                @mkdir('uploads'); move_uploaded_file($_FILES['evidence']['tmp_name'], 'uploads/' . $evidence);
            }
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO incidents (reference_no, reported_by, course_id, exam_date, exam_time, venue, semester, academic_session, offence_type, description, student_name, student_matric, student_dept_id, student_level) VALUES ('TEMP', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
            $stmt->execute([$user['id'], $input['course_id'], $input['exam_date'], $input['exam_time'], $input['venue'], $input['semester'], $input['academic_session'], $input['offence_type'], $input['description'], $input['student_name'], $input['student_matric'], $input['student_dept_id'], $input['student_level']]);
            $inc_id = $stmt->fetchColumn();
            
            $ref = 'INC-' . date('Y') . '-' . str_pad((string)$inc_id, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE incidents SET reference_no = ? WHERE id = ?")->execute([$ref, $inc_id]);
            
            if ($evidence) {
                $pdo->prepare("INSERT INTO incident_evidence (incident_id, uploaded_by, original_name, stored_name, file_path, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$inc_id, $user['id'], $original_name, $evidence, 'uploads/'.$evidence, $mime_type, $uploaded_size]);
            }
            
            $officers = $pdo->query("SELECT id FROM users WHERE role = 'exam_officer' AND is_active = TRUE")->fetchAll();
            foreach($officers as $off) {
                notify($pdo, (int)$off['id'], 'incident_reported', 'New Incident Alert', "Incident {$ref} was reported by {$user['full_name']}.");
                $pdo->prepare("INSERT INTO tasks (assigned_to, incident_id, title, description, task_type, priority, due_date) VALUES (?, ?, ?, ?, 'triage', 'normal', NOW() + INTERVAL '2 days')")->execute([(int)$off['id'], $inc_id, "Review {$ref}", 'Triage the newly submitted examination incident report.']);
            }
            
            $pdo->commit();
            sendJson(['success' => true, 'message' => 'Incident reported successfully']);
            break;

        case 'open_case':
            $user = requireRole(['admin', 'exam_officer']);
            validateCSRF($pdo);
            $inc_id = $input['incident_id'];
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO cases (case_no, incident_id, opened_by, stage) VALUES ('TEMP', ?, ?, 'reported') RETURNING id");
            $stmt->execute([$inc_id, $user['id']]);
            $case_id = $stmt->fetchColumn();
            
            $case_no = 'CASE-' . date('Y') . '-' . str_pad((string)$case_id, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE cases SET case_no = ? WHERE id = ?")->execute([$case_no, $case_id]);
            $pdo->prepare("UPDATE incidents SET status = 'case_opened' WHERE id = ?")->execute([$inc_id]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, to_stage, note) VALUES (?, ?, 'case_opened', 'reported', 'Case initiated from incident review.')")->execute([$case_id, $user['id']]);
            
            $inc = $pdo->prepare("SELECT reported_by, reference_no FROM incidents WHERE id = ?"); $inc->execute([$inc_id]); $inc_data = $inc->fetch();
            notify($pdo, (int)$inc_data['reported_by'], 'case_opened', 'Case Opened', "Case {$case_no} was opened for your incident report ({$inc_data['reference_no']}).");
            
            $pdo->commit();
            sendJson(['success' => true, 'message' => 'Case opened successfully']);
            break;

        case 'advance_case_stage':
            $user = requireRole(['admin', 'exam_officer', 'committee']);
            validateCSRF($pdo);
            
            $stmt = $pdo->prepare("SELECT stage, case_no FROM cases WHERE id = ?"); $stmt->execute([$input['case_id']]); $case = $stmt->fetch();
            
            $allowed = false;
            if (in_array($user['role'], ['admin', 'exam_officer'])) {
                if ($case['stage'] === 'reported' && $input['stage'] === 'under_review') $allowed = true;
                if ($case['stage'] === 'under_review' && $input['stage'] === 'investigation') $allowed = true;
            }
            if (!$allowed) sendJson(['success' => false, 'message' => 'Unauthorized stage transition.'], 403);
            
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE cases SET stage = ? WHERE id = ?")->execute([$input['stage'], $input['case_id']]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, from_stage, to_stage, note) VALUES (?, ?, 'stage_advanced', ?, ?, ?)")->execute([$input['case_id'], $user['id'], $case['stage'], $input['stage'], $input['note'] ?? 'Stage advanced.']);
            $pdo->commit();
            sendJson(['success' => true, 'message' => 'Case advanced successfully']);
            break;

        case 'assign_committee':
            $user = requireRole(['admin', 'exam_officer']);
            validateCSRF($pdo);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE cases SET assigned_committee = ? WHERE id = ?")->execute([$input['committee_id'], $input['case_id']]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'committee_assigned', 'Committee member assigned.')")->execute([$input['case_id'], $user['id']]);
            
            $case_no = $pdo->prepare("SELECT case_no FROM cases WHERE id = ?"); $case_no->execute([$input['case_id']]);
            notify($pdo, (int)$input['committee_id'], 'system', 'Case Assignment', "You have been assigned to handle {$case_no->fetchColumn()}.");
            
            $pdo->commit();
            sendJson(['success' => true, 'message' => 'Assigned successfully.']);
            break;

        case 'add_case_note':
            $user = requireRole(['hod', 'committee', 'exam_officer', 'admin']);
            validateCSRF($pdo);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'note_added', ?)")->execute([$input['case_id'], $user['id'], $input['note']]);
            sendJson(['success' => true, 'message' => 'Note added successfully']);
            break;

        case 'schedule_hearing':
            $user = requireRole(['committee', 'admin', 'exam_officer']);
            validateCSRF($pdo);
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO hearings (case_id, scheduled_by, scheduled_date, venue) VALUES (?, ?, ?, ?)")->execute([$input['case_id'], $user['id'], $input['date'], $input['venue']]);
            $pdo->prepare("UPDATE cases SET stage = 'hearing' WHERE id = ?")->execute([$input['case_id']]);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, to_stage, note) VALUES (?, ?, 'hearing_scheduled', 'hearing', 'Hearing officially scheduled')")->execute([$input['case_id'], $user['id']]);
            $pdo->commit();
            sendJson(['success' => true, 'message' => 'Hearing scheduled']);
            break;

        case 'resolve_case':
            $user = requireRole(['committee', 'admin']);
            validateCSRF($pdo);
            $pdo->beginTransaction();
            $status = $input['status'];
            
            $pdo->prepare("UPDATE cases SET stage = ?, resolution_summary = ?, resolved_at = NOW() WHERE id = ?")->execute([$status, $input['resolution_summary'], $input['case_id']]);
            if ($status === 'resolved') {
                $pdo->prepare("INSERT INTO sanctions (case_id, recorded_by, sanction_type, duration, description, effective_date) VALUES (?, ?, ?, ?, ?, ?)")->execute([$input['case_id'], $user['id'], $input['sanction_type'], $input['duration'] ?? null, $input['description'], $input['effective_date']]);
            }
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, to_stage, note) VALUES (?, ?, ?, ?, ?)")->execute([$input['case_id'], $user['id'], $status === 'resolved' ? 'case_resolved' : 'case_dismissed', $status, $input['resolution_summary']]);
            $pdo->commit();
            
            sendJson(['success' => true, 'message' => 'Case officially closed']);
            break;

        case 'get_lookups':
            sendJson(['success' => true, 'data' => [
                'departments' => $pdo->query("SELECT id, name, code, faculty_id FROM departments")->fetchAll(), 
                'faculties' => $pdo->query("SELECT id, name, code FROM faculties")->fetchAll(), 
                'courses' => $pdo->query("SELECT id, code, title, department_id FROM courses")->fetchAll(),
                'committee_members' => $pdo->query("SELECT id, full_name, staff_id FROM users WHERE role = 'committee' AND is_active = TRUE")->fetchAll(), 'sessions' => $pdo->query("SELECT * FROM academic_sessions ORDER BY start_date DESC")->fetchAll(), 'venues' => $pdo->query("SELECT * FROM venues WHERE is_active = TRUE ORDER BY name")->fetchAll()
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
            $stmt = $pdo->prepare("SELECT id, title, description, task_type, priority, status, due_date, case_id, incident_id FROM tasks WHERE assigned_to = ? AND status IN ('open','in_progress') ORDER BY due_date NULLS LAST, CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END LIMIT 8");
            $stmt->execute([$uid]); $tasks = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT id, type, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$uid]); $notifications = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT id, title, body, audience_role, created_at FROM announcements WHERE is_published = TRUE AND (audience_role IS NULL OR audience_role = ?) AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$role]); $announcements = $stmt->fetchAll();
            $hearingsSql = "SELECT h.id, h.scheduled_date, h.venue, h.status, c.id as case_id, c.case_no, i.student_name FROM hearings h JOIN cases c ON h.case_id = c.id JOIN incidents i ON c.incident_id = i.id WHERE h.scheduled_date >= NOW()";
            $hearingsParams = [];
            if ($role === 'committee') { $hearingsSql .= " AND c.assigned_committee = ?"; $hearingsParams[] = $uid; }
            $hearingsSql .= " ORDER BY h.scheduled_date ASC LIMIT 8";
            $stmt = $pdo->prepare($hearingsSql); $stmt->execute($hearingsParams); $hearings = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT l.id, l.case_id, l.action, l.note, l.logged_at, u.full_name as actor_name, c.case_no FROM case_logs l JOIN users u ON l.actor_id = u.id JOIN cases c ON l.case_id = c.id ORDER BY l.logged_at DESC LIMIT 8"); $stmt->execute(); $activity = $stmt->fetchAll();
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
            $stmt->execute([(int)$user['id']]); sendJson(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'update_task_status':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']); validateCSRF($pdo);
            $status = $input['status'] ?? 'in_progress';
            if (!in_array($status, ['open','in_progress','completed','dismissed'], true)) sendJson(['success' => false, 'message' => 'Invalid task status'], 422);
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END, updated_at = NOW() WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$status, $status, $input['task_id'], $user['id']]); sendJson(['success' => true, 'message' => 'Task updated']);
            break;

        case 'mark_notification_read':
            $user = requireRole(); $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?"); $stmt->execute([$input['id'], $user['id']]); sendJson(['success' => true]);
            break;

        case 'mark_all_notifications_read':
            $user = requireRole(); $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?"); $stmt->execute([$user['id']]); sendJson(['success' => true]);
            break;

        case 'get_case_timeline':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $caseId = (int)$input['case_id'];
            $stmt = $pdo->prepare("SELECT c.*, i.reference_no, i.student_name, i.student_matric, i.description, i.offence_type, i.exam_date, i.venue, co.code as course_code FROM cases c JOIN incidents i ON c.incident_id = i.id JOIN courses co ON i.course_id = co.id WHERE c.id = ?"); $stmt->execute([$caseId]); $case = $stmt->fetch();
            if (!$case) sendJson(['success' => false, 'message' => 'Case not found'], 404);
            $stmt = $pdo->prepare("SELECT l.*, u.full_name as actor_name FROM case_logs l JOIN users u ON l.actor_id = u.id WHERE l.case_id = ? ORDER BY l.logged_at ASC"); $stmt->execute([$caseId]); $logs = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT cc.*, u.full_name as author_name FROM case_comments cc JOIN users u ON cc.author_id = u.id WHERE cc.case_id = ? ORDER BY cc.created_at ASC"); $stmt->execute([$caseId]); $comments = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT a.*, u.full_name as submitter_name FROM appeals a JOIN users u ON a.submitted_by = u.id WHERE a.case_id = ? ORDER BY a.created_at DESC"); $stmt->execute([$caseId]); $appeals = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT * FROM hearings WHERE case_id = ? ORDER BY scheduled_date DESC"); $stmt->execute([$caseId]); $hearings = $stmt->fetchAll();
            sendJson(['success' => true, 'data' => compact('case','logs','comments','appeals','hearings')]);
            break;

        case 'add_case_comment':
            $user = requireRole(['admin', 'exam_officer', 'hod', 'committee']); validateCSRF($pdo);
            $stmt = $pdo->prepare("INSERT INTO case_comments (case_id, author_id, body, visibility) VALUES (?, ?, ?, ?)"); $stmt->execute([$input['case_id'], $user['id'], trim($input['body']), $input['visibility'] ?? 'internal']);
            $pdo->prepare("INSERT INTO case_logs (case_id, actor_id, action, note) VALUES (?, ?, 'note_added', ?)")->execute([$input['case_id'], $user['id'], trim($input['body'])]); sendJson(['success' => true, 'message' => 'Comment added']);
            break;

        case 'submit_appeal':
            $user = requireRole(['admin', 'exam_officer', 'hod', 'committee', 'invigilator']); validateCSRF($pdo);
            $stmt = $pdo->prepare("INSERT INTO appeals (case_id, submitted_by, grounds) VALUES (?, ?, ?)"); $stmt->execute([$input['case_id'], $user['id'], trim($input['grounds'])]);
            sendJson(['success' => true, 'message' => 'Appeal submitted']);
            break;

        case 'review_appeal':
            $user = requireRole(['admin', 'exam_officer', 'committee']); validateCSRF($pdo);
            $status = $input['status'] ?? 'under_review'; if (!in_array($status, ['under_review','approved','rejected','withdrawn'], true)) sendJson(['success'=>false,'message'=>'Invalid appeal status'],422);
            $stmt = $pdo->prepare("UPDATE appeals SET status = ?, decision = ?, decided_by = ?, decided_at = CASE WHEN ? IN ('approved','rejected') THEN NOW() ELSE NULL END, updated_at = NOW() WHERE id = ?"); $stmt->execute([$status, $input['decision'] ?? null, $user['id'], $status, $input['appeal_id']]); sendJson(['success' => true, 'message' => 'Appeal updated']);
            break;

        case 'get_calendar':
            $user = requireRole(['admin', 'exam_officer', 'invigilator', 'hod', 'committee']);
            $stmt = $pdo->prepare("SELECT h.id, 'hearing' as event_type, h.scheduled_date as starts_at, h.venue as location, c.case_no as title, h.status FROM hearings h JOIN cases c ON h.case_id = c.id WHERE h.scheduled_date >= NOW() - INTERVAL '30 days' ORDER BY h.scheduled_date ASC LIMIT 50"); $stmt->execute(); $events = $stmt->fetchAll();
            $stmt = $pdo->prepare("SELECT id, 'task' as event_type, due_date as starts_at, NULL as location, title, status FROM tasks WHERE assigned_to = ? AND due_date IS NOT NULL ORDER BY due_date ASC LIMIT 50"); $stmt->execute([$user['id']]); $events = array_merge($events, $stmt->fetchAll());
            $stmt = $pdo->query("SELECT id, 'session' as event_type, start_date as starts_at, NULL as location, CONCAT(name, ' — ', semester, ' Semester') as title, CASE WHEN is_active THEN 'active' ELSE 'planned' END as status FROM academic_sessions ORDER BY start_date DESC LIMIT 20"); $events = array_merge($events, $stmt->fetchAll());
            sendJson(['success' => true, 'data' => $events]);
            break;

        case 'get_drafts':
            $user = requireRole(['invigilator', 'exam_officer', 'admin']); $stmt = $pdo->prepare("SELECT id, title, progress, created_at, updated_at, payload FROM incident_drafts WHERE owner_id = ? ORDER BY updated_at DESC"); $stmt->execute([$user['id']]); sendJson(['success'=>true,'data'=>$stmt->fetchAll()]);
            break;

        case 'save_draft':
            $user = requireRole(['invigilator', 'exam_officer', 'admin']); validateCSRF($pdo); $payload = json_encode($input['payload'] ?? []);
            if (!empty($input['draft_id'])) { $stmt = $pdo->prepare("UPDATE incident_drafts SET title = ?, payload = ?::jsonb, progress = ?, updated_at = NOW() WHERE id = ? AND owner_id = ?"); $stmt->execute([$input['title'] ?? 'Untitled incident draft', $payload, $input['progress'] ?? 0, $input['draft_id'], $user['id']]); $id = $input['draft_id']; }
            else { $stmt = $pdo->prepare("INSERT INTO incident_drafts (owner_id, title, payload, progress) VALUES (?, ?, ?::jsonb, ?) RETURNING id"); $stmt->execute([$user['id'], $input['title'] ?? 'Untitled incident draft', $payload, $input['progress'] ?? 0]); $id = $stmt->fetchColumn(); }
            sendJson(['success'=>true,'id'=>$id,'message'=>'Draft saved']);
            break;

        case 'delete_draft':
            $user = requireRole(['invigilator', 'exam_officer', 'admin']); validateCSRF($pdo); $stmt = $pdo->prepare("DELETE FROM incident_drafts WHERE id = ? AND owner_id = ?"); $stmt->execute([$input['draft_id'], $user['id']]); sendJson(['success'=>true]);
            break;

        case 'get_preferences':
            $user = requireRole(); $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?"); $stmt->execute([$user['id']]); $pref = $stmt->fetch(); sendJson(['success'=>true,'data'=>$pref ?: ['default_view'=>'overview','notify_in_app'=>true,'notify_email'=>true,'compact_mode'=>false]]);
            break;

        case 'save_preferences':
            $user = requireRole(); validateCSRF($pdo); $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, default_view, notify_in_app, notify_email, compact_mode) VALUES (?, ?, ?, ?, ?) ON CONFLICT (user_id) DO UPDATE SET default_view=EXCLUDED.default_view, notify_in_app=EXCLUDED.notify_in_app, notify_email=EXCLUDED.notify_email, compact_mode=EXCLUDED.compact_mode, updated_at=NOW()"); $stmt->execute([$user['id'], $input['default_view'] ?? 'overview', !empty($input['notify_in_app']) ? 1 : 0, !empty($input['notify_email']) ? 1 : 0, !empty($input['compact_mode']) ? 1 : 0]); sendJson(['success'=>true,'message'=>'Preferences saved']);
            break;

        case 'create_announcement':
            $user = requireRole(['admin']); validateCSRF($pdo); $stmt = $pdo->prepare("INSERT INTO announcements (title, body, audience_role, created_by) VALUES (?, ?, ?, ?)"); $stmt->execute([$input['title'], $input['body'], $input['audience_role'] ?: null, $user['id']]); sendJson(['success'=>true,'message'=>'Announcement published']);
            break;

        case 'save_academic_session':
            $user = requireRole(['admin']); validateCSRF($pdo); $pdo->beginTransaction(); if (!empty($input['is_active'])) $pdo->exec("UPDATE academic_sessions SET is_active = FALSE"); $stmt = $pdo->prepare("INSERT INTO academic_sessions (name, semester, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?) ON CONFLICT (name, semester) DO UPDATE SET start_date=EXCLUDED.start_date, end_date=EXCLUDED.end_date, is_active=EXCLUDED.is_active"); $stmt->execute([$input['name'], $input['semester'], $input['start_date'], $input['end_date'], !empty($input['is_active'])]); $pdo->commit(); sendJson(['success'=>true,'message'=>'Academic session saved']);
            break;

        case 'review_incident':
            $user = requireRole(['admin', 'exam_officer']); validateCSRF($pdo);
            $decision = $input['decision'] ?? 'reviewed';
            $allowed = ['reviewed','returned','duplicate','closed','escalated'];
            if (!in_array($decision, $allowed, true)) sendJson(['success'=>false,'message'=>'Invalid triage decision'],422);
            $status = in_array($decision, ['closed','duplicate'], true) ? 'closed' : 'reviewed';
            $stmt = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?"); $stmt->execute([$status, $input['incident_id']]);
            $refStmt = $pdo->prepare("SELECT reference_no, reported_by FROM incidents WHERE id = ?"); $refStmt->execute([$input['incident_id']]); $inc = $refStmt->fetch();
            if ($inc) { notify($pdo, (int)$inc['reported_by'], 'system', 'Incident review updated', "{$inc['reference_no']} was marked {$decision}." . (!empty($input['note']) ? ' ' . $input['note'] : '')); }
            sendJson(['success'=>true,'message'=>'Triage decision saved']);
            break;

        case 'assign_case_officer':
            $user = requireRole(['admin','exam_officer']); validateCSRF($pdo);
            $stmt = $pdo->prepare("UPDATE cases SET assigned_officer = ? WHERE id = ?"); $stmt->execute([$input['officer_id'] ?: null, $input['case_id']]);
            if (!empty($input['officer_id'])) { $caseStmt=$pdo->prepare("SELECT case_no FROM cases WHERE id=?"); $caseStmt->execute([$input['case_id']]); notify($pdo,(int)$input['officer_id'],'system','Case assignment',"You have been assigned {$caseStmt->fetchColumn()}."); }
            sendJson(['success'=>true,'message'=>'Case officer assignment updated']);
            break;

        default:
            sendJson(['success' => false, 'message' => 'Invalid API Action'], 404);
    }
} catch (PDOException $e) {
    // Postgres uses SQLSTATE class '23' (23505 unique, 23503 FK, 23502 not-null, 23514 check)
    // for integrity constraint violations, vs MySQL's single generic '23000' code.
    $code = substr((string)$e->getCode(), 0, 2) === '23' ? 422 : 500;
    sendJson(['success' => false, 'message' => 'System Error', 'errors' => $e->getMessage()], $code);
}
?>