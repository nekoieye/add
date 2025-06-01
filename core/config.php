<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 설정 파일
 * PSR-12 준수, 보안 강화, 에러 처리 최적화
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

// 기본 환경 설정
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// 타임존 설정
date_default_timezone_set('Asia/Seoul');

// 메인 데이터베이스 연결 설정
define('MAIN_DB_HOST', 'localhost');
define('MAIN_DB_NAME', 'nekoi93_addkey');
define('MAIN_DB_USER', 'nekoi93_addkey');
define('MAIN_DB_PASS', 'z1x2c3');
define('MAIN_DB_CHARSET', 'utf8mb4');

// 클라이언트 데이터베이스 공통 설정
define('CLIENT_DB_HOST', 'localhost');
define('CLIENT_DB_PASS', 'z1x2c3');
define('CLIENT_DB_CHARSET', 'utf8mb4');

// 인증 설정
define('AUTH_KEY', 'kimtaeyeon');
define('SESSION_NAME', 'ayabid_session');
define('SESSION_LIFETIME', 86400); // 24시간

// 시스템 설정
define('SYSTEM_NAME', '아야비드 발급키 관리 시스템');
define('SYSTEM_VERSION', '1.0.0');
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_CONNECTION_RETRY', 3);
define('CONNECTION_TIMEOUT', 30);

// 로그 설정
define('LOG_DIR', __DIR__ . '/../logs');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR, CRITICAL
define('LOG_MAX_FILES', 30);

// 보안 설정
define('CSRF_TOKEN_NAME', '_token');
define('CSRF_TOKEN_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_TIMEOUT', 900); // 15분

// 유효성 기간 설정
define('VALIDITY_PERIODS', [
    '3일' => 3,
    '7일' => 7,
    '30일' => 30,
    '영구' => -1
]);

// 발급키 유형 설정
define('LICENSE_TYPES', [
    'G2B_A' => 'G2B A타입',
    'G2B_B' => 'G2B B타입', 
    'G2B_C' => 'G2B C타입',
    'EAT' => 'EAT 시스템',
    'ALL' => '전체'
]);

// 상태 설정
define('LICENSE_STATUSES', [
    'ACTIVE' => '활성',
    'SUSPENDED' => '정지',
    'EXPIRED' => '만료',
    'REVOKED' => '취소'
]);

// 경로 설정
define('BASE_PATH', realpath(__DIR__ . '/..'));
define('CORE_PATH', BASE_PATH . '/core');
define('CSS_PATH', BASE_PATH . '/css');
define('JS_PATH', BASE_PATH . '/js');
define('LOGS_PATH', BASE_PATH . '/logs');

// URL 설정 (개발/운영 환경 자동 감지)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('BASE_URL', $protocol . $host . $path);

// 에러 핸들링
function systemErrorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    $logMessage = sprintf(
        "[%s] Error #%d: %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $errno,
        $errstr,
        $errfile,
        $errline
    );
    
    error_log($logMessage);
    
    // 개발 환경에서만 에러 표시
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div class='alert alert-danger'>시스템 오류: {$errstr}</div>";
    }
    
    return true;
}

set_error_handler('systemErrorHandler');

// 예외 처리
function systemExceptionHandler(Throwable $exception): void
{
    $logMessage = sprintf(
        "[%s] Uncaught Exception: %s in %s on line %d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    
    error_log($logMessage);
    
    // 개발 환경에서만 상세 정보 표시
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div class='alert alert-danger'>예외 발생: " . htmlspecialchars($exception->getMessage()) . "</div>";
    } else {
        echo "<div class='alert alert-danger'>시스템 오류가 발생했습니다. 관리자에게 문의하세요.</div>";
    }
}

set_exception_handler('systemExceptionHandler');

// 시스템 초기화
function initializeSystem(): void
{
    // 로그 디렉토리 생성
    if (!is_dir(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }
    
    // 세션 보안 설정 (auth.php의 enhanceSessionSecurity와 통합)
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    
    // HTTPS 환경에서 Secure 쿠키 사용
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    
    // 세션 시작 (한 번만)
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
        
        // 세션 보안 강화 (초기화 시에만)
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['created_at'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        // 세션 만료 체크
        if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at'] > SESSION_LIFETIME)) {
            session_destroy();
            session_start();
        }
    }
}

// 디버그 모드 설정 (개발 환경에서만)
if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    define('DEBUG_MODE', true);
    ini_set('display_errors', '1');
} else {
    define('DEBUG_MODE', false);
}

// 시스템 초기화 실행
initializeSystem();

// 로깅 함수
function writeLog(string $level, string $message, array $context = []): void
{
    $logLevels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];
    $currentLevel = $logLevels[LOG_LEVEL] ?? 1;
    
    if (($logLevels[$level] ?? 1) < $currentLevel) {
        return;
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    $logFile = LOGS_PATH . '/system_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    
    // 오래된 로그 파일 정리
    cleanupOldLogs();
}

// 오래된 로그 파일 정리
function cleanupOldLogs(): void
{
    static $lastCleanup = 0;
    
    // 하루에 한 번만 정리
    if (time() - $lastCleanup < 86400) {
        return;
    }
    
    $logFiles = glob(LOGS_PATH . '/system_*.log');
    if (count($logFiles) > LOG_MAX_FILES) {
        usort($logFiles, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $filesToDelete = array_slice($logFiles, 0, count($logFiles) - LOG_MAX_FILES);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }
    
    $lastCleanup = time();
}