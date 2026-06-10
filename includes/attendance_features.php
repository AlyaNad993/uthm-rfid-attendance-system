<?php

function ensureAttendanceFeatureSchema(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [];
    foreach ($pdo->query("SHOW COLUMNS FROM attendance_sessions") as $column) {
        $columns[$column['Field']] = true;
    }

    if (!isset($columns['attendance_method'])) {
        try {
            $pdo->exec("ALTER TABLE attendance_sessions ADD attendance_method ENUM('rfid','qr','both') NOT NULL DEFAULT 'rfid' AFTER session_status");
        } catch (Throwable $e) {
        }
    }

    if (!isset($columns['qr_token'])) {
        try {
            $pdo->exec("ALTER TABLE attendance_sessions ADD qr_token VARCHAR(80) NULL UNIQUE AFTER attendance_method");
        } catch (Throwable $e) {
        }
    }

    if (!isset($columns['qr_expires_at'])) {
        try {
            $pdo->exec("ALTER TABLE attendance_sessions ADD qr_expires_at DATETIME NULL AFTER qr_token");
        } catch (Throwable $e) {
        }
    }

    try {
        $scheduleColumns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM class_schedule") as $column) {
            $scheduleColumns[$column['Field']] = true;
        }
        if (!isset($scheduleColumns['section_name'])) {
            $pdo->exec("ALTER TABLE class_schedule ADD section_name VARCHAR(30) NOT NULL DEFAULT 'Section 1' AFTER lecturer_id");
        }
        if (!isset($scheduleColumns['academic_year'])) {
            $pdo->exec("ALTER TABLE class_schedule ADD academic_year VARCHAR(20) NULL AFTER section_name");
        }
        if (!isset($scheduleColumns['semester_label'])) {
            $pdo->exec("ALTER TABLE class_schedule ADD semester_label VARCHAR(60) NULL AFTER academic_year");
        }
    } catch (Throwable $e) {
        // Non-fatal for existing installs.
    }

    try {
        $enrollmentColumns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM enrollments") as $column) {
            $enrollmentColumns[$column['Field']] = true;
        }
        if (!isset($enrollmentColumns['section_name'])) {
            $pdo->exec("ALTER TABLE enrollments ADD section_name VARCHAR(30) NOT NULL DEFAULT 'Section 1' AFTER course_id");
        }
        if (!isset($enrollmentColumns['academic_year'])) {
            $pdo->exec("ALTER TABLE enrollments ADD academic_year VARCHAR(20) NULL AFTER section_name");
        }
    } catch (Throwable $e) {
        // Non-fatal for existing installs.
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS warning_letters (
                letter_id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                student_id INT NOT NULL,
                lecturer_id INT NOT NULL,
                course_id INT NOT NULL,
                reason VARCHAR(255) NOT NULL,
                letter_content TEXT NOT NULL,
                generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status ENUM('draft','issued') NOT NULL DEFAULT 'issued',
                UNIQUE KEY unique_warning_per_session_student (session_id, student_id),
                INDEX idx_warning_student (student_id),
                INDEX idx_warning_session (session_id)
            )
        ");
    } catch (Throwable $e) {
        // Non-fatal for existing installs.
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS excuse_requests (
                excuse_id INT AUTO_INCREMENT PRIMARY KEY,
                record_id INT NOT NULL,
                session_id INT NOT NULL,
                student_id INT NOT NULL,
                course_id INT NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                notes TEXT NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME NULL,
                UNIQUE KEY unique_excuse_record_student (record_id, student_id),
                INDEX idx_excuse_student (student_id),
                INDEX idx_excuse_course (course_id),
                INDEX idx_excuse_status (status)
            )
        ");
    } catch (Throwable $e) {
        // Non-fatal for existing installs.
    }

    try {
        $excuseColumns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM excuse_requests") as $column) {
            $excuseColumns[$column['Field']] = true;
        }
        if (!isset($excuseColumns['excuse_type'])) {
            $pdo->exec("ALTER TABLE excuse_requests ADD excuse_type VARCHAR(40) NULL AFTER notes");
        }
    } catch (Throwable $e) {
        // Non-fatal for existing installs.
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                notification_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(100) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('attendance','system','alert','reminder') DEFAULT 'system',
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_unread (user_id, is_read),
                INDEX idx_created (created_at)
            )
        ");

        $notificationColumns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM notifications") as $column) {
            $notificationColumns[$column['Field']] = true;
        }
        if (!isset($notificationColumns['related_url'])) {
            $pdo->exec("ALTER TABLE notifications ADD related_url VARCHAR(255) NULL AFTER message");
        }
    } catch (Throwable $e) {
        // Non-fatal for existing installs.
    }
}

function generateQrToken(): string {
    return bin2hex(random_bytes(24));
}

function refreshSessionTotals(PDO $pdo, int $sessionId): void {
    $stmt = $pdo->prepare("
        UPDATE attendance_sessions
        SET
            total_present = (
                SELECT COUNT(*)
                FROM attendance_records
                WHERE session_id = ?
                  AND status = 'present'
            ),
            total_late = (
                0
            ),
            total_absent = (
                SELECT COUNT(*)
                FROM attendance_records
                WHERE session_id = ?
                  AND status = 'absent'
            )
        WHERE session_id = ?
    ");
    $stmt->execute([$sessionId, $sessionId, $sessionId]);
}

function profileImageUrl(?string $profileImage, string $name): string {
    $profileImage = trim((string)$profileImage);
    if ($profileImage !== '') {
        if (preg_match('/^https?:\/\//i', $profileImage)) {
            return $profileImage;
        }

        return '../' . ltrim($profileImage, '/');
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'Student') . '&background=4f46e5&color=fff&size=128';
}
