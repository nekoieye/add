<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 발급키 생성/수정 폼
 * PSR-12 준수, 헤더/푸터 시스템 활용, 완전한 CRUD 기능
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
// 폼 모드 결정 (생성 또는 수정)
// =====================================================

$licenseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $licenseId > 0;
$formTitle = $isEdit ? '발급키 수정' : '새 발급키 생성';
$submitText = $isEdit ? '수정하기' : '생성하기';

$licenseData = null;
$errorMessage = '';
$successMessage = '';

// 수정 모드인 경우 기존 데이터 조회
if ($isEdit) {
    try {
        $licenseData = getLicenseById($licenseId);
        if (!$licenseData) {
            throw new Exception('존재하지 않는 발급키입니다.');
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        writeLog('ERROR', 'Failed to load license data for edit', [
            'license_id' => $licenseId,
            'error' => $e->getMessage(),
            'user' => getCurrentUser()['user_id'] ?? 'unknown'
        ]);
    }
}

// =====================================================
// 폼 제출 처리
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF 토큰 검증
        if (!isset($_POST['_token']) || !validateCSRFToken($_POST['_token'])) {
            throw new Exception('보안 토큰이 유효하지 않습니다.');
        }
        
        // 입력값 검증 및 정화
        $formData = [
            'license_key' => sanitizeInput($_POST['license_key'] ?? '', 'string'),
            'db_name' => sanitizeInput($_POST['db_name'] ?? '', 'string'),
            'company_name' => sanitizeInput($_POST['company_name'] ?? '', 'string'),
            'contact_person' => sanitizeInput($_POST['contact_person'] ?? '', 'string'),
            'contact_email' => sanitizeInput($_POST['contact_email'] ?? '', 'email'),
            'contact_phone' => sanitizeInput($_POST['contact_phone'] ?? '', 'string'),
            'license_type' => sanitizeInput($_POST['license_type'] ?? 'ALL', 'string'),
            'validity_period' => sanitizeInput($_POST['validity_period'] ?? '30일', 'string'),
            'notes' => sanitizeInput($_POST['notes'] ?? '', 'string')
        ];
        
        // 필수 필드 검증
        $requiredFields = ['license_key', 'company_name', 'contact_person', 'contact_email'];
        foreach ($requiredFields as $field) {
            if (empty($formData[$field])) {
                throw new Exception("필수 입력 항목이 누락되었습니다: " . $field);
            }
        }
        
        // 이메일 형식 검증
        if (!filter_var($formData['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('올바른 이메일 주소를 입력해주세요.');
        }
        
        // 발급키 형식 검증
        if (strlen($formData['license_key']) < 3 || strlen($formData['license_key']) > 128) {
            throw new Exception('발급키는 3~128자 사이로 입력해주세요.');
        }
        
        // 발급키 중복 검사 (수정 시 자기 자신 제외)
        try {
            $connection = Database::getMainConnection();
            $duplicateQuery = "SELECT license_id FROM license_keys WHERE license_key = ?";
            $duplicateParams = [$formData['license_key']];
            
            if ($isEdit) {
                $duplicateQuery .= " AND license_id != ?";
                $duplicateParams[] = $licenseId;
            }
            
            $duplicateResult = Database::executeQuery($connection, $duplicateQuery, $duplicateParams);
            if ($duplicateResult->num_rows > 0) {
                throw new Exception('이미 존재하는 발급키입니다.');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '이미 존재하는') !== false) {
                throw $e;
            }
            // DB 연결 오류는 경고만 로그
            writeLog('WARNING', 'Duplicate check failed', ['error' => $e->getMessage()]);
        }
        
        // DB 연결 테스트 (DB명이 입력된 경우)
        if (!empty($formData['db_name'])) {
            $connectionTest = Database::testConnection($formData['db_name']);
            if (!$connectionTest['success']) {
                // 경고만 표시하고 계속 진행
                $errorMessage = "경고: DB 연결 테스트 실패 - " . $connectionTest['error_message'];
            }
        }
        
        // 생성 또는 수정 실행
        if ($isEdit) {
            $result = updateLicense($licenseId, $formData);
            if ($result) {
                $successMessage = '발급키가 성공적으로 수정되었습니다.';
                
                // 수정된 데이터 다시 로드
                $licenseData = getLicenseById($licenseId);
                
                writeLog('INFO', 'License updated successfully', [
                    'license_id' => $licenseId,
                    'license_key' => $formData['license_key'],
                    'user' => getCurrentUser()['user_id'] ?? 'unknown'
                ]);
            } else {
                throw new Exception('발급키 수정에 실패했습니다.');
            }
        } else {
            $newLicenseId = createLicense($formData);
            if ($newLicenseId) {
                $successMessage = '발급키가 성공적으로 생성되었습니다.';
                
                // 생성 모드에서 수정 모드로 전환
                $licenseId = $newLicenseId;
                $isEdit = true;
                $licenseData = getLicenseById($licenseId);
                $formTitle = '발급키 수정';
                $submitText = '수정하기';
                
                writeLog('INFO', 'License created successfully', [
                    'license_id' => $newLicenseId,
                    'license_key' => $formData['license_key'],
                    'user' => getCurrentUser()['user_id'] ?? 'unknown'
                ]);
                
                // URL 업데이트 (JavaScript로 처리)
                echo "<script>
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, '', '" . BASE_URL . "/license_form.php?id={$newLicenseId}');
                    }
                </script>";
            } else {
                throw new Exception('발급키 생성에 실패했습니다.');
            }
        }
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        
        writeLog('ERROR', 'License form submission failed', [
            'error' => $e->getMessage(),
            'form_data' => $formData ?? [],
            'is_edit' => $isEdit,
            'license_id' => $licenseId,
            'user' => getCurrentUser()['user_id'] ?? 'unknown'
        ]);
    }
}

// 폼 기본값 설정
$defaultValues = $licenseData ?? [
    'license_key' => '',
    'db_name' => '',
    'company_name' => '',
    'contact_person' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'license_type' => 'ALL',
    'validity_period' => '30일',
    'notes' => ''
];

// 헤더 설정
$headerConfig = [
    'title' => $formTitle . ' - ' . SYSTEM_NAME,
    'description' => '발급키 생성 및 수정',
    'page_type' => 'form',
    'require_auth' => true,
    'show_navbar' => true,
    'body_class' => 'dashboard-body',
    'navbar_active' => 'license',
    'custom_css' => [],
    'custom_js' => [],
    'breadcrumb' => [
        ['title' => '발급키 관리', 'url' => BASE_URL . '/license_list.php'],
        ['title' => $formTitle]
    ],
    'page_scripts' => "
        // 폼 설정
        window.licenseFormConfig = {
            isEdit: " . ($isEdit ? 'true' : 'false') . ",
            licenseId: {$licenseId},
            validityPeriods: " . json_encode(array_keys(VALIDITY_PERIODS)) . ",
            licenseTypes: " . json_encode(LICENSE_TYPES) . "
        };
        
        // DB 연결 테스트 함수
        function testDbConnection() {
            const dbName = document.getElementById('db_name').value.trim();
            if (!dbName) {
                window.showAlert('DB명을 입력해주세요.', 'warning');
                return;
            }
            
            window.showLoading('DB 연결을 테스트하는 중...');
            
            $.ajax({
                url: '" . BASE_URL . "/api/test_db_connection.php',
                method: 'POST',
                data: {
                    db_name: dbName,
                    _token: window.getCSRFToken()
                },
                success: function(response) {
                    if (response.success) {
                        window.showAlert('DB 연결에 성공했습니다. (응답시간: ' + response.connection_time + 'ms)', 'success');
                    } else {
                        window.showAlert('DB 연결에 실패했습니다: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    window.showAlert('DB 연결 테스트 중 오류가 발생했습니다.', 'danger');
                }
            });
        }
        
        // 발급키 자동 생성 함수
        function generateLicenseKey() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            const segments = [8, 4, 4, 4, 12]; // UUID 형식
            
            for (let i = 0; i < segments.length; i++) {
                for (let j = 0; j < segments[i]; j++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                if (i < segments.length - 1) result += '-';
            }
            
            document.getElementById('license_key').value = result;
            document.getElementById('license_key').focus();
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
                <h1 class="h3 mb-0"><?php echo $formTitle; ?></h1>
                <p class="text-muted mb-0">
                    <?php if ($isEdit): ?>
                        발급키 정보를 수정할 수 있습니다.
                    <?php else: ?>
                        새로운 발급키를 생성합니다.
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/license_list.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>목록으로
                </a>
                <?php if ($isEdit): ?>
                    <a href="<?php echo BASE_URL; ?>/license_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>새 발급키
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 알림 메시지 -->
<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($errorMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($successMessage); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- 발급키 정보 카드 (수정 모드에서만 표시) -->
<?php if ($isEdit && $licenseData): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>현재 발급키 정보
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>발급키:</strong><br>
                            <code class="text-primary"><?php echo htmlspecialchars($licenseData['license_key']); ?></code>
                        </div>
                        <div class="col-md-3">
                            <strong>상태:</strong><br>
                            <?php echo getStatusBadge($licenseData['status']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>남은 기간:</strong><br>
                            <?php echo getDaysRemainingBadge($licenseData['days_remaining']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>접근 횟수:</strong><br>
                            <span class="badge bg-secondary"><?php echo number_format($licenseData['access_count']); ?>회</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 메인 폼 -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-edit me-2"></i><?php echo $formTitle; ?>
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" id="licenseForm" data-validate="true" class="needs-validation" novalidate>
                    <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                    
                    <!-- 발급키 정보 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2 mb-3">
                                <i class="fas fa-key me-2"></i>발급키 정보
                            </h5>
                        </div>
                        
                        <div class="col-md-8">
                            <label for="license_key" class="form-label required">발급키</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="license_key" name="license_key" 
                                       value="<?php echo htmlspecialchars($defaultValues['license_key']); ?>"
                                       placeholder="발급키를 입력하세요"
                                       required maxlength="128" minlength="3"
                                       pattern="[A-Za-z0-9\-_]+"
                                       data-autofocus>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateLicenseKey()" 
                                        data-bs-toggle="tooltip" title="랜덤 발급키 생성">
                                    <i class="fas fa-random"></i>
                                </button>
                                <div class="invalid-feedback">
                                    3~128자의 영문, 숫자, 하이픈, 언더스코어만 사용 가능합니다.
                                </div>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                중복되지 않는 고유한 발급키를 입력하세요.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="db_name" class="form-label">연결 DB명</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="db_name" name="db_name" 
                                       value="<?php echo htmlspecialchars($defaultValues['db_name']); ?>"
                                       placeholder="데이터베이스명">
                                <button type="button" class="btn btn-outline-info" onclick="testDbConnection()" 
                                        data-bs-toggle="tooltip" title="DB 연결 테스트">
                                    <i class="fas fa-database"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                입찰시스템 DB명 (선택사항)
                            </div>
                        </div>
                    </div>
                    
                    <!-- 업체 정보 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2 mb-3">
                                <i class="fas fa-building me-2"></i>업체 정보
                            </h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="company_name" class="form-label required">업체명</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?php echo htmlspecialchars($defaultValues['company_name']); ?>"
                                   placeholder="업체명을 입력하세요"
                                   required maxlength="255">
                            <div class="invalid-feedback">
                                업체명을 입력해주세요.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="license_type" class="form-label">발급키 유형</label>
                            <select class="form-select" id="license_type" name="license_type">
                                <?php foreach (LICENSE_TYPES as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" 
                                            <?php echo $defaultValues['license_type'] === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                사용할 시스템 유형을 선택하세요.
                            </div>
                        </div>
                    </div>
                    
                    <!-- 담당자 정보 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2 mb-3">
                                <i class="fas fa-user me-2"></i>담당자 정보
                            </h5>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="contact_person" class="form-label required">담당자명</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                   value="<?php echo htmlspecialchars($defaultValues['contact_person']); ?>"
                                   placeholder="담당자명을 입력하세요"
                                   required maxlength="100">
                            <div class="invalid-feedback">
                                담당자명을 입력해주세요.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="contact_email" class="form-label required">이메일</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                   value="<?php echo htmlspecialchars($defaultValues['contact_email']); ?>"
                                   placeholder="example@company.com"
                                   required maxlength="255">
                            <div class="invalid-feedback">
                                올바른 이메일 주소를 입력해주세요.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="contact_phone" class="form-label">전화번호</label>
                            <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                   value="<?php echo htmlspecialchars($defaultValues['contact_phone']); ?>"
                                   placeholder="010-1234-5678"
                                   maxlength="20">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                선택사항입니다.
                            </div>
                        </div>
                    </div>
                    
                    <!-- 사용 설정 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-primary border-bottom pb-2 mb-3">
                                <i class="fas fa-cog me-2"></i>사용 설정
                            </h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">사용 기간</label>
                            <div class="row">
                                <?php foreach (array_keys(VALIDITY_PERIODS) as $period): ?>
                                    <div class="col-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="validity_period" value="<?php echo $period; ?>" 
                                                   id="validity_<?php echo str_replace(['일', '구'], ['day', 'permanent'], $period); ?>"
                                                   <?php echo $defaultValues['validity_period'] === $period ? 'checked' : ''; ?>
                                                   required>
                                            <label class="form-check-label" 
                                                   for="validity_<?php echo str_replace(['일', '구'], ['day', 'permanent'], $period); ?>">
                                                <?php echo $period; ?>
                                                <?php if ($period === '영구'): ?>
                                                    <small class="text-muted">(무제한)</small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                발급 후 사용 가능한 기간을 선택하세요.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="notes" class="form-label">비고</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" 
                                      placeholder="추가 메모나 특이사항을 입력하세요" 
                                      maxlength="1000"><?php echo htmlspecialchars($defaultValues['notes']); ?></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                최대 1000자까지 입력 가능합니다.
                            </div>
                        </div>
                    </div>
                    
                    <!-- 폼 액션 버튼 -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="<?php echo BASE_URL; ?>/license_list.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-1"></i>취소
                                    </a>
                                </div>
                                
                                <div class="btn-group">
                                    <?php if (!$isEdit): ?>
                                        <button type="button" class="btn btn-outline-primary" onclick="generateLicenseKey()">
                                            <i class="fas fa-random me-1"></i>발급키 생성
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="fas fa-save me-1"></i>
                                        <span class="btn-text"><?php echo $submitText; ?></span>
                                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($isEdit && $licenseData): ?>
<!-- 추가 관리 기능 (수정 모드에서만) -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-tools me-2"></i>추가 관리 기능
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-success" onclick="renewLicense(<?php echo $licenseId; ?>)">
                        <i class="fas fa-redo me-2"></i>발급키 갱신
                    </button>
                    
                    <?php if ($licenseData['status'] === 'ACTIVE'): ?>
                        <button type="button" class="btn btn-outline-warning" 
                                onclick="changeStatus(<?php echo $licenseId; ?>, 'SUSPENDED')">
                            <i class="fas fa-pause me-2"></i>일시 정지
                        </button>
                    <?php elseif ($licenseData['status'] === 'SUSPENDED'): ?>
                        <button type="button" class="btn btn-outline-success" 
                                onclick="changeStatus(<?php echo $licenseId; ?>, 'ACTIVE')">
                            <i class="fas fa-play me-2"></i>정지 해제
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-outline-danger" 
                            onclick="confirmDelete(<?php echo $licenseId; ?>, '<?php echo addslashes($licenseData['license_key']); ?>')">
                        <i class="fas fa-trash me-2"></i>발급키 삭제
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>사용 통계
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo number_format($licenseData['access_count']); ?></h4>
                            <small class="text-muted">총 접근 횟수</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?php echo $licenseData['current_sessions']; ?></h4>
                        <small class="text-muted">현재 활성 세션</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="small">
                    <div class="d-flex justify-content-between">
                        <span>발급일:</span>
                        <span><?php echo formatDateTime($licenseData['issued_at'], 'Y-m-d H:i'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>만료일:</span>
                        <span><?php echo formatDateTime($licenseData['expires_at'], 'Y-m-d H:i'); ?></span>
                    </div>
                    <?php if (!empty($licenseData['last_accessed'])): ?>
                        <div class="d-flex justify-content-between">
                            <span>마지막 접근:</span>
                            <span><?php echo formatDateTime($licenseData['last_accessed'], 'Y-m-d H:i'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 갱신 모달 (수정 모드에서만) -->
<?php if ($isEdit): ?>
<div class="modal fade" id="renewModal" tabindex="-1" aria-labelledby="renewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewModalLabel">발급키 갱신</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="renewForm" data-validate="true">
                <input type="hidden" name="_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="license_id" id="renewLicenseId" value="<?php echo $licenseId; ?>">
                
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
<?php endif; ?>

<?php
// 푸터 포함
require_once __DIR__ . '/core/footer.php';
?>