<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 인증 관리
 * PSR-12 준수, 보안 강화, 세션 관리
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

/**
 * 인증 상태 확인
 * 
 * @return bool 인증 여부
 */
function checkAuth(): bool
{
    // 세션 시작은 config.php의 initializeSystem()에서 이미 처리됨
    // session_start() 호출 제거
    
    // 기본 인증 체크
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return false;
    }
    
    // 세션 만료 체크
    if (isset($_SESSION['expires_at']) && $_SESSION['expires_at'] < time()) {
        logout();
        return false;
    }
    
    // IP 주소 변경 체크 (세션 하이재킹 방지)
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        writeLog('WARNING', 'IP address changed during session', [
            'original_ip' => $_SESSION['ip_address'],
            'current_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'session_id' => session_id()
        ]);
        
        logout();
        return false;
    }
    
    // User Agent 변경 체크 (세션 하이재킹 방지)
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        writeLog('WARNING', 'User agent changed during session', [
            'original_user_agent' => $_SESSION['user_agent'],
            'current_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id()
        ]);
        
        logout();
        return false;
    }
    
    // 마지막 활동 시간 업데이트
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * 로그인 처리 - 단순 평문 비교
 * 
 * @param string $authKey 인증키
 * @return bool 로그인 성공 여부
 */
function login(string $authKey): bool
{
    // 인증키 검증 (평문 비교)
    if ($authKey !== AUTH_KEY) {
        return false;
    }
    
    // 세션 재생성 (세션 고정 공격 방지)
    regenerateSession();
    
    // 인증 정보 설정
    $_SESSION['authenticated'] = true;
    $_SESSION['auth_time'] = time();
    $_SESSION['expires_at'] = time() + SESSION_LIFETIME;
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['last_activity'] = time();
    $_SESSION['admin_user'] = 'ADMIN';
    $_SESSION['session_id'] = session_id();
    
    return true;
}

/**
 * 로그아웃 처리
 */
function logout(): void
{
    // 세션 시작은 config.php의 initializeSystem()에서 이미 처리됨
    // session_start() 호출 제거
    
    // 로그아웃 로그 기록 (세션 정보가 남아있을 때)
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        logAdminAction('LOGOUT', 'AUTH', null, '관리자 로그아웃');
        
        writeLog('INFO', 'Admin logout', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'session_id' => session_id(),
            'session_duration' => isset($_SESSION['auth_time']) ? (time() - $_SESSION['auth_time']) : 0
        ]);
    }
    
    // 세션 데이터 모두 삭제
    $_SESSION = [];
    
    // 세션 쿠키 삭제
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // 세션 파괴
    session_destroy();
}

/**
 * 로그인 상태 확인
 * 
 * @return bool 로그인 여부
 */
function isLoggedIn(): bool
{
    return checkAuth();
}

/**
 * 인증 필수 페이지 접근 제어
 * 
 * @param string $redirectUrl 리다이렉트할 URL
 */
function requireAuth(string $redirectUrl = 'login.php'): void
{
    if (!checkAuth()) {
        // 현재 페이지 정보 저장 (로그인 후 복귀용)
        // 세션은 이미 config.php의 initializeSystem()에서 시작됨
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        
        writeLog('INFO', 'Unauthorized access attempt redirected to login', [
            'requested_url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // 로그인 페이지로 리다이렉트
        header("Location: {$redirectUrl}");
        exit;
    }
}

/**
 * 세션 재생성
 */
function regenerateSession(): void
{
    // 기존 세션 데이터 백업
    $sessionData = $_SESSION;
    
    // 세션 ID 재생성
    session_regenerate_id(true);
    
    // 세션 데이터 복원
    $_SESSION = $sessionData;
    
    writeLog('DEBUG', 'Session regenerated', [
        'new_session_id' => session_id(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

/**
 * 로그인 시도 제한 확인
 * 
 * @return bool 로그인 시도 가능 여부
 */
function checkLoginAttempts(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // 세션 기반 시도 횟수 체크 (간단한 구현)
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $currentTime = time();
    
    // 만료된 시도 기록 정리
    foreach ($_SESSION['login_attempts'] as $timestamp => $attempts) {
        if ($currentTime - $timestamp > (LOGIN_ATTEMPT_TIMEOUT ?? 900)) {
            unset($_SESSION['login_attempts'][$timestamp]);
        }
    }
    
    // 현재 시간대의 시도 횟수 계산
    $totalAttempts = 0;
    foreach ($_SESSION['login_attempts'] as $attempts) {
        $totalAttempts += $attempts;
    }
    
    return $totalAttempts < (MAX_LOGIN_ATTEMPTS ?? 5);
}

/**
 * 로그인 시도 기록
 * 
 * @param bool $success 성공 여부
 */
function recordLoginAttempt(bool $success): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $currentTime = time();
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if ($success) {
        // 성공 시 모든 시도 기록 초기화
        $_SESSION['login_attempts'] = [];
    } else {
        // 실패 시 시도 횟수 증가
        $timeSlot = floor($currentTime / 300) * 300; // 5분 단위로 그룹화
        
        if (!isset($_SESSION['login_attempts'][$timeSlot])) {
            $_SESSION['login_attempts'][$timeSlot] = 0;
        }
        
        $_SESSION['login_attempts'][$timeSlot]++;
    }
    
    // 로그인 시도 로그 기록
    try {
        $connection = Database::getMainConnection();
        
        $sql = "INSERT INTO system_logs (
            log_level, log_category, log_message, log_context, 
            client_ip, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $context = [
            'success' => $success,
            'timestamp' => $currentTime,
            'total_attempts' => array_sum($_SESSION['login_attempts'])
        ];
        
        $params = [
            $success ? 'INFO' : 'WARNING',
            'AUTH',
            $success ? 'Login attempt successful' : 'Login attempt failed',
            json_encode($context, JSON_UNESCAPED_UNICODE),
            $ip,
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        Database::executeQuery($connection, $sql, $params);
        
    } catch (Exception $e) {
        writeLog('ERROR', 'Failed to record login attempt', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * 현재 사용자 정보 반환
 * 
 * @return array 사용자 정보
 */
function getCurrentUser(): array
{
    if (!checkAuth()) {
        return [];
    }
    
    return [
        'user_id' => $_SESSION['admin_user'] ?? 'ADMIN',
        'authenticated' => true,
        'auth_time' => $_SESSION['auth_time'] ?? 0,
        'expires_at' => $_SESSION['expires_at'] ?? 0,
        'last_activity' => $_SESSION['last_activity'] ?? 0,
        'session_id' => $_SESSION['session_id'] ?? session_id(),
        'ip_address' => $_SESSION['ip_address'] ?? '',
        'session_duration' => time() - ($_SESSION['auth_time'] ?? time())
    ];
}

/**
 * 세션 만료 시간 연장
 * 
 * @param int $additionalTime 추가 시간 (초)
 */
function extendSession(int $additionalTime = 0): void
{
    if (!checkAuth()) {
        return;
    }
    
    $newExpiration = time() + ($additionalTime > 0 ? $additionalTime : SESSION_LIFETIME);
    $_SESSION['expires_at'] = $newExpiration;
    $_SESSION['last_activity'] = time();
    
    writeLog('DEBUG', 'Session extended', [
        'new_expiration' => date('Y-m-d H:i:s', $newExpiration),
        'session_id' => session_id()
    ]);
}

/**
 * 세션 정보 조회
 * 
 * @return array 세션 정보
 */
function getSessionInfo(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        return ['status' => 'not_started'];
    }
    
    $info = [
        'status' => 'active',
        'session_id' => session_id(),
        'authenticated' => $_SESSION['authenticated'] ?? false,
        'created_at' => $_SESSION['created_at'] ?? null,
        'auth_time' => $_SESSION['auth_time'] ?? null,
        'expires_at' => $_SESSION['expires_at'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'ip_address' => $_SESSION['ip_address'] ?? null,
        'remaining_time' => isset($_SESSION['expires_at']) ? $_SESSION['expires_at'] - time() : 0
    ];
    
    return $info;
}