/**
 * 아야비드 발급키 관리 시스템 - 발급키 목록 JavaScript
 * PSR-12 준수, DataTables 최적화, 실시간 검색
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

'use strict';

class LicenseListManager {
    constructor() {
        this.dataTable = null;
        this.searchTimeout = null;
        this.autoRefresh = false;
        this.refreshInterval = null;
        this.selectedLicenses = new Set();
        
        this.init();
    }
    
    /**
     * 초기화
     */
    init() {
        $(document).ready(() => {
            this.initializeDataTable();
            this.bindEvents();
            this.setupFilters();
            this.setupKeyboardShortcuts();
            this.setupBulkActions();
            this.restoreFilters();
        });
    }
    
    /**
     * DataTable 초기화
     */
    initializeDataTable() {
        if (!$('#licenseTable').length) return;
        
        // DataTables 한국어 설정
        const koreanLang = {
            "lengthMenu": "_MENU_ 개씩 보기",
            "zeroRecords": "검색 결과가 없습니다",
            "info": "_START_ - _END_ / _TOTAL_개",
            "infoEmpty": "0개",
            "infoFiltered": "(전체 _MAX_개에서 필터링)",
            "search": "검색:",
            "paginate": {
                "first": "처음",
                "last": "마지막",
                "next": "다음",
                "previous": "이전"
            },
            "processing": "처리 중...",
            "loadingRecords": "로딩 중...",
            "emptyTable": "테이블에 데이터가 없습니다"
        };
        
        this.dataTable = $('#licenseTable').DataTable({
            responsive: true,
            pageLength: window.licenseListConfig?.limit || 20,
            lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
            searching: false, // 커스텀 검색 사용
            ordering: true,
            order: [[4, 'asc']], // 만료일 기준 오름차순
            info: true,
            paging: false, // 서버사이드 페이징 사용
            autoWidth: false,
            language: koreanLang,
            columnDefs: [
                {
                    targets: 0,
                    orderable: false,
                    className: 'select-checkbox',
                    render: function(data, type, row, meta) {
                        return `<input type="checkbox" class="license-checkbox" value="${data}" data-license-id="${data}">`;
                    }
                },
                {
                    targets: 1, // 발급키 컬럼
                    render: function(data, type, row, meta) {
                        const licenseKey = data;
                        const dbName = row[1]; // DB명은 별도 처리
                        let html = `<code class="license-key" onclick="window.copyToClipboard('${licenseKey}')" title="클릭하여 복사">${licenseKey}</code>`;
                        if (dbName) {
                            html += `<br><small class="text-muted">DB: ${dbName}</small>`;
                        }
                        return html;
                    }
                },
                {
                    targets: 4, // 만료일 컬럼
                    type: 'date',
                    render: function(data, type, row, meta) {
                        if (type === 'display') {
                            const date = new Date(data);
                            const now = new Date();
                            const diffTime = date.getTime() - now.getTime();
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            
                            let badgeClass = 'bg-primary';
                            let badgeText = `${diffDays}일 남음`;
                            
                            if (data === '2099-12-31 23:59:59') {
                                badgeClass = 'bg-success';
                                badgeText = '영구';
                            } else if (diffDays <= 0) {
                                badgeClass = 'bg-danger';
                                badgeText = '만료';
                            } else if (diffDays <= 3) {
                                badgeClass = 'bg-warning text-dark';
                                badgeText = `${diffDays}일 남음`;
                            } else if (diffDays <= 7) {
                                badgeClass = 'bg-info';
                            }
                            
                            return `
                                <div>${window.formatDate(data, 'MM/DD HH:mm')}</div>
                                <span class="badge ${badgeClass}">${badgeText}</span>
                            `;
                        }
                        return data;
                    }
                },
                {
                    targets: 5, // 상태 컬럼
                    render: function(data, type, row, meta) {
                        const statusMap = {
                            'ACTIVE': '<span class="badge bg-success">활성</span>',
                            'SUSPENDED': '<span class="badge bg-warning">정지</span>',
                            'EXPIRED': '<span class="badge bg-danger">만료</span>',
                            'REVOKED': '<span class="badge bg-secondary">취소</span>'
                        };
                        return statusMap[data] || `<span class="badge bg-secondary">${data}</span>`;
                    }
                },
                {
                    targets: -1, // 액션 컬럼 (마지막)
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row, meta) {
                        const licenseId = row[0]; // 첫 번째 컬럼이 license_id라고 가정
                        const status = row[5]; // 상태 컬럼
                        const licenseKey = row[1];
                        
                        let actions = `
                            <div class="btn-group-vertical btn-group-sm" role="group">
                                <a href="${window.AYABID_CONFIG.BASE_URL}/license_form.php?id=${licenseId}" 
                                   class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="수정">
                                    <i class="fas fa-edit"></i>
                                </a>
                        `;
                        
                        if (status === 'ACTIVE') {
                            actions += `
                                <button type="button" class="btn btn-outline-success btn-sm" 
                                        onclick="renewLicense(${licenseId})"
                                        data-bs-toggle="tooltip" title="갱신">
                                    <i class="fas fa-redo"></i>
                                </button>
                            `;
                        }
                        
                        actions += `
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                        `;
                        
                        if (status === 'ACTIVE') {
                            actions += `
                                <li>
                                    <a class="dropdown-item" href="#" 
                                       onclick="changeStatus(${licenseId}, 'SUSPENDED')">
                                        <i class="fas fa-pause text-warning me-2"></i>정지
                                    </a>
                                </li>
                            `;
                        } else if (status === 'SUSPENDED') {
                            actions += `
                                <li>
                                    <a class="dropdown-item" href="#" 
                                       onclick="changeStatus(${licenseId}, 'ACTIVE')">
                                        <i class="fas fa-play text-success me-2"></i>활성화
                                    </a>
                                </li>
                            `;
                        }
                        
                        actions += `
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" 
                                               onclick="confirmDelete(${licenseId}, '${licenseKey}')">
                                                <i class="fas fa-trash me-2"></i>삭제
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        `;
                        
                        return actions;
                    }
                }
            ],
            drawCallback: function() {
                // 툴팁 재초기화
                $('[data-bs-toggle="tooltip"]').tooltip();
                
                // 체크박스 이벤트 바인딩
                $('.license-checkbox').off('change').on('change', function() {
                    const licenseId = $(this).data('license-id');
                    if ($(this).is(':checked')) {
                        window.licenseListManager.selectedLicenses.add(licenseId);
                    } else {
                        window.licenseListManager.selectedLicenses.delete(licenseId);
                    }
                    window.licenseListManager.updateBulkActionButtons();
                });
            },
            initComplete: function() {
                // 테이블 로드 완료 후 추가 설정
                console.log('License table initialized');
            }
        });
    }
    
    /**
     * 이벤트 바인딩
     */
    bindEvents() {
        // 검색 폼 제출
        $('#filterForm').on('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });
        
        // 실시간 검색
        $('#search').on('input', window.debounce((e) => {
            this.performSearch($(e.target).val());
        }, 500));
        
        // 필터 변경
        $('#status, #validity, #limit').on('change', () => {
            this.applyFilters();
        });
        
        // 전체 선택 체크박스
        $('#selectAll').on('change', (e) => {
            this.toggleSelectAll($(e.target).is(':checked'));
        });
        
        // 갱신 폼 제출
        $('#renewForm').on('submit', (e) => this.handleRenewalSubmit(e));
        
        // 테이블 행 클릭 (선택)
        $('#licenseTable tbody').on('click', 'tr', (e) => {
            if (!$(e.target).is('input, button, a') && !$(e.target).closest('button, a').length) {
                const checkbox = $(e.currentTarget).find('.license-checkbox');
                checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
            }
        });
        
        // 행 더블클릭 (수정 페이지로 이동)
        $('#licenseTable tbody').on('dblclick', 'tr', (e) => {
            const licenseId = $(e.currentTarget).find('.license-checkbox').data('license-id');
            if (licenseId) {
                window.location.href = `${window.AYABID_CONFIG.BASE_URL}/license_form.php?id=${licenseId}`;
            }
        });
    }
    
    /**
     * 필터 설정
     */
    setupFilters() {
        // URL 파라미터에서 필터 복원
        const urlParams = new URLSearchParams(window.location.search);
        
        ['search', 'status', 'validity', 'limit'].forEach(param => {
            const value = urlParams.get(param);
            if (value) {
                $(`#${param}`).val(value);
            }
        });
        
        // 필터 초기화 버튼
        $('.filter-reset').on('click', () => {
            this.resetFilters();
        });
    }
    
    /**
     * 키보드 단축키 설정
     */
    setupKeyboardShortcuts() {
        $(document).on('keydown', (e) => {
            // Ctrl/Cmd + F: 검색 필드로 포커스
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 70) {
                e.preventDefault();
                $('#search').focus().select();
            }
            
            // Ctrl/Cmd + A: 전체 선택
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 65 && !$(e.target).is('input, textarea')) {
                e.preventDefault();
                this.toggleSelectAll(true);
            }
            
            // Delete: 선택된 항목 삭제
            if (e.keyCode === 46 && this.selectedLicenses.size > 0 && !$(e.target).is('input, textarea')) {
                e.preventDefault();
                this.confirmBulkDelete();
            }
            
            // ESC: 선택 해제
            if (e.keyCode === 27) {
                this.toggleSelectAll(false);
                $('.modal.show').modal('hide');
            }
            
            // Enter: 선택된 항목이 하나면 수정
            if (e.keyCode === 13 && this.selectedLicenses.size === 1 && !$(e.target).is('input, textarea, button')) {
                const licenseId = Array.from(this.selectedLicenses)[0];
                window.location.href = `${window.AYABID_CONFIG.BASE_URL}/license_form.php?id=${licenseId}`;
            }
        });
    }
    
    /**
     * 일괄 작업 설정
     */
    setupBulkActions() {
        // 일괄 작업 버튼 컨테이너 생성
        const bulkActions = $(`
            <div id="bulkActions" class="alert alert-info mt-3" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="selected-count">0개 선택됨</span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-success" onclick="window.licenseListManager.bulkRenew()">
                            <i class="fas fa-redo me-1"></i>일괄 갱신
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="window.licenseListManager.bulkChangeStatus('SUSPENDED')">
                            <i class="fas fa-pause me-1"></i>일괄 정지
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="window.licenseListManager.bulkChangeStatus('ACTIVE')">
                            <i class="fas fa-play me-1"></i>일괄 활성화
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="window.licenseListManager.confirmBulkDelete()">
                            <i class="fas fa-trash me-1"></i>일괄 삭제
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        $('#licenseTable').after(bulkActions);
    }
    
    /**
     * 검색 수행
     */
    performSearch(query) {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        this.searchTimeout = setTimeout(() => {
            $('#search').val(query);
            this.applyFilters();
        }, 300);
    }
    
    /**
     * 필터 적용
     */
    applyFilters() {
        const formData = $('#filterForm').serialize();
        const newUrl = `${window.location.pathname}?${formData}`;
        
        // 필터 설정 저장
        this.saveFilters();
        
        // 페이지 새로고침으로 필터 적용
        window.location.href = newUrl;
    }
    
    /**
     * 필터 초기화
     */
    resetFilters() {
        $('#filterForm')[0].reset();
        window.location.href = window.location.pathname;
    }
    
    /**
     * 필터 저장
     */
    saveFilters() {
        const filters = {
            search: $('#search').val(),
            status: $('#status').val(),
            validity: $('#validity').val(),
            limit: $('#limit').val()
        };
        window.setSessionData('license_filters', filters);
    }
    
    /**
     * 필터 복원
     */
    restoreFilters() {
        const savedFilters = window.getSessionData('license_filters');
        if (savedFilters) {
            Object.keys(savedFilters).forEach(key => {
                const value = savedFilters[key];
                if (value) {
                    $(`#${key}`).val(value);
                }
            });
        }
    }
    
    /**
     * 전체 선택 토글
     */
    toggleSelectAll(checked) {
        $('.license-checkbox').prop('checked', checked).trigger('change');
        $('#selectAll').prop('checked', checked);
    }
    
    /**
     * 일괄 작업 버튼 업데이트
     */
    updateBulkActionButtons() {
        const count = this.selectedLicenses.size;
        const bulkActions = $('#bulkActions');
        
        if (count > 0) {
            bulkActions.show();
            bulkActions.find('.selected-count').text(`${count}개 선택됨`);
        } else {
            bulkActions.hide();
        }
        
        // 전체 선택 체크박스 상태 업데이트
        const totalCheckboxes = $('.license-checkbox').length;
        $('#selectAll').prop('checked', count === totalCheckboxes && count > 0);
    }
    
    /**
     * 갱신 폼 제출 처리
     */
    async handleRenewalSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const licenseId = formData.get('license_id');
        const renewalPeriod = formData.get('renewal_period');
        
        if (!licenseId || !renewalPeriod) {
            window.showAlert('필수 정보가 누락되었습니다.', 'warning');
            return;
        }
        
        try {
            const submitBtn = $(form).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>처리 중...');
            
            const response = await $.ajax({
                url: `${window.AYABID_CONFIG.BASE_URL}/api/renew_license.php`,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 30000
            });
            
            if (response.success) {
                window.showAlert('발급키가 성공적으로 갱신되었습니다.', 'success');
                $('#renewModal').modal('hide');
                
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(response.message || '갱신 처리에 실패했습니다.');
            }
            
        } catch (error) {
            console.error('Renewal error:', error);
            const errorMsg = error.responseJSON?.message || error.message || '갱신 처리 중 오류가 발생했습니다.';
            window.showAlert(errorMsg, 'danger');
        } finally {
            const submitBtn = $(form).find('button[type="submit"]');
            submitBtn.prop('disabled', false).html(originalText);
        }
    }
    
    /**
     * 일괄 갱신
     */
    async bulkRenew() {
        if (this.selectedLicenses.size === 0) {
            window.showAlert('갱신할 발급키를 선택해주세요.', 'warning');
            return;
        }
        
        const licenses = Array.from(this.selectedLicenses);
        const result = await this.showBulkRenewModal(licenses);
        
        if (result) {
            this.processBulkRenewal(licenses, result.period);
        }
    }
    
    /**
     * 일괄 갱신 모달 표시
     */
    showBulkRenewModal(licenses) {
        return new Promise((resolve) => {
            const modal = $(`
                <div class="modal fade" id="bulkRenewModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">일괄 갱신</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p><strong>${licenses.length}개</strong>의 발급키를 일괄 갱신합니다.</p>
                                <div class="mb-3">
                                    <label class="form-label">갱신 기간</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bulk_renewal_period" value="3일" id="bulk_period3">
                                        <label class="form-check-label" for="bulk_period3">3일</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bulk_renewal_period" value="7일" id="bulk_period7">
                                        <label class="form-check-label" for="bulk_period7">7일</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bulk_renewal_period" value="30일" id="bulk_period30" checked>
                                        <label class="form-check-label" for="bulk_period30">30일</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="bulk_renewal_period" value="영구" id="bulk_periodPermanent">
                                        <label class="form-check-label" for="bulk_periodPermanent">영구</label>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                                <button type="button" class="btn btn-primary" id="confirmBulkRenew">갱신하기</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            modal.modal('show');
            
            modal.on('hidden.bs.modal', () => {
                modal.remove();
                resolve(null);
            });
            
            $('#confirmBulkRenew').on('click', () => {
                const period = $('input[name="bulk_renewal_period"]:checked').val();
                modal.modal('hide');
                resolve({ period });
            });
        });
    }
    
    /**
     * 일괄 갱신 처리
     */
    async processBulkRenewal(licenses, period) {
        const progress = this.showProgress('발급키 갱신 중...', licenses.length);
        let successCount = 0;
        let errorCount = 0;
        
        for (let i = 0; i < licenses.length; i++) {
            const licenseId = licenses[i];
            
            try {
                const formData = new FormData();
                formData.append('license_id', licenseId);
                formData.append('renewal_period', period);
                formData.append('_token', window.getCSRFToken());
                
                const response = await $.ajax({
                    url: `${window.AYABID_CONFIG.BASE_URL}/api/renew_license.php`,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 10000
                });
                
                if (response.success) {
                    successCount++;
                } else {
                    errorCount++;
                }
                
            } catch (error) {
                errorCount++;
                console.error(`License ${licenseId} renewal failed:`, error);
            }
            
            progress.update(i + 1);
        }
        
        progress.close();
        
        if (errorCount === 0) {
            window.showAlert(`${successCount}개의 발급키가 성공적으로 갱신되었습니다.`, 'success');
        } else {
            window.showAlert(`${successCount}개 성공, ${errorCount}개 실패했습니다.`, 'warning');
        }
        
        setTimeout(() => window.location.reload(), 2000);
    }
    
    /**
     * 진행률 표시
     */
    showProgress(title, total) {
        const modal = $(`
            <div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">${title}</h6>
                        </div>
                        <div class="modal-body text-center">
                            <div class="progress mb-3">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <div class="progress-text">0 / ${total}</div>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.modal('show');
        
        return {
            update: (current) => {
                const percent = (current / total) * 100;
                modal.find('.progress-bar').css('width', `${percent}%`);
                modal.find('.progress-text').text(`${current} / ${total}`);
            },
            close: () => {
                modal.modal('hide');
                setTimeout(() => modal.remove(), 300);
            }
        };
    }
    
    /**
     * 일괄 상태 변경
     */
    async bulkChangeStatus(newStatus) {
        if (this.selectedLicenses.size === 0) {
            window.showAlert('상태를 변경할 발급키를 선택해주세요.', 'warning');
            return;
        }
        
        const statusText = {
            'ACTIVE': '활성화',
            'SUSPENDED': '정지',
            'EXPIRED': '만료',
            'REVOKED': '취소'
        };
        
        const confirmed = await new Promise(resolve => {
            window.showConfirm(
                `선택된 ${this.selectedLicenses.size}개의 발급키를 "${statusText[newStatus]}" 상태로 변경하시겠습니까?`,
                () => resolve(true),
                '상태 변경'
            );
        });
        
        if (confirmed) {
            // 실제 구현은 API 개발 후 추가
            window.showAlert('일괄 상태 변경 기능은 개발 중입니다.', 'info');
        }
    }
    
    /**
     * 일괄 삭제 확인
     */
    confirmBulkDelete() {
        if (this.selectedLicenses.size === 0) {
            window.showAlert('삭제할 발급키를 선택해주세요.', 'warning');
            return;
        }
        
        window.showConfirm(
            `선택된 ${this.selectedLicenses.size}개의 발급키를 정말 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`,
            () => this.processBulkDelete(),
            '일괄 삭제'
        );
    }
    
    /**
     * 일괄 삭제 처리
     */
    async processBulkDelete() {
        // 실제 구현은 API 개발 후 추가
        window.showAlert('일괄 삭제 기능은 개발 중입니다.', 'info');
    }
    
    /**
     * 정리 작업
     */
    destroy() {
        if (this.dataTable) {
            this.dataTable.destroy();
        }
        
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        $(document).off('keydown');
        $('#bulkActions').remove();
    }
}

// 전역 인스턴스
let licenseListManager;

// DOM 로드 완료 후 초기화
$(document).ready(function() {
    licenseListManager = new LicenseListManager();
    window.licenseListManager = licenseListManager;
    
    // 페이지 언로드 시 정리
    $(window).on('beforeunload', function() {
        if (licenseListManager) {
            licenseListManager.destroy();
        }
    });
});

// 전역 함수들
window.renewLicense = function(licenseId) {
    $('#renewLicenseId').val(licenseId);
    $('#renewModal').modal('show');
};

window.confirmDelete = function(licenseId, licenseKey) {
    window.showConfirm(
        `발급키 "${licenseKey}"를 정말 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`,
        function() {
            deleteLicense(licenseId);
        },
        '발급키 삭제'
    );
};

window.deleteLicense = async function(licenseId) {
    try {
        window.showLoading('발급키를 삭제하는 중...');
        
        const response = await $.ajax({
            url: `${window.AYABID_CONFIG.BASE_URL}/api/delete_license.php`,
            method: 'POST',
            data: {
                license_id: licenseId,
                _token: window.getCSRFToken()
            },
            timeout: 15000
        });
        
        if (response.success) {
            window.showAlert('발급키가 성공적으로 삭제되었습니다.', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(response.message || '삭제 처리에 실패했습니다.');
        }
        
    } catch (error) {
        console.error('Delete error:', error);
        const errorMsg = error.responseJSON?.message || error.message || '삭제 처리 중 오류가 발생했습니다.';
        window.showAlert(errorMsg, 'danger');
    }
};

window.changeStatus = async function(licenseId, newStatus) {
    try {
        window.showLoading('상태를 변경하는 중...');
        
        const response = await $.ajax({
            url: `${window.AYABID_CONFIG.BASE_URL}/api/change_license_status.php`,
            method: 'POST',
            data: {
                license_id: licenseId,
                new_status: newStatus,
                _token: window.getCSRFToken()
            },
            timeout: 15000
        });
        
        if (response.success) {
            window.showAlert('상태가 성공적으로 변경되었습니다.', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(response.message || '상태 변경에 실패했습니다.');
        }
        
    } catch (error) {
        console.error('Status change error:', error);
        const errorMsg = error.responseJSON?.message || error.message || '상태 변경 중 오류가 발생했습니다.';
        window.showAlert(errorMsg, 'danger');
    }
};