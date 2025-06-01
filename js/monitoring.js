/**
 * 아야비드 발급키 관리 시스템 - 모니터링 JavaScript
 * PSR-12 준수, 실시간 차트 업데이트, 성능 최적화
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

'use strict';

class MonitoringManager {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
        this.autoRefresh = true;
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
            this.bindEvents();
            this.setupAutoRefresh();
            this.updateLastRefresh();
        });
    }
    
    /**
     * 차트 초기화
     */
    initializeCharts() {
        this.initializeHourlyChart();
        this.initializeResultChart();
    }
    
    /**
     * 시간대별 차트 초기화
     */
    initializeHourlyChart() {
        const ctx = document.getElementById('hourlyChart');
        if (!ctx || !window.monitoringConfig.hourlyStats) return;
        
        const hourlyData = window.monitoringConfig.hourlyStats;
        const hours = Object.keys(hourlyData).map(h => h + ':00');
        const totalData = Object.values(hourlyData).map(d => d.total);
        const successData = Object.values(hourlyData).map(d => d.success);
        const failedData = Object.values(hourlyData).map(d => d.failed);
        
        this.charts.hourly = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: hours,
                datasets: [{
                    label: '성공',
                    data: successData,
                    backgroundColor: 'rgba(28, 200, 138, 0.8)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    borderWidth: 1
                }, {
                    label: '실패',
                    data: failedData,
                    backgroundColor: 'rgba(231, 74, 59, 0.8)',
                    borderColor: 'rgba(231, 74, 59, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    /**
     * 결과별 분포 차트 초기화
     */
    initializeResultChart() {
        const ctx = document.getElementById('resultChart');
        if (!ctx || !window.monitoringConfig.resultStats) return;
        
        const resultData = window.monitoringConfig.resultStats;
        const labels = ['성공', '실패', '차단', '기타'];
        const data = [
            resultData.SUCCESS || 0,
            resultData.FAILED || 0,
            resultData.BLOCKED || 0,
            resultData.OTHER || 0
        ];
        const colors = [
            'rgba(28, 200, 138, 0.8)',
            'rgba(231, 74, 59, 0.8)',
            'rgba(246, 194, 62, 0.8)',
            'rgba(133, 135, 150, 0.8)'
        ];
        
        this.charts.result = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value}건 (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * 이벤트 바인딩
     */
    bindEvents() {
        // 자동 새로고침 토글
        $('#autoRefresh').on('change', (e) => {
            this.toggleAutoRefresh(e.target.checked);
        });
        
        // 새로고침 버튼
        $('[data-action="refresh"]').on('click', () => {
            this.refreshData();
        });
        
        // 필터 폼 제출
        $('#filterForm').on('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });
    }
    
    /**
     * 자동 새로고침 설정
     */
    setupAutoRefresh() {
        if (this.autoRefresh) {
            this.startAutoRefresh();
        }
    }
    
    /**
     * 자동 새로고침 토글
     */
    toggleAutoRefresh(enabled) {
        this.autoRefresh = enabled;
        
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
        this.stopAutoRefresh();
        
        if (this.autoRefresh) {
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
     * 데이터 새로고침
     */
    async refreshData() {
        if (this.isUpdating) return;
        
        this.isUpdating = true;
        
        try {
            const params = new URLSearchParams(window.location.search);
            
            const response = await $.ajax({
                url: `${window.AYABID_CONFIG.BASE_URL}/api/monitoring_data.php`,
                method: 'GET',
                data: params.toString(),
                timeout: 10000,
                showLoading: false
            });
            
            if (response.success) {
                this.updateMonitoringData(response.data);
                this.updateLastRefresh();
            }
            
        } catch (error) {
            console.warn('Monitoring data refresh error:', error);
        } finally {
            this.isUpdating = false;
        }
    }
    
    /**
     * 모니터링 데이터 업데이트
     */
    updateMonitoringData(data) {
        // 통계 카드 업데이트
        if (data.stats) {
            this.updateStatCards(data.stats);
        }
        
        // 차트 업데이트
        if (data.hourlyStats) {
            this.updateHourlyChart(data.hourlyStats);
        }
        
        if (data.resultStats) {
            this.updateResultChart(data.resultStats);
        }
        
        // 테이블 업데이트
        if (data.authHistory) {
            this.updateAuthHistoryTable(data.authHistory);
        }
    }
    
    /**
     * 통계 카드 업데이트
     */
    updateStatCards(stats) {
        $('#totalAttempts').text(window.formatNumber(stats.total_attempts || 0));
        $('#successfulAttempts').text(window.formatNumber(stats.successful_attempts || 0));
        $('#failedAttempts').text(window.formatNumber(stats.failed_attempts || 0));
        $('#uniqueUsers').text(window.formatNumber(stats.unique_users || 0));
    }
    
    /**
     * 시간대별 차트 업데이트
     */
    updateHourlyChart(hourlyData) {
        if (!this.charts.hourly) return;
        
        const successData = Object.values(hourlyData).map(d => d.success);
        const failedData = Object.values(hourlyData).map(d => d.failed);
        
        this.charts.hourly.data.datasets[0].data = successData;
        this.charts.hourly.data.datasets[1].data = failedData;
        this.charts.hourly.update('none');
    }
    
    /**
     * 결과별 차트 업데이트
     */
    updateResultChart(resultData) {
        if (!this.charts.result) return;
        
        const data = [
            resultData.SUCCESS || 0,
            resultData.FAILED || 0,
            resultData.BLOCKED || 0,
            resultData.OTHER || 0
        ];
        
        this.charts.result.data.datasets[0].data = data;
        this.charts.result.update('none');
    }
    
    /**
     * 인증 이력 테이블 업데이트
     */
    updateAuthHistoryTable(authHistory) {
        const tbody = $('#authHistoryTable tbody');
        if (!tbody.length) return;
        
        tbody.empty();
        
        authHistory.forEach(record => {
            const row = this.createAuthHistoryRow(record);
            tbody.append(row);
        });
    }
    
    /**
     * 인증 이력 행 생성
     */
    createAuthHistoryRow(record) {
        const resultBadgeClass = {
            'SUCCESS': 'bg-success',
            'FAILED': 'bg-danger',
            'BLOCKED': 'bg-warning text-dark',
            'UNKNOWN': 'bg-secondary'
        };
        
        const badgeClass = resultBadgeClass[record.auth_result] || 'bg-secondary';
        
        return $(`
            <tr class="auth-record" data-result="${record.auth_result}">
                <td>${window.formatDateTime(record.auth_time)}</td>
                <td>${record.database_name || '-'}</td>
                <td>${record.user_id || '-'}</td>
                <td class="font-monospace">${record.ip_address}</td>
                <td><span class="badge ${badgeClass}">${record.auth_result}</span></td>
                <td>
                    <small>
                        ${record.auth_method ? `방법: ${record.auth_method}<br>` : ''}
                        ${record.user_agent ? `UA: ${record.user_agent.substring(0, 50)}...` : ''}
                        ${record.session_id ? `<br>세션: ${record.session_id.substring(0, 8)}...` : ''}
                        ${record.error_message ? `<br><span class="text-danger">오류: ${record.error_message}</span>` : ''}
                    </small>
                </td>
            </tr>
        `);
    }
    
    /**
     * 필터 적용
     */
    applyFilters() {
        const form = $('#filterForm');
        const formData = form.serialize();
        
        // URL 업데이트
        const newUrl = window.location.pathname + '?' + formData;
        window.history.pushState({}, '', newUrl);
        
        // 페이지 새로고침
        window.location.reload();
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
            $('.card-header h6').first().after(refreshIndicator);
        }
        
        refreshIndicator.text(`최종 업데이트: ${timeString}`);
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
        
        this.charts = {};
    }
}

// 전역 인스턴스
let monitoringManager;

// DOM 로드 완료 후 초기화
$(document).ready(function() {
    monitoringManager = new MonitoringManager();
    
    // 페이지 언로드 시 정리
    $(window).on('beforeunload', function() {
        if (monitoringManager) {
            monitoringManager.destroy();
        }
    });
});

// 전역 함수로 노출
window.MonitoringManager = MonitoringManager;
window.toggleAutoRefresh = function() {
    const checkbox = document.getElementById('autoRefresh');
    if (monitoringManager && checkbox) {
        monitoringManager.toggleAutoRefresh(checkbox.checked);
    }
};

window.refreshMonitoringData = function() {
    if (monitoringManager) {
        monitoringManager.refreshData();
    } else {
        window.location.reload();
    }
};