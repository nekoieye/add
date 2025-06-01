<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 사용자 모니터링
 * PSR-12 준수, 헤더/푸터 시스템 활용, 통합 모니터링
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/auth.php';

// =====================================================
// 필터 및 검색 파라미터 처리
// =====================================================

$selectedDb = sanitizeInput($_GET['db'] ?? '', 'string');
$dateFrom = sanitizeInput($_GET['date_from'] ?? '', 'string');
$dateTo = sanitizeInput($_GET['date_to'] ?? '', 'string');
$resultFilter = sanitizeInput($_GET['result'] ?? '', 'string');
$limit = max(50, min(500, (int) ($_GET['limit'] ?? 100)));

// 날짜 기본값 설정 (최근 7일)
if (empty($dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime('-7 days'));
}
if (empty($dateTo)) {
    $dateTo = date('Y-m-d');
}

// 필터 조건 구성
$filters = [
    'start_date' => $dateFrom . ' 00:00:00',
    'end_date' => $dateTo . ' 23:59:59',
    'result' => $resultFilter
];

// =====================================================
// 데이터 조회
// =====================================================

try {
    // 연결된 데이터베이스 목록 조회
    $connectedDatabases = Database::getConnectedDatabases();
    
    // 통합 사용자 데이터 조회
    $aggregatedData = aggregateUserData();
    
    // 선택된 DB 또는 전체 인증 이력 조회
    $authHistory = getUserAuthHistory($selectedDb, $limit, $filters);
    
    // 인증 통계 조회
    $authStatistics = getAuthStatistics($selectedDb);
    
    // DB별 연결 상태 조회
    $connectionStatus = Database::getConnectionStatus();
    
    // 시간대별 통계 계산 (차트용)
    $hourlyStats = [];
    foreach ($authHistory as $record) {
        $hour = date('H', strtotime($record['auth_time']));
        if (!isset($hourlyStats[$hour])) {
            $hourlyStats[$hour] = ['total' => 0, 'success' => 0, 'failed' => 0];
        }
        $hourlyStats[$hour]['total']++;
        if ($record['auth_result'] === 'SUCCESS') {
            $hourlyStats[$hour]['success']++;
        } else {
            $hourlyStats[$hour]['failed']++;
        }
    }
    
    // 결과별 통계 계산
    $resultStats = ['SUCCESS' => 0, 'FAILED' => 0, 'BLOCKED' => 0, 'OTHER' => 0];
    foreach ($authHistory as $record) {
        $result = $record['auth_result'] ?? 'OTHER';
        if (isset($resultStats[$result])) {
            $resultStats[$result]++;
        } else {
            $resultStats['OTHER']++;
        }
    }
    
} catch (Exception $e) {
    writeLog('ERROR', 'Failed to load monitoring data', [
        'error' => $e->getMessage(),
        'user' => getCurrentUser()['user_id'] ?? 'unknown',
        'filters' => $filters
    ]);
    
    $connectedDatabases = [];
    $aggregatedData = [];
    $authHistory = [];
    $authStatistics = [];
    $connectionStatus = [];
    $hourlyStats = [];
    $resultStats = ['SUCCESS' => 0, 'FAILED' => 0, 'BLOCKED' => 0, 'OTHER' => 0];
}

// 헤더 설정
$headerConfig = [
    'title' => '사용자 모니터링 - ' . SYSTEM_NAME,
    'description' => '통합 사용자 활동 모니터링 및 분석',
    'page_type' => 'monitoring',
    'require_auth' => true,
    'show_navbar' => true,
    'body_class' => 'dashboard-body',
    'navbar_active' => 'monitoring',
    'custom_css' => ['css/monitoring_css.css'],
    'custom_js' => ['js/monitoring.js'],
    'breadcrumb' => [
        ['title' => '사용자 모니터링']
    ],
    'page_scripts' => "
        // 모니터링 설정
        window.monitoringConfig = {
            connectedDatabases: " . json_encode($connectedDatabases) . ",
            selectedDb: '" . addslashes($selectedDb) . "',
            filters: " . json_encode($filters) . ",
            hourlyStats: " . json_encode($hourlyStats) . ",
            resultStats: " . json_encode($resultStats) . ",
            autoRefresh: true,
            refreshInterval: 30000
        };
        
        // 자동 새로고침 토글
        function toggleAutoRefresh() {
            const checkbox = document.getElementById('autoRefresh');
            window.monitoringConfig.autoRefresh = checkbox.checked;
            
            if (checkbox.checked) {
                window.showAlert('자동 새로고침이 활성화되었습니다.', 'info', 3000);
                startAutoRefresh();
            } else {
                window.showAlert('자동 새로고침이 비활성화되었습니다.', 'info', 3000);
                stopAutoRefresh();
            }
        }
        
        // 실시간 모니터링 시작
        function startAutoRefresh() {
            if (window.monitoringRefreshInterval) {
                clearInterval(window.monitoringRefreshInterval);
            }
            
            if (window.monitoringConfig.autoRefresh) {
                window.monitoringRefreshInterval = setInterval(function() {
                    refreshMonitoringData();
                }, window.monitoringConfig.refreshInterval);
            }
        }
        
        // 실시간 모니터링 중지
        function stopAutoRefresh() {
            if (window.monitoringRefreshInterval) {
                clearInterval(window.monitoringRefreshInterval);
            }
        }
        
        // 모니터링 데이터 새로고침
        function refreshMonitoringData() {
            const currentParams = new URLSearchParams(window.location.search);
            
            $.ajax({
                url: '" . BASE_URL . "/api/monitoring_data.php',
                method: 'GET',
                data: currentParams.toString(),
                success: function(response) {
                    if (response.success) {
                        updateMonitoringDisplay(response.data);
                        updateLastRefreshTime();
                    }
                },
                error: function() {
                    console.warn('Failed to refresh monitoring data');
                }
            });
        }
        
        // 화면 업데이트
        function updateMonitoringDisplay(data) {
            // 통계 카드 업데이트
            if (data.stats) {
                $('#totalAttempts').text(window.formatNumber(data.stats.total_attempts || 0));
                $('#successfulAttempts').text(window.formatNumber(data.stats.successful_attempts || 0));
                $('#failedAttempts').text(window.formatNumber(data.stats.failed_attempts || 0));
                $('#uniqueUsers').text(window.formatNumber(data.stats.unique_users || 0));
            }
            
            // 최근 활동 업데이트
            if (data.recent_activities) {
                updateRecentActivities(data.recent_activities);
            }
        }
        
        // 마지막 업데이트 시간 표시
        function updateLastRefreshTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ko-KR');
            $('#lastRefreshTime').text('마지막 업데이트: ' + timeString);
        }
        
        // DB 상태 확인
        function checkDbStatus(dbName) {
            window.showLoading('DB 상태를 확인하는 중...');
            
            $.ajax({
                url: '" . BASE_URL . "/api/check_db_status.php',
                method: 'POST',
                data: {
                    db_name: dbName,
                    _token: window.getCSRFToken()
                },
                success: function(response) {
                    if (response.success) {
                        window.showAlert('DB 연결 상태: ' + response.status + ' (응답시간: ' + response.response_time + 'ms)', 'success');
                    } else {
                        window.showAlert('DB 상태 확인 실패: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    window.showAlert('DB 상태 확인 중 오류가 발생했습니다.', 'danger');
                }
            });
        }
    "
];

// 헤더 포함
require_once __DIR__ . '/core/header.php';

?>

<!-- 페이지 헤더 -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">사용자 모니터링</h1>
                <p class="text-muted mb-0">
                    통합 사용자 활동 분석 및 실시간 모니터링
                    <span id="lastRefreshTime" class="ms-2 small"></span>
                </p>
            </div>
            <div>
                <div class="form-check form-switch me-3 d-inline-block">
                    <input class="form-check-input" type="checkbox" id="autoRefresh" 
                           onchange="toggleAutoRefresh()" checked>
                    <label class="form-check-label" for="autoRefresh">
                        자동 새로고침
                    </label>
                </div>
                <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync me-1"></i>새로고침
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 통합 통계 카드 -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">총 시도</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalAttempts">
                            <?php echo number_format(array_sum(array_column($authStatistics, 'total_attempts'))); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card border-left-success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">성공</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="successfulAttempts">
                            <?php echo number_format(array_sum(array_column($authStatistics, 'successful_attempts'))); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card border-left-danger">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">실패</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="failedAttempts">
                            <?php echo number_format(array_sum(array_column($authStatistics, 'failed_attempts'))); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card border-left-info">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">고유 사용자</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="uniqueUsers">
                            <?php echo number_format(array_sum(array_column($authStatistics, 'unique_users'))); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-friends fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 필터 및 검색 -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-filter me-2"></i>필터 및 검색
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-3">
                <label for="db" class="form-label">데이터베이스</label>
                <select class="form-select" id="db" name="db">
                    <option value="">전체 DB</option>
                    <?php foreach ($connectedDatabases as $dbName): ?>
                        <option value="<?php echo htmlspecialchars($dbName); ?>" 
                                <?php echo $selectedDb === $dbName ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dbName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="date_from" class="form-label">시작일</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">종료일</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="result" class="form-label">결과</label>
                <select class="form-select" id="result" name="result">
                    <option value="">전체</option>
                    <option value="SUCCESS" <?php echo $resultFilter === 'SUCCESS' ? 'selected' : ''; ?>>성공</option>
                    <option value="FAILED" <?php echo $resultFilter === 'FAILED' ? 'selected' : ''; ?>>실패</option>
                    <option value="BLOCKED" <?php echo $resultFilter === 'BLOCKED' ? 'selected' : ''; ?>>차단</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="limit" class="form-label">표시 개수</label>
                <select class="form-select" id="limit" name="limit">
                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50개</option>
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100개</option>
                    <option value="200" <?php echo $limit === 200 ? 'selected' : ''; ?>>200개</option>
                    <option value="500" <?php echo $limit === 500 ? 'selected' : ''; ?>>500개</option>
                </select>
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
        
        <!-- 빠른 필터 버튼 -->
        <div class="mt-3">
            <div class="btn-group btn-group-sm" role="group">
                <a href="?<?php echo http_build_query(['date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')]); ?>" 
                   class="btn btn-outline-primary">오늘</a>
                <a href="?<?php echo http_build_query(['date_from' => date('Y-m-d', strtotime('-1 day')), 'date_to' => date('Y-m-d', strtotime('-1 day'))]); ?>" 
                   class="btn btn-outline-primary">어제</a>
                <a href="?<?php echo http_build_query(['date_from' => date('Y-m-d', strtotime('-7 days')), 'date_to' => date('Y-m-d')]); ?>" 
                   class="btn btn-outline-primary">최근 7일</a>
                <a href="?<?php echo http_build_query(['date_from' => date('Y-m-d', strtotime('-30 days')), 'date_to' => date('Y-m-d')]); ?>" 
                   class="btn btn-outline-primary">최근 30일</a>
            </div>
            
            <?php if (!empty($selectedDb) || !empty($resultFilter) || $dateFrom !== date('Y-m-d', strtotime('-7 days'))): ?>
                <a href="?" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="fas fa-times me-1"></i>필터 초기화
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 차트 및 분석 -->
<div class="row mb-4">
    <!-- 시간대별 분석 -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-bar me-2"></i>시간대별 인증 분석
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 결과별 분포 -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-pie me-2"></i>인증 결과 분포
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="resultChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 메인 컨텐츠 영역 -->
<div class="row">
    <!-- DB 연결 상태 -->
    <div class="col-xl-4 col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-database me-2"></i>DB 연결 상태
                </h6>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($connectionStatus)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <p>연결 상태 정보가 없습니다</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($connectionStatus as $status): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($status['db_name']); ?></div>
                                <small class="text-muted">
                                    마지막 확인: <?php echo formatDateTime($status['last_checked_at'], 'H:i:s'); ?>
                                </small>
                                <?php if (!empty($status['connection_time_ms'])): ?>
                                    <br><small class="text-muted">
                                        응답시간: <?php echo $status['connection_time_ms']; ?>ms
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <?php if ($status['connection_result'] === 'SUCCESS'): ?>
                                    <span class="badge bg-success mb-1">연결됨</span>
                                    <br><small class="text-success">
                                        성공: <?php echo $status['success_count']; ?>회
                                    </small>
                                <?php else: ?>
                                    <span class="badge bg-danger mb-1" 
                                          data-bs-toggle="tooltip" 
                                          title="<?php echo htmlspecialchars($status['error_message'] ?? '연결 실패'); ?>">
                                        실패
                                    </span>
                                    <br><small class="text-danger">
                                        실패: <?php echo $status['failure_count']; ?>회
                                    </small>
                                <?php endif; ?>
                                <br><button type="button" class="btn btn-sm btn-outline-info mt-1" 
                                            onclick="checkDbStatus('<?php echo htmlspecialchars($status['db_name']); ?>')">
                                    <i class="fas fa-sync"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 인증 이력 테이블 -->
    <div class="col-xl-8 col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-history me-2"></i>최근 인증 이력
                </h6>
                <small class="text-muted">
                    총 <?php echo number_format(count($authHistory)); ?>건
                </small>
            </div>
            <div class="card-body">
                <?php if (empty($authHistory)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">인증 이력이 없습니다</h5>
                        <p class="text-muted mb-0">선택한 조건에 해당하는 인증 기록이 없습니다.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover table-sm" id="authHistoryTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 12%;">시간</th>
                                    <th style="width: 15%;">데이터베이스</th>
                                    <th style="width: 15%;">사용자 ID</th>
                                    <th style="width: 13%;">IP 주소</th>
                                    <th style="width: 10%;">결과</th>
                                    <th style="width: 35%;">상세 정보</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($authHistory as $record): ?>
                                    <tr class="auth-record" data-result="<?php echo htmlspecialchars($record['auth_result'] ?? 'UNKNOWN'); ?>">
                                        <td>
                                            <small><?php echo formatDateTime($record['auth_time'] ?? '', 'H:i:s'); ?></small>
                                            <br><small class="text-muted"><?php echo formatDateTime($record['auth_time'] ?? '', 'm/d'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($record['db_name'] ?? 'Unknown'); ?></span>
                                        </td>
                                        <td>
                                            <code class="small"><?php echo htmlspecialchars($record['user_id'] ?? 'N/A'); ?></code>
                                        </td>
                                        <td>
                                            <small class="font-monospace"><?php echo htmlspecialchars($record['client_ip'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $result = $record['auth_result'] ?? 'UNKNOWN';
                                            $badgeClass = match($result) {
                                                'SUCCESS' => 'bg-success',
                                                'FAILED' => 'bg-danger',
                                                'BLOCKED' => 'bg-warning',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($result); ?></span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php if (!empty($record['auth_method'])): ?>
                                                    방법: <?php echo htmlspecialchars($record['auth_method']); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($record['user_agent'])): ?>
                                                    UA: <?php echo htmlspecialchars(substr($record['user_agent'], 0, 50)); ?>...
                                                <?php endif; ?>
                                                <?php if (!empty($record['session_id'])): ?>
                                                    <br>세션: <?php echo htmlspecialchars(substr($record['session_id'], 0, 8)); ?>...
                                                <?php endif; ?>
                                                <?php if (!empty($record['error_message'])): ?>
                                                    <br><span class="text-danger">오류: <?php echo htmlspecialchars($record['error_message']); ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- DB별 상세 통계 (선택된 DB가 있는 경우) -->
<?php if (!empty($selectedDb) && isset($authStatistics[$selectedDb])): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i><?php echo htmlspecialchars($selectedDb); ?> DB 상세 통계
                </h6>
            </div>
            <div class="card-body">
                <?php $dbStats = $authStatistics[$selectedDb]; ?>
                <div class="row">
                    <div class="col-md-3 text-center">
                        <h4 class="text-primary"><?php echo number_format($dbStats['total_attempts'] ?? 0); ?></h4>
                        <small class="text-muted">총 시도</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-success"><?php echo number_format($dbStats['successful_attempts'] ?? 0); ?></h4>
                        <small class="text-muted">성공</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-danger"><?php echo number_format($dbStats['failed_attempts'] ?? 0); ?></h4>
                        <small class="text-muted">실패</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-info"><?php echo number_format($dbStats['unique_users'] ?? 0); ?></h4>
                        <small class="text-muted">고유 사용자</small>
                    </div>
                </div>
                
                <?php if (!empty($dbStats['last_auth_time'])): ?>
                    <hr>
                    <div class="text-center">
                        <small class="text-muted">
                            마지막 인증: <?php echo formatDateTime($dbStats['last_auth_time'], 'Y-m-d H:i:s'); ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// 푸터 포함
require_once __DIR__ . '/core/footer.php';
?>