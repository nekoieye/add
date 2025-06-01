<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 핵심 함수
 * PSR-12 준수, 완전한 CRUD 기능 및 모니터링
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

// =====================================================
// 발급키 관리 함수
// =====================================================

/**
 * 발급키 목록 조회
 * 
 * @param string $search 검색어
 * @param string $status 상태 필터
 * @param string $validityPeriod 사용기간 필터
 * @param int $limit 조회 제한
 * @param int $offset 시작 위치
 * @return array 발급키 목록
 */
function getLicenseList(
    string $search = '',
    string $status = '',
    string $validityPeriod = '',
    int $limit = 0,
    int $offset = 0
): array {
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM v_dashboard_license_summary WHERE 1=1";
        $params = [];
        
        // 검색 조건
        if (!empty($search)) {
            $sql .= " AND (license_key LIKE ? OR company_name LIKE ? OR contact_person LIKE ? OR contact_email LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        // 상태 필터
        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        // 사용기간 필터
        if (!empty($validityPeriod)) {
            $sql .= " AND validity_period = ?";
            $params[] = $validityPeriod;
        }
        
        $sql .= " ORDER BY issued_at DESC";
        
        // 페이징
        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        $result = Database::executeQuery($connection, $sql, $params);
        
        $licenses = [];
        while ($row = $result->fetch_assoc()) {
            $licenses[] = $row;
        }
        
        return $licenses;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get license list', [
            'search' => $search,
            'status' => $status,
            'validity_period' => $validityPeriod,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 발급키 개별 조회
 * 
 * @param int $licenseId 발급키 ID
 * @return array|null 발급키 정보
 */
function getLicenseById(int $licenseId): ?array
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM v_dashboard_license_summary WHERE license_id = ?";
        $result = Database::executeQuery($connection, $sql, [$licenseId]);
        
        return $result->fetch_assoc() ?: null;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get license by ID', [
            'license_id' => $licenseId,
            'error' => $e->getMessage()
        ]);
        
        return null;
    }
}

/**
 * 발급키 생성
 * 
 * @param array $data 발급키 데이터
 * @return int|false 새로 생성된 발급키 ID 또는 false
 */
function createLicense(array $data)
{
    try {
        $connection = Database::getMainConnection();
        
        // 필수 필드 검증
        $requiredFields = ['license_key', 'company_name', 'contact_person', 'contact_email', 'validity_period'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("필수 필드가 누락되었습니다: {$field}");
            }
        }
        
        // 발급키 중복 검사
        $checkSql = "SELECT COUNT(*) as count FROM license_keys WHERE license_key = ?";
        $checkResult = Database::executeQuery($connection, $checkSql, [$data['license_key']]);
        $checkRow = $checkResult->fetch_assoc();
        
        if ($checkRow['count'] > 0) {
            throw new Exception("이미 존재하는 발급키입니다.");
        }
        
        // 만료일 계산
        $expiresAt = calculateExpiryDate($data['validity_period']);
        
        Database::beginTransaction($connection);
        
        $sql = "INSERT INTO license_keys (
            license_key, db_name, company_name, contact_person, contact_email, 
            contact_phone, license_type, validity_period, expires_at, 
            issued_by, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['license_key'],
            $data['db_name'] ?? null,
            $data['company_name'],
            $data['contact_person'],
            $data['contact_email'],
            $data['contact_phone'] ?? null,
            $data['license_type'] ?? 'ALL',
            $data['validity_period'],
            $expiresAt,
            $_SESSION['admin_user'] ?? 'ADMIN',
            $data['notes'] ?? null
        ];
        
        Database::executeQuery($connection, $sql, $params);
        $licenseId = Database::getLastInsertId($connection);
        
        // 관리자 액션 로그 기록
        logAdminAction('CREATE', 'LICENSE', $licenseId, '발급키 생성', null, $data);
        
        Database::commit($connection);
        
        writeLog('INFO', 'License created successfully', [
            'license_id' => $licenseId,
            'license_key' => $data['license_key'],
            'company_name' => $data['company_name']
        ]);
        
        return $licenseId;
        
    } catch (Exception $e) {
        if (isset($connection)) {
            Database::rollback($connection);
        }
        
        writeLog('ERROR', 'Failed to create license', [
            'data' => $data,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * 발급키 수정
 * 
 * @param int $licenseId 발급키 ID
 * @param array $data 수정할 데이터
 * @return bool 성공 여부
 */
function updateLicense(int $licenseId, array $data): bool
{
    try {
        $connection = Database::getMainConnection();
        
        // 기존 데이터 조회
        $oldData = getLicenseById($licenseId);
        if (!$oldData) {
            throw new Exception("존재하지 않는 발급키입니다.");
        }
        
        // 사용기간이 변경된 경우 만료일 재계산
        if (isset($data['validity_period']) && $data['validity_period'] !== $oldData['validity_period']) {
            $data['expires_at'] = calculateExpiryDate($data['validity_period']);
        }
        
        Database::beginTransaction($connection);
        
        // 동적 업데이트 쿼리 생성
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'license_key', 'db_name', 'company_name', 'contact_person', 
            'contact_email', 'contact_phone', 'license_type', 'validity_period', 
            'expires_at', 'status', 'notes'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("수정할 필드가 없습니다.");
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $licenseId;
        
        $sql = "UPDATE license_keys SET " . implode(', ', $updateFields) . " WHERE license_id = ?";
        
        Database::executeQuery($connection, $sql, $params);
        
        // 관리자 액션 로그 기록
        logAdminAction('UPDATE', 'LICENSE', $licenseId, '발급키 수정', $oldData, $data);
        
        Database::commit($connection);
        
        writeLog('INFO', 'License updated successfully', [
            'license_id' => $licenseId,
            'changes' => $data
        ]);
        
        return true;
        
    } catch (Exception $e) {
        if (isset($connection)) {
            Database::rollback($connection);
        }
        
        writeLog('ERROR', 'Failed to update license', [
            'license_id' => $licenseId,
            'data' => $data,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * 발급키 삭제
 * 
 * @param int $licenseId 발급키 ID
 * @return bool 성공 여부
 */
function deleteLicense(int $licenseId): bool
{
    try {
        $connection = Database::getMainConnection();
        
        // 기존 데이터 조회
        $licenseData = getLicenseById($licenseId);
        if (!$licenseData) {
            throw new Exception("존재하지 않는 발급키입니다.");
        }
        
        Database::beginTransaction($connection);
        
        // 관련 세션 정리
        $sql = "DELETE FROM license_sessions WHERE license_id = ?";
        Database::executeQuery($connection, $sql, [$licenseId]);
        
        // 발급키 삭제 (CASCADE로 관련 로그도 삭제됨)
        $sql = "DELETE FROM license_keys WHERE license_id = ?";
        Database::executeQuery($connection, $sql, [$licenseId]);
        
        // 관리자 액션 로그 기록
        logAdminAction('DELETE', 'LICENSE', $licenseId, '발급키 삭제', $licenseData, null);
        
        Database::commit($connection);
        
        writeLog('INFO', 'License deleted successfully', [
            'license_id' => $licenseId,
            'license_key' => $licenseData['license_key'],
            'company_name' => $licenseData['company_name']
        ]);
        
        return true;
        
    } catch (Exception $e) {
        if (isset($connection)) {
            Database::rollback($connection);
        }
        
        writeLog('ERROR', 'Failed to delete license', [
            'license_id' => $licenseId,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * 발급키 갱신 (빠른 갱신)
 * 
 * @param int $licenseId 발급키 ID
 * @param string $renewalPeriod 갱신 기간
 * @return bool 성공 여부
 */
function renewLicense(int $licenseId, string $renewalPeriod): bool
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "CALL sp_quick_renewal(?, ?, ?, ?)";
        $params = [
            $licenseId,
            $renewalPeriod,
            $_SESSION['session_id'] ?? 'UNKNOWN',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $result = Database::executeQuery($connection, $sql, $params);
        $renewalResult = $result->fetch_assoc();
        
        if ($renewalResult['result'] === 'SUCCESS') {
            writeLog('INFO', 'License renewed successfully', [
                'license_id' => $licenseId,
                'renewal_period' => $renewalPeriod,
                'new_expires_at' => $renewalResult['new_expires_at']
            ]);
            
            return true;
        } else {
            throw new Exception("갱신 처리 실패");
        }
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to renew license', [
            'license_id' => $licenseId,
            'renewal_period' => $renewalPeriod,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * 만료일 계산
 * 
 * @param string $validityPeriod 사용 기간
 * @return string 만료일시
 */
function calculateExpiryDate(string $validityPeriod): string
{
    $now = new DateTime();
    
    switch ($validityPeriod) {
        case '3일':
            return $now->add(new DateInterval('P3D'))->format('Y-m-d H:i:s');
        case '7일':
            return $now->add(new DateInterval('P7D'))->format('Y-m-d H:i:s');
        case '30일':
            return $now->add(new DateInterval('P30D'))->format('Y-m-d H:i:s');
        case '영구':
            return '2099-12-31 23:59:59';
        default:
            return $now->add(new DateInterval('P30D'))->format('Y-m-d H:i:s');
    }
}

/**
 * 남은 일수 계산
 * 
 * @param string $expiresAt 만료일시
 * @return int 남은 일수 (-1: 영구, 0: 만료)
 */
function calculateDaysRemaining(string $expiresAt): int
{
    if ($expiresAt === '2099-12-31 23:59:59') {
        return -1; // 영구 라이센스
    }
    
    $now = new DateTime();
    $expiry = new DateTime($expiresAt);
    $diff = $now->diff($expiry);
    
    if ($expiry < $now) {
        return 0; // 만료됨
    }
    
    return $diff->days;
}

// =====================================================
// 사용자 모니터링 함수
// =====================================================

/**
 * 사용자 인증 이력 조회
 * 
 * @param string $dbName 데이터베이스명
 * @param int $limit 조회 제한
 * @param array $filters 필터 조건
 * @return array 인증 이력
 */
function getUserAuthHistory(string $dbName = '', int $limit = 100, array $filters = []): array
{
    try {
        $authHistory = [];
        
        if (empty($dbName)) {
            // 모든 클라이언트 DB에서 조회
            $databases = Database::getConnectedDatabases();
            
            foreach ($databases as $db) {
                $history = getUserAuthHistoryFromDb($db, $limit, $filters);
                $authHistory = array_merge($authHistory, $history);
            }
        } else {
            // 특정 DB에서만 조회
            $authHistory = getUserAuthHistoryFromDb($dbName, $limit, $filters);
        }
        
        // 시간순으로 정렬
        usort($authHistory, function ($a, $b) {
            return strtotime($b['auth_time']) - strtotime($a['auth_time']);
        });
        
        return array_slice($authHistory, 0, $limit);
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get user auth history', [
            'db_name' => $dbName,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 특정 DB에서 인증 이력 조회
 * 
 * @param string $dbName 데이터베이스명
 * @param int $limit 조회 제한
 * @param array $filters 필터 조건
 * @return array 인증 이력
 */
function getUserAuthHistoryFromDb(string $dbName, int $limit, array $filters): array
{
    try {
        $connection = Database::getClientConnection($dbName);
        
        // user_auth_history 테이블이 존재하는지 확인
        $checkSql = "SHOW TABLES LIKE 'user_auth_history'";
        $checkResult = Database::executeQuery($connection, $checkSql);
        
        if ($checkResult->num_rows === 0) {
            return []; // 테이블이 없으면 빈 배열 반환
        }
        
        $sql = "SELECT * FROM user_auth_history WHERE 1=1";
        $params = [];
        
        // 필터 적용
        if (!empty($filters['start_date'])) {
            $sql .= " AND auth_time >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND auth_time <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['result'])) {
            $sql .= " AND auth_result = ?";
            $params[] = $filters['result'];
        }
        
        $sql .= " ORDER BY auth_time DESC LIMIT ?";
        $params[] = $limit;
        
        $result = Database::executeQuery($connection, $sql, $params);
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $row['db_name'] = $dbName; // DB 정보 추가
            $history[] = $row;
        }
        
        return $history;
        
    } catch (Exception $e) {
        writeLog('WARNING', 'Failed to get auth history from specific DB', [
            'db_name' => $dbName,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 인증 통계 조회
 * 
 * @param string $dbName 데이터베이스명
 * @return array 통계 정보
 */
function getAuthStatistics(string $dbName = ''): array
{
    try {
        $statistics = [];
        
        if (empty($dbName)) {
            // 모든 클라이언트 DB 통계
            $databases = Database::getConnectedDatabases();
            
            foreach ($databases as $db) {
                $stats = getAuthStatisticsFromDb($db);
                if (!empty($stats)) {
                    $statistics[$db] = $stats;
                }
            }
        } else {
            // 특정 DB 통계
            $statistics[$dbName] = getAuthStatisticsFromDb($dbName);
        }
        
        return $statistics;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get auth statistics', [
            'db_name' => $dbName,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 특정 DB에서 인증 통계 조회
 * 
 * @param string $dbName 데이터베이스명
 * @return array 통계 정보
 */
function getAuthStatisticsFromDb(string $dbName): array
{
    try {
        $connection = Database::getClientConnection($dbName);
        
        // 기본 통계 쿼리
        $sql = "SELECT 
            COUNT(*) as total_attempts,
            SUM(CASE WHEN auth_result = 'SUCCESS' THEN 1 ELSE 0 END) as successful_attempts,
            SUM(CASE WHEN auth_result != 'SUCCESS' THEN 1 ELSE 0 END) as failed_attempts,
            COUNT(DISTINCT user_id) as unique_users,
            MAX(auth_time) as last_auth_time
        FROM user_auth_history 
        WHERE auth_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $result = Database::executeQuery($connection, $sql);
        $stats = $result->fetch_assoc();
        
        // 시간대별 통계
        $hourlySql = "SELECT 
            HOUR(auth_time) as hour,
            COUNT(*) as attempts
        FROM user_auth_history 
        WHERE auth_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(auth_time)
        ORDER BY hour";
        
        $hourlyResult = Database::executeQuery($connection, $hourlySql);
        $hourlyStats = [];
        while ($row = $hourlyResult->fetch_assoc()) {
            $hourlyStats[$row['hour']] = $row['attempts'];
        }
        
        $stats['hourly_stats'] = $hourlyStats;
        
        return $stats;
        
    } catch (Exception $e) {
        writeLog('WARNING', 'Failed to get auth statistics from specific DB', [
            'db_name' => $dbName,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 통합 사용자 데이터 집계
 * 
 * @return array 집계된 데이터
 */
function aggregateUserData(): array
{
    try {
        $databases = Database::getConnectedDatabases();
        $aggregatedData = [
            'total_databases' => count($databases),
            'total_users' => 0,
            'total_auth_attempts' => 0,
            'successful_attempts' => 0,
            'failed_attempts' => 0,
            'active_users_24h' => 0,
            'database_stats' => []
        ];
        
        foreach ($databases as $dbName) {
            $stats = getAuthStatisticsFromDb($dbName);
            
            if (!empty($stats)) {
                $aggregatedData['total_auth_attempts'] += $stats['total_attempts'];
                $aggregatedData['successful_attempts'] += $stats['successful_attempts'];
                $aggregatedData['failed_attempts'] += $stats['failed_attempts'];
                $aggregatedData['total_users'] += $stats['unique_users'];
                
                $aggregatedData['database_stats'][$dbName] = $stats;
            }
        }
        
        return $aggregatedData;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to aggregate user data', [
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

// =====================================================
// 유틸리티 함수
// =====================================================

/**
 * 날짜/시간 포맷팅
 * 
 * @param string|null $datetime 날짜/시간 문자열
 * @param string $format 포맷
 * @return string 포맷된 날짜/시간
 */
function formatDateTime(?string $datetime, string $format = 'Y-m-d H:i:s'): string
{
    if (empty($datetime)) {
        return '-';
    }
    
    try {
        $date = new DateTime($datetime);
        return $date->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * 상태 배지 생성
 * 
 * @param string $status 상태
 * @return string HTML 배지
 */
function getStatusBadge(string $status): string
{
    $badges = [
        'ACTIVE' => '<span class="badge bg-success">활성</span>',
        'SUSPENDED' => '<span class="badge bg-warning">정지</span>',
        'EXPIRED' => '<span class="badge bg-danger">만료</span>',
        'REVOKED' => '<span class="badge bg-secondary">취소</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">알 수 없음</span>';
}

/**
 * 사용기간 텍스트 반환
 * 
 * @param string $period 사용기간
 * @return string 텍스트
 */
function getValidityPeriodText(string $period): string
{
    $periods = [
        '3일' => '3일',
        '7일' => '7일', 
        '30일' => '30일',
        '영구' => '영구'
    ];
    
    return $periods[$period] ?? $period;
}

/**
 * 남은 일수 배지 생성
 * 
 * @param int $days 남은 일수
 * @return string HTML 배지
 */
function getDaysRemainingBadge(int $days): string
{
    if ($days === -1) {
        return '<span class="badge bg-success">영구</span>';
    } elseif ($days === 0) {
        return '<span class="badge bg-danger">만료</span>';
    } elseif ($days <= 3) {
        return '<span class="badge bg-warning text-dark">' . $days . '일 남음</span>';
    } elseif ($days <= 7) {
        return '<span class="badge bg-info">' . $days . '일 남음</span>';
    } else {
        return '<span class="badge bg-primary">' . $days . '일 남음</span>';
    }
}

/**
 * 입력값 정화
 * 
 * @param mixed $input 입력값
 * @param string $type 타입
 * @return mixed 정화된 값
 */
function sanitizeInput($input, string $type = 'string')
{
    if ($input === null) {
        return null;
    }
    
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int) $input : 0;
            
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float) $input : 0.0;
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false ? $input : '';
            
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) !== false ? $input : '';
            
        case 'string':
        default:
            return htmlspecialchars(trim((string) $input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * CSRF 토큰 생성
 * 
 * @return string CSRF 토큰
 */
function generateCSRFToken(): string
{
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$token] = time() + CSRF_TOKEN_LIFETIME;
    
    // 만료된 토큰 정리
    foreach ($_SESSION['csrf_tokens'] as $t => $expiry) {
        if ($expiry < time()) {
            unset($_SESSION['csrf_tokens'][$t]);
        }
    }
    
    return $token;
}

/**
 * CSRF 토큰 검증
 * 
 * @param string $token 검증할 토큰
 * @return bool 유효 여부
 */
function validateCSRFToken(string $token): bool
{
    if (!isset($_SESSION['csrf_tokens'][$token])) {
        return false;
    }
    
    if ($_SESSION['csrf_tokens'][$token] < time()) {
        unset($_SESSION['csrf_tokens'][$token]);
        return false;
    }
    
    unset($_SESSION['csrf_tokens'][$token]);
    return true;
}

/**
 * CSRF 토큰 검증 (별칭 함수)
 * 
 * @param string $token 검증할 토큰
 * @return bool 유효 여부
 */
function verifyCSRFToken(string $token): bool
{
    return validateCSRFToken($token);
}

/**
 * 시스템 로그 기록
 * 
 * @param string $level 로그 레벨
 * @param string $category 로그 카테고리
 * @param string $message 로그 메시지
 * @param array $context 컨텍스트 데이터
 * @param int|null $relatedLicenseId 관련 발급키 ID
 * @return bool 성공 여부
 */
function logSystemEvent(
    string $level,
    string $category,
    string $message,
    array $context = [],
    ?int $relatedLicenseId = null
): bool {
    try {
        $connection = Database::getMainConnection();
        
        $sql = "INSERT INTO system_logs (
            log_level, log_category, log_message, log_context,
            related_license_id, session_id, client_ip, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $level,
            $category,
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            $relatedLicenseId,
            $_SESSION['session_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        Database::executeQuery($connection, $sql, $params);
        
        return true;
        
    } catch (Exception $e) {
        // 시스템 로그 기록 실패 시 파일 로그에 기록
        writeLog('ERROR', 'Failed to log system event', [
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * DB 연결 상태 업데이트
 * 
 * @param string $dbName DB명
 * @param string $connectionResult 연결 결과
 * @param int|null $connectionTimeMs 연결 시간
 * @param string|null $errorMessage 에러 메시지
 * @return bool 성공 여부
 */
function updateDbConnectionStatus(
    string $dbName,
    string $connectionResult,
    ?int $connectionTimeMs = null,
    ?string $errorMessage = null
): bool {
    try {
        $connection = Database::getMainConnection();
        
        $sql = "CALL sp_update_db_connection_status(?, ?, ?, ?)";
        $params = [$dbName, $connectionResult, $connectionTimeMs, $errorMessage];
        
        Database::executeQuery($connection, $sql, $params);
        
        return true;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to update DB connection status', [
            'db_name' => $dbName,
            'connection_result' => $connectionResult,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * DB 연결 상태 조회
 * 
 * @return array DB 연결 상태 목록
 */
function getDbConnectionStatuses(): array
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM db_connection_status ORDER BY last_checked_at DESC";
        $result = Database::executeQuery($connection, $sql);
        
        $statuses = [];
        while ($row = $result->fetch_assoc()) {
            $statuses[] = $row;
        }
        
        return $statuses;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get DB connection statuses', [
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 라이센스 세션 생성
 * 
 * @param int $licenseId 발급키 ID
 * @param string $sessionId 세션 ID
 * @param array $sessionData 세션 데이터
 * @return bool 성공 여부
 */
function createLicenseSession(int $licenseId, string $sessionId, array $sessionData = []): bool
{
    try {
        $connection = Database::getMainConnection();
        
        Database::beginTransaction($connection);
        
        // 기존 세션 정리 (만료된 것들)
        $cleanupSql = "DELETE FROM license_sessions WHERE expires_at < NOW()";
        Database::executeQuery($connection, $cleanupSql);
        
        // 새 세션 생성
        $sql = "INSERT INTO license_sessions (
            session_id, license_id, session_data, client_ip, user_agent,
            started_at, last_activity, expires_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))";
        
        $params = [
            $sessionId,
            $licenseId,
            json_encode($sessionData, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        Database::executeQuery($connection, $sql, $params);
        
        // 현재 세션 수 업데이트
        $updateSql = "UPDATE license_keys SET 
                      current_sessions = (
                          SELECT COUNT(*) FROM license_sessions 
                          WHERE license_id = ? AND is_active = TRUE AND expires_at > NOW()
                      )
                      WHERE license_id = ?";
        Database::executeQuery($connection, $updateSql, [$licenseId, $licenseId]);
        
        Database::commit($connection);
        
        return true;
        
    } catch (Exception $e) {
        if (isset($connection)) {
            Database::rollback($connection);
        }
        
        writeLog('ERROR', 'Failed to create license session', [
            'license_id' => $licenseId,
            'session_id' => $sessionId,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * 라이센스 세션 종료
 * 
 * @param string $sessionId 세션 ID
 * @return bool 성공 여부
 */
function endLicenseSession(string $sessionId): bool
{
    try {
        $connection = Database::getMainConnection();
        
        Database::beginTransaction($connection);
        
        // 세션 정보 조회
        $selectSql = "SELECT license_id FROM license_sessions WHERE session_id = ?";
        $result = Database::executeQuery($connection, $selectSql, [$sessionId]);
        $session = $result->fetch_assoc();
        
        if (!$session) {
            Database::rollback($connection);
            return false;
        }
        
        // 세션 비활성화
        $updateSql = "UPDATE license_sessions SET is_active = FALSE WHERE session_id = ?";
        Database::executeQuery($connection, $updateSql, [$sessionId]);
        
        // 현재 세션 수 업데이트
        $licenseUpdateSql = "UPDATE license_keys SET 
                             current_sessions = (
                                 SELECT COUNT(*) FROM license_sessions 
                                 WHERE license_id = ? AND is_active = TRUE AND expires_at > NOW()
                             )
                             WHERE license_id = ?";
        Database::executeQuery($connection, $licenseUpdateSql, [$session['license_id'], $session['license_id']]);
        
        Database::commit($connection);
        
        return true;
        
    } catch (Exception $e) {
        if (isset($connection)) {
            Database::rollback($connection);
        }
        
        writeLog('ERROR', 'Failed to end license session', [
            'session_id' => $sessionId,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * 라이센스 상태 변경 및 이력 기록
 * 
 * @param int $licenseId 발급키 ID
 * @param string $newStatus 새로운 상태
 * @param string|null $changeReason 변경 사유
 * @return bool 성공 여부
 */
function changeLicenseStatus(int $licenseId, string $newStatus, ?string $changeReason = null): bool
{
    try {
        $connection = Database::getMainConnection();
        
        // 현재 상태 조회
        $currentData = getLicenseById($licenseId);
        if (!$currentData) {
            throw new Exception("존재하지 않는 발급키입니다.");
        }
        
        $oldStatus = $currentData['status'];
        
        if ($oldStatus === $newStatus) {
            return true; // 동일한 상태로 변경 시도
        }
        
        Database::beginTransaction($connection);
        
        // 상태 업데이트
        $updateSql = "UPDATE license_keys SET status = ?, updated_at = NOW() WHERE license_id = ?";
        Database::executeQuery($connection, $updateSql, [$newStatus, $licenseId]);
        
        // 상태 변경 이력 기록
        $historySql = "INSERT INTO license_status_history (
            license_id, previous_status, new_status, change_reason,
            changed_by, client_ip
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $historyParams = [
            $licenseId,
            $oldStatus,
            $newStatus,
            $changeReason,
            $_SESSION['admin_user'] ?? 'ADMIN',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        Database::executeQuery($connection, $historySql, $historyParams);
        
        // 관리자 액션 로그 기록
        logAdminAction(
            'UPDATE',
            'LICENSE',
            $licenseId,
            "라이센스 상태 변경: {$oldStatus} -> {$newStatus}",
            ['status' => $oldStatus],
            ['status' => $newStatus, 'change_reason' => $changeReason]
        );
        
        Database::commit($connection);
        
        writeLog('INFO', 'License status changed', [
            'license_id' => $licenseId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'change_reason' => $changeReason
        ]);
        
        return true;
        
    } catch (Exception $e) {
        if (isset($connection)) {
            Database::rollback($connection);
        }
        
        writeLog('ERROR', 'Failed to change license status', [
            'license_id' => $licenseId,
            'new_status' => $newStatus,
            'change_reason' => $changeReason,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * 시스템 통계 조회
 * 
 * @return array 시스템 통계
 */
function getSystemStatistics(): array
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM v_system_statistics";
        $result = Database::executeQuery($connection, $sql);
        $stats = $result->fetch_assoc();
        
        return $stats ?: [];
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get system statistics', [
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 최근 활동 조회
 * 
 * @param int $limit 조회 제한
 * @return array 최근 활동 목록
 */
function getRecentActivities(int $limit = 50): array
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM v_recent_activities LIMIT ?";
        $result = Database::executeQuery($connection, $sql, [$limit]);
        
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        return $activities;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get recent activities', [
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 라이센스 접근 로그 기록
 * 
 * @param int $licenseId 발급키 ID
 * @param string $accessResult 접근 결과
 * @param string|null $sessionId 세션 ID
 * @param int|null $responseTimeMs 응답 시간
 * @return bool 성공 여부
 */
function logLicenseAccess(
    int $licenseId,
    string $accessResult,
    ?string $sessionId = null,
    ?int $responseTimeMs = null
): bool {
    try {
        $connection = Database::getMainConnection();
        
        $sql = "INSERT INTO license_access_logs (
            license_id, access_ip, user_agent, access_result,
            session_id, response_time_ms
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $licenseId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $accessResult,
            $sessionId,
            $responseTimeMs
        ];
        
        Database::executeQuery($connection, $sql, $params);
        
        // 접근 횟수 업데이트 (성공한 경우만)
        if ($accessResult === 'SUCCESS') {
            $updateSql = "UPDATE license_keys SET 
                          access_count = access_count + 1,
                          last_accessed = NOW()
                          WHERE license_id = ?";
            Database::executeQuery($connection, $updateSql, [$licenseId]);
        }
        
        return true;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to log license access', [
            'license_id' => $licenseId,
            'access_result' => $accessResult,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}

/**
 * 만료 예정 라이센스 조회
 * 
 * @param int $days 며칠 이내 만료 예정
 * @return array 만료 예정 라이센스 목록
 */
function getExpiringLicenses(int $days = 7): array
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM v_dashboard_license_summary 
                WHERE validity_period != '영구' 
                AND expires_at > NOW() 
                AND expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
                AND status = 'ACTIVE'
                ORDER BY expires_at ASC";
        
        $result = Database::executeQuery($connection, $sql, [$days]);
        
        $licenses = [];
        while ($row = $result->fetch_assoc()) {
            $licenses[] = $row;
        }
        
        return $licenses;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get expiring licenses', [
            'days' => $days,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 라이센스 갱신 이력 조회
 * 
 * @param int $licenseId 발급키 ID
 * @return array 갱신 이력
 */
function getLicenseRenewalHistory(int $licenseId): array
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM license_renewals 
                WHERE license_id = ? 
                ORDER BY renewed_at DESC";
        
        $result = Database::executeQuery($connection, $sql, [$licenseId]);
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        return $history;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get license renewal history', [
            'license_id' => $licenseId,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 라이센스 상태 변경 이력 조회
 * 
 * @param int $licenseId 발급키 ID
 * @return array 상태 변경 이력
 */
function getLicenseStatusHistory(int $licenseId): array
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM license_status_history 
                WHERE license_id = ? 
                ORDER BY changed_at DESC";
        
        $result = Database::executeQuery($connection, $sql, [$licenseId]);
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        return $history;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get license status history', [
            'license_id' => $licenseId,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 관리자 액션 로그 조회
 * 
 * @param array $filters 필터 조건
 * @param int $limit 조회 제한
 * @param int $offset 시작 위치
 * @return array 관리자 액션 로그
 */
function getAdminActionLogs(array $filters = [], int $limit = 100, int $offset = 0): array
{
    try {
        $connection = Database::getMainConnection();
        
        $sql = "SELECT * FROM admin_action_logs WHERE 1=1";
        $params = [];
        
        // 필터 적용
        if (!empty($filters['action_type'])) {
            $sql .= " AND action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['target_type'])) {
            $sql .= " AND target_type = ?";
            $params[] = $filters['target_type'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $result = Database::executeQuery($connection, $sql, $params);
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        return $logs;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to get admin action logs', [
            'filters' => $filters,
            'error' => $e->getMessage()
        ]);
        
        return [];
    }
}

/**
 * 관리자 액션 로그 기록
 * 
 * @param string $actionType 액션 타입 (CREATE, UPDATE, DELETE, etc.)
 * @param string $targetType 대상 타입 (LICENSE, USER, SYSTEM, etc.)
 * @param int|null $targetId 대상 ID
 * @param string $description 액션 설명
 * @param array|null $oldData 변경 전 데이터
 * @param array|null $newData 변경 후 데이터
 * @return bool 성공 여부
 */
function logAdminAction(
    string $actionType,
    string $targetType,
    ?int $targetId = null,
    string $description = '',
    ?array $oldData = null,
    ?array $newData = null
): bool {
    try {
        $connection = Database::getMainConnection();
        
        $sql = "INSERT INTO admin_action_logs (
            action_type, target_type, target_id, description,
            old_data, new_data, admin_user, session_id,
            client_ip, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $actionType,
            $targetType,
            $targetId,
            $description,
            $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            $_SESSION['admin_user'] ?? 'ADMIN',
            $_SESSION['session_id'] ?? session_id(),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        Database::executeQuery($connection, $sql, $params);
        
        return true;
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to log admin action', [
            'action_type' => $actionType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'description' => $description,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}