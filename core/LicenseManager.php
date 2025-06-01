<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 라이센스 매니저 클래스
 * PSR-12 준수, 데이터베이스 스키마 100% 활용
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

class LicenseManager
{
    private mysqli $connection;
    private array $validityPeriods = ['3일', '7일', '30일', '영구'];
    private array $licenseTypes = ['G2B_A', 'G2B_B', 'G2B_C', 'EAT', 'ALL'];
    private array $statusTypes = ['ACTIVE', 'SUSPENDED', 'EXPIRED', 'REVOKED'];
    
    public function __construct()
    {
        $this->connection = Database::getMainConnection();
    }
    
    /**
     * 발급키 목록 조회 (뷰 활용)
     * 
     * @param array $filters 필터 조건
     * @param int $limit 조회 제한
     * @param int $offset 시작 위치
     * @return array 발급키 목록
     */
    public function getLicenseList(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        try {
            $sql = "SELECT * FROM v_dashboard_license_summary WHERE 1=1";
            $params = [];
            
            // 검색 조건 처리
            if (!empty($filters['search'])) {
                $sql .= " AND (license_key LIKE ? OR company_name LIKE ? OR contact_person LIKE ? OR contact_email LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            // 상태 필터
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            // 사용기간 필터
            if (!empty($filters['validity_period'])) {
                $sql .= " AND validity_period = ?";
                $params[] = $filters['validity_period'];
            }
            
            // 라이센스 타입 필터
            if (!empty($filters['license_type'])) {
                $sql .= " AND license_type = ?";
                $params[] = $filters['license_type'];
            }
            
            // 만료 상태 필터
            if (!empty($filters['expiry_status'])) {
                $sql .= " AND expiry_status = ?";
                $params[] = $filters['expiry_status'];
            }
            
            // DB 연결 상태 필터
            if (!empty($filters['db_connection_status'])) {
                $sql .= " AND db_connection_status = ?";
                $params[] = $filters['db_connection_status'];
            }
            
            // 정렬
            $orderBy = $filters['order_by'] ?? 'issued_at';
            $orderDir = $filters['order_dir'] ?? 'DESC';
            $sql .= " ORDER BY {$orderBy} {$orderDir}";
            
            // 페이징
            if ($limit > 0) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
            }
            
            $result = Database::executeQuery($this->connection, $sql, $params);
            
            $licenses = [];
            while ($row = $result->fetch_assoc()) {
                $licenses[] = $this->formatLicenseData($row);
            }
            
            return $licenses;
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to get license list', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("발급키 목록 조회 중 오류가 발생했습니다: " . $e->getMessage());
        }
    }
    
    /**
     * 발급키 개수 조회
     * 
     * @param array $filters 필터 조건
     * @return int 전체 개수
     */
    public function getLicenseCount(array $filters = []): int
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM v_dashboard_license_summary WHERE 1=1";
            $params = [];
            
            // 동일한 필터 조건 적용
            if (!empty($filters['search'])) {
                $sql .= " AND (license_key LIKE ? OR company_name LIKE ? OR contact_person LIKE ? OR contact_email LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['validity_period'])) {
                $sql .= " AND validity_period = ?";
                $params[] = $filters['validity_period'];
            }
            
            if (!empty($filters['license_type'])) {
                $sql .= " AND license_type = ?";
                $params[] = $filters['license_type'];
            }
            
            if (!empty($filters['expiry_status'])) {
                $sql .= " AND expiry_status = ?";
                $params[] = $filters['expiry_status'];
            }
            
            if (!empty($filters['db_connection_status'])) {
                $sql .= " AND db_connection_status = ?";
                $params[] = $filters['db_connection_status'];
            }
            
            $result = Database::executeQuery($this->connection, $sql, $params);
            $row = $result->fetch_assoc();
            
            return (int) $row['total'];
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to get license count', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }
    
    /**
     * 발급키 상세 조회
     * 
     * @param int $licenseId 발급키 ID
     * @return array|null 발급키 정보
     */
    public function getLicenseById(int $licenseId): ?array
    {
        try {
            $sql = "SELECT * FROM v_dashboard_license_summary WHERE license_id = ?";
            $result = Database::executeQuery($this->connection, $sql, [$licenseId]);
            
            $license = $result->fetch_assoc();
            return $license ? $this->formatLicenseData($license) : null;
            
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
     * @return int 새로 생성된 발급키 ID
     */
    public function createLicense(array $data): int
    {
        try {
            // 유효성 검사
            $this->validateLicenseData($data);
            
            // 중복 검사
            if ($this->isDuplicateLicenseKey($data['license_key'])) {
                throw new Exception('이미 존재하는 발급키입니다.');
            }
            
            Database::beginTransaction($this->connection);
            
            // 만료일 계산
            $expiresAt = $this->calculateExpiryDate($data['validity_period']);
            
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
            
            Database::executeQuery($this->connection, $sql, $params);
            $licenseId = Database::getLastInsertId($this->connection);
            
            // 관리자 액션 로그 기록
            $this->logAdminAction('CREATE', $licenseId, '발급키 생성', null, $data);
            
            Database::commit($this->connection);
            
            writeLog('INFO', 'License created successfully', [
                'license_id' => $licenseId,
                'license_key' => $data['license_key']
            ]);
            
            return $licenseId;
            
        } catch (Exception $e) {
            Database::rollback($this->connection);
            
            writeLog('ERROR', 'Failed to create license', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("발급키 생성 중 오류가 발생했습니다: " . $e->getMessage());
        }
    }
    
    /**
     * 발급키 수정
     * 
     * @param int $licenseId 발급키 ID
     * @param array $data 수정할 데이터
     * @return bool 성공 여부
     */
    public function updateLicense(int $licenseId, array $data): bool
    {
        try {
            // 기존 데이터 조회
            $oldData = $this->getLicenseById($licenseId);
            if (!$oldData) {
                throw new Exception('존재하지 않는 발급키입니다.');
            }
            
            // 유효성 검사
            $this->validateLicenseData($data, $licenseId);
            
            // 중복 검사 (자기 자신 제외)
            if (isset($data['license_key']) && $data['license_key'] !== $oldData['license_key']) {
                if ($this->isDuplicateLicenseKey($data['license_key'], $licenseId)) {
                    throw new Exception('이미 존재하는 발급키입니다.');
                }
            }
            
            Database::beginTransaction($this->connection);
            
            // 사용기간이 변경된 경우 만료일 재계산
            if (isset($data['validity_period']) && $data['validity_period'] !== $oldData['validity_period']) {
                $data['expires_at'] = $this->calculateExpiryDate($data['validity_period']);
            }
            
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
                throw new Exception('수정할 필드가 없습니다.');
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $licenseId;
            
            $sql = "UPDATE license_keys SET " . implode(', ', $updateFields) . " WHERE license_id = ?";
            Database::executeQuery($this->connection, $sql, $params);
            
            // 관리자 액션 로그 기록
            $this->logAdminAction('UPDATE', $licenseId, '발급키 수정', $oldData, $data);
            
            Database::commit($this->connection);
            
            writeLog('INFO', 'License updated successfully', [
                'license_id' => $licenseId,
                'changes' => $data
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Database::rollback($this->connection);
            
            writeLog('ERROR', 'Failed to update license', [
                'license_id' => $licenseId,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("발급키 수정 중 오류가 발생했습니다: " . $e->getMessage());
        }
    }
    
    /**
     * 발급키 삭제
     * 
     * @param int $licenseId 발급키 ID
     * @return bool 성공 여부
     */
    public function deleteLicense(int $licenseId): bool
    {
        try {
            // 기존 데이터 조회
            $licenseData = $this->getLicenseById($licenseId);
            if (!$licenseData) {
                throw new Exception('존재하지 않는 발급키입니다.');
            }
            
            Database::beginTransaction($this->connection);
            
            // 관련 세션 정리
            $sql = "DELETE FROM license_sessions WHERE license_id = ?";
            Database::executeQuery($this->connection, $sql, [$licenseId]);
            
            // 발급키 삭제 (CASCADE로 관련 로그도 삭제됨)
            $sql = "DELETE FROM license_keys WHERE license_id = ?";
            Database::executeQuery($this->connection, $sql, [$licenseId]);
            
            // 관리자 액션 로그 기록
            $this->logAdminAction('DELETE', $licenseId, '발급키 삭제', $licenseData, null);
            
            Database::commit($this->connection);
            
            writeLog('INFO', 'License deleted successfully', [
                'license_id' => $licenseId,
                'license_key' => $licenseData['license_key']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Database::rollback($this->connection);
            
            writeLog('ERROR', 'Failed to delete license', [
                'license_id' => $licenseId,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("발급키 삭제 중 오류가 발생했습니다: " . $e->getMessage());
        }
    }
    
    /**
     * 발급키 갱신 (저장 프로시저 호출)
     * 
     * @param int $licenseId 발급키 ID
     * @param string $renewalPeriod 갱신 기간
     * @return array 갱신 결과
     */
    public function renewLicense(int $licenseId, string $renewalPeriod): array
    {
        try {
            if (!in_array($renewalPeriod, $this->validityPeriods)) {
                throw new Exception('유효하지 않은 갱신 기간입니다.');
            }
            
            $sql = "CALL sp_quick_renewal(?, ?, ?, ?)";
            $params = [
                $licenseId,
                $renewalPeriod,
                $_SESSION['session_id'] ?? 'UNKNOWN',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];
            
            $result = Database::executeQuery($this->connection, $sql, $params);
            $renewalResult = $result->fetch_assoc();
            
            if ($renewalResult['result'] === 'SUCCESS') {
                writeLog('INFO', 'License renewed successfully', [
                    'license_id' => $licenseId,
                    'renewal_period' => $renewalPeriod,
                    'new_expires_at' => $renewalResult['new_expires_at']
                ]);
                
                return [
                    'success' => true,
                    'new_expires_at' => $renewalResult['new_expires_at'],
                    'extension_days' => $renewalResult['extension_days'],
                    'new_days_remaining' => $renewalResult['new_days_remaining']
                ];
            } else {
                throw new Exception('갱신 프로시저 실행 실패');
            }
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to renew license', [
                'license_id' => $licenseId,
                'renewal_period' => $renewalPeriod,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("발급키 갱신 중 오류가 발생했습니다: " . $e->getMessage());
        }
    }
    
    /**
     * 발급키 상태 변경
     * 
     * @param int $licenseId 발급키 ID
     * @param string $newStatus 새로운 상태
     * @param string $reason 변경 사유
     * @return bool 성공 여부
     */
    public function changeStatus(int $licenseId, string $newStatus, string $reason = ''): bool
    {
        try {
            if (!in_array($newStatus, $this->statusTypes)) {
                throw new Exception('유효하지 않은 상태입니다.');
            }
            
            // 현재 상태 조회
            $currentData = $this->getLicenseById($licenseId);
            if (!$currentData) {
                throw new Exception('존재하지 않는 발급키입니다.');
            }
            
            $oldStatus = $currentData['status'];
            
            if ($oldStatus === $newStatus) {
                return true; // 동일한 상태면 성공 처리
            }
            
            Database::beginTransaction($this->connection);
            
            // 상태 업데이트
            $sql = "UPDATE license_keys SET status = ?, updated_at = NOW() WHERE license_id = ?";
            Database::executeQuery($this->connection, $sql, [$newStatus, $licenseId]);
            
            // 상태 변경 이력 기록
            $sql = "INSERT INTO license_status_history (
                license_id, previous_status, new_status, change_reason, 
                changed_by, client_ip
            ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $params = [
                $licenseId,
                $oldStatus,
                $newStatus,
                $reason,
                $_SESSION['admin_user'] ?? 'ADMIN',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];
            
            Database::executeQuery($this->connection, $sql, $params);
            
            // 관리자 액션 로그 기록
            $this->logAdminAction('UPDATE', $licenseId, "상태 변경: {$oldStatus} → {$newStatus}", 
                ['status' => $oldStatus], ['status' => $newStatus]);
            
            Database::commit($this->connection);
            
            writeLog('INFO', 'License status changed successfully', [
                'license_id' => $licenseId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Database::rollback($this->connection);
            
            writeLog('ERROR', 'Failed to change license status', [
                'license_id' => $licenseId,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("상태 변경 중 오류가 발생했습니다: " . $e->getMessage());
        }
    }
    
    /**
     * 시스템 통계 조회 (뷰 활용)
     * 
     * @return array 시스템 통계
     */
    public function getSystemStatistics(): array
    {
        try {
            $sql = "SELECT * FROM v_system_statistics";
            $result = Database::executeQuery($this->connection, $sql);
            $stats = $result->fetch_assoc();
            
            return $stats ?: [
                'total_licenses' => 0,
                'active_licenses' => 0,
                'suspended_licenses' => 0,
                'expired_licenses' => 0,
                'revoked_licenses' => 0,
                'permanent_licenses' => 0,
                'three_day_licenses' => 0,
                'seven_day_licenses' => 0,
                'thirty_day_licenses' => 0,
                'overdue_licenses' => 0,
                'expiring_urgent' => 0,
                'expiring_soon' => 0,
                'avg_access_count' => 0,
                'total_active_sessions' => 0,
                'total_connected_dbs' => 0
            ];
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to get system statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * 최근 활동 조회 (뷰 활용)
     * 
     * @param int $limit 조회 제한
     * @return array 최근 활동 목록
     */
    public function getRecentActivities(int $limit = 50): array
    {
        try {
            $sql = "SELECT * FROM v_recent_activities LIMIT ?";
            $result = Database::executeQuery($this->connection, $sql, [$limit]);
            
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
     * 발급키 접근 로그 기록
     * 
     * @param int $licenseId 발급키 ID
     * @param string $accessResult 접근 결과
     * @param string $sessionId 세션 ID
     * @param int $responseTime 응답 시간
     */
    public function logAccess(int $licenseId, string $accessResult, string $sessionId = '', int $responseTime = 0): void
    {
        try {
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
                $responseTime
            ];
            
            Database::executeQuery($this->connection, $sql, $params);
            
            // 접근 횟수 업데이트
            if ($accessResult === 'SUCCESS') {
                $sql = "UPDATE license_keys SET 
                    last_accessed = NOW(), 
                    access_count = access_count + 1,
                    first_accessed = COALESCE(first_accessed, NOW())
                WHERE license_id = ?";
                
                Database::executeQuery($this->connection, $sql, [$licenseId]);
            }
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to log access', [
                'license_id' => $licenseId,
                'access_result' => $accessResult,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 유효성 검사
     * 
     * @param array $data 검사할 데이터
     * @param int $excludeId 제외할 ID (수정 시)
     */
    private function validateLicenseData(array $data, int $excludeId = 0): void
    {
        $requiredFields = ['license_key', 'company_name', 'contact_person', 'contact_email', 'validity_period'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("필수 필드가 누락되었습니다: {$field}");
            }
        }
        
        // 발급키 길이 검사
        if (strlen($data['license_key']) < 3 || strlen($data['license_key']) > 128) {
            throw new Exception('발급키는 3~128자 사이로 입력해주세요.');
        }
        
        // 사용기간 유효성 검사
        if (!in_array($data['validity_period'], $this->validityPeriods)) {
            throw new Exception('유효하지 않은 사용기간입니다.');
        }
        
        // 라이센스 타입 유효성 검사
        if (isset($data['license_type']) && !in_array($data['license_type'], $this->licenseTypes)) {
            throw new Exception('유효하지 않은 라이센스 타입입니다.');
        }
        
        // 상태 유효성 검사
        if (isset($data['status']) && !in_array($data['status'], $this->statusTypes)) {
            throw new Exception('유효하지 않은 상태입니다.');
        }
        
        // 이메일 형식 검사
        if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('유효하지 않은 이메일 형식입니다.');
        }
    }
    
    /**
     * 발급키 중복 검사
     * 
     * @param string $licenseKey 발급키
     * @param int $excludeId 제외할 ID
     * @return bool 중복 여부
     */
    private function isDuplicateLicenseKey(string $licenseKey, int $excludeId = 0): bool
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM license_keys WHERE license_key = ?";
            $params = [$licenseKey];
            
            if ($excludeId > 0) {
                $sql .= " AND license_id != ?";
                $params[] = $excludeId;
            }
            
            $result = Database::executeQuery($this->connection, $sql, $params);
            $row = $result->fetch_assoc();
            
            return $row['count'] > 0;
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to check duplicate license key', [
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * 만료일 계산
     * 
     * @param string $validityPeriod 사용기간
     * @return string 만료일
     */
    private function calculateExpiryDate(string $validityPeriod): string
    {
        switch ($validityPeriod) {
            case '3일':
                return date('Y-m-d H:i:s', strtotime('+3 days'));
            case '7일':
                return date('Y-m-d H:i:s', strtotime('+7 days'));
            case '30일':
                return date('Y-m-d H:i:s', strtotime('+30 days'));
            case '영구':
                return '2099-12-31 23:59:59';
            default:
                return date('Y-m-d H:i:s', strtotime('+30 days'));
        }
    }
    
    /**
     * 발급키 데이터 포맷팅
     * 
     * @param array $license 발급키 데이터
     * @return array 포맷된 데이터
     */
    private function formatLicenseData(array $license): array
    {
        // 남은 일수 계산
        if ($license['validity_period'] === '영구') {
            $license['days_remaining'] = -1;
        } else {
            $license['days_remaining'] = max(0, (int) floor((strtotime($license['expires_at']) - time()) / 86400));
        }
        
        return $license;
    }
    
    /**
     * 관리자 액션 로그 기록
     * 
     * @param string $actionType 액션 타입
     * @param int $targetId 대상 ID
     * @param string $details 상세 내용
     * @param array|null $oldValues 이전 값
     * @param array|null $newValues 새로운 값
     */
    private function logAdminAction(string $actionType, int $targetId, string $details, ?array $oldValues = null, ?array $newValues = null): void
    {
        try {
            $sql = "INSERT INTO admin_action_logs (
                action_type, target_type, target_id, action_details, 
                old_values, new_values, admin_session_id, client_ip, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $actionType,
                'LICENSE',
                $targetId,
                $details,
                $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                $_SESSION['session_id'] ?? 'UNKNOWN',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            Database::executeQuery($this->connection, $sql, $params);
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to log admin action', [
                'action_type' => $actionType,
                'target_id' => $targetId,
                'error' => $e->getMessage()
            ]);
        }
    }
}