<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 공통 헤더
 * PSR-12 준수, 중앙집중식 리소스 관리, 일관된 레이아웃
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

// 헤더 설정 기본값
$headerConfig = array_merge([
    'title' => SYSTEM_NAME,
    'description' => '아야비드 발급키 관리 시스템',
    'page_type' => 'dashboard', // dashboard, login, form, monitoring
    'require_auth' => true,
    'show_navbar' => true,
    'custom_css' => [],
    'custom_js' => [],
    'meta_robots' => 'noindex, nofollow',
    'body_class' => 'dashboard-body',
    'navbar_active' => '',
    'page_scripts' => '',
    'breadcrumb' => []
], $headerConfig ?? []);

// 인증 확인 (login 페이지 제외)
if ($headerConfig['require_auth'] && $headerConfig['page_type'] !== 'login') {
    requireAuth();
    $currentUser = getCurrentUser();
}

// 페이지별 CSS/JS 리소스 매핑
$pageResources = [
    'login' => [
        'css' => ['css/login_css.css'],
        'js' => ['js/login_js.js']
    ],
    'dashboard' => [
        'css' => ['css/dashboard_css.css'],
        'js' => ['js/dashboard_js.js']
    ],
    'form' => [
        'css' => ['css/dashboard_css.css', 'css/form.css'],
        'js' => ['js/dashboard_js.js', 'js/form.js']
    ],
    'monitoring' => [
        'css' => ['css/dashboard_css.css', 'css/monitoring_css.css'],
        'js' => ['js/dashboard_js.js', 'js/monitoring.js']
    ]
];

// 현재 페이지 리소스 가져오기
$currentResources = $pageResources[$headerConfig['page_type']] ?? $pageResources['dashboard'];

// CDN 리소스 정의
$cdnResources = [
    'bootstrap_css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'fontawesome_css' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'datatables_css' => 'https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css',
    'datatables_responsive_css' => 'https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css',
    'chartjs' => 'https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.js',
    'bootstrap_js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'jquery' => 'https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js',
    'datatables_js' => 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
    'datatables_bootstrap_js' => 'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js'
];

// CSRF 토큰 생성
$csrfToken = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo htmlspecialchars($headerConfig['meta_robots']); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($headerConfig['description']); ?>">
    <meta name="author" content="아야비드 시스템">
    <meta name="generator" content="<?php echo SYSTEM_NAME; ?> v<?php echo SYSTEM_VERSION; ?>">
    
    <!-- 보안 헤더 -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    
    <title><?php echo htmlspecialchars($headerConfig['title']); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/apple-touch-icon.png">
    
    <!-- Bootstrap CSS -->
    <link href="<?php echo $cdnResources['bootstrap_css']; ?>" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Font Awesome -->
    <link href="<?php echo $cdnResources['fontawesome_css']; ?>" rel="stylesheet" crossorigin="anonymous">
    
    <?php if (in_array($headerConfig['page_type'], ['dashboard', 'form', 'monitoring'])): ?>
    <!-- DataTables CSS -->
    <link href="<?php echo $cdnResources['datatables_css']; ?>" rel="stylesheet" crossorigin="anonymous">
    <link href="<?php echo $cdnResources['datatables_responsive_css']; ?>" rel="stylesheet" crossorigin="anonymous">
    <?php endif; ?>
    
    <!-- 페이지별 CSS -->
    <?php foreach ($currentResources['css'] as $cssFile): ?>
    <link href="<?php echo BASE_URL; ?>/<?php echo $cssFile; ?>?v=<?php echo SYSTEM_VERSION; ?>" rel="stylesheet">
    <?php endforeach; ?>
    
    <!-- 추가 커스텀 CSS -->
    <?php foreach ($headerConfig['custom_css'] as $customCss): ?>
    <link href="<?php echo BASE_URL; ?>/<?php echo $customCss; ?>?v=<?php echo SYSTEM_VERSION; ?>" rel="stylesheet">
    <?php endforeach; ?>
    
    <!-- 전역 CSS 변수 -->
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --secondary-color: #858796;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
    </style>
    
    <?php if ($headerConfig['page_type'] === 'dashboard'): ?>
    <!-- Chart.js (대시보드에서만 로드) -->
    <script src="<?php echo $cdnResources['chartjs']; ?>" crossorigin="anonymous"></script>
    <?php endif; ?>
</head>

<body class="<?php echo htmlspecialchars($headerConfig['body_class']); ?>">
    <?php if ($headerConfig['show_navbar'] && $headerConfig['page_type'] !== 'login'): ?>
    <!-- 네비게이션 바 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>/index.php">
                <i class="fas fa-shield-alt me-2"></i>
                <?php echo SYSTEM_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $headerConfig['navbar_active'] === 'dashboard' ? 'active' : ''; ?>" 
                           href="<?php echo BASE_URL; ?>/index.php">
                            <i class="fas fa-tachometer-alt me-1"></i>대시보드
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $headerConfig['navbar_active'] === 'license' ? 'active' : ''; ?>" 
                           href="<?php echo BASE_URL; ?>/license_list.php">
                            <i class="fas fa-key me-1"></i>발급키 관리
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $headerConfig['navbar_active'] === 'monitoring' ? 'active' : ''; ?>" 
                           href="<?php echo BASE_URL; ?>/user_monitoring.php">
                            <i class="fas fa-users me-1"></i>사용자 모니터링
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- 알림 드롭다운 -->
                    <?php if (isset($currentUser)): ?>
                    <?php
                    // 만료 임박 알림 계산
                    try {
                        $connection = Database::getMainConnection();
                        $urgentQuery = "SELECT COUNT(*) as count FROM v_dashboard_license_summary WHERE expiry_status = 'EXPIRING_URGENT'";
                        $urgentResult = Database::executeQuery($connection, $urgentQuery);
                        $urgentCount = $urgentResult->fetch_assoc()['count'] ?? 0;
                        
                        $soonQuery = "SELECT COUNT(*) as count FROM v_dashboard_license_summary WHERE expiry_status = 'EXPIRING_SOON'";
                        $soonResult = Database::executeQuery($connection, $soonQuery);
                        $soonCount = $soonResult->fetch_assoc()['count'] ?? 0;
                    } catch (Exception $e) {
                        $urgentCount = $soonCount = 0;
                    }
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" 
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($urgentCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $urgentCount; ?>
                                    <span class="visually-hidden">긴급 알림</span>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">알림</h6></li>
                            
                            <?php if ($urgentCount > 0): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/license_list.php?filter=expiring">
                                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                        긴급 만료 예정: <?php echo $urgentCount; ?>건
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($soonCount > 0): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/license_list.php?filter=expiring_soon">
                                        <i class="fas fa-clock text-warning me-2"></i>
                                        만료 예정: <?php echo $soonCount; ?>건
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($urgentCount === 0 && $soonCount === 0): ?>
                                <li><span class="dropdown-item text-muted">새 알림이 없습니다</span></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <!-- 사용자 메뉴 -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            관리자
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><h6 class="dropdown-header">계정 정보</h6></li>
                            <?php if (isset($currentUser)): ?>
                                <li><span class="dropdown-item-text">
                                    <small class="text-muted">
                                        로그인: <?php echo formatDateTime(date('Y-m-d H:i:s', $currentUser['auth_time'])); ?>
                                    </small>
                                </span></li>
                                <li><span class="dropdown-item-text">
                                    <small class="text-muted">
                                        세션: <?php echo substr($currentUser['session_id'], 0, 8); ?>...
                                    </small>
                                </span></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="location.reload()">
                                    <i class="fas fa-sync me-2"></i>새로고침
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="<?php echo BASE_URL; ?>/logout.php" class="d-inline">
                                    <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fas fa-sign-out-alt me-2"></i>로그아웃
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- 메인 컨텐츠 시작 -->
    <?php if ($headerConfig['show_navbar'] && $headerConfig['page_type'] !== 'login'): ?>
    <main class="main-content">
        <div class="container-fluid">
            <?php if (!empty($headerConfig['breadcrumb'])): ?>
            <!-- 브레드크럼 -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo BASE_URL; ?>/index.php">
                            <i class="fas fa-home"></i> 홈
                        </a>
                    </li>
                    <?php foreach ($headerConfig['breadcrumb'] as $item): ?>
                        <?php if (isset($item['url'])): ?>
                            <li class="breadcrumb-item">
                                <a href="<?php echo htmlspecialchars($item['url']); ?>">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="breadcrumb-item active" aria-current="page">
                                <?php echo htmlspecialchars($item['title']); ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>
    <?php endif; ?>
    
    <!-- 전역 JavaScript 변수 설정 -->
    <script>
        // 시스템 설정
        window.AYABID_CONFIG = {
            BASE_URL: '<?php echo BASE_URL; ?>',
            SYSTEM_NAME: '<?php echo SYSTEM_NAME; ?>',
            SYSTEM_VERSION: '<?php echo SYSTEM_VERSION; ?>',
            CSRF_TOKEN: '<?php echo $csrfToken; ?>',
            PAGE_TYPE: '<?php echo $headerConfig['page_type']; ?>',
            DEBUG_MODE: <?php echo DEBUG_MODE ? 'true' : 'false'; ?>,
            USER: <?php echo isset($currentUser) ? json_encode([
                'authenticated' => true,
                'user_id' => $currentUser['user_id'],
                'session_id' => substr($currentUser['session_id'], 0, 8)
            ]) : json_encode(['authenticated' => false]); ?>
        };
        
        // 전역 유틸리티 함수
        window.showAlert = function(message, type = 'info', duration = 5000) {
            const alertId = 'alert-' + Date.now();
            const iconMap = {
                success: 'fas fa-check-circle',
                danger: 'fas fa-exclamation-triangle',
                warning: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle'
            };
            
            const alert = $(`
                <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show position-fixed" 
                     style="top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
                    <i class="${iconMap[type] || iconMap.info} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            
            $('body').append(alert);
            
            setTimeout(() => {
                $(`#${alertId}`).fadeOut(() => $(`#${alertId}`).remove());
            }, duration);
        };
        
        // CSRF 토큰 헬퍼
        window.getCSRFToken = function() {
            return window.AYABID_CONFIG.CSRF_TOKEN;
        };
        
        // URL 헬퍼
        window.getBaseUrl = function(path = '') {
            return window.AYABID_CONFIG.BASE_URL + (path.startsWith('/') ? path : '/' + path);
        };
    </script>