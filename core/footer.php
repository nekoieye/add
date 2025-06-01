<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 공통 푸터
 * PSR-12 준수, 중앙집중식 JavaScript 관리, 성능 최적화
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

// footer.php는 header.php에서 설정된 변수들을 사용
// $headerConfig, $currentResources, $cdnResources 등이 이미 정의되어 있음

?>

    <?php if ($headerConfig['show_navbar'] && $headerConfig['page_type'] !== 'login'): ?>
        </div> <!-- container-fluid 종료 -->
    </main> <!-- main-content 종료 -->
    <?php endif; ?>
    
    <!-- 공통 모달들 -->
    
    <!-- 로딩 오버레이 -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">로딩 중...</span>
            </div>
            <div class="loading-text mt-3">처리 중...</div>
        </div>
    </div>
    
    <!-- 확인 모달 -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">확인</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    정말로 실행하시겠습니까?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="button" class="btn btn-primary" id="confirmModalConfirm">확인</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 알림 모달 -->
    <div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="alertModalLabel">알림</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="alertModalBody">
                    알림 내용
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">확인</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript 리소스 -->
    
    <!-- jQuery -->
    <script src="<?php echo $cdnResources['jquery']; ?>" crossorigin="anonymous"></script>
    
    <!-- Bootstrap JavaScript -->
    <script src="<?php echo $cdnResources['bootstrap_js']; ?>" crossorigin="anonymous"></script>
    
    <?php if (in_array($headerConfig['page_type'], ['dashboard', 'form', 'monitoring'])): ?>
    <!-- DataTables JavaScript -->
    <script src="<?php echo $cdnResources['datatables_js']; ?>" crossorigin="anonymous"></script>
    <script src="<?php echo $cdnResources['datatables_bootstrap_js']; ?>" crossorigin="anonymous"></script>
    <?php endif; ?>
    
    <!-- 공통 유틸리티 스크립트 -->
    <script>
        /**
         * 공통 유틸리티 함수들
         * PSR-12 준수, ES6+ 문법 활용
         */
        
        // 로딩 오버레이 제어
        window.showLoading = function(text = '처리 중...') {
            $('#loadingOverlay .loading-text').text(text);
            $('#loadingOverlay').fadeIn(200);
        };
        
        window.hideLoading = function() {
            $('#loadingOverlay').fadeOut(200);
        };
        
        // 확인 모달
        window.showConfirm = function(message, callback, title = '확인') {
            $('#confirmModalLabel').text(title);
            $('#confirmModalBody').text(message);
            
            // 기존 이벤트 리스너 제거
            $('#confirmModalConfirm').off('click');
            
            // 새 이벤트 리스너 추가
            $('#confirmModalConfirm').on('click', function() {
                $('#confirmModal').modal('hide');
                if (typeof callback === 'function') {
                    callback();
                }
            });
            
            $('#confirmModal').modal('show');
        };
        
        // 알림 모달
        window.showModal = function(message, title = '알림') {
            $('#alertModalLabel').text(title);
            $('#alertModalBody').html(message);
            $('#alertModal').modal('show');
        };
        
        // AJAX 기본 설정
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                // CSRF 토큰 자동 추가 (POST 요청에만)
                if (settings.type === 'POST' && !settings.crossDomain) {
                    xhr.setRequestHeader('X-CSRF-Token', window.getCSRFToken());
                }
                
                // 로딩 표시
                if (settings.showLoading !== false) {
                    window.showLoading(settings.loadingText || '처리 중...');
                }
            },
            complete: function(xhr, status) {
                // 로딩 숨김
                window.hideLoading();
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', { xhr, status, error });
                
                let message = '요청 처리 중 오류가 발생했습니다.';
                
                if (xhr.status === 403) {
                    message = '권한이 없습니다.';
                } else if (xhr.status === 404) {
                    message = '요청하신 페이지를 찾을 수 없습니다.';
                } else if (xhr.status === 500) {
                    message = '서버 내부 오류가 발생했습니다.';
                } else if (xhr.status === 0) {
                    message = '네트워크 연결을 확인해주세요.';
                }
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        message = response.message;
                    }
                } catch (e) {
                    // JSON 파싱 실패시 기본 메시지 사용
                }
                
                window.showAlert(message, 'danger');
            }
        });
        
        // 폼 유효성 검사 헬퍼
        window.validateForm = function(formElement) {
            const form = $(formElement);
            const requiredFields = form.find('[required]');
            let isValid = true;
            
            requiredFields.each(function() {
                const field = $(this);
                const value = field.val().trim();
                
                if (!value) {
                    field.addClass('is-invalid');
                    isValid = false;
                } else {
                    field.removeClass('is-invalid').addClass('is-valid');
                }
            });
            
            return isValid;
        };
        
        // 숫자 포맷팅
        window.formatNumber = function(num) {
            return new Intl.NumberFormat('ko-KR').format(num || 0);
        };
        
        // 날짜 포맷팅
        window.formatDate = function(dateString, format = 'YYYY-MM-DD HH:mm:ss') {
            if (!dateString) return '-';
            
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            
            return format
                .replace('YYYY', year)
                .replace('MM', month)
                .replace('DD', day)
                .replace('HH', hours)
                .replace('mm', minutes)
                .replace('ss', seconds);
        };
        
        // 로컬 스토리지 헬퍼 (세션 기반으로 대체)
        window.setSessionData = function(key, value) {
            try {
                sessionStorage.setItem(`ayabid_${key}`, JSON.stringify(value));
            } catch (e) {
                console.warn('Session storage not available:', e);
            }
        };
        
        window.getSessionData = function(key, defaultValue = null) {
            try {
                const item = sessionStorage.getItem(`ayabid_${key}`);
                return item ? JSON.parse(item) : defaultValue;
            } catch (e) {
                console.warn('Session storage not available:', e);
                return defaultValue;
            }
        };
        
        // 디바운스 함수
        window.debounce = function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        };
        
        // 클립보드 복사
        window.copyToClipboard = function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text).then(() => {
                    window.showAlert('클립보드에 복사되었습니다.', 'success');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    window.showAlert('클립보드에 복사되었습니다.', 'success');
                } catch (err) {
                    console.error('클립보드 복사 실패:', err);
                    window.showAlert('클립보드 복사에 실패했습니다.', 'warning');
                } finally {
                    document.body.removeChild(textArea);
                }
            }
        };
    </script>
    
    <!-- 페이지별 JavaScript -->
    <?php foreach ($currentResources['js'] as $jsFile): ?>
    <script src="<?php echo BASE_URL; ?>/<?php echo $jsFile; ?>?v=<?php echo SYSTEM_VERSION; ?>"></script>
    <?php endforeach; ?>
    
    <!-- 추가 커스텀 JavaScript -->
    <?php foreach ($headerConfig['custom_js'] as $customJs): ?>
    <script src="<?php echo BASE_URL; ?>/<?php echo $customJs; ?>?v=<?php echo SYSTEM_VERSION; ?>"></script>
    <?php endforeach; ?>
    
    <!-- 페이지별 인라인 스크립트 -->
    <?php if (!empty($headerConfig['page_scripts'])): ?>
    <script>
        <?php echo $headerConfig['page_scripts']; ?>
    </script>
    <?php endif; ?>
    
    <!-- 전역 이벤트 리스너 -->
    <script>
        $(document).ready(function() {
            // 폼 유효성 검사 자동 적용
            $('form[data-validate="true"]').on('submit', function(e) {
                if (!window.validateForm(this)) {
                    e.preventDefault();
                    window.showAlert('필수 입력 항목을 확인해주세요.', 'warning');
                    return false;
                }
            });
            
            // 확인 필요한 액션들
            $('[data-confirm]').on('click', function(e) {
                e.preventDefault();
                const message = $(this).data('confirm');
                const href = $(this).attr('href');
                const form = $(this).closest('form');
                
                window.showConfirm(message, function() {
                    if (href) {
                        window.location.href = href;
                    } else if (form.length) {
                        form.submit();
                    }
                });
            });
            
            // 자동 포커스
            $('[data-autofocus]').first().focus();
            
            // 툴팁 초기화
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // 실시간 입력 검증
            $('input, textarea, select').on('input change', function() {
                const field = $(this);
                if (field.hasClass('is-invalid')) {
                    if (field.val().trim()) {
                        field.removeClass('is-invalid').addClass('is-valid');
                    }
                }
            });
            
            // 세션 만료 체크 (로그인 페이지가 아닌 경우)
            <?php if ($headerConfig['page_type'] !== 'login' && isset($currentUser)): ?>
            setInterval(function() {
                const expiresAt = <?php echo $currentUser['expires_at']; ?>;
                const now = Math.floor(Date.now() / 1000);
                const remainingTime = expiresAt - now;
                
                // 5분 전 경고
                if (remainingTime <= 300 && remainingTime > 0) {
                    window.showAlert('세션이 곧 만료됩니다. 작업을 저장해주세요.', 'warning');
                } else if (remainingTime <= 0) {
                    window.showModal('세션이 만료되었습니다. 다시 로그인해주세요.', '세션 만료');
                    setTimeout(() => {
                        window.location.href = window.getBaseUrl('login.php');
                    }, 3000);
                }
            }, 60000); // 1분마다 체크
            <?php endif; ?>
        });
        
        // 전역 에러 핸들러
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', {
                message: e.message,
                source: e.filename,
                line: e.lineno,
                column: e.colno,
                error: e.error
            });
            
            <?php if (DEBUG_MODE): ?>
            window.showAlert(`JavaScript 오류: ${e.message}`, 'danger');
            <?php endif; ?>
        });
        
        // Promise 오류 핸들러
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled Promise Rejection:', e.reason);
            
            <?php if (DEBUG_MODE): ?>
            window.showAlert('Promise 오류가 발생했습니다.', 'warning');
            <?php endif; ?>
            
            e.preventDefault();
        });
        
        // 성능 모니터링
        window.addEventListener('load', function() {
            if (window.performance && window.performance.timing) {
                const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
                console.log(`Page load time: ${loadTime}ms`);
                
                <?php if (DEBUG_MODE): ?>
                // 개발 모드에서는 성능 정보 표시
                if (loadTime > 3000) {
                    console.warn('Page load time is over 3 seconds:', loadTime);
                }
                <?php endif; ?>
            }
        });
        
        // 마지막 활동 시간 업데이트 (세션 연장용)
        let lastActivity = Date.now();
        document.addEventListener('click', function() {
            lastActivity = Date.now();
        });
        document.addEventListener('keypress', function() {
            lastActivity = Date.now();
        });
    </script>
    
    <?php if (DEBUG_MODE): ?>
    <!-- 개발 모드 디버그 정보 -->
    <div class="position-fixed bottom-0 start-0 p-3" style="z-index: 1000;">
        <div class="card border-warning bg-warning bg-opacity-10" style="max-width: 300px;">
            <div class="card-body p-2">
                <h6 class="card-title text-warning mb-1">
                    <i class="fas fa-bug me-1"></i>개발 모드
                </h6>
                <small class="text-muted d-block">
                    페이지: <?php echo $headerConfig['page_type']; ?><br>
                    IP: <?php echo $_SERVER['REMOTE_ADDR'] ?? 'unknown'; ?><br>
                    <?php if (isset($currentUser)): ?>
                    세션: <?php echo substr($currentUser['session_id'], 0, 8); ?>...<br>
                    <?php endif; ?>
                    시간: <span id="debug-time"></span>
                </small>
            </div>
        </div>
    </div>
    
    <script>
        // 실시간 시계
        function updateDebugTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ko-KR');
            const timeElement = document.getElementById('debug-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        updateDebugTime();
        setInterval(updateDebugTime, 1000);
    </script>
    <?php endif; ?>

</body>
</html>