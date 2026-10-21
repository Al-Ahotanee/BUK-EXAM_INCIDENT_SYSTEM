<?php
// db.php
// PostgreSQL (Neon) connection + one-time schema bootstrap for the BUK EIMS platform.
// Bayero University Kano — Examination Incident Management System
declare(strict_types=1);

/**
 * Returns a shared PDO connection to the Postgres/Neon database.
 */
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $databaseUrl = getenv('DATABASE_URL') ?: '';

    if ($databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if ($parts === false || !isset($parts['host'])) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'DB Configuration Error', 'error' => 'Invalid DATABASE_URL']);
            exit;
        }
        $host   = $parts['host'];
        $port   = $parts['port'] ?? 5432;
        $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
        $user   = isset($parts['user']) ? rawurldecode($parts['user']) : '';
        $pass   = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';

        $queryParams = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
        }
        $sslmode = $queryParams['sslmode'] ?? 'require';
    } else {
        $host    = getenv('DB_HOST') ?: '127.0.0.1';
        $port    = getenv('DB_PORT') ?: '5432';
        $dbname  = getenv('DB_NAME') ?: 'buk_eims';
        $user    = getenv('DB_USER') ?: 'postgres';
        $pass    = getenv('DB_PASS') ?: '';
        $sslmode = getenv('DB_SSLMODE') ?: 'prefer';
    }

    try {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database Service Unavailable']);
        exit;
    }

    // Fast check: Only run schema initialization once if tables do not exist yet.
    static $schemaChecked = false;
    if (!$schemaChecked) {
        $hasSchema = false;
        try {
            $check = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'senate_memos' LIMIT 1");
            if ($check && $check->fetchColumn() !== false) {
                $hasSchema = true;
            }
        } catch (Throwable $_) {
            $hasSchema = false;
        }

        if (!$hasSchema) {
            init_schema($pdo);
        }
        $schemaChecked = true;
    }

    return $pdo;
}

/**
 * Creates all tables (idempotent), the updated_at trigger, and seeds demo data on first run.
 */
function init_schema(PDO $pdo): void {
    $statements = [
        "CREATE TABLE IF NOT EXISTS faculties (
            id SERIAL PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE,
            code VARCHAR(20) NOT NULL UNIQUE,
            dean_id INTEGER NULL,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS departments (
            id SERIAL PRIMARY KEY,
            faculty_id INTEGER NOT NULL REFERENCES faculties(id) ON DELETE RESTRICT,
            name VARCHAR(150) NOT NULL,
            code VARCHAR(20) NOT NULL,
            hod_id INTEGER NULL,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS courses (
            id SERIAL PRIMARY KEY,
            department_id INTEGER NOT NULL REFERENCES departments(id) ON DELETE RESTRICT,
            code VARCHAR(20) NOT NULL UNIQUE,
            title VARCHAR(200) NOT NULL,
            credit_units SMALLINT DEFAULT 2,
            level VARCHAR(3) NOT NULL CHECK (level IN ('100','200','300','400','500')),
            semester VARCHAR(6) NOT NULL CHECK (semester IN ('First','Second')),
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            staff_id VARCHAR(20) UNIQUE NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL CHECK (role IN ('admin','exam_officer','invigilator','hod','committee')),
            faculty_id INTEGER NULL REFERENCES faculties(id) ON DELETE RESTRICT,
            department_id INTEGER NULL REFERENCES departments(id) ON DELETE RESTRICT,
            phone VARCHAR(20) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS students (
            id SERIAL PRIMARY KEY,
            matric_no VARCHAR(30) UNIQUE NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            department_id INTEGER NOT NULL REFERENCES departments(id) ON DELETE RESTRICT,
            level VARCHAR(3) NOT NULL CHECK (level IN ('100','200','300','400','500')),
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS incidents (
            id SERIAL PRIMARY KEY,
            reference_no VARCHAR(30) UNIQUE NOT NULL,
            reported_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE RESTRICT,
            exam_date DATE NOT NULL,
            exam_time TIME NOT NULL,
            venue VARCHAR(100) NOT NULL,
            semester VARCHAR(6) NOT NULL CHECK (semester IN ('First','Second')),
            academic_session VARCHAR(20) NOT NULL,
            offence_type VARCHAR(30) NOT NULL CHECK (offence_type IN ('foreign_material','electronic_device','impersonation','collusion','assault','misconduct','other')),
            description TEXT NOT NULL,
            student_name VARCHAR(150) NOT NULL,
            student_matric VARCHAR(30) NOT NULL,
            student_dept_id INTEGER NOT NULL REFERENCES departments(id) ON DELETE RESTRICT,
            student_level VARCHAR(3) NOT NULL CHECK (student_level IN ('100','200','300','400','500')),
            status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','reviewed','case_opened','closed')),
            result_status VARCHAR(20) DEFAULT 'held' CHECK (result_status IN ('normal','held','nullified','released')),
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS incident_evidence (
            id SERIAL PRIMARY KEY,
            incident_id INTEGER NOT NULL REFERENCES incidents(id) ON DELETE RESTRICT,
            uploaded_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL UNIQUE,
            file_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INTEGER NOT NULL,
            file_hash VARCHAR(64) NULL,
            uploaded_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS senate_memos (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            academic_session VARCHAR(30) NOT NULL,
            semester VARCHAR(10) NOT NULL CHECK (semester IN ('First','Second')),
            meeting_number VARCHAR(50) NULL,
            memo_reference VARCHAR(50) UNIQUE NOT NULL,
            summary_statistics JSONB NOT NULL DEFAULT '{}'::jsonb,
            status VARCHAR(30) DEFAULT 'draft' CHECK (status IN ('draft','submitted','senate_approved','senate_amended','senate_rejected')),
            decision_date DATE NULL,
            decision_notes TEXT NULL,
            created_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS cases (
            id SERIAL PRIMARY KEY,
            case_no VARCHAR(30) UNIQUE NOT NULL,
            incident_id INTEGER UNIQUE NOT NULL REFERENCES incidents(id) ON DELETE RESTRICT,
            opened_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            assigned_officer INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT,
            assigned_committee INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT,
            tier VARCHAR(20) DEFAULT 'central' CHECK (tier IN ('departmental','faculty','central')),
            senate_memo_id INTEGER NULL REFERENCES senate_memos(id) ON DELETE SET NULL,
            stage VARCHAR(20) DEFAULT 'reported' CHECK (stage IN ('reported','under_review','investigation','hearing','resolved','dismissed')),
            priority VARCHAR(10) DEFAULT 'normal' CHECK (priority IN ('low','normal','high','urgent')),
            result_status VARCHAR(20) DEFAULT 'held' CHECK (result_status IN ('normal','held','nullified','released')),
            lockout_start DATE NULL,
            lockout_end DATE NULL,
            resolution_summary TEXT NULL,
            opened_at TIMESTAMP DEFAULT NOW(),
            resolved_at TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS evidence_lockers (
            id SERIAL PRIMARY KEY,
            evidence_id INTEGER NOT NULL REFERENCES incident_evidence(id) ON DELETE CASCADE,
            tag_barcode VARCHAR(50) UNIQUE NOT NULL,
            storage_location VARCHAR(150) NOT NULL,
            locker_safe VARCHAR(50) NOT NULL,
            custodian_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            status VARCHAR(30) DEFAULT 'deposited' CHECK (status IN ('deposited','in_transit','in_committee','disposed','archived')),
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS evidence_custody_transfers (
            id SERIAL PRIMARY KEY,
            locker_id INTEGER NOT NULL REFERENCES evidence_lockers(id) ON DELETE CASCADE,
            transferred_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            transferred_to INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            purpose TEXT NOT NULL,
            transferred_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS hall_security_alerts (
            id SERIAL PRIMARY KEY,
            reported_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            venue VARCHAR(100) NOT NULL,
            course_code VARCHAR(30) NULL,
            incident_type VARCHAR(50) NOT NULL CHECK (incident_type IN ('violence','riot_threat','impersonation_gang','invigilator_assault','mass_cheating','other')),
            urgency VARCHAR(20) DEFAULT 'critical' CHECK (urgency IN ('medium','high','critical')),
            description TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active','dispatched','resolved')),
            resolved_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            resolved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS exam_schedules (
            id SERIAL PRIMARY KEY,
            course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
            exam_date DATE NOT NULL,
            exam_time TIME NOT NULL,
            venue VARCHAR(100) NOT NULL,
            semester VARCHAR(10) NOT NULL CHECK (semester IN ('First','Second')),
            academic_session VARCHAR(30) NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS case_panel_members (
            id SERIAL PRIMARY KEY,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            panel_role VARCHAR(30) DEFAULT 'member' CHECK (panel_role IN ('chair','secretary','member')),
            assigned_at TIMESTAMP DEFAULT NOW(),
            UNIQUE(case_id, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS case_logs (
            id SERIAL PRIMARY KEY,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE RESTRICT,
            actor_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            action VARCHAR(30) NOT NULL CHECK (action IN ('case_opened','stage_advanced','stage_reverted','officer_assigned','committee_assigned','note_added','hearing_scheduled','hearing_updated','hearing_conducted','sanction_recorded','case_resolved','case_dismissed','evidence_added','incident_updated','tier_escalated','result_held','result_released','result_nullified')),
            from_stage VARCHAR(50) NULL,
            to_stage VARCHAR(50) NULL,
            note TEXT NULL,
            logged_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS hearings (
            id SERIAL PRIMARY KEY,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE RESTRICT,
            scheduled_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            scheduled_date TIMESTAMP NOT NULL,
            venue VARCHAR(200) NOT NULL,
            status VARCHAR(20) DEFAULT 'scheduled' CHECK (status IN ('scheduled','conducted','adjourned','cancelled')),
            outcome_notes TEXT NULL,
            adjourned_to TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS sanctions (
            id SERIAL PRIMARY KEY,
            case_id INTEGER UNIQUE NOT NULL REFERENCES cases(id) ON DELETE RESTRICT,
            recorded_by INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            sanction_type VARCHAR(30) NOT NULL CHECK (sanction_type IN ('warning','suspension_semester','suspension_year','expulsion','course_cancellation','other')),
            duration VARCHAR(100) NULL,
            description TEXT NOT NULL,
            effective_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS notifications (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
            type VARCHAR(30) NOT NULL CHECK (type IN ('incident_reported','case_opened','stage_advanced','hearing_scheduled','case_resolved','system','security_alert','senate_memo')),
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(500) NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS csrf_tokens (
            id SERIAL PRIMARY KEY,
            session_id VARCHAR(128) NOT NULL,
            token VARCHAR(128) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS tasks (
            id SERIAL PRIMARY KEY,
            assigned_to INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            case_id INTEGER NULL REFERENCES cases(id) ON DELETE CASCADE,
            incident_id INTEGER NULL REFERENCES incidents(id) ON DELETE CASCADE,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            task_type VARCHAR(40) DEFAULT 'workflow',
            priority VARCHAR(10) DEFAULT 'normal' CHECK (priority IN ('low','normal','high','urgent')),
            status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open','in_progress','completed','dismissed')),
            due_date TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS case_comments (
            id SERIAL PRIMARY KEY,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
            author_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            body TEXT NOT NULL,
            visibility VARCHAR(20) DEFAULT 'internal' CHECK (visibility IN ('internal','committee','official')),
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS appeals (
            id SERIAL PRIMARY KEY,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
            submitted_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            grounds TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'submitted' CHECK (status IN ('submitted','under_review','approved','rejected','withdrawn')),
            decision TEXT NULL,
            decided_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            decided_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS announcements (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            audience_role VARCHAR(30) NULL,
            is_published BOOLEAN DEFAULT TRUE,
            starts_at TIMESTAMP DEFAULT NOW(),
            ends_at TIMESTAMP NULL,
            created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS academic_sessions (
            id SERIAL PRIMARY KEY,
            name VARCHAR(30) NOT NULL,
            semester VARCHAR(10) NOT NULL CHECK (semester IN ('First','Second')),
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_active BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT NOW(),
            UNIQUE(name, semester)
        )",
        "CREATE TABLE IF NOT EXISTS venues (
            id SERIAL PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE,
            location VARCHAR(200) NULL,
            capacity INTEGER DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS incident_drafts (
            id SERIAL PRIMARY KEY,
            owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title VARCHAR(255) DEFAULT 'Untitled incident draft',
            payload JSONB NOT NULL DEFAULT '{}'::jsonb,
            progress SMALLINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS user_preferences (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            default_view VARCHAR(40) DEFAULT 'overview',
            notify_in_app BOOLEAN DEFAULT TRUE,
            notify_email BOOLEAN DEFAULT TRUE,
            compact_mode BOOLEAN DEFAULT FALSE,
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS account_requests (
            id SERIAL PRIMARY KEY,
            staff_id VARCHAR(20) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NULL,
            password_hash VARCHAR(255) NOT NULL,
            role_requested VARCHAR(20) NOT NULL CHECK (role_requested IN ('exam_officer','invigilator','hod','committee')),
            faculty_id INTEGER NULL REFERENCES faculties(id) ON DELETE RESTRICT,
            department_id INTEGER NULL REFERENCES departments(id) ON DELETE RESTRICT,
            justification TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
            decision_note TEXT NULL,
            reviewed_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NULL,
            entity_id INTEGER NULL,
            details JSONB NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT NOW()
        )",
        "CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS \$\$
         BEGIN
             NEW.updated_at = NOW();
             RETURN NEW;
         END;
         \$\$ LANGUAGE plpgsql",
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    // Safe column migrations for existing deployments
    $alterStatements = [
        "ALTER TABLE cases ADD COLUMN IF NOT EXISTS tier VARCHAR(20) DEFAULT 'central'",
        "ALTER TABLE cases ADD COLUMN IF NOT EXISTS senate_memo_id INTEGER NULL",
        "ALTER TABLE cases ADD COLUMN IF NOT EXISTS result_status VARCHAR(20) DEFAULT 'held'",
        "ALTER TABLE cases ADD COLUMN IF NOT EXISTS lockout_start DATE NULL",
        "ALTER TABLE cases ADD COLUMN IF NOT EXISTS lockout_end DATE NULL",
        "ALTER TABLE incidents ADD COLUMN IF NOT EXISTS result_status VARCHAR(20) DEFAULT 'held'",
    ];
    foreach ($alterStatements as $alter) {
        try {
            $pdo->exec($alter);
        } catch (Throwable $_) {}
    }

    foreach (['users', 'incidents', 'cases', 'hearings', 'senate_memos', 'evidence_lockers'] as $table) {
        $pdo->exec("DROP TRIGGER IF EXISTS trg_{$table}_updated_at ON {$table}");
        $pdo->exec("CREATE TRIGGER trg_{$table}_updated_at BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION set_updated_at()");
    }

    // --- Seed Data ---
    if ((int)$pdo->query("SELECT COUNT(*) FROM faculties")->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO faculties (name, code) VALUES ('Faculty of Computing & IT Sciences', 'FCIT'), ('Faculty of Natural & Applied Sciences', 'FNAS')");
        $pdo->exec("INSERT INTO departments (faculty_id, name, code) VALUES (1, 'Computer Science', 'CSC'), (1, 'Information Technology', 'ITN'), (2, 'Mathematics', 'MTH'), (2, 'Physics', 'PHY')");
        $pdo->exec("INSERT INTO courses (department_id, code, title, level, semester) VALUES (1, 'CSC 301', 'Data Structures', '300', 'First'), (1, 'CSC 401', 'Software Engineering', '400', 'First'), (1, 'CSC 201', 'OOP', '200', 'Second'), (2, 'ITN 301', 'Networking', '300', 'First'), (3, 'MTH 301', 'Calculus', '300', 'First'), (4, 'PHY 201', 'Mechanics', '200', 'First')");

        $pdo->exec("INSERT INTO students (matric_no, full_name, department_id, level) VALUES 
            ('CST/21/COM/00142', 'Ibrahim Musa Sani', 1, '300'),
            ('CST/22/IFT/00089', 'Zainab Abdullahi', 2, '200'),
            ('NAS/20/MTH/00055', 'Usman Bello Faruk', 3, '400')
            ON CONFLICT (matric_no) DO NOTHING");

        $hashes = [
            password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]),
            password_hash('Officer@1234', PASSWORD_BCRYPT, ['cost' => 12]),
            password_hash('Invigi@1234', PASSWORD_BCRYPT, ['cost' => 12]),
            password_hash('Hod@12345', PASSWORD_BCRYPT, ['cost' => 12]),
            password_hash('Commit@1234', PASSWORD_BCRYPT, ['cost' => 12]),
        ];

        $stmt = $pdo->prepare("INSERT INTO users (staff_id, full_name, email, password_hash, role, department_id) VALUES
            ('BUK/ADM/001', 'System Admin', 'admin@buk.edu.ng', ?, 'admin', NULL),
            ('BUK/REG/042', 'Dr. Aminu Yusuf', 'officer@buk.edu.ng', ?, 'exam_officer', NULL),
            ('BUK/ACA/101', 'Mr. Kabiru Umar', 'invigilator@buk.edu.ng', ?, 'invigilator', 1),
            ('BUK/ACA/005', 'Prof. Halima Bello', 'hod@buk.edu.ng', ?, 'hod', 1),
            ('BUK/ACA/088', 'Dr. Fatima Sule', 'committee@buk.edu.ng', ?, 'committee', 3)
        ");
        $stmt->execute($hashes);
        $pdo->exec("INSERT INTO academic_sessions (name, semester, start_date, end_date, is_active) VALUES ('2025/2026', 'First', '2025-10-01', '2026-03-31', TRUE) ON CONFLICT (name, semester) DO NOTHING");
        $pdo->exec("INSERT INTO venues (name, location, capacity) VALUES ('Senate Chamber', 'Main Campus', 120), ('Faculty Board Room', 'Academic Block', 40), ('Examination Hall A', 'New Campus', 300) ON CONFLICT (name) DO NOTHING");
        
        // Seed timetable exam schedules
        $pdo->exec("INSERT INTO exam_schedules (course_id, exam_date, exam_time, venue, semester, academic_session) VALUES
            (1, CURRENT_DATE, '09:00:00', 'Examination Hall A', 'First', '2025/2026'),
            (2, CURRENT_DATE, '14:00:00', 'Senate Chamber', 'First', '2025/2026'),
            (3, CURRENT_DATE + INTERVAL '1 day', '09:00:00', 'Faculty Board Room', 'Second', '2025/2026')");

        $pdo->exec("INSERT INTO user_preferences (user_id) SELECT id FROM users ON CONFLICT (user_id) DO NOTHING");
        $pdo->exec("INSERT INTO announcements (title, body, audience_role, created_by) SELECT 'Welcome to BUK EIMS', 'Use the new workspace to manage incidents, tasks, hearings, reports, and official communications in one place.', NULL, id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
        $pdo->exec("INSERT INTO audit_logs (user_id, action, entity_type, details) VALUES (1, 'system_initialized', 'system', '{\"message\": \"BUK EIMS initial schema and demo seed created successfully\"}'::jsonb)");
    }
}
