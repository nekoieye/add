# 아야비드 발급키 관리 시스템 구현 가이드

## 시스템 개요

PSR-12 규칙 강제적용.
PHP 기반 발급키 관리 시스템으로 단일 인증키 `kimtaeyeon`을 사용하여 관리자 인증을 수행하고, 발급키의 생성/수정/삭제 및 통합 사용자 모니터링 기능을 제공합니다.

## 데이터베이스 구조

### 메인 발급키 관리 DB 연결 정보
```
호스트: localhost
데이터베이스: nekoi93_addkey
사용자명: nekoi93_addkey
비밀번호: z1x2c3
```

### 클라이언트 DB 연결 정보 (동적)
```
호스트: localhost
데이터베이스: {db_name} (license_keys 테이블의 db_name 필드값)
사용자명: {db_name} (데이터베이스명과 동일)
비밀번호: z1x2c3 (모든 DB 공통)
```

### license_keys 테이블 스키마 확장
```sql
ALTER TABLE license_keys 
ADD COLUMN db_name VARCHAR(100) DEFAULT NULL COMMENT 'DB명 (입찰시스템 DB 식별자)' 
AFTER license_key;

ALTER TABLE license_keys 
ADD COLUMN validity_period ENUM('3days', '7days', '30days', 'permanent') NOT NULL DEFAULT '30days' COMMENT '사용 기간 유형'
AFTER expires_at;

ALTER TABLE license_keys 
ADD COLUMN days_remaining INT DEFAULT NULL COMMENT '남은 일수 (계산된 값)'
AFTER validity_period;

CREATE INDEX idx_db_name ON license_keys(db_name);
CREATE INDEX idx_validity_period ON license_keys(validity_period);
CREATE INDEX idx_expires_at ON license_keys(expires_at);
```

## 프로젝트 구조

```
root/ 
root/core = DB연결, 필요한 모든 PHP , 프로세스용 php포함
root/css - 모든 PHP에 직접 스타일시트, 자바스크립트 구현금지 <style> , <script> 사용금지. 무조건 css로 만들고 네임규칙적용 호출로 통일
root/js - 모든 PHP에 직접 스타일시트, 자바스크립트 구현금지 <style> , <script> 사용금지. 무조건 js로 만들고 네임규칙적용 호출로 통일

```
100% DB 구조대로 만 만들고 추가 기능 구현금지. 이 가이드라인에 없는 기능 구현금지.
## 핵심 기능 명세

### 인증 시스템
- **인증키**: `kimtaeyeon` (하드코딩)
- **세션 기반 인증**: 브라우저 닫기 전까지 유지

### 발급키 관리 (CRUD)
#### 생성 기능
- **수동 발급키 입력**: 랜덤 생성 없이 관리자가 직접 입력
- **사용 기간 선택**: 3일, 7일, 30일, 영구 중 선택
- **자동 만료일 계산**: 선택한 기간에 따라 expires_at 자동 설정
- **DB명 입력**: 클라이언트 DB 연결을 위한 db_name 필드

#### 수정 기능
- **모든 필드 수정 가능**: 발급키, 사용기간, 업체정보 등
- **기간 변경 시 만료일 재계산**: validity_period 변경 시 expires_at 업데이트
- **DB 연결 테스트**: db_name 변경 시 연결 가능 여부 확인

#### 갱신 기능
- 빠르게 남은 기간 갱신 기능 3일, 7일, 30일, 영구만 존재 기간이 해당 시간 기준으로 늘어나는 방식. 영구일경우 무조건 영구

#### 조회 기능
- **남은 기간 표시**: days_remaining 필드로 실시간 계산
- **상태별 필터링**: 활성, 만료, 정지, 만료임박 등
- **업체 연락 정보**: 만료 예정 시 담당자 연락처 표시

#### 삭제 기능
- **확인 후 삭제**: JavaScript 확인창 + 서버사이드 재확인
- **관련 데이터 정리**: 세션, 로그 등 연관 데이터 처리

### 통합 사용자 모니터링
#### 다중 DB 연결
- **동적 DB 연결**: license_keys.db_name 기반으로 각 클라이언트 DB 연결
- **연결 상태 모니터링**: 각 DB 연결 성공/실패 상태 표시
- **자동 재연결**: 연결 실패 시 재시도 로직

#### 사용자 활동 통합 조회
- **user_auth_history 통합**: 모든 클라이언트 DB의 인증 이력 수집
- **실시간 모니터링**: 최근 인증 시도 실시간 표시
- **필터링 기능**: DB별, 날짜별, 인증결과별 필터

#### 통계 및 리포트
- **사용량 통계**: DB별 사용자 수, 인증 성공/실패율
- **차트 시각화**: Chart.js를 활용한 통계 차트
- **알림 시스템**: 만료 임박, 연결 실패 등 알림

### 대시보드 기능
#### 발급키 현황
- **전체 통계**: 총 발급키 수, 활성/만료/정지 개수
- **만료 임박 알림**: 7일 이내 만료 예정 발급키 목록
- **사용 기간별 분포**: 3일/7일/30일/영구별 발급키 분포

#### 시스템 상태
- **DB 연결 상태**: 메인 DB + 모든 클라이언트 DB 연결 상태
- **최근 활동**: 최근 관리자 액션 로그
- **시스템 리소스**: 서버 상태 간단 표시

## 기술 스택

### 백엔드
- **PHP 7.4+**: 서버사이드 로직
- **MySQLi**: 데이터베이스 연결 (PDO 사용 금지)
- **세션 관리**: PHP 내장 세션 사용

### 프론트엔드
- **Bootstrap 5.3**: UI 프레임워크 (CDN)
- **jQuery 3.6**: JavaScript 라이브러리 (CDN)
- **DataTables 1.13**: 테이블 관리 (CDN)
- **Chart.js 4.0**: 차트 시각화 (CDN)
- **Font Awesome 6**: 아이콘 (CDN)

## 보안 요구사항

### 입력 검증
- **SQL Injection 방지**: MySQLi Prepared Statement 사용
- **XSS 방지**: htmlspecialchars() 적용
- **CSRF 방지**: 토큰 기반 폼 보호

### 세션 보안
- **세션 하이재킹 방지**: session_regenerate_id() 사용
- **HTTP Only 쿠키**: JavaScript 접근 차단
- **Secure 쿠키**: HTTPS 환경에서만 전송

### 접근 제어
- **인증 확인**: 모든 페이지에서 세션 검증
- **권한 검증**: 관리자 권한 확인
- **로그 기록**: 모든 관리자 액션 로깅

## 핵심 함수 명세

### core/config.php
```php
// 메인 DB 연결 설정
define('MAIN_DB_HOST', 'localhost');
define('MAIN_DB_NAME', 'nekoi93_addkey');
define('MAIN_DB_USER', 'nekoi93_addkey');
define('MAIN_DB_PASS', 'z1x2c3');

// 클라이언트 DB 공통 설정
define('CLIENT_DB_HOST', 'localhost');
define('CLIENT_DB_PASS', 'z1x2c3');

// 인증키
define('AUTH_KEY', 'kimtaeyeon');

// 기본 설정
define('DEFAULT_TIMEZONE', 'Asia/Seoul');
define('SESSION_LIFETIME', 86400); // 24시간
```

### core/database.php
```php
class Database {
    private static $main_connection = null;
    private static $client_connections = [];
    
    // 메인 DB 연결
    public static function getMainConnection()
    
    // 클라이언트 DB 연결 (동적)
    public static function getClientConnection($db_name)
    
    // DB 연결 테스트
    public static function testConnection($db_name)
    
    // 안전한 쿼리 실행
    public static function executeQuery($connection, $sql, $params = [])
    
    // 연결 풀 관리
    public static function closeConnection($db_name)
    public static function closeAllConnections()
}
```

### core/functions.php
```php
// 발급키 관리 함수
function getLicenseList($search = '', $status = '', $validity_period = '');
function getLicenseById($license_id);
function createLicense($data);
function updateLicense($license_id, $data);
function deleteLicense($license_id);
function calculateExpiryDate($validity_period);
function calculateDaysRemaining($expires_at);

// 사용자 모니터링 함수
function getUserAuthHistory($db_name = '', $limit = 100, $filters = []);
function getAuthStatistics($db_name = '');
function getConnectedDatabases();
function aggregateUserData();

// 유틸리티 함수
function formatDateTime($datetime);
function getStatusBadge($status);
function getValidityPeriodText($period);
function getDaysRemainingBadge($days);
function sanitizeInput($input, $type = 'string');
function generateCSRFToken();
function validateCSRFToken($token);
```

### core/auth.php
```php
// 인증 관련 함수
function checkAuth();
function login($auth_key);
function logout();
function isLoggedIn();
function requireAuth();
function regenerateSession();
```

## 페이지별 구현 명세

### login.php
#### 기능
- 단일 인증키 입력 폼
- 인증 성공 시 대시보드로 리다이렉트
- 실패 시 에러 메시지 표시

#### UI 구성
- 중앙 정렬된 로그인 카드
- 인증키 입력 필드
- 로그인 버튼
- 에러 메시지 영역

### index.php (대시보드)
#### 기능
- 발급키 현황 통계 카드
- 만료 임박 발급키 목록
- DB 연결 상태 표시
- 최근 활동 로그
- 사용 기간별 분포 차트

#### UI 구성
- 4개 통계 카드 (전체/활성/만료/정지)
- 만료 임박 테이블
- DB 상태 인디케이터
- 도넛 차트 (사용기간별 분포)
- 라인 차트 (일별 인증 통계)

### license_list.php
#### 기능
- DataTables 적용된 발급키 목록
- 검색/필터/정렬 기능
- 상태별 배지 표시
- 남은 기간 표시
- 액션 버튼 (보기/수정/삭제)

#### UI 구성
- 필터 드롭다운 (상태, 사용기간)
- DataTables 테이블
- 각 행별 액션 버튼
- 상태 배지 (색상별 구분)
- 남은 기간 배지

### license_form.php
#### 기능
- 발급키 추가/수정 통합 폼
- 사용 기간 선택 (3일/7일/30일/영구)
- 자동 만료일 계산
- DB 연결 테스트
- 유효성 검사

#### UI 구성
- 발급키 입력 필드
- 사용 기간 라디오 버튼
- 업체 정보 입력 필드
- DB명 입력 + 연결 테스트 버튼
- 저장/취소 버튼

### user_monitoring.php
#### 기능
- 모든 클라이언트 DB 통합 모니터링
- DB별 탭 구성
- 실시간 새로고침
- 상세 로그 모달
- 필터링 기능

#### UI 구성
- DB별 탭 네비게이션
- 인증 이력 테이블
- 새로고침 버튼
- 필터 컨트롤
- 상세 보기 모달

## 데이터 처리 로직

### 사용 기간 계산
```php
function calculateExpiryDate($validity_period) {
    $now = new DateTime();
    
    switch($validity_period) {
        case '3days':
            return $now->add(new DateInterval('P3D'))->format('Y-m-d H:i:s');
        case '7days':
            return $now->add(new DateInterval('P7D'))->format('Y-m-d H:i:s');
        case '30days':
            return $now->add(new DateInterval('P30D'))->format('Y-m-d H:i:s');
        case 'permanent':
            return '2099-12-31 23:59:59';
        default:
            return $now->add(new DateInterval('P30D'))->format('Y-m-d H:i:s');
    }
}

function calculateDaysRemaining($expires_at) {
    if ($expires_at === '2099-12-31 23:59:59') {
        return -1; // 영구 라이센스
    }
    
    $now = new DateTime();
    $expiry = new DateTime($expires_at);
    $diff = $now->diff($expiry);
    
    if ($expiry < $now) {
        return 0; // 만료됨
    }
    
    return $diff->days;
}
```

### 상태 배지 생성
```php
function getDaysRemainingBadge($days) {
    if ($days === -1) {
        return '<span class="badge bg-success">영구</span>';
    } elseif ($days === 0) {
        return '<span class="badge bg-danger">만료</span>';
    } elseif ($days <= 3) {
        return '<span class="badge bg-warning text-dark">' . $days . '일 남음</span>';
    } elseif ($days <= 7) {
        return '<span class="badge bg-info">' . $days . '일 남음</span>';
    } else {
        return '<span class="badge bg-primary">' . $days . '일 남음</span>';
    }
}
```

## 스타일 가이드

### 색상 테마
- **Primary**: #4e73df (파란색)
- **Success**: #1cc88a (초록색)
- **Warning**: #f6c23e (노란색)
- **Danger**: #e74a3b (빨간색)
- **Info**: #36b9cc (하늘색)

### 레이아웃 구조
- **사이드바**: 250px 고정 너비
- **메인 콘텐츠**: 카드 기반 레이아웃
- **테이블**: 스트라이프 + 호버 효과
- **버튼**: 둥근 모서리 + 아이콘

### 반응형 디자인
- **데스크톱**: 사이드바 + 메인 콘텐츠
- **태블릿**: 접이식 사이드바
- **모바일**: 전체 너비 + 햄버거 메뉴

## 에러 처리

### 데이터베이스 오류
- 연결 실패 시 재시도 로직
- 쿼리 오류 시 롤백 처리
- 사용자 친화적 오류 메시지

### 인증 오류
- 잘못된 인증키 시 에러 메시지
- 세션 만료 시 로그인 페이지 리다이렉트
- 권한 없음 시 403 페이지

### 입력 오류
- 클라이언트/서버 이중 검증
- 실시간 피드백
- 명확한 오류 메시지

## 성능 최적화

### 데이터베이스 최적화
- 적절한 인덱스 설정
- 쿼리 최적화
- 연결 풀링

### 캐싱 전략
- PHP OpCache 활용
- 세션 데이터 최적화
- 정적 리소스 캐싱

### 프론트엔드 최적화
- CDN 활용
- 이미지 압축
- JavaScript/CSS 압축

## 배포 가이드

### 서버 요구사항
- **PHP 7.4+**: MySQLi 확장 필수
- **MySQL 5.7+** 또는 **MariaDB 10.3+**
- **Apache 2.4+** 또는 **Nginx 1.18+**

### 설치 절차
1. 프로젝트 파일 업로드
2. 데이터베이스 스키마 설정
3. 설정 파일 수정 (core/config.php)
4. 권한 설정 (755 for directories, 644 for files)
5. 로그 디렉토리 생성 및 권한 설정

### 보안 설정
- HTTPS 강제 설정
- 디렉토리 탐색 차단
- 민감한 파일 접근 차단
- 로그 파일 보호

이 가이드를 기반으로 완전히 동작하는 발급키 관리 시스템을 구현하세요. 모든 파일을 생성하고 요구사항에 맞게 구현해주세요.