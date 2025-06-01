/**
 * 아야비드 발급키 관리 시스템 - 대시보드 JavaScript
 * PSR-12 준수, Chart.js 활용, 실시간 모니터링
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

'use strict';

class DashboardManager {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
        this.autoRefresh = false;
        this.refreshRate = 30000; // 30초
        this.isUpdating = false;
        
        this.init();
    }
    
    /**
     * 초기화
     */
    init() {
        $(document).ready(() => {
            this.initializeCharts();
            this.initializeTables();
            this.bindEvents();
            this.setupAutoRefresh();
            this.updateLastRefresh();
            this.setupKeyboardShortcuts();
        });
    }
    
    /**
     * 차트 초기화
     */
    initializeCharts() {
        // Chart.js 전역 설정
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#5a5c69';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.boxWidth = 12;
        
        this.initializePeriodDistributionChart();
        this.initializeDailyIssueChart();
    }
    
    /**
     * 사용기간별 분포 차트 초기화
     */
    initializePeriodDistributionChart() {
        const ctx = document.getElementById('periodDistributionChart');
        if (!ctx) return;
        
        const data = window.dashboardData?.periodDistribution || {};
        
        // 데이터 준비
        const labels = [];
        const values = [];
        const colors = [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'
        ];
        
        Object.entries(data).forEach(([period, count]) => {
            labels.push(this.formatPeriodLabel(period));
            values.push(count);
        });
        
        // 빈 데이터 처리
        if (values.length === 0) {
            labels.push('데이터 없음');
            values.push(1);
        }
        
        this.charts.periodDistribution = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverBorderWidth: 5,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 11,
                                weight: '500'
                            },
                            color: '#5a5c69',
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const dataset = data.datasets[0];
                                        const value = dataset.data[i];
                                        const total = dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        
                                        return {
                                            text: `${label} (${percentage}%)`,
                                            fillStyle: dataset.backgroundColor[i],
                                            strokeStyle: dataset.borderColor,
                                            lineWidth: dataset.borderWidth,
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#fff',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value}개 (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '65%',
                animation: {
                    animateRotate: true,
                    duration: 2000,
                    easing: 'easeOutQuart'
                },
                interaction: {
                    intersect: false,
                    mode: 'point'
                }
            }
        });
    }
    
    /**
     * 일별 발급 추이 차트 초기화
     */
    initializeDailyIssueChart() {
        const ctx = document.getElementById('dailyIssueChart');
        if (!ctx) return;
        
        const data = window.dashboardData?.dailyStats || [];
        
        // 데이터 준비 (최근 30일)
        const labels = [];
        const values = [];
        const now = new Date();
        
        // 최근 30일 데이터 생성
        for (let i = 29; i >= 0; i--) {
            const date = new Date(now);
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            
            labels.push(this.formatDateLabel(dateStr));
            
            // 해당 날짜의 데이터 찾기
            const dayData = data.find(d => d.date === dateStr);
            values.push(dayData ? parseInt(dayData.count) : 0);
        }
        
        this.charts.dailyIssue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '발급 건수',
                    data: values,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#4e73df',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#4e73df',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#4e73df',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                return `발급 건수: ${context.parsed.y}개`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxTicksLimit: 7,
                            color: '#858796',
                            font: {
                                size: 10
                            },
                            callback: function(value, index) {
                                // 일주일마다 표시
                                return index % 7 === 0 ? this.getLabelForValue(value) : '';
                            }
                        },
                        border: {
                            display: false
                        }
                    },
                    y: {
                        display: true,
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#858796',
                            font: {
                                size: 10
                            },
                            callback: function(value) {
                                return value + '개';
                            },
                            stepSize: Math.max(1, Math.ceil(Math.max(...values) / 5))
                        },
                        border: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeOutQuart'
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });
    }
    
    /**
     * 테이블 초기화
     */
    initializeTables() {
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
            }
        };
        
        // 만료 임박 테이블
        if ($('#expiringTable').length) {
            this.charts.expiringTable = $('#expiringTable').DataTable({
                responsive: true,
                pageLength: 5,
                lengthChange: false,
                searching: false,
                ordering: true,
                info: false,
                paging: false,
                language: koreanLang,
                columnDefs: [
                    { 
                        orderable: false, 
                        targets: [5] // 액션 컬럼 정렬 비활성화
                    },
                    {
                        targets: [3], // 만료일 컬럼
                        type: 'date'
                    }
                ],
                order: [[3, 'asc']], // 만료일 기준 오름차순
                drawCallback: function() {
                    // 툴팁 재초기화
                    $('[data-bs-toggle="tooltip"]').tooltip();
                }
            });
        }
    }
    
    /**
     * 이벤트 바인딩
     */
    bindEvents() {
        // 갱신 폼 제출
        $('#renewForm').on('submit', (e) => this.handleRenewalSubmit(e));
        
        // 자동 새로고침 토글
        $('#autoRefreshToggle').on('change', (e) => this.toggleAutoRefresh(e.target.checked));
        
        // 새로고침 버튼
        $(document).on('click', '[data-action="refresh"]', () => this.refreshDashboard());
        
        // 윈도우 포커스 이벤트
        $(window).on('focus', () => this.handleWindowFocus());
        $(window).on('blur', () => this.handleWindowBlur());
        
        // 브라우저 가시성 API
        document.addEventListener('visibilitychange', () => this.handleVisibilityChange());
        
        // 차트 클릭 이벤트
        this.bindChartEvents();
        
        // 통계 카드 클릭 이벤트
        this.bindStatCardEvents();
    }
    
    /**
     * 차트 클릭 이벤트 바인딩
     */
    bindChartEvents() {
        // 기간별 분포 차트 클릭
        if (this.charts.periodDistribution) {
            document.getElementById('periodDistributionChart').onclick = (evt) => {
                const points = this.charts.periodDistribution.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                
                if (points.length) {
                    const firstPoint = points[0];
                    const label = this.charts.periodDistribution.data.labels[firstPoint.index];
                    const value = this.charts.periodDistribution.data.datasets[firstPoint.datasetIndex].data[firstPoint.index];
                    
                    // 해당 기간으로 필터링된 발급키 목록으로 이동
                    const period = this.getPeriodFromLabel(label);
                    if (period) {
                        window.location.href = `${window.AYABID_CONFIG.BASE_URL}/license_list.php?validity=${encodeURIComponent(period)}`;
                    }
                }
            };
        }
        
        // 일별 발급 차트 클릭
        if (this.charts.dailyIssue) {
            document.getElementById('dailyIssueChart').onclick = (evt) => {
                const points = this.charts.dailyIssue.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                
                if (points.length) {
                    const firstPoint = points[0];
                    const label = this.charts.dailyIssue.data.labels[firstPoint.index];
                    const value = this.charts.dailyIssue.data.datasets[firstPoint.datasetIndex].data[firstPoint.index];
                    
                    window.showAlert(`${label}: ${value}개 발급`, 'info', 3000);
                }
            };
        }
    }
    
    /**
     * 통계 카드 클릭 이벤트 바인딩
     */
    bindStatCardEvents() {
        $('.stat-card').on('click', function() {
            const cardType = $(this).find('.text-uppercase').text().trim();
            let filter = '';
            
            switch(cardType) {
                case '활성 발급키':
                    filter = 'status=ACTIVE';
                    break;
                case '만료 임박':
                    filter = 'filter=expiring';
                    break;
                case '만료/정지':
                    filter = 'status=EXPIRED';
                    break;
                default:
                    filter = '';
            }
            
            if (filter) {
                window.location.href = `${window.AYABID_CONFIG.BASE_URL}/license_list.php?${filter}`;
            } else {
                window.location.href = `${window.AYABID_CONFIG.BASE_URL}/license_list.php`;
            }
        });
        
        // 카드 호버 효과
        $('.stat-card').on('mouseenter', function() {
            $(this).addClass('shadow-lg');
        }).on('mouseleave', function() {
            $(this).removeClass('shadow-lg');
        });
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
            // 버튼 로딩 상태
            const submitBtn = $(form).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>처리 중...');
            
            // AJAX 요청
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
                
                // 페이지 새로고침 또는 데이터 업데이트
                setTimeout(() => {
                    this.refreshDashboard();
                }, 1500);
            } else {
                throw new Error(response.message || '갱신 처리에 실패했습니다.');
            }
            
        } catch (error) {
            console.error('Renewal error:', error);
            const errorMsg = error.responseJSON?.message || error.message || '갱신 처리 중 오류가 발생했습니다.';
            window.showAlert(errorMsg, 'danger');
        } finally {
            // 버튼 상태 복원
            const submitBtn = $(form).find('button[type="submit"]');
            submitBtn.prop('disabled', false).html(originalText);
        }
    }
    
    /**
     * 자동 새로고침 설정
     */
    setupAutoRefresh() {
        // 로컬 저장소에서 설정 복원
        const autoRefreshSetting = window.getSessionData('dashboard_auto_refresh', true);
        this.autoRefresh = autoRefreshSetting;
        
        // UI 상태 업데이트
        const toggle = $('#autoRefreshToggle');
        if (toggle.length) {
            toggle.prop('checked', this.autoRefresh);
        }
        
        if (this.autoRefresh) {
            this.startAutoRefresh();
        }
    }
    
    /**
     * 자동 새로고침 토글
     */
    toggleAutoRefresh(enabled) {
        this.autoRefresh = enabled;
        window.setSessionData('dashboard_auto_refresh', enabled);
        
        if (enabled) {
            this.startAutoRefresh();
            window.showAlert('자동 새로고침이 활성화되었습니다.', 'info', 3000);
        } else {
            this.stopAutoRefresh();
            window.showAlert('자동 새로고침이 비활성화되었습니다.', 'info', 3000);
        }
    }
    
    /**
     * 자동 새로고침 시작
     */
    startAutoRefresh() {
        this.stopAutoRefresh(); // 기존 타이머 정리
        
        if (this.autoRefresh && !document.hidden) {
            this.refreshInterval = setInterval(() => {
                if (!this.isUpdating) {
                    this.refreshData();
                }
            }, this.refreshRate);
        }
    }
    
    /**
     * 자동 새로고침 중지
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    /**
     * 대시보드 새로고침
     */
    refreshDashboard() {
        window.location.reload();
    }
    
    /**
     * 데이터만 새로고침 (AJAX)
     */
    async refreshData() {
        if (this.isUpdating) return;
        
        this.isUpdating = true;
        
        try {
            const response = await $.ajax({
                url: `${window.AYABID_CONFIG.BASE_URL}/api/dashboard_data.php`,
                method: 'GET',
                timeout: 10000,
                showLoading: false
            });
            
            if (response.success) {
                this.updateDashboardData(response.data);
                this.updateLastRefresh();
                this.showUpdateIndicator();
            }
            
        } catch (error) {
            console.warn('Data refresh error:', error);
            // 자동 새로고침 실패는 조용히 처리
        } finally {
            this.isUpdating = false;
        }
    }
    
    /**
     * 대시보드 데이터 업데이트
     */
    updateDashboardData(data) {
        // 통계 카드 업데이트
        if (data.systemStats) {
            this.updateStatCards(data.systemStats);
        }
        
        // 차트 데이터 업데이트
        if (data.periodDistribution && this.charts.periodDistribution) {
            this.updatePeriodDistributionChart(data.periodDistribution);
        }
        
        if (data.dailyStats && this.charts.dailyIssue) {
            this.updateDailyIssueChart(data.dailyStats);
        }
        
        // 만료 임박 테이블 업데이트
        if (data.expiringLicenses) {
            this.updateExpiringTable(data.expiringLicenses);
        }
        
        // 알림 배지 업데이트
        this.updateNotificationBadge(data.systemStats);
    }
    
    /**
     * 통계 카드 업데이트
     */
    updateStatCards(stats) {
        const animations = [];
        
        $('[data-stat="total"]').each(function() {
            const $this = $(this);
            const newValue = stats.total_licenses || 0;
            animations.push(animateNumber($this, newValue));
        });
        
        $('[data-stat="active"]').each(function() {
            const $this = $(this);
            const newValue = stats.active_licenses || 0;
            animations.push(animateNumber($this, newValue));
        });
        
        $('[data-stat="expiring"]').each(function() {
            const $this = $(this);
            const newValue = (stats.expiring_urgent || 0) + (stats.expiring_soon || 0);
            animations.push(animateNumber($this, newValue));
        });
        
        $('[data-stat="expired"]').each(function() {
            const $this = $(this);
            const newValue = (stats.expired_licenses || 0) + (stats.suspended_licenses || 0);
            animations.push(animateNumber($this, newValue));
        });
        
        // 숫자 애니메이션 함수
        function animateNumber($element, targetValue) {
            const currentValue = parseInt($element.text().replace(/,/g, '')) || 0;
            
            if (currentValue !== targetValue) {
                $element.parent().addClass('pulse');
                
                $({ value: currentValue }).animate({ value: targetValue }, {
                    duration: 1000,
                    easing: 'easeOutCubic',
                    step: function() {
                        $element.text(window.formatNumber(Math.round(this.value)));
                    },
                    complete: function() {
                        $element.text(window.formatNumber(targetValue));
                        $element.parent().removeClass('pulse');
                    }
                });
            }
        }
    }
    
    /**
     * 알림 배지 업데이트
     */
    updateNotificationBadge(stats) {
        const badge = $('#notificationDropdown .badge');
        const urgentCount = stats.expiring_urgent || 0;
        
        if (urgentCount > 0) {
            badge.text(urgentCount).show();
            
            // 새로운 긴급 알림이 있는 경우 깜빡이는 효과
            const previousCount = parseInt(badge.data('previous-count')) || 0;
            if (urgentCount > previousCount) {
                badge.addClass('animate__animated animate__flash');
                setTimeout(() => badge.removeClass('animate__animated animate__flash'), 1000);
            }
            badge.data('previous-count', urgentCount);
        } else {
            badge.hide();
        }
    }
    
    /**
     * 업데이트 인디케이터 표시
     */
    showUpdateIndicator() {
        const indicator = $('<div class="update-indicator">').html('<i class="fas fa-sync"></i> 업데이트됨');
        $('body').append(indicator);
        
        setTimeout(() => indicator.addClass('show'), 100);
        setTimeout(() => {
            indicator.removeClass('show');
            setTimeout(() => indicator.remove(), 300);
        }, 2000);
    }
    
    /**
     * 마지막 업데이트 시간 표시
     */
    updateLastRefresh() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('ko-KR');
        
        let refreshIndicator = $('#lastRefreshTime');
        if (!refreshIndicator.length) {
            refreshIndicator = $('<small id="lastRefreshTime" class="text-muted ms-2"></small>');
            $('.navbar-brand').after(refreshIndicator);
        }
        
        refreshIndicator.text(`최종 업데이트: ${timeString}`);
    }
    
    /**
     * 키보드 단축키 설정
     */
    setupKeyboardShortcuts() {
        $(document).on('keydown', (e) => {
            // Ctrl/Cmd + R: 새로고침
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 82) {
                e.preventDefault();
                this.refreshDashboard();
            }
            
            // Ctrl/Cmd + N: 새 발급키
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 78) {
                e.preventDefault();
                window.location.href = `${window.AYABID_CONFIG.BASE_URL}/license_form.php`;
            }
            
            // ESC: 모달 닫기
            if (e.keyCode === 27) {
                $('.modal.show').modal('hide');
            }
            
            // Space: 자동 새로고침 토글
            if (e.keyCode === 32 && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                const toggle = $('#autoRefreshToggle');
                if (toggle.length) {
                    toggle.prop('checked', !toggle.prop('checked')).trigger('change');
                }
            }
        });
    }
    
    /**
     * 윈도우 포커스 처리
     */
    handleWindowFocus() {
        if (this.autoRefresh) {
            this.startAutoRefresh();
            // 포커스 복귀 시 즉시 새로고침
            setTimeout(() => this.refreshData(), 1000);
        }
    }
    
    /**
     * 윈도우 블러 처리
     */
    handleWindowBlur() {
        // 백그라운드에서는 새로고침 중지
        this.stopAutoRefresh();
    }
    
    /**
     * 가시성 변경 처리
     */
    handleVisibilityChange() {
        if (document.hidden) {
            this.stopAutoRefresh();
        } else if (this.autoRefresh) {
            this.startAutoRefresh();
            this.refreshData(); // 포커스 복귀 시 즉시 새로고침
        }
    }
    
    /**
     * 유틸리티 메서드들
     */
    formatPeriodLabel(period) {
        const labelMap = {
            '3일': '3일',
            '7일': '7일',
            '30일': '30일',
            '영구': '영구'
        };
        return labelMap[period] || period;
    }
    
    getPeriodFromLabel(label) {
        const periodMap = {
            '3일': '3일',
            '7일': '7일',
            '30일': '30일',
            '영구': '영구'
        };
        return periodMap[label.split(' ')[0]] || null;
    }
    
    formatDateLabel(dateStr) {
        const date = new Date(dateStr);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        if (dateStr === today.toISOString().split('T')[0]) {
            return '오늘';
        } else if (dateStr === yesterday.toISOString().split('T')[0]) {
            return '어제';
        } else {
            return `${date.getMonth() + 1}/${date.getDate()}`;
        }
    }
    
    /**
     * 차트 업데이트 메서드들
     */
    updatePeriodDistributionChart(data) {
        const chart = this.charts.periodDistribution;
        if (!chart) return;
        
        const labels = [];
        const values = [];
        
        Object.entries(data).forEach(([period, count]) => {
            labels.push(this.formatPeriodLabel(period));
            values.push(count);
        });
        
        chart.data.labels = labels;
        chart.data.datasets[0].data = values;
        chart.update('none'); // 애니메이션 없이 업데이트
    }
    
    updateDailyIssueChart(data) {
        const chart = this.charts.dailyIssue;
        if (!chart) return;
        
        // 새로운 데이터로 차트 업데이트
        const labels = [];
        const values = [];
        const now = new Date();
        
        for (let i = 29; i >= 0; i--) {
            const date = new Date(now);
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            
            labels.push(this.formatDateLabel(dateStr));
            
            const dayData = data.find(d => d.date === dateStr);
            values.push(dayData ? parseInt(dayData.count) : 0);
        }
        
        chart.data.labels = labels;
        chart.data.datasets[0].data = values;
        chart.update('none');
    }
    
    updateExpiringTable(data) {
        if (this.charts.expiringTable) {
            this.charts.expiringTable.clear();
            
            data.forEach(license => {
                this.charts.expiringTable.row.add([
                    `<code class="text-primary">${license.license_key}</code>`,
                    `<div class="fw-bold">${license.company_name}</div>`,
                    `<div>${license.contact_person}</div><small class="text-muted">${license.contact_email}</small>`,
                    `<div>${window.formatDate(license.expires_at, 'YYYY-MM-DD')}</div><small class="text-muted">${window.formatDate(license.expires_at, 'HH:mm')}</small>`,
                    this.getDaysRemainingBadge(license.days_remaining),
                    `<div class="btn-group btn-group-sm">
                        <a href="${window.AYABID_CONFIG.BASE_URL}/license_form.php?id=${license.license_id}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i></a>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="renewLicense(${license.license_id})"><i class="fas fa-redo"></i></button>
                    </div>`
                ]);
            });
            
            this.charts.expiringTable.draw();
        }
    }
    
    getDaysRemainingBadge(days) {
        if (days === -1) {
            return '<span class="badge bg-success">영구</span>';
        } else if (days === 0) {
            return '<span class="badge bg-danger">만료</span>';
        } else if (days <= 3) {
            return `<span class="badge bg-warning text-dark">${days}일 남음</span>`;
        } else if (days <= 7) {
            return `<span class="badge bg-info">${days}일 남음</span>`;
        } else {
            return `<span class="badge bg-primary">${days}일 남음</span>`;
        }
    }
    
    /**
     * 정리 작업
     */
    destroy() {
        this.stopAutoRefresh();
        
        // 차트 정리
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        
        // DataTable 정리
        if (this.charts.expiringTable && $.fn.DataTable.isDataTable('#expiringTable')) {
            this.charts.expiringTable.destroy();
        }
        
        this.charts = {};
        
        // 이벤트 리스너 정리
        $(document).off('keydown');
        $(window).off('focus blur');
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
    }
}

// 전역 인스턴스
let dashboardManager;

// DOM 로드 완료 후 초기화
$(document).ready(function() {
    dashboardManager = new DashboardManager();
    
    // 페이지 언로드 시 정리
    $(window).on('beforeunload', function() {
        if (dashboardManager) {
            dashboardManager.destroy();
        }
    });
    
    // 성능 모니터링
    if (window.performance && window.performance.timing) {
        const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
        if (window.AYABID_CONFIG.DEBUG_MODE) {
            console.log(`Dashboard load time: ${loadTime}ms`);
        }
    }
});

// 전역 함수로 노출
window.DashboardManager = DashboardManager;
window.renewLicense = function(licenseId) {
    $('#renewLicenseId').val(licenseId);
    $('#renewModal').modal('show');
};

window.refreshDashboard = function() {
    if (dashboardManager) {
        dashboardManager.refreshDashboard();
    } else {
        window.location.reload();
    }
};