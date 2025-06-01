<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 로그아웃 처리
 * PSR-12 준수, 보안 강화, 완전한 세션 정리
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/auth.php';

// POST 요청만 허용 (CSRF 방지)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET 요청인 경우 확인 페이지 표시
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>로그아웃 - <?php echo SYSTEM_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="css/login.css" rel="stylesheet">
    </head>
    <body class="login-body">
        <div class="container-fluid vh-100">
            <div class="row h-100 justify-content-center align-items-center">
                <div class="col-md-4 col-lg-3">
                    <div class="card login-card shadow-lg border-0">
                        <div class="card-header bg-warning text-white text-center py-4">
                            <div class="login-icon mb-3">
                                <i class="fas fa-sign-out-alt fa-3x"></i>
                            </div>
                            <h4 class="mb-0 fw-bold">로그아웃</h4>
                            <small class="opacity-75">세션 종료 확인</small>
                        </div>
                        
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <p class="mb-3">정말로 로그아웃하시겠습니까?</p>
                                <p class="text-muted small">모든 세션 정보가 삭제됩니다.</p>
                            </div>
                            
                            <form method="POST" class="d-grid gap-2">
                                <input type="hidden" name="_token" value="<?php echo generateCSRFToken(); ?>">
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="fas fa-sign-out-alt me-2"></i>로그아웃
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>취소
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // 엔터 키로 로그아웃 실행
            document.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.querySelector('form').submit();
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// CSRF 토큰 검증
if (!isset($_POST['_token']) || !validateCSRFToken($_POST['_token'])) {
    writeLog('WARNING', 'Invalid CSRF token in logout attempt', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    header('Location: login.php?error=invalid_token');
    exit;
}

// 현재 사용자 정보 (로그아웃 전에 수집)
$currentUser = getCurrentUser();

try {
    // 로그아웃 처리
    logout();
    
    writeLog('INFO', 'User logout completed successfully', [
        'user' => $currentUser['user_id'] ?? 'unknown',
        'session_duration' => $currentUser['session_duration'] ?? 0,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
} catch (Exception $e) {
    writeLog('ERROR', 'Logout process failed', [
        'error' => $e->getMessage(),
        'user' => $currentUser['user_id'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// 로그인 페이지로 리다이렉트
header('Location: login.php?message=logout_success');
exit;