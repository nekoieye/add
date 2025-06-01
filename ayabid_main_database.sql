-- =====================================================
-- 아야비드 메인 데이터베이스 (발급키 관리용) - 최종 버전
-- PSR-12 준수, 단일 인증키 기반, MVC 패턴 지원
-- 웹호스팅 환경 (MariaDB 10.04) 호환
-- =====================================================


-- =====================================================
-- 1. 발급키 관리 테이블
-- =====================================================

-- 발급키 마스터 테이블
CREATE TABLE license_keys (
    license_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(128) NOT NULL UNIQUE COMMENT '발급키',
    db_name VARCHAR(100) DEFAULT NULL COMMENT 'DB명 (입찰시스템 DB 식별자)',
    company_name VARCHAR(255) NOT NULL COMMENT '업체명',
    contact_person VARCHAR(100) NOT NULL COMMENT '담당자명',
    contact_email VARCHAR(255) NOT NULL COMMENT '담당자 이메일',
    contact_phone VARCHAR(20) DEFAULT NULL COMMENT '담당자 전화번호',
    license_type ENUM('G2B_A', 'G2B_B', 'G2B_C', 'EAT', 'ALL') NOT NULL DEFAULT 'ALL' COMMENT '발급키 유형',
    status ENUM('ACTIVE', 'SUSPENDED', 'EXPIRED', 'REVOKED') NOT NULL DEFAULT 'ACTIVE' COMMENT '상태',
    validity_period ENUM('3일', '7일', '30일', '영구') NOT NULL DEFAULT '30일' COMMENT '사용 기간 유형',
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '발급일시',
    expires_at DATETIME NOT NULL COMMENT '만료일시',
    first_accessed DATETIME DEFAULT NULL COMMENT '첫 접근일시 (차감 시작 기준점, NULL이면 아직 미사용)',
    days_remaining INT DEFAULT NULL COMMENT '남은 일수 (충전형, 첫 접근부터 차감)',
    last_accessed DATETIME DEFAULT NULL COMMENT '마지막 접근일시',
    access_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '접근 횟수',
    max_concurrent_sessions TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '최대 동시 세션',
    current_sessions TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '현재 세션 수',
    issued_by VARCHAR(100) NOT NULL DEFAULT 'ADMIN' COMMENT '발급자',
    notes TEXT DEFAULT NULL COMMENT '비고',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_db_name (db_name),
    INDEX idx_company_name (company_name),
    INDEX idx_status (status),
    INDEX idx_license_type (license_type),
    INDEX idx_validity_period (validity_period),
    INDEX idx_expires_at (expires_at),
    INDEX idx_issued_at (issued_at),
    INDEX idx_first_accessed (first_accessed),
    INDEX idx_last_accessed (last_accessed),
    INDEX idx_days_remaining (days_remaining)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='발급키 마스터 테이블';

-- 발급키 연장/갱신 이력 테이블
CREATE TABLE license_renewals (
    renewal_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    license_id INT UNSIGNED NOT NULL COMMENT '발급키 ID',
    renewal_type ENUM('EXTENSION', 'QUICK_RENEWAL') NOT NULL DEFAULT 'EXTENSION' COMMENT '갱신 유형',
    previous_expires_at DATETIME NOT NULL COMMENT '이전 만료일시',
    new_expires_at DATETIME NOT NULL COMMENT '새로운 만료일시',
    previous_validity_period ENUM('3일', '7일', '30일', '영구') NOT NULL COMMENT '이전 사용기간',
    new_validity_period ENUM('3일', '7일', '30일', '영구') NOT NULL COMMENT '새로운 사용기간',
    extension_days INT UNSIGNED NOT NULL COMMENT '연장 일수',
    renewal_reason VARCHAR(255) DEFAULT NULL COMMENT '갱신 사유',
    renewed_by VARCHAR(100) NOT NULL DEFAULT 'ADMIN' COMMENT '갱신 처리자',
    client_ip VARCHAR(45) DEFAULT NULL COMMENT '처리자 IP',
    renewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '갱신 처리일시',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES license_keys(license_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_license_id (license_id),
    INDEX idx_renewal_type (renewal_type),
    INDEX idx_renewed_at (renewed_at),
    INDEX idx_extension_days (extension_days)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='발급키 갱신 이력 테이블';

-- 발급키 상태 변경 이력 테이블
CREATE TABLE license_status_history (
    history_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    license_id INT UNSIGNED NOT NULL COMMENT '발급키 ID',
    previous_status ENUM('ACTIVE', 'SUSPENDED', 'EXPIRED', 'REVOKED') NOT NULL COMMENT '이전 상태',
    new_status ENUM('ACTIVE', 'SUSPENDED', 'EXPIRED', 'REVOKED') NOT NULL COMMENT '새로운 상태',
    change_reason VARCHAR(255) DEFAULT NULL COMMENT '변경 사유',
    changed_by VARCHAR(100) NOT NULL DEFAULT 'ADMIN' COMMENT '변경 처리자',
    client_ip VARCHAR(45) DEFAULT NULL COMMENT '처리자 IP',
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '변경 처리일시',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES license_keys(license_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_license_id (license_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_previous_status (previous_status),
    INDEX idx_new_status (new_status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='발급키 상태 변경 이력 테이블';

-- 발급키 접근 로그 테이블
DROP TABLE IF EXISTS license_access_logs;
CREATE TABLE license_access_logs (
    log_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    license_id INT UNSIGNED NOT NULL COMMENT '발급키 ID',
    access_ip VARCHAR(45) NOT NULL COMMENT '접근 IP',
    user_agent TEXT DEFAULT NULL COMMENT 'User Agent',
    access_result ENUM('SUCCESS', 'EXPIRED', 'SUSPENDED', 'REVOKED', 'NOT_FOUND', 'ERROR') NOT NULL COMMENT '접근 결과',
    session_id VARCHAR(128) DEFAULT NULL COMMENT '세션 ID',
    accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '접근일시',
    response_time_ms INT UNSIGNED DEFAULT NULL COMMENT '응답시간 (밀리초)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES license_keys(license_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_license_id (license_id),
    INDEX idx_accessed_at (accessed_at),
    INDEX idx_access_ip (access_ip),
    INDEX idx_access_result (access_result),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='발급키 접근 로그 테이블';

-- 발급키 세션 관리 테이블

CREATE TABLE license_sessions (
    session_id VARCHAR(128) NOT NULL PRIMARY KEY COMMENT '세션 ID',
    license_id INT UNSIGNED NOT NULL COMMENT '발급키 ID',
    session_data TEXT DEFAULT NULL COMMENT '세션 데이터',
    client_ip VARCHAR(45) NOT NULL COMMENT '클라이언트 IP',
    user_agent TEXT DEFAULT NULL COMMENT 'User Agent',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '세션 시작일시',
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '마지막 활동일시',
    expires_at DATETIME NOT NULL COMMENT '세션 만료일시',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT '활성 상태',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES license_keys(license_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_license_id (license_id),
    INDEX idx_started_at (started_at),
    INDEX idx_last_activity (last_activity),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_active (is_active),
    INDEX idx_client_ip (client_ip)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='발급키 세션 관리 테이블';

-- =====================================================
-- 2. 관리자 액션 및 시스템 로그 테이블
-- =====================================================

-- 관리자 액션 로그 테이블 (PSR-12 준수, 단순화)

CREATE TABLE admin_action_logs (
    log_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    action_type ENUM('CREATE', 'READ', 'UPDATE', 'DELETE', 'RENEW', 'LOGIN', 'LOGOUT') NOT NULL COMMENT '액션 유형',
    target_type ENUM('LICENSE', 'SYSTEM', 'AUTH', 'MONITORING') NOT NULL COMMENT '대상 유형',
    target_id INT UNSIGNED DEFAULT NULL COMMENT '대상 ID (발급키 ID 등)',
    action_details TEXT DEFAULT NULL COMMENT '액션 상세 내용',
    old_values JSON DEFAULT NULL COMMENT '변경 전 값',
    new_values JSON DEFAULT NULL COMMENT '변경 후 값',
    admin_session_id VARCHAR(128) DEFAULT NULL COMMENT '관리자 세션 ID',
    client_ip VARCHAR(45) DEFAULT NULL COMMENT 'IP 주소',
    user_agent TEXT DEFAULT NULL COMMENT 'User Agent',
    execution_time_ms INT UNSIGNED DEFAULT NULL COMMENT '실행 시간 (밀리초)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_type (action_type),
    INDEX idx_target_type (target_type),
    INDEX idx_target_id (target_id),
    INDEX idx_admin_session_id (admin_session_id),
    INDEX idx_created_at (created_at),
    INDEX idx_client_ip (client_ip)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='관리자 액션 로그 테이블';

-- 시스템 로그 테이블 (단순화)

CREATE TABLE system_logs (
    log_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    log_level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL COMMENT '로그 레벨',
    log_category ENUM('AUTH', 'DATABASE', 'LICENSE', 'MONITORING', 'SYSTEM') NOT NULL COMMENT '로그 카테고리',
    log_message TEXT NOT NULL COMMENT '로그 메시지',
    log_context JSON DEFAULT NULL COMMENT '로그 컨텍스트 데이터',
    related_license_id INT UNSIGNED DEFAULT NULL COMMENT '관련 발급키 ID',
    session_id VARCHAR(128) DEFAULT NULL COMMENT '세션 ID',
    client_ip VARCHAR(45) DEFAULT NULL COMMENT 'IP 주소',
    user_agent TEXT DEFAULT NULL COMMENT 'User Agent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (related_license_id) REFERENCES license_keys(license_id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_log_level (log_level),
    INDEX idx_log_category (log_category),
    INDEX idx_related_license_id (related_license_id),
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at),
    INDEX idx_client_ip (client_ip)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='시스템 로그 테이블';

-- DB 연결 상태 모니터링 테이블

CREATE TABLE db_connection_status (
    status_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    db_name VARCHAR(100) NOT NULL COMMENT 'DB명',
    connection_result ENUM('SUCCESS', 'FAILED', 'TIMEOUT', 'UNKNOWN') NOT NULL COMMENT '연결 결과',
    connection_time_ms INT UNSIGNED DEFAULT NULL COMMENT '연결 시간 (밀리초)',
    error_message TEXT DEFAULT NULL COMMENT '오류 메시지',
    last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '마지막 체크 시간',
    check_count INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '체크 횟수',
    success_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '성공 횟수',
    failure_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '실패 횟수',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_db_name (db_name),
    INDEX idx_connection_result (connection_result),
    INDEX idx_last_checked_at (last_checked_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='DB 연결 상태 모니터링 테이블';

-- =====================================================
-- 3. 트리거 설정 (PSR-12 준수)
-- =====================================================


-- =====================================================
-- 4. 갱신 기능을 위한 Stored Procedures
-- =====================================================

DELIMITER $$

-- 충전형 갱신 프로시저 (현재 남은 기간에 추가하는 방식)
CREATE PROCEDURE sp_quick_renewal(
    IN p_license_id INT UNSIGNED,
    IN p_renewal_period ENUM('3일', '7일', '30일', '영구'),
    IN p_admin_session_id VARCHAR(128),
    IN p_client_ip VARCHAR(45)
)
BEGIN
    DECLARE v_old_expires DATETIME;
    DECLARE v_old_period ENUM('3일', '7일', '30일', '영구');
    DECLARE v_current_remaining INT;
    DECLARE v_new_expires DATETIME;
    DECLARE v_extension_days INT;
    DECLARE v_license_key VARCHAR(128);
    DECLARE v_company_name VARCHAR(255);
    DECLARE v_first_accessed DATETIME;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- 현재 라이센스 정보 조회
    SELECT license_key, company_name, expires_at, validity_period, days_remaining, first_accessed
    INTO v_license_key, v_company_name, v_old_expires, v_old_period, v_current_remaining, v_first_accessed
    FROM license_keys 
    WHERE license_id = p_license_id;
    
    -- 충전형 로직: 갱신 기간 설정
    CASE p_renewal_period
        WHEN '3일' THEN SET v_extension_days = 3;
        WHEN '7일' THEN SET v_extension_days = 7;
        WHEN '30일' THEN SET v_extension_days = 30;
        WHEN '영구' THEN SET v_extension_days = 36500;
    END CASE;
    
    -- 새로운 만료일 계산 (충전형 개념)
    IF p_renewal_period = '영구' THEN
        -- 영구로 변경하는 경우
        SET v_new_expires = '2099-12-31 23:59:59';
    ELSEIF v_old_period = '영구' AND p_renewal_period != '영구' THEN
        -- 영구에서 기간제로 변경하는 경우 (해당 기간으로 새로 설정)
        IF v_first_accessed IS NULL THEN
            -- 아직 사용 시작 안한 경우 현재 시간 기준
            SET v_new_expires = DATE_ADD(NOW(), INTERVAL v_extension_days DAY);
        ELSE
            -- 이미 사용 중인 경우 첫 접근 시점 기준으로 재계산
            SET v_new_expires = DATE_ADD(NOW(), INTERVAL v_extension_days DAY);
        END IF;
    ELSE
        -- 일반적인 충전 (현재 남은 기간에 추가)
        IF v_current_remaining IS NULL OR v_current_remaining <= 0 THEN
            -- 만료되었거나 남은 기간이 없는 경우 현재 시간 기준으로 새로 시작
            SET v_new_expires = DATE_ADD(NOW(), INTERVAL v_extension_days DAY);
        ELSE
            -- 남은 기간이 있는 경우 현재 만료일에 추가
            SET v_new_expires = DATE_ADD(v_old_expires, INTERVAL v_extension_days DAY);
        END IF;
    END IF;
    
    -- 라이센스 정보 업데이트
    UPDATE license_keys 
    SET 
        validity_period = p_renewal_period,
        expires_at = v_new_expires,
        status = 'ACTIVE',
        updated_at = NOW()
    WHERE license_id = p_license_id;
    
    -- 갱신 이력 기록
    INSERT INTO license_renewals (
        license_id,
        renewal_type,
        previous_expires_at,
        new_expires_at,
        previous_validity_period,
        new_validity_period,
        extension_days,
        renewal_reason,
        renewed_by,
        client_ip
    ) VALUES (
        p_license_id,
        'QUICK_RENEWAL',
        v_old_expires,
        v_new_expires,
        v_old_period,
        p_renewal_period,
        v_extension_days,
        CONCAT('충전형 갱신: ', v_old_period, ' -> ', p_renewal_period, ' (', v_extension_days, '일 추가)'),
        'ADMIN',
        p_client_ip
    );
    
    -- 관리자 액션 로그 기록
    INSERT INTO admin_action_logs (
        action_type,
        target_type,
        target_id,
        action_details,
        old_values,
        new_values,
        admin_session_id,
        client_ip
    ) VALUES (
        'RENEW',
        'LICENSE',
        p_license_id,
        CONCAT('충전형 갱신: ', v_license_key, ' (', v_company_name, ') - ', v_extension_days, '일 추가'),
        JSON_OBJECT('expires_at', v_old_expires, 'validity_period', v_old_period, 'days_remaining', v_current_remaining),
        JSON_OBJECT('expires_at', v_new_expires, 'validity_period', p_renewal_period),
        p_admin_session_id,
        p_client_ip
    );
    
    COMMIT;
    
    -- 성공 응답
    SELECT 
        'SUCCESS' as result, 
        v_new_expires as new_expires_at, 
        v_extension_days as extension_days,
        CASE 
            WHEN p_renewal_period = '영구' THEN -1
            ELSE GREATEST(0, DATEDIFF(v_new_expires, NOW()))
        END as new_days_remaining;
    
END$

-- DB 연결 상태 업데이트 프로시저
CREATE PROCEDURE sp_update_db_connection_status(
    IN p_db_name VARCHAR(100),
    IN p_connection_result ENUM('SUCCESS', 'FAILED', 'TIMEOUT', 'UNKNOWN'),
    IN p_connection_time_ms INT UNSIGNED,
    IN p_error_message TEXT
)
BEGIN
    INSERT INTO db_connection_status (
        db_name,
        connection_result,
        connection_time_ms,
        error_message,
        last_checked_at,
        check_count,
        success_count,
        failure_count
    ) VALUES (
        p_db_name,
        p_connection_result,
        p_connection_time_ms,
        p_error_message,
        NOW(),
        1,
        CASE WHEN p_connection_result = 'SUCCESS' THEN 1 ELSE 0 END,
        CASE WHEN p_connection_result != 'SUCCESS' THEN 1 ELSE 0 END
    )
    ON DUPLICATE KEY UPDATE
        connection_result = p_connection_result,
        connection_time_ms = p_connection_time_ms,
        error_message = p_error_message,
        last_checked_at = NOW(),
        check_count = check_count + 1,
        success_count = success_count + CASE WHEN p_connection_result = 'SUCCESS' THEN 1 ELSE 0 END,
        failure_count = failure_count + CASE WHEN p_connection_result != 'SUCCESS' THEN 1 ELSE 0 END,
        updated_at = NOW();
END$$

DELIMITER ;

-- =====================================================
-- 5. 뷰 생성 (효율적인 데이터 조회용)
-- =====================================================

-- 대시보드용 발급키 요약 뷰
CREATE VIEW v_dashboard_license_summary AS
SELECT 
    lk.license_id,
    lk.license_key,
    lk.db_name,
    lk.company_name,
    lk.contact_person,
    lk.contact_email,
    lk.contact_phone,
    lk.license_type,
    lk.status,
    lk.validity_period,
    lk.issued_at,
    lk.expires_at,
    lk.days_remaining,
    lk.last_accessed,
    lk.access_count,
    lk.current_sessions,
    CASE 
        WHEN lk.validity_period = '영구' THEN 'PERMANENT'
        WHEN lk.expires_at <= NOW() THEN 'EXPIRED'
        WHEN lk.expires_at <= DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 'EXPIRING_URGENT'
        WHEN lk.expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 'EXPIRING_SOON'
        ELSE 'ACTIVE'
    END AS expiry_status,
    CASE
        WHEN lk.validity_period = '영구' THEN '영구'
        WHEN lk.expires_at <= NOW() THEN '만료됨'
        WHEN lk.days_remaining = 0 THEN '오늘 만료'
        WHEN lk.days_remaining = 1 THEN '내일 만료'
        ELSE CONCAT(lk.days_remaining, '일 남음')
    END AS expiry_text,
    -- DB 연결 상태 정보
    dcs.connection_result as db_connection_status,
    dcs.last_checked_at as db_last_checked,
    dcs.connection_time_ms as db_connection_time
FROM license_keys lk
LEFT JOIN db_connection_status dcs ON lk.db_name = dcs.db_name;

-- 시스템 통계 요약 뷰
CREATE VIEW v_system_statistics AS
SELECT 
    -- 발급키 통계
    COUNT(*) AS total_licenses,
    SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_licenses,
    SUM(CASE WHEN status = 'SUSPENDED' THEN 1 ELSE 0 END) AS suspended_licenses,
    SUM(CASE WHEN status = 'EXPIRED' THEN 1 ELSE 0 END) AS expired_licenses,
    SUM(CASE WHEN status = 'REVOKED' THEN 1 ELSE 0 END) AS revoked_licenses,
    
    -- 사용기간별 통계
    SUM(CASE WHEN validity_period = '영구' THEN 1 ELSE 0 END) AS permanent_licenses,
    SUM(CASE WHEN validity_period = '3일' THEN 1 ELSE 0 END) AS three_day_licenses,
    SUM(CASE WHEN validity_period = '7일' THEN 1 ELSE 0 END) AS seven_day_licenses,
    SUM(CASE WHEN validity_period = '30일' THEN 1 ELSE 0 END) AS thirty_day_licenses,
    
    -- 만료 관련 통계
    SUM(CASE WHEN expires_at <= NOW() AND validity_period != '영구' THEN 1 ELSE 0 END) AS overdue_licenses,
    SUM(CASE WHEN expires_at <= DATE_ADD(NOW(), INTERVAL 3 DAY) AND validity_period != '영구' THEN 1 ELSE 0 END) AS expiring_urgent,
    SUM(CASE WHEN expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND validity_period != '영구' THEN 1 ELSE 0 END) AS expiring_soon,
    
    -- 활동 통계
    AVG(access_count) AS avg_access_count,
    SUM(current_sessions) AS total_active_sessions,
    COUNT(DISTINCT db_name) AS total_connected_dbs
FROM license_keys;

-- 최근 활동 요약 뷰
CREATE VIEW v_recent_activities AS
SELECT 
    'LICENSE_ACCESS' as activity_type,
    CONCAT('라이센스 접근: ', lk.license_key, ' (', lk.company_name, ')') as activity_description,
    lal.access_result as result_status,
    lal.client_ip as client_ip,
    lal.accessed_at as activity_time
FROM license_access_logs lal
JOIN license_keys lk ON lal.license_id = lk.license_id
WHERE lal.accessed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)

UNION ALL

SELECT 
    'ADMIN_ACTION' as activity_type,
    CONCAT(aal.action_type, ': ', aal.action_details) as activity_description,
    'SUCCESS' as result_status,
    aal.client_ip as client_ip,
    aal.created_at as activity_time
FROM admin_action_logs aal
WHERE aal.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)

ORDER BY activity_time DESC
LIMIT 50;

-- =====================================================