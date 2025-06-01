<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 발급키 목록 페이지
 * PSR-12 준수, 헤더/푸터 시스템 활용, DataTables 적용
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

$search = sanitizeInput($_GET['search'] ?? '', 'string');
$statusFilter = sanitizeInput($_GET['status'] ?? '', 'string');
$validityFilter = sanitizeInput($_GET['validity'] ?? '', 'string');
$filterType = sanitizeInput($_GET['filter'] ?? '', 'string');

// 특수 필터 처리
$expiringOnly = false;
$expiringSoon = false;

switch ($filterType) {
    case 'expiring':
        $statusFilter = 'ACTIVE';
        $expiringOnly = true;
        break;
    case 'expiring_soon':
        $statusFilter = 'ACTIVE';
        $expiringSoon = true;
        break;
}

// 페이징 파라미터
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(10, min(100, (int) ($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
$offset = ($page - 1) * $limit;

// =====================================================
// 데이터 조회
// =====================================================

try {
    // 전체 개수 조회 (페이징용)
    $connection = Database::getMainConnection();
    
    $countQuery = "SELECT COUNT(*) as total FROM v_dashboard_license_summary WHERE 1=1";
    $countParams = [];
    
    if (!empty($search)) {
        $countQuery .= " AND (license_key LIKE ? OR company_name LIKE ? OR contact_person LIKE ? OR contact_email LIKE ?)";
        $searchTerm = "%{$search}%";
        $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($statusFilter)) {
        $countQuery .= " AND status = ?";
        $countParams[] = $statusFilter;
    }
    
    if (!empty($validityFilter)) {
        $countQuery .= " AND validity_period = ?";
        $countParams[] = $validityFilter;
    }
    
    $countResult = Database::executeQuery($connection, $countQuery, $countParams);
    $totalCount = $countResult->fetch_assoc()['total'];
    
    // 발급키 목록 조회
    $licenses = getLicenseList($search, $statusFilter, $validityFilter, $limit, $offset);
    
    // 특수 필터 적용
    if ($expiringOnly || $expiringSoon) {
        $licenses = array_filter($licenses, function($license) use ($expiringOnly, $expiringSoon) {
            if ($expiringOnly) {
                return in_array($license['expiry_status'], ['EXPIRING_URGENT', 'EXPIRING_SOON', 'EXPIRED']);
            }
            if ($expiringSoon) {
                return $license['expiry_status'] === 'EXPIRING_SOON';
            }
            return true;
        });
        
        // 필터링 후 실제 개수 계산
        $totalCount = count($licenses);
    }
    
    // 통계 계산
    $activeCount = count(array_filter($licenses, fn($l) => $l['status'] === 'ACTIVE'));
    $expiredCount = count(array_filter($licenses, fn($l) => $l['status'] === 'EXPIRED'));
    $suspendedCount = count(array_filter($licenses, fn($l) => $l['status'] === 'SUSPENDED'));
    $expiringCount = count(array_filter($licenses, fn($l) => in_array($l['expiry_status'], ['EXPIRING_URGENT', 'EXPIRING_SOON'])));
    
    // 페이징 계산
    $totalPages = ceil($totalCount / $limit);
    $hasNext = $page < $totalPages;
    $hasPrev = $page > 1;
    
} catch (Exception $e) {
    writeLog('ERROR', 'Failed to load license list', [
        'error' => $e->getMessage(),
        'user' => getCurrentUser()['user_id'] ?? 'unknown',
        'filters' => compact('search', 'statusFilter', 'validityFilter')
    ]);
    
    $licenses = [];
    $totalCount = 0;
    $totalPages = 0;
    $activeCount = $expiredCount = $suspendedCount = $expiringCount = 0;
    $hasNext = $hasPrev = false;
}

// 헤더 설정
$headerConfig = [
    'title' => '발급키 관리 - ' . SYSTEM_NAME,
    'description' => '발급키 목록 조회, 검색, 관리',
    'page_type' => 'form',
    'require_auth' => true,
    'show_navbar' => true,
    'body_class' => 'dashboard-body',
    'navbar_active' => 'license',
    'custom_css' => ['css/license-list.css'],
    'custom_js' => ['js/license-list.js'],
    'breadcrumb' => [
        ['title' => '발급키 관리']
    ],
    'page_scripts' => "
        // 페이지별 설정
        window.licenseListConfig = {
            totalCount: {$totalCount},
            currentPage: {$page},
            totalPages: {$totalPages},
            limit: {$limit},
            filters: {
                search: '" . addslashes($search) . "',
                status: '" . addslashes($statusFilter) . "',
                validity: '" . addslashes($validityFilter) . "',
                filter: '" . addslashes($filterType) . "'
            }
        };
        
        // 삭제 확인 함수
        function confirmDelete(licenseId, licenseKey) {
            window.showConfirm(
                '발급키 \"' + licenseKey + '\"를 정말 삭제하시겠습니까?\\n\\n이 작업은 되돌릴 수 없습니다.',
                function() {
                    deleteLicense(licenseId);
                },
                '발급키 삭제'
            );
        }
        
        // 갱신 함수
        function renewLicense(licenseId) {
            $('#renewLicenseId').val(licenseId);
            $('#renewModal').modal('show');
        }
        
        // 상태 변경 함수
        function changeStatus(licenseId, newStatus) {
            const statusText = {
                'ACTIVE': '활성화',
                'SUSPENDED': '정지',
                'EXPIRED': '만료',
                'REVOKED': '취소'
            };
            
            window.showConfirm(
                '발급키 상태를 \"' + statusText[newStatus] + '\"로 변경하시겠습니까?',
                function() {
                    updateLicenseStatus(licenseId, newStatus);
                },
                '상태 변경'
            );
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
                <h1 class="h3 mb-0">발급키 관리</h1>
                <p class="text-muted mb-0">
                    전체 <?php echo number_format($totalCount); ?>개의 발급키
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($validityFilter) || !empty($filterType)): ?>
                        <span class="text-primary">(필터 적용됨)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                    <i class="fas fa-sync me-1"></i>새로고침
                </button>
                <a href="<?php echo BASE_URL; ?>/license_form.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>새 발급키
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 통계 카드 (축약형) -->
<div class="row mb-4">
    <div class="col-md-3 mb-2">
        <div class="card border-left-success">
            <div class="card-body py-2">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                    <div>
                        <div class="text-xs font-weight-bold text-success text-uppercase">활성</div>
                        <div class="h6 mb-0 font-weight-bold"><?php echo number_format($activeCount); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-2">
        <div class="card border-left-warning">
            <div class="card-body py-2">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-clock fa-2x text-warning"></i>
                    </div>
                    <div>
                        <div class="text-xs font-weight-bold text-warning text-uppercase">만료 임박</div>
                        <div class="h6 mb-0 font-weight-bold"><?php echo number_format($expiringCount); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-2">
        <div class="card border-left-danger">
            <div class="card-body py-2">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                    </div>
                    <div>
                        <div class="text-xs font-weight-bold text-danger text-uppercase">만료</div>
                        <div class="h6 mb-0 font-weight-bold"><?php echo number_format($expiredCount); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-2">
        <div class="card border-left-secondary">
            <div class="card-body py-2">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-pause-circle fa-2x text-secondary"></i>
                    </div>
                    <div>
                        <div class="text-xs font-weight-bold text-secondary text-uppercase">정지</div>
                        <div class="h6 mb-0 font-weight-bold"><?php echo number_format($suspendedCount); ?></div>
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
            <i class="fas fa-filter me-2"></i>검색 및 필터
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-4">
                <label for="search" class="form-label">검색</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="발급키, 업체명, 담당자, 이메일">
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">상태</label>
                <select class="form-select" id="status" name="status">
                    <option value="">전체</option>
                    <?php foreach (LICENSE_STATUSES as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="validity" class="form-label">사용기간</label>
                <select class="form-select" id="validity" name="validity">
                    <option value="">전체</option>
                    <?php foreach (array_keys(VALIDITY_PERIODS) as $period): ?>
                        <option value="<?php echo $period; ?>" <?php echo $validityFilter === $period ? 'selected' : ''; ?>>
                            <?php echo $period; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="limit" class="form-label">표시 개수</label>
                <select class="form-select" id="limit" name="limit">
                    <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10개</option>
                    <option value="20" <?php echo $limit === 20 ? 'selected' : ''; ?>>20개</option>
                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50개</option>
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100개</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>검색
                    </button>
                </div>
            </div>
        </form>
        
        <!-- 빠른 필터 버튼 -->
        <div class="mt-3">
            <div class="btn-group btn-group-sm" role="group" aria-label="빠른 필터">
                <a href="?<?php echo http_build_query(['search' => $search]); ?>" 
                   class="btn <?php echo empty($filterType) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    전체
                </a>
                <a href="?<?php echo http_build_query(['search' => $search, 'filter' => 'expiring']); ?>" 
                   class="btn <?php echo $filterType === 'expiring' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                    만료 임박
                </a>
                <a href="?<?php echo http_build_query(['search' => $search, 'status' => 'ACTIVE']); ?>" 
                   class="btn <?php echo $statusFilter === 'ACTIVE' ? 'btn-success' : 'btn-outline-success'; ?>">
                    활성
                </a>
                <a href="?<?php echo http_build_query(['search' => $search, 'status' => 'EXPIRED']); ?>" 
                   class="btn <?php echo $statusFilter === 'EXPIRED' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                    만료
                </a>
            </div>
            
            <?php if (!empty($search) || !empty($statusFilter) || !empty($validityFilter) || !empty($filterType)): ?>
                <a href="?" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="fas fa-times me-1"></i>필터 초기화
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 발급키 목록 테이블 -->
<div class="card shadow">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-list me-2"></i>발급키 목록
        </h6>
        <div>
            <?php if ($totalCount > 0): ?>
                <small class="text-muted">
                    <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $limit, $totalCount)); ?> / 
                    <?php echo number_format($totalCount); ?>
                </small>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($licenses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">검색 결과가 없습니다</h5>
                <p class="text-muted mb-0">
                    <?php if (!empty($search) || !empty($statusFilter) || !empty($validityFilter)): ?>
                        검색 조건을 변경하거나 필터를 초기화해보세요.
                    <?php else: ?>
                        아직 발급된 키가 없습니다. 새 발급키를 생성해보세요.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search) || !empty($statusFilter) || !empty($validityFilter) || !empty($filterType)): ?>
                    <a href="?" class="btn btn-outline-primary mt-3">
                        <i class="fas fa-times me-1"></i>필터 초기화
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="licenseTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 15%;">발급키</th>
                            <th style="width: 20%;">업체 정보</th>
                            <th style="width: 15%;">담당자</th>
                            <th style="width: 10%;">사용기간</th>
                            <th style="width: 12%;">만료일</th>
                            <th style="width: 8%;">상태</th>
                            <th style="width: 10%;">접근</th>
                            <th style="width: 10%;">액션</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licenses as $license): ?>
                            <tr data-license-id="<?php echo $license['license_id']; ?>">
                                <td>
                                    <code class="text-primary license-key" data-bs-toggle="tooltip" 
                                          title="클릭하여 복사" onclick="window.copyToClipboard('<?php echo htmlspecialchars($license['license_key']); ?>')">
                                        <?php echo htmlspecialchars($license['license_key']); ?>
                                    </code>
                                    <?php if (!empty($license['db_name'])): ?>
                                        <br><small class="text-muted">DB: <?php echo htmlspecialchars($license['db_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($license['company_name']); ?></div>
                                    <small class="text-muted"><?php echo getValidityPeriodText($license['license_type']); ?></small>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($license['contact_person']); ?></div>
                                    <small class="text-muted">
                                        <a href="mailto:<?php echo htmlspecialchars($license['contact_email']); ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($license['contact_email']); ?>
                                        </a>
                                    </small>
                                    <?php if (!empty($license['contact_phone'])): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($license['contact_phone']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $license['validity_period']; ?></span>
                                    <br><small class="text-muted">
                                        발급: <?php echo formatDateTime($license['issued_at'], 'm/d'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div><?php echo formatDateTime($license['expires_at'], 'm/d H:i'); ?></div>
                                    <?php echo getDaysRemainingBadge($license['days_remaining']); ?>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($license['status']); ?>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <div class="fw-bold"><?php echo number_format($license['access_count']); ?>회</div>
                                        <?php if (!empty($license['last_accessed'])): ?>
                                            <small class="text-muted">
                                                <?php echo formatDateTime($license['last_accessed'], 'm/d H:i'); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">미사용</small>
                                        <?php endif; ?>
                                        <?php if ($license['current_sessions'] > 0): ?>
                                            <br><span class="badge bg-success">활성 세션: <?php echo $license['current_sessions']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm" role="group">
                                        <a href="<?php echo BASE_URL; ?>/license_form.php?id=<?php echo $license['license_id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="수정">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($license['status'] === 'ACTIVE' && $license['days_remaining'] <= 30): ?>
                                            <button type="button" class="btn btn-outline-success btn-sm" 
                                                    onclick="renewLicense(<?php echo $license['license_id']; ?>)"
                                                    data-bs-toggle="tooltip" title="갱신">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($license['status'] === 'ACTIVE'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" 
                                                           onclick="changeStatus(<?php echo $license['license_id']; ?>, 'SUSPENDED')">
                                                            <i class="fas fa-pause text-warning me-2"></i>정지
                                                        </a>
                                                    </li>
                                                <?php elseif ($license['status'] === 'SUSPENDED'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" 
                                                           onclick="changeStatus(<?php echo $license['license_id']; ?>, 'ACTIVE')">
                                                            <i class="fas fa-play text-success me-2"></i>활성화
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" 
                                                       onclick="confirmDelete(<?php echo $license['license_id']; ?>, '<?php echo addslashes($license['license_key']); ?>')">
                                                        <i class="fas fa-trash me-2"></i>삭제
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 페이징 -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="페이지 네비게이션" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php
                        $queryParams = $_GET;
                        ?>
                        
                        <!-- 이전 페이지 -->
                        <?php if ($hasPrev): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($queryParams, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link"><i class="fas fa-chevron-left"></i></span>
                            </li>
                        <?php endif; ?>
                        
                        <!-- 페이지 번호 -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($queryParams, ['page' => 1])); ?>">1</a>
                            </li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($queryParams, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($queryParams, ['page' => $totalPages])); ?>">
                                    <?php echo $totalPages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- 다음 페이지 -->
                        <?php if ($hasNext): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($queryParams, ['page' => $page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link"><i class="fas fa-chevron-right"></i></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
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