/**
 * 아야비드 발급키 관리 시스템 - 폼 관리 JavaScript
 * PSR-12 준수, 유효성 검사, AJAX 처리
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

'use strict';

class FormManager {
    constructor() {
        this.form = null;
        this.isSubmitting = false;
        this.validationRules = {};
        this.originalFormData = {};
        
        this.init();
    }
    
    /**
     * 초기화
     */
    init() {
        $(document).ready(() => {
            this.setupForm();
            this.bindEvents();
            this.setupValidation();
            this.setupAutoSave();
        });
    }
    
    /**
     * 폼 설정
     */
    setupForm() {
        this.form = $('#licenseForm');
        if (!this.form.length) {
            console.warn('License form not found');
            return;
        }
        
        // 원본 데이터 저장 (변경 감지용)
        this.originalFormData = this.getFormData();
        
        // 폼 필드 초기화
        this.initializeFields();
    }
    
    /**
     * 폼 필드 초기화
     */
    initializeFields() {
        // 발급키 자동 생성 설정
        const generateKeyBtn = $('#generateKey');
        if (generateKeyBtn.length) {
            generateKeyBtn.on('click', () => this.generateLicenseKey());
        }
        
        // 사용기간에 따른 만료일 자동 계산
        $('#validity_period').on('change', () => this.calculateExpiryDate());
        
        // 실시간 유효성 검사
        this.form.find('input, select, textarea').on('input change', (e) => {
            this.validateField($(e.target));
        });
        
        // 초기 만료일 계산
        this.calculateExpiryDate();
    }
    
    /**
     * 이벤트 바인딩
     */
    bindEvents() {
        // 폼 제출
        this.form.on('submit', (e) => this.handleSubmit(e));
        
        // 초기화 버튼
        $('#resetForm').on('click', () => this.resetForm());
        
        // 미리보기 버튼
        $('#previewLicense').on('click', () => this.showPreview());
        
        // 중복 검사 버튼
        $('#checkDuplicate').on('click', () => this.checkDuplicate());
        
        // 페이지 이탈 감지
        $(window).on('beforeunload', (e) => this.handlePageUnload(e));
    }
    
    /**
     * 유효성 검사 설정
     */
    setupValidation() {
        this.validationRules = {
            license_key: {
                required: true,
                minLength: 32,
                maxLength: 128,
                pattern: /^[A-Z0-9\-]+$/,
                message: '발급키는 32-128자의 영문 대문자, 숫자, 하이픈만 허용됩니다.'
            },
            company_name: {
                required: true,
                minLength: 2,
                maxLength: 255,
                message: '업체명은 2-255자 사이여야 합니다.'
            },
            contact_person: {
                required: true,
                minLength: 2,
                maxLength: 100,
                message: '담당자명은 2-100자 사이여야 합니다.'
            },
            contact_email: {
                required: true,
                pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                message: '올바른 이메일 주소를 입력해주세요.'
            },
            contact_phone: {
                required: false,
                pattern: /^[0-9\-\+\(\)\s]+$/,
                message: '올바른 전화번호 형식을 입력해주세요.'
            }
        };
    }
    
    /**
     * 자동 저장 설정
     */
    setupAutoSave() {
        // 5분마다 임시 저장
        setInterval(() => {
            if (this.hasFormChanged() && !this.isSubmitting) {
                this.autoSave();
            }
        }, 300000); // 5분
    }
    
    /**
     * 발급키 자동 생성
     */
    generateLicenseKey() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let key = '';
        
        // 4글자씩 8그룹 (총 32자)
        for (let group = 0; group < 8; group++) {
            if (group > 0) key += '-';
            for (let i = 0; i < 4; i++) {
                key += chars.charAt(Math.floor(Math.random() * chars.length));
            }
        }
        
        $('#license_key').val(key);
        this.validateField($('#license_key'));
        
        window.showAlert('새로운 발급키가 생성되었습니다.', 'success', 3000);
    }
    
    /**
     * 만료일 자동 계산
     */
    calculateExpiryDate() {
        const validityPeriod = $('#validity_period').val();
        const issuedAt = new Date();
        let expiresAt;
        
        switch (validityPeriod) {
            case '3일':
                expiresAt = new Date(issuedAt.getTime() + (3 * 24 * 60 * 60 * 1000));
                break;
            case '7일':
                expiresAt = new Date(issuedAt.getTime() + (7 * 24 * 60 * 60 * 1000));
                break;
            case '30일':
                expiresAt = new Date(issuedAt.getTime() + (30 * 24 * 60 * 60 * 1000));
                break;
            case '영구':
                expiresAt = new Date('2099-12-31T23:59:59');
                break;
            default:
                expiresAt = new Date(issuedAt.getTime() + (30 * 24 * 60 * 60 * 1000));
        }
        
        // 만료일 표시 (읽기 전용)
        const expiryDisplay = $('#expiryDisplay');
        if (expiryDisplay.length) {
            if (validityPeriod === '영구') {
                expiryDisplay.text('영구 사용');
            } else {
                expiryDisplay.text(window.formatDateTime(expiresAt.toISOString()));
            }
        }
    }
    
    /**
     * 필드 유효성 검사
     */
    validateField($field) {
        const fieldName = $field.attr('name');
        const value = $field.val().trim();
        const rules = this.validationRules[fieldName];
        
        if (!rules) return true;
        
        // 필수 필드 검사
        if (rules.required && !value) {
            this.showFieldError($field, '필수 입력 항목입니다.');
            return false;
        }
        
        // 길이 검사
        if (value && rules.minLength && value.length < rules.minLength) {
            this.showFieldError($field, `최소 ${rules.minLength}자 이상 입력해주세요.`);
            return false;
        }
        
        if (value && rules.maxLength && value.length > rules.maxLength) {
            this.showFieldError($field, `최대 ${rules.maxLength}자까지 입력 가능합니다.`);
            return false;
        }
        
        // 패턴 검사
        if (value && rules.pattern && !rules.pattern.test(value)) {
            this.showFieldError($field, rules.message);
            return false;
        }
        
        // 유효한 경우
        this.clearFieldError($field);
        return true;
    }
    
    /**
     * 전체 폼 유효성 검사
     */
    validateForm() {
        let isValid = true;
        const requiredFields = this.form.find('[required]');
        
        requiredFields.each((index, field) => {
            const $field = $(field);
            if (!this.validateField($field)) {
                isValid = false;
            }
        });
        
        // 추가 커스텀 검사
        if (!this.validateLicenseKeyUnique()) {
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * 발급키 중복 검사
     */
    async validateLicenseKeyUnique() {
        const licenseKey = $('#license_key').val().trim();
        const licenseId = $('#license_id').val(); // 수정 모드인 경우
        
        if (!licenseKey) return true;
        
        try {
            const response = await $.ajax({
                url: `${window.AYABID_CONFIG.BASE_URL}/api/check_duplicate.php`,
                method: 'POST',
                data: {
                    license_key: licenseKey,
                    license_id: licenseId,
                    csrf_token: window.AYABID_CONFIG.CSRF_TOKEN
                }
            });
            
            if (response.exists) {
                this.showFieldError($('#license_key'), '이미 사용 중인 발급키입니다.');
                return false;
            }
            
            return true;
            
        } catch (error) {
            console.warn('Duplicate check failed:', error);
            return true; // 검사 실패 시 통과로 처리
        }
    }
    
    /**
     * 중복 검사 버튼 처리
     */
    async checkDuplicate() {
        const $button = $('#checkDuplicate');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('검사 중...');
        
        try {
            const isUnique = await this.validateLicenseKeyUnique();
            
            if (isUnique) {
                window.showAlert('사용 가능한 발급키입니다.', 'success');
            }
            
        } catch (error) {
            window.showAlert('중복 검사 중 오류가 발생했습니다.', 'danger');
        } finally {
            $button.prop('disabled', false).text(originalText);
        }
    }
    
    /**
     * 폼 제출 처리
     */
    async handleSubmit(e) {
        e.preventDefault();
        
        if (this.isSubmitting) return;
        
        // 유효성 검사
        if (!this.validateForm()) {
            window.showAlert('입력 정보를 확인해주세요.', 'warning');
            return;
        }
        
        this.isSubmitting = true;
        
        try {
            const formData = new FormData(this.form[0]);
            formData.append('csrf_token', window.AYABID_CONFIG.CSRF_TOKEN);
            
            // 제출 버튼 상태 변경
            const $submitBtn = this.form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>처리 중...');
            
            const response = await $.ajax({
                url: this.form.attr('action') || `${window.AYABID_CONFIG.BASE_URL}/api/save_license.php`,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 30000
            });
            
            if (response.success) {
                window.showAlert('발급키가 성공적으로 저장되었습니다.', 'success');
                
                // 원본 데이터 업데이트
                this.originalFormData = this.getFormData();
                
                // 목록 페이지로 이동 (선택적)
                setTimeout(() => {
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        window.location.href = `${window.AYABID_CONFIG.BASE_URL}/license_list.php`;
                    }
                }, 1500);
                
            } else {
                throw new Error(response.message || '저장에 실패했습니다.');
            }
            
        } catch (error) {
            console.error('Form submission error:', error);
            const errorMsg = error.responseJSON?.message || error.message || '저장 중 오류가 발생했습니다.';
            window.showAlert(errorMsg, 'danger');
            
        } finally {
            this.isSubmitting = false;
            
            // 버튼 상태 복원
            const $submitBtn = this.form.find('button[type="submit"]');
            const originalText = $submitBtn.data('original-text') || '저장';
            $submitBtn.prop('disabled', false).html(originalText);
        }
    }
    
    /**
     * 폼 초기화
     */
    resetForm() {
        if (this.hasFormChanged()) {
            if (!confirm('변경된 내용이 있습니다. 정말 초기화하시겠습니까?')) {
                return;
            }
        }
        
        this.form[0].reset();
        this.clearAllErrors();
        this.calculateExpiryDate();
        
        window.showAlert('폼이 초기화되었습니다.', 'info', 3000);
    }
    
    /**
     * 미리보기 표시
     */
    showPreview() {
        const formData = this.getFormData();
        
        const previewHtml = `
            <div class="license-preview">
                <h5>발급키 정보 미리보기</h5>
                <table class="table table-bordered">
                    <tr><th>발급키</th><td class="font-monospace">${formData.license_key}</td></tr>
                    <tr><th>업체명</th><td>${formData.company_name}</td></tr>
                    <tr><th>담당자</th><td>${formData.contact_person}</td></tr>
                    <tr><th>이메일</th><td>${formData.contact_email}</td></tr>
                    <tr><th>전화번호</th><td>${formData.contact_phone || '-'}</td></tr>
                    <tr><th>라이센스 유형</th><td>${formData.license_type}</td></tr>
                    <tr><th>사용기간</th><td>${formData.validity_period}</td></tr>
                    <tr><th>비고</th><td>${formData.notes || '-'}</td></tr>
                </table>
            </div>
        `;
        
        // 모달로 표시
        this.showModal('발급키 미리보기', previewHtml);
    }
    
    /**
     * 폼 변경 감지
     */
    hasFormChanged() {
        const currentData = this.getFormData();
        return JSON.stringify(currentData) !== JSON.stringify(this.originalFormData);
    }
    
    /**
     * 폼 데이터 추출
     */
    getFormData() {
        const data = {};
        this.form.find('input, select, textarea').each((index, field) => {
            const $field = $(field);
            data[$field.attr('name')] = $field.val();
        });
        return data;
    }
    
    /**
     * 자동 저장
     */
    autoSave() {
        const formData = this.getFormData();
        window.setSessionData('license_form_autosave', formData);
        
        // 자동 저장 알림 (조용히)
        const indicator = $('<div class="autosave-indicator">자동 저장됨</div>');
        $('body').append(indicator);
        setTimeout(() => indicator.fadeOut(() => indicator.remove()), 2000);
    }
    
    /**
     * 필드 오류 표시
     */
    showFieldError($field, message) {
        $field.addClass('is-invalid');
        
        let $feedback = $field.siblings('.invalid-feedback');
        if (!$feedback.length) {
            $feedback = $('<div class="invalid-feedback"></div>');
            $field.after($feedback);
        }
        
        $feedback.text(message);
    }
    
    /**
     * 필드 오류 제거
     */
    clearFieldError($field) {
        $field.removeClass('is-invalid').addClass('is-valid');
        $field.siblings('.invalid-feedback').remove();
    }
    
    /**
     * 모든 오류 제거
     */
    clearAllErrors() {
        this.form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        this.form.find('.invalid-feedback').remove();
    }
    
    /**
     * 모달 표시
     */
    showModal(title, content) {
        const modalHtml = `
            <div class="modal fade" id="previewModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">${content}</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // 기존 모달 제거
        $('#previewModal').remove();
        
        // 새 모달 추가 및 표시
        $('body').append(modalHtml);
        $('#previewModal').modal('show');
        
        // 모달 제거 이벤트
        $('#previewModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    }
    
    /**
     * 페이지 이탈 처리
     */
    handlePageUnload(e) {
        if (this.hasFormChanged() && !this.isSubmitting) {
            const message = '변경된 내용이 저장되지 않았습니다. 정말 나가시겠습니까?';
            e.returnValue = message;
            return message;
        }
    }
}

// 전역 인스턴스
let formManager;

// DOM 로드 완료 후 초기화
$(document).ready(function() {
    formManager = new FormManager();
});

// 전역 함수로 노출
window.FormManager = FormManager;