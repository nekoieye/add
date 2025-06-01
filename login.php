<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 로그인 페이지 (단순화)
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/auth.php';

// 이미 로그인된 경우 대시보드로 리다이렉트
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$errorMessage = '';

// CSRF 토큰 생성
$csrfToken = generateCSRFToken();

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 토큰 검증
    if (!isset($_POST['_token']) || !validateCSRFToken($_POST['_token'])) {
        $errorMessage = '보안 토큰이 유효하지 않습니다.';
    } else {
        $authKey = $_POST['auth_key'] ?? '';
        
        if (empty($authKey)) {
            $errorMessage = '인증키를 입력해주세요.';
        } else {
            // 단순 로그인 처리
            if (login($authKey)) {
                header("Location: " . BASE_URL . "/index.php");
                exit;
            } else {
                $errorMessage = '인증키가 올바르지 않습니다.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/login_css.css" rel="stylesheet">
</head>
<body class="login-body">

<div class="container-fluid vh-100">
    <div class="row h-100 justify-content-center align-items-center">
        <div class="col-md-4 col-lg-3">
            <div class="card login-card shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center py-4">
                    <div class="login-icon mb-3">
                        <i class="fas fa-shield-alt fa-3x"></i>
                    </div>
                    <h4 class="mb-0 fw-bold"><?php echo SYSTEM_NAME; ?></h4>
                    <small class="opacity-75">관리자 인증</small>
                </div>
                
                <div class="card-body p-4">
                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="mb-4">
                            <label for="auth_key" class="form-label fw-semibold">
                                <i class="fas fa-key me-2 text-primary"></i>인증키
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input 
                                    type="password" 
                                    class="form-control form-control-lg" 
                                    id="auth_key" 
                                    name="auth_key" 
                                    placeholder="인증키를 입력하세요"
                                    required
                                    autocomplete="current-password"
                                >
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                로그인
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-light text-center py-3">
                    <small class="text-muted">
                        .< i class="fas fa-info-circle me-1"></i>
                        시스템 버전 <?php echo SYSTEM_VERSION; ?>
                    </small>
                    <br>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        현재 시간: <span id="current-time"></span>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>