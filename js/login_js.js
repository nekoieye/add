/**
 * 아야비드 발급키 관리 시스템 - 로그인 페이지 JavaScript
 * PSR-12 준수, 보안 강화, 사용자 경험 최적화
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

'use strict';

class LoginManager {
    constructor() {
        this.form = null;
        this.authKeyInput = null;
        this.loginBtn = null;
        this.isSubmitting = false;
        this.attemptCount = 0;
        this.maxAttempts = 5;
        
        this.init();
    }
    
    /**
     * 초기화
     */
    init() {
        $(document).ready(() => {
            this.setupElements();
            this.bindEvents();
            this.setupSecurity();
            this.setupAccessibility();
            this.focusAuthInput();
        });
    }
    
    /**
     * DOM 요소 설정
     */
    setupElements() {
        this.form = $('#loginForm');
        this.authKeyInput = $('#auth_key');
        this.loginBtn = $('#loginBtn');
        
        if (!this.form.length || !this.authKeyInput.length || !this.loginBtn.length) {
            console.error('Required form elements not found');
            return;
        }
    }
    
    /**
     * 이벤트 바인딩
     */
    bindEvents() {
        // 폼 제출 이벤트
        this.form.on('submit', (e) => this.handleSubmit(e));
        
        // 입력 필드 이벤트
        this.authKeyInput.on('input', () => this.handleInput());
        this.authKeyInput.on('keypress', (e) => this.handleKeyPress(e));
        this.authKeyInput.on('focus', () => this.handleFocus());
        this.authKeyInput.on('blur', () => this.handleBlur());
        
        // 버튼 이벤트
        this.loginBtn.on('click', (e) => this.handleButtonClick(e));
        
        // 페이지 이벤트
        $(window).on('beforeunload', () => this.handlePageUnload());
        $(document).on('visibilitychange', () => this.handleVisibilityChange());
        
        // 키보드 이벤트
        $(document).on('keydown', (e) => this.handleGlobalKeyDown(e));
    }
    
    /**
     * 보안 설정
     */
    setupSecurity() {
        // 개발자 도구 감지 (기본적인 수준)
        this.detectDevTools();
        
        // 우클릭 방지 (선택적)
        if (window.location.hostname !== 'localhost') {
            $(document).on('contextmenu', (e) => {
                e.preventDefault();
                return false;
            });
        }
        
        // 복사/붙여넣기 제한
        this.authKeyInput.on('paste', (e) => {
            this.showWarning('보안상 붙여넣기가 제한됩니다.');
            e.preventDefault();
        });
        
        // 자동완성 방지 강화
        this.authKeyInput.attr('autocomplete', 'new-password');
        
        // 비밀번호 표시 토글 방지
        this.authKeyInput.on('input', () => {
            // 입력값이 화면에 표시되지 않도록 추가 보안
            setTimeout(() => {
                this.authKeyInput[0].setSelectionRange(this.authKeyInput.val().length, this.authKeyInput.val().length);
            }, 0);
        });
    }
    
    /**
     * 접근성 설정
     */
    setupAccessibility() {
        // ARIA 속성 설정
        this.authKeyInput.attr({
            'aria-describedby': 'auth-key-help',
            'aria-label': '관리자 인증키 입력',
            'aria-required': 'true'
        });
        
        this.loginBtn.attr({
            'aria-describedby': 'login-status',
            'aria-label': '로그인 버튼'
        });
        
        // 도움말 텍스트 추가
        if (!$('#auth-key-help').length) {
            $('<div id="auth-key-help" class="sr-only">관리자 인증키를 입력하고 엔터를 누르거나 로그인 버튼을 클릭하세요.</div>')
                .insertAfter(this.authKeyInput);
        }
    }
    
    /**
     * 인증키 입력 필드에 포커스
     */
    focusAuthInput() {
        setTimeout(() => {
            this.authKeyInput.focus();
        }, 100);
    }
    
    /**
     * 폼 제출 처리
     */
    handleSubmit(e) {
        e.preventDefault();
        
        if (this.isSubmitting) {
            return false;
        }
        
        if (!this.validateForm()) {
            return false;
        }
        
        this.attemptCount++;
        
        if (this.attemptCount > this.maxAttempts) {
            this.showError('너무 많은 로그인 시도가 있었습니다. 잠시 후 다시 시도해주세요.');
            this.disableForm(300000); // 5분 대기
            return false;
        }
        
        this.startSubmission();
        
        // 실제 폼 제출
        setTimeout(() => {
            this.form[0].submit();
        }, 500);
        
        return false;
    }
    
    /**
     * 입력 처리
     */
    handleInput() {
        const value = this.authKeyInput.val();
        
        // 실시간 유효성 검사
        if (value.length > 0) {
            this.authKeyInput.removeClass('is-invalid');
            this.hideError();
        }
        
        // 글자 수 제한
        if (value.length > 100) {
            this.authKeyInput.val(value.substring(0, 100));
            this.showWarning('인증키는 100자를 초과할 수 없습니다.');
        }
        
        // 특수 문자 필터링 (필요시)
        const filtered = value.replace(/[<>'"&]/g, '');
        if (filtered !== value) {
            this.authKeyInput.val(filtered);
            this.showWarning('허용되지 않는 문자가 제거되었습니다.');
        }
    }
    
    /**
     * 키 입력 처리
     */
    handleKeyPress(e) {
        // 엔터 키
        if (e.which === 13) {
            e.preventDefault();
            this.handleSubmit(e);
            return false;
        }
        
        // ESC 키 - 입력 초기화
        if (e.which === 27) {
            this.authKeyInput.val('');
            this.clearValidation();
        }
    }
    
    /**
     * 포커스 처리
     */
    handleFocus() {
        this.authKeyInput.addClass('focused');
        this.clearValidation();
    }
    
    /**
     * 블러 처리
     */
    handleBlur() {
        this.authKeyInput.removeClass('focused');
    }
    
    /**
     * 버튼 클릭 처리
     */
    handleButtonClick(e) {
        if (this.isSubmitting) {
            e.preventDefault();
            return false;
        }
        
        this.handleSubmit(e);
    }
    
    /**
     * 페이지 언로드 처리
     */
    handlePageUnload() {
        // 보안을 위해 입력값 정리
        if (this.authKeyInput) {
            this.authKeyInput.val('');
        }
        
        // 세션 정리
        this.clearSensitiveData();
    }
    
    /**
     * 가시성 변경 처리
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // 페이지가 숨겨진 경우 보안 강화
            this.pauseForm();
        } else {
            // 페이지가 다시 보이는 경우
            this.resumeForm();
        }
    }
    
    /**
     * 전역 키보드 이벤트 처리
     */
    handleGlobalKeyDown(e) {
        // F12, Ctrl+Shift+I 등 개발자 도구 단축키 차단
        if (e.keyCode === 123 || 
            (e.ctrlKey && e.shiftKey && e.keyCode === 73) ||
            (e.ctrlKey && e.shiftKey && e.keyCode === 74) ||
            (e.ctrlKey && e.keyCode === 85)) {
            e.preventDefault();
            this.showWarning('개발자 도구 사용이 제한됩니다.');
            return false;
        }
    }
    
    /**
     * 폼 유효성 검사
     */
    validateForm() {
        const authKey = this.authKeyInput.val().trim();
        
        if (!authKey) {
            this.showFieldError(this.authKeyInput, '인증키를 입력해주세요.');
            return false;
        }
        
        if (authKey.length < 3) {
            this.showFieldError(this.authKeyInput, '인증키가 너무 짧습니다.');
            return false;
        }
        
        if (authKey.length > 100) {
            this.showFieldError(this.authKeyInput, '인증키가 너무 깁니다.');
            return false;
        }
        
        return true;
    }
    
    /**
     * 제출 시작
     */
    startSubmission() {
        this.isSubmitting = true;
        
        // 버튼 상태 변경
        this.loginBtn.addClass('btn-loading');
        this.loginBtn.prop('disabled', true);
        this.loginBtn.find('.btn-text').text('로그인 중...');
        this.loginBtn.find('.spinner-border').removeClass('d-none');
        
        // 입력 필드 비활성화
        this.authKeyInput.prop('disabled', true);
        
        // 폼 전체 로딩 표시
        this.form.addClass('submitting');
    }
    
    /**
     * 제출 완료
     */
    finishSubmission() {
        this.isSubmitting = false;
        
        // 버튼 상태 복원
        this.loginBtn.removeClass('btn-loading');
        this.loginBtn.prop('disabled', false);
        this.loginBtn.find('.btn-text').text('로그인');
        this.loginBtn.find('.spinner-border').addClass('d-none');
        
        // 입력 필드 활성화
        this.authKeyInput.prop('disabled', false);
        
        // 폼 로딩 표시 제거
        this.form.removeClass('submitting');
    }
    
    /**
     * 필드 오류 표시
     */
    showFieldError(field, message) {
        field.addClass('is-invalid');
        field.focus();
        
        let feedback = field.siblings('.invalid-feedback');
        if (!feedback.length) {
            feedback = $('<div class="invalid-feedback"></div>').insertAfter(field);
        }
        feedback.text(message);
        
        // 접근성을 위한 ARIA 속성
        field.attr('aria-invalid', 'true');
        field.attr('aria-describedby', 'error-message');
        feedback.attr('id', 'error-message');
    }
    
    /**
     * 오류 메시지 표시
     */
    showError(message) {
        this.showAlert(message, 'danger');
    }
    
    /**
     * 경고 메시지 표시
     */
    showWarning(message) {
        this.showAlert(message, 'warning');
    }
    
    /**
     * 성공 메시지 표시
     */
    showSuccess(message) {
        this.showAlert(message, 'success');
    }
    
    /**
     * 알림 표시
     */
    showAlert(message, type) {
        const existingAlert = $('.alert');
        if (existingAlert.length) {
            existingAlert.remove();
        }
        
        const icon = {
            'danger': 'fas fa-exclamation-triangle',
            'warning': 'fas fa-exclamation-circle',
            'success': 'fas fa-check-circle',
            'info': 'fas fa-info-circle'
        }[type] || 'fas fa-info-circle';
        
        const alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
        
        alert.insertBefore(this.form);
        
        // 자동 제거
        setTimeout(() => {
            alert.fadeOut(() => alert.remove());
        }, 5000);
    }
    
    /**
     * 오류 숨기기
     */
    hideError() {
        $('.alert-danger').fadeOut(() => $('.alert-danger').remove());
    }
    
    /**
     * 유효성 검사 초기화
     */
    clearValidation() {
        this.authKeyInput.removeClass('is-invalid is-valid');
        this.authKeyInput.removeAttr('aria-invalid');
        $('.invalid-feedback').remove();
    }
    
    /**
     * 폼 일시 정지
     */
    pauseForm() {
        this.form.addClass('paused');
        this.authKeyInput.prop('disabled', true);
        this.loginBtn.prop('disabled', true);
    }
    
    /**
     * 폼 재개
     */
    resumeForm() {
        this.form.removeClass('paused');
        if (!this.isSubmitting) {
            this.authKeyInput.prop('disabled', false);
            this.loginBtn.prop('disabled', false);
            this.focusAuthInput();
        }
    }
    
    /**
     * 폼 비활성화
     */
    disableForm(duration = 60000) {
        this.pauseForm();
        
        let remainingTime = Math.ceil(duration / 1000);
        const updateTimer = () => {
            this.loginBtn.find('.btn-text').text(`대기 중... (${remainingTime}초)`);
            
            if (remainingTime > 0) {
                remainingTime--;
                setTimeout(updateTimer, 1000);
            } else {
                this.resumeForm();
                this.loginBtn.find('.btn-text').text('로그인');
                this.attemptCount = 0;
            }
        };
        
        updateTimer();
    }
    
    /**
     * 민감한 데이터 정리
     */
    clearSensitiveData() {
        if (this.authKeyInput) {
            this.authKeyInput.val('');
        }
        
        // 메모리에서 민감한 데이터 제거
        setTimeout(() => {
            if (window.gc) {
                window.gc();
            }
        }, 100);
    }
    
    /**
     * 개발자 도구 감지
     */
    detectDevTools() {
        let devtools = false;
        
        const detectDevTools = () => {
            if (window.outerHeight - window.innerHeight > 200 || 
                window.outerWidth - window.innerWidth > 200) {
                if (!devtools) {
                    devtools = true;
                    console.clear();
                    this.showWarning('보안을 위해 개발자 도구 사용이 감지되었습니다.');
                }
            } else {
                devtools = false;
            }
        };
        
        setInterval(detectDevTools, 1000);
    }
    
    /**
     * 키보드 단축키 설정
     */
    setupKeyboardShortcuts() {
        $(document).on('keydown', (e) => {
            // Alt + L: 로그인 필드로 포커스
            if (e.altKey && e.keyCode === 76) {
                e.preventDefault();
                this.focusAuthInput();
            }
            
            // Ctrl + Enter: 폼 제출
            if (e.ctrlKey && e.keyCode === 13) {
                e.preventDefault();
                this.handleSubmit(e);
            }
        });
    }
}

// 로그인 매니저 인스턴스 생성
const loginManager = new LoginManager();

// 전역 함수로 노출 (필요시)
window.LoginManager = LoginManager;

// 페이지 로드 완료 후 추가 초기화
$(window).on('load', function() {
    // 페이지 로딩 완료 표시
    $('body').addClass('loaded');
    
    // 성능 모니터링
    if (window.performance && window.performance.timing) {
        const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
        console.log(`Page load time: ${loadTime}ms`);
    }
});

// 에러 핸들링
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    
    // 사용자에게 친화적인 오류 메시지 표시
    if (loginManager) {
        loginManager.showError('시스템 오류가 발생했습니다. 페이지를 새로고침해주세요.');
    }
});

// Promise 오류 핸들링
window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
    e.preventDefault();
});