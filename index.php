<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 메인 대시보드
 * PSR-12 준수, 헤더/푸터 시스템 활용, 실시간 모니터링
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
// 대시보드 데이터 수집
// =====================================================

try {
    $connection = Database::getMainConnection();
    
    // 1. 시스템 통계 조회
    $systemStatsQuery = "SELECT * FROM v_system_statistics";
    $systemStatsResult = Database::executeQuery($connection, $systemStatsQuery);
    $systemStats = $systemStatsResult->fetch_assoc();
    
    // 2. 만료 임박 발급키 조회 (7일 이내)
    $expiringQuery = "
        SELECT license_id, license_key, company_name, contact_person, contact_email, 
               expires_at, days_remaining, expiry_status, expiry_text
        FROM v_dashboard_license_summary 
        WHERE expiry_status IN ('EXPIRING_URGENT', 'EXPIRING_SOON', 'EXPIRED')
        ORDER BY expires_at ASC
        LIMIT 10
    ";
    $expiringResult = Database::executeQuery($connection, $expiringQuery);
    $expiringLicenses = [];
    while ($row = $expiringResult->fetch_assoc()) {
        $expiringLicenses[] = $row;
    }
    
    // 3. 최근 활동 로그 조회
    $recentActivitiesQuery = "SELECT * FROM v_recent_activities LIMIT 15";
    $recentActivitiesResult = Database::executeQuery($connection, $recentActivitiesQuery);
    $recentActivities = [];
    while ($row = $recentActivitiesResult->fetch_assoc()) {
        $recentActivities[] = $row;
    }
    
    // 4. DB 연결 상태 조회
    $connectionStatus = Database::getConnectionStatus();
    
    // 5. 사용기간별 분포 데이터
    $periodDistributionQuery = "
        SELECT validity_period, COUNT(*) as count 
        FROM license_keys 
        WHERE status = 'ACTIVE'
        GROUP BY validity_period
    ";
    $periodDistributionResult = Database::executeQuery($connection, $periodDistributionQuery);
    $periodDistribution = [];
    while ($row = $periodDistributionResult->fetch_assoc()) {
        $periodDistribution[$row['validity_period']] = $row['count'];
    }
    
    // 6. 일별 발급 통계 (최근 30일)
    $dailyStatsQuery = "
        SELECT DATE(issued_at) as date, COUNT(*) as count
        FROM license_keys 
        WHERE issued_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(issued_at)
        ORDER BY date ASC
    ";
    $dailyStatsResult = Database::executeQuery($connection, $dailyStatsQuery);
    $dailyStats = [];
    while ($row = $dailyStatsResult->fetch_assoc()) {
        $dailyStats[] = $row;
    }
    
    // 7. 통합 사용자 데이터 (모니터링용)
    $aggregatedUserData = aggregateUserData();
    
} catch (Exception $e) {
    writeLog('ERROR', 'Failed to load dashboard data', [
        'error' => $e->getMessage(),
        'user' => getCurrentUser()['user_id'] ?? 'unknown'
    ]);
    
    // 기본값 설정
    $systemStats = [
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
    
    $expiringLicenses = [];
    $recentActivities = [];
    $connectionStatus = [];
    $periodDistribution = [];
    $dailyStats = [];
    $aggregatedUserData = [];
}

// 헤더 설정
$headerConfig = [
    'title' => '대시보드 - ' . SYSTEM_NAME,
    'description' => '발급키 현황 및 시스템 상태 모니터링',
    'page_type' => 'dashboard',
    'require_auth' => true,
    'show_navbar' => true,
    'body_class' => 'dashboard-body',
    'navbar_active' => 'dashboard',
    'custom_css' => [],
    'custom_js' => [],
    'breadcrumb' => [
        ['title' => '대시보드']
    ],
    'page_scripts' => "
        // 차트 데이터 전달
        window.dashboardData = {
            periodDistribution: " . json_encode($periodDistribution) . ",
            dailyStats: " . json_encode($dailyStats) . ",
            systemStats: " . json_encode($systemStats) . "
        };
        
        // 갱신 함수
        function renewLicense(licenseId) {
            $('#renewLicenseId').val(licenseId);
            $('#renewModal').modal('show');
        }
        
        // 대시보드 새로고침
        function refreshDashboard() {
            location.reload();
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
                <h1 class="h3 mb-0">대시보드</h1>
                <p class="text-muted mb-0">발급키 현황 및 시스템 상태 모니터링</p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-primary" onclick="refreshDashboard()">
                    <i class="fas fa-sync me-1"></i>새로고침
                </button>
                <a href="<?php echo BASE_URL; ?>/license_form.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>새 발급키
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 통계 카드 -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">전체 발급키</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" data-stat="total">
                            <?php echo number_format($systemStats['total_licenses']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-key fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">활성 발급키</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" data-stat="active">
                            <?php echo number_format($systemStats['active_licenses']); ?>
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
        <div class="card stat-card border-left-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">만료 임박</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" data-stat="expiring">
                            <?php echo number_format($systemStats['expiring_urgent'] + $systemStats['expiring_soon']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">만료/정지</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" data-stat="expired">
                            <?php echo number_format($systemStats['expired_licenses'] + $systemStats['suspended_licenses']); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 차트 및 모니터링 -->
<div class="row mb-4">
    <!-- 사용기간별 분포 차트 -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-pie me-2"></i>사용기간별 분포
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="periodDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 발급 추이 차트 -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-line me-2"></i>최근 발급 추이 (30일)
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="dailyIssueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 테이블 섹션 -->
<div class="row">
    <!-- 만료 임박 발급키 -->
    <div class="col-xl-8 col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-clock me-2"></i>만료 임박 발급키
                </h6>
                <a href="<?php echo BASE_URL; ?>/license_list.php?filter=expiring" class="btn btn-sm btn-outline-primary">
                    전체 보기
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($expiringLicenses)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-muted">만료 임박 발급키가 없습니다</h5>
                        <p class="text-muted mb-0">모든 발급키가 정상적으로 관리되고 있습니다.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-borderless table-hover" id="expiringTable">
                            <thead class="table-light">
                                <tr>
                                    <th>발급키</th>
                                    <th>업체명</th>
                                    <th>담당자</th>
                                    <th>만료일</th>
                                    <th>상태</th>
                                    <th>액션</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expiringLicenses as $license): ?>
                                    <tr>
                                        <td>
                                            <code class="text-primary"><?php echo htmlspecialchars($license['license_key']); ?></code>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($license['company_name']); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($license['contact_person']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($license['contact_email']); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo formatDateTime($license['expires_at'], 'Y-m-d'); ?></div>
                                            <small class="text-muted"><?php echo formatDateTime($license['expires_at'], 'H:i'); ?></small>
                                        </td>
                                        <td>
                                            <?php echo getDaysRemainingBadge($license['days_remaining']); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/license_form.php?id=<?php echo $license['license_id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm" 
                                                   data-bs-toggle="tooltip" title="수정">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-outline-success btn-sm" 
                                                        onclick="renewLicense(<?php echo $license['license_id']; ?>)"
                                                        data-bs-toggle="tooltip" title="갱신">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                            </div>
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
    
    <!-- 시스템 상태 및 최근 활동 -->
    <div class="col-xl-4 col-lg-4 mb-4">
        <!-- DB 연결 상태 -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-database me-2"></i>DB 연결 상태
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($connectionStatus)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-info-circle"></i>
                        연결 상태 정보가 없습니다
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($connectionStatus, 0, 5) as $status): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($status['db_name']); ?></div>
                                <small class="text-muted">
                                    <?php echo formatDateTime($status['last_checked_at'], 'H:i:s'); ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($status['connection_result'] === 'SUCCESS'): ?>
                                    <span class="badge bg-success">연결됨</span>
                                <?php else: ?>
                                    <span class="badge bg-danger" data-bs-toggle="tooltip" 
                                          title="<?php echo htmlspecialchars($status['error_message'] ?? '연결 실패'); ?>">
                                        실패
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 최근 활동 -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-history me-2"></i>최근 활동
                </h6>
            </div>
            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($recentActivities)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-info-circle"></i>
                        최근 활동이 없습니다
                    </div>
                <?php else: ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item mb-2 pb-2 border-bottom">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">
                                    <?php echo formatDateTime($activity['activity_time'], 'H:i'); ?>
                                </small>
                                <span class="badge bg-<?php echo $activity['result_status'] === 'SUCCESS' ? 'success' : 'warning'; ?> badge-sm">
                                    <?php echo $activity['result_status']; ?>
                                </span>
                            </div>
                            <div class="mt-1">
                                <small><?php echo htmlspecialchars($activity['activity_description']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 갱신 모달 -->
<div class="modal fade" id="renewModal" tabindex="-1" aria-labelledby="renewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewModalLabel">발급키 갱신</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="renewForm" data-validate="true">
                <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="license_id" id="renewLicenseId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">갱신 기간</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="renewal_period" value="3일" id="period3">
                            <label class="form-check-label" for="period3">
                                3일 <small class="text-muted">(현재 기간에 추가)</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="renewal_period" value="7일" id="period7">
                            <label class="form-check-label" for="period7">
                                7일 <small class="text-muted">(현재 기간에 추가)</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="renewal_period" value="30일" id="period30" checked required>
                            <label class="form-check-label" for="period30">
                                30일 <small class="text-muted">(현재 기간에 추가)</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="renewal_period" value="영구" id="periodPermanent">
                            <label class="form-check-label" for="periodPermanent">
                                영구 <small class="text-muted">(영구 라이센스로 변경)</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>충전형 갱신:</strong> 선택한 기간이 현재 남은 기간에 추가됩니다.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-redo me-1"></i>갱신하기
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// 푸터 포함
require_once __DIR__ . '/core/footer.php';
?>