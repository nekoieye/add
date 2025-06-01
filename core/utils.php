<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 유틸리티 함수
 * 보조 기능 및 헬퍼 함수 모음
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

// =====================================================
// 보안 관련 유틸리티
// =====================================================

/**
 * IP 주소 검증
 * 
 * @param string $ip IP 주소
 * @return bool 유효 여부
 */
function isValidIP(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * 안전한 리다이렉트
 * 
 * @param string $url 리다이렉트 URL
 * @param array $allowedDomains 허용된 도메인 목록
 */
function safeRedirect(string $url, array $allowedDomains = []): void
{
    // 상대 경로는 허용
    if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
        header('Location: ' . $url);
        exit;
    }
    
    // 절대 URL인 경우 도메인 검증
    $parsedUrl = parse_url($url);
    if (isset($parsedUrl['host'])) {
        $host = strtolower($parsedUrl['host']);
        
        // 허용된 도메인 목록이 있는 경우 검증
        if (!empty($allowedDomains)) {
            $allowed = false;
            foreach ($allowedDomains as $domain) {
                if ($host === strtolower($domain) || 
                    str_ends_with($host, '.' . strtolower($domain))) {
                    $allowed = true;
                    break;
                }
            }
            
            if (!$allowed) {
                $url = '/'; // 기본 페이지로 리다이렉트
            }
        } else {
            // 외부 도메인은 허용하지 않음
            $url = '/';
        }
    }
    
    header('Location: ' . $url);
    exit;
}

/**
 * 세션 보안 강화
 */
function secureSession(): void
{
    // 세션 쿠키 보안 설정
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    
    // 세션 ID 재생성 (세션 하이재킹 방지)
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5분마다
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * 브루트포스 공격 방지
 * 
 * @param string $identifier 식별자 (IP, 사용자 등)
 * @param int $maxAttempts 최대 시도 횟수
 * @param int $timeWindow 시간 윈도우 (초)
 * @return bool 허용 여부
 */
function checkRateLimit(string $identifier, int $maxAttempts = 5, int $timeWindow = 300): bool
{
    $cacheKey = 'rate_limit_' . md5($identifier);
    
    // 간단한 파일 기반 캐시 (실제 환경에서는 Redis 등 사용 권장)
    $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.tmp';
    
    $attempts = [];
    if (file_exists($cacheFile)) {
        $data = file_get_contents($cacheFile);
        $attempts = json_decode($data, true) ?: [];
    }
    
    $currentTime = time();
    
    // 시간 윈도우 밖의 시도는 제거
    $attempts = array_filter($attempts, function ($timestamp) use ($currentTime, $timeWindow) {
        return $currentTime - $timestamp < $timeWindow;
    });
    
    // 최대 시도 횟수 확인
    if (count($attempts) >= $maxAttempts) {
        return false;
    }
    
    // 현재 시도 기록
    $attempts[] = $currentTime;
    file_put_contents($cacheFile, json_encode($attempts));
    
    return true;
}

// =====================================================
// 데이터 처리 유틸리티
// =====================================================

/**
 * 배열 깊은 정리 (null, 빈 문자열 제거)
 * 
 * @param array $array 입력 배열
 * @return array 정리된 배열
 */
function arrayDeepClean(array $array): array
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = arrayDeepClean($value);
            if (empty($array[$key])) {
                unset($array[$key]);
            }
        } elseif ($value === null || $value === '') {
            unset($array[$key]);
        }
    }
    
    return $array;
}

/**
 * 배열을 안전하게 병합
 * 
 * @param array ...$arrays 병합할 배열들
 * @return array 병합된 배열
 */
function safeMergeArrays(array ...$arrays): array
{
    $result = [];
    
    foreach ($arrays as $array) {
        if (is_array($array)) {
            $result = array_merge($result, $array);
        }
    }
    
    return $result;
}

/**
 * 중첩 배열에서 값 추출
 * 
 * @param array $array 배열
 * @param string $path 경로 (점으로 구분)
 * @param mixed $default 기본값
 * @return mixed 추출된 값
 */
function arrayGet(array $array, string $path, $default = null)
{
    $keys = explode('.', $path);
    
    foreach ($keys as $key) {
        if (is_array($array) && array_key_exists($key, $array)) {
            $array = $array[$key];
        } else {
            return $default;
        }
    }
    
    return $array;
}

/**
 * 중첩 배열에 값 설정
 * 
 * @param array &$array 배열 (참조)
 * @param string $path 경로 (점으로 구분)
 * @param mixed $value 설정할 값
 */
function arraySet(array &$array, string $path, $value): void
{
    $keys = explode('.', $path);
    $current = &$array;
    
    foreach ($keys as $key) {
        if (!isset($current[$key]) || !is_array($current[$key])) {
            $current[$key] = [];
        }
        $current = &$current[$key];
    }
    
    $current = $value;
}

// =====================================================
// 문자열 처리 유틸리티
// =====================================================

/**
 * 안전한 문자열 자르기 (UTF-8 지원)
 * 
 * @param string $string 문자열
 * @param int $length 길이
 * @param string $append 추가할 문자열
 * @return string 잘린 문자열
 */
function safeTruncate(string $string, int $length, string $append = '...'): string
{
    if (mb_strlen($string, 'UTF-8') <= $length) {
        return $string;
    }
    
    return mb_substr($string, 0, $length, 'UTF-8') . $append;
}

/**
 * 슬러그 생성 (URL 친화적 문자열)
 * 
 * @param string $string 입력 문자열
 * @return string 슬러그
 */
function createSlug(string $string): string
{
    // 한글을 영문으로 변환하는 간단한 매핑
    $koreanMap = [
        'ㄱ' => 'g', 'ㄴ' => 'n', 'ㄷ' => 'd', 'ㄹ' => 'r', 'ㅁ' => 'm',
        'ㅂ' => 'b', 'ㅅ' => 's', 'ㅇ' => '', 'ㅈ' => 'j', 'ㅊ' => 'ch',
        'ㅋ' => 'k', 'ㅌ' => 't', 'ㅍ' => 'p', 'ㅎ' => 'h'
    ];
    
    $string = strtolower(trim($string));
    $string = strtr($string, $koreanMap);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    $string = trim($string, '-');
    
    return $string;
}

/**
 * 마스킹 처리
 * 
 * @param string $string 입력 문자열
 * @param int $start 시작 위치
 * @param int $length 마스킹 길이
 * @param string $mask 마스킹 문자
 * @return string 마스킹된 문자열
 */
function maskString(string $string, int $start = 2, int $length = 0, string $mask = '*'): string
{
    $stringLength = mb_strlen($string, 'UTF-8');
    
    if ($length === 0) {
        $length = max(1, $stringLength - $start - 2);
    }
    
    $masked = mb_substr($string, 0, $start, 'UTF-8');
    $masked .= str_repeat($mask, $length);
    $masked .= mb_substr($string, $start + $length, null, 'UTF-8');
    
    return $masked;
}

/**
 * 이메일 마스킹
 * 
 * @param string $email 이메일 주소
 * @return string 마스킹된 이메일
 */
function maskEmail(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    
    [$local, $domain] = explode('@', $email);
    
    $localLength = strlen($local);
    $visibleChars = min(2, $localLength - 1);
    
    $maskedLocal = substr($local, 0, $visibleChars) . 
                   str_repeat('*', $localLength - $visibleChars);
    
    return $maskedLocal . '@' . $domain;
}

// =====================================================
// 시간/날짜 유틸리티
// =====================================================

/**
 * 상대적 시간 표시
 * 
 * @param string $datetime 날짜/시간
 * @return string 상대적 시간
 */
function timeAgo(string $datetime): string
{
    try {
        $time = new DateTime($datetime);
        $now = new DateTime();
        $diff = $now->diff($time);
        
        if ($diff->y > 0) {
            return $diff->y . '년 전';
        } elseif ($diff->m > 0) {
            return $diff->m . '개월 전';
        } elseif ($diff->d > 0) {
            return $diff->d . '일 전';
        } elseif ($diff->h > 0) {
            return $diff->h . '시간 전';
        } elseif ($diff->i > 0) {
            return $diff->i . '분 전';
        } else {
            return '방금 전';
        }
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * 업무일 계산
 * 
 * @param DateTime $startDate 시작일
 * @param int $businessDays 업무일 수
 * @return DateTime 결과일
 */
function addBusinessDays(DateTime $startDate, int $businessDays): DateTime
{
    $date = clone $startDate;
    $addedDays = 0;
    
    while ($addedDays < $businessDays) {
        $date->add(new DateInterval('P1D'));
        
        // 주말 제외 (토요일=6, 일요일=0)
        if ($date->format('w') != 0 && $date->format('w') != 6) {
            $addedDays++;
        }
    }
    
    return $date;
}

/**
 * 한국 시간대로 변환
 * 
 * @param DateTime $dateTime 원본 DateTime
 * @return DateTime 한국 시간대 DateTime
 */
function toKoreanTime(DateTime $dateTime): DateTime
{
    $koreanTime = clone $dateTime;
    $koreanTime->setTimezone(new DateTimeZone('Asia/Seoul'));
    return $koreanTime;
}

// =====================================================
// 파일 처리 유틸리티
// =====================================================

/**
 * 안전한 파일명 생성
 * 
 * @param string $filename 원본 파일명
 * @return string 안전한 파일명
 */
function sanitizeFilename(string $filename): string
{
    // 확장자 분리
    $pathInfo = pathinfo($filename);
    $name = $pathInfo['filename'] ?? '';
    $extension = $pathInfo['extension'] ?? '';
    
    // 특수문자 제거 및 공백을 언더스코어로 변경
    $name = preg_replace('/[^a-zA-Z0-9가-힣\s-_]/', '', $name);
    $name = preg_replace('/\s+/', '_', trim($name));
    
    // 길이 제한
    $name = mb_substr($name, 0, 100, 'UTF-8');
    
    return $name . ($extension ? '.' . $extension : '');
}

/**
 * 파일 MIME 타입 검증
 * 
 * @param string $filePath 파일 경로
 * @param array $allowedTypes 허용된 MIME 타입
 * @return bool 유효 여부
 */
function validateFileType(string $filePath, array $allowedTypes): bool
{
    if (!file_exists($filePath)) {
        return false;
    }
    
    $mimeType = mime_content_type($filePath);
    return in_array($mimeType, $allowedTypes, true);
}

/**
 * 디렉토리 재귀적 생성
 * 
 * @param string $directory 디렉토리 경로
 * @param int $permissions 권한
 * @return bool 성공 여부
 */
function createDirectory(string $directory, int $permissions = 0755): bool
{
    if (is_dir($directory)) {
        return true;
    }
    
    return mkdir($directory, $permissions, true);
}

// =====================================================
// 검증 유틸리티
// =====================================================

/**
 * 한국 전화번호 검증
 * 
 * @param string $phone 전화번호
 * @return bool 유효 여부
 */
function validateKoreanPhone(string $phone): bool
{
    // 하이픈 제거
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // 패턴 검증
    $patterns = [
        '/^010[0-9]{8}$/',     // 휴대폰
        '/^02[0-9]{7,8}$/',    // 서울 지역번호
        '/^0[3-6][1-9][0-9]{6,7}$/', // 기타 지역번호
        '/^070[0-9]{8}$/',     // 인터넷 전화
        '/^080[0-9]{8}$/'      // 무료전화
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $phone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * 한국 사업자등록번호 검증
 * 
 * @param string $businessNumber 사업자등록번호
 * @return bool 유효 여부
 */
function validateBusinessNumber(string $businessNumber): bool
{
    // 하이픈 제거
    $number = preg_replace('/[^0-9]/', '', $businessNumber);
    
    if (strlen($number) !== 10) {
        return false;
    }
    
    $checksum = 0;
    $weights = [1, 3, 7, 1, 3, 7, 1, 3, 5];
    
    for ($i = 0; $i < 9; $i++) {
        $checksum += (int)$number[$i] * $weights[$i];
    }
    
    $checksum += (int)($number[8] * 5 / 10);
    $checkDigit = (10 - ($checksum % 10)) % 10;
    
    return $checkDigit === (int)$number[9];
}

/**
 * 강력한 비밀번호 검증
 * 
 * @param string $password 비밀번호
 * @param int $minLength 최소 길이
 * @return array 검증 결과
 */
function validateStrongPassword(string $password, int $minLength = 8): array
{
    $result = [
        'valid' => true,
        'errors' => [],
        'strength' => 0
    ];
    
    // 길이 검사
    if (strlen($password) < $minLength) {
        $result['valid'] = false;
        $result['errors'][] = "최소 {$minLength}자 이상이어야 합니다.";
    } else {
        $result['strength'] += 1;
    }
    
    // 대문자 포함
    if (!preg_match('/[A-Z]/', $password)) {
        $result['valid'] = false;
        $result['errors'][] = '대문자를 포함해야 합니다.';
    } else {
        $result['strength'] += 1;
    }
    
    // 소문자 포함
    if (!preg_match('/[a-z]/', $password)) {
        $result['valid'] = false;
        $result['errors'][] = '소문자를 포함해야 합니다.';
    } else {
        $result['strength'] += 1;
    }
    
    // 숫자 포함
    if (!preg_match('/[0-9]/', $password)) {
        $result['valid'] = false;
        $result['errors'][] = '숫자를 포함해야 합니다.';
    } else {
        $result['strength'] += 1;
    }
    
    // 특수문자 포함
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $result['valid'] = false;
        $result['errors'][] = '특수문자를 포함해야 합니다.';
    } else {
        $result['strength'] += 1;
    }
    
    return $result;
}

// =====================================================
// 캐싱 유틸리티
// =====================================================

/**
 * 간단한 파일 캐시
 * 
 * @param string $key 캐시 키
 * @param callable|null $callback 데이터 생성 콜백
 * @param int $ttl 생존 시간 (초)
 * @return mixed 캐시된 데이터
 */
function simpleCache(string $key, ?callable $callback = null, int $ttl = 3600)
{
    $cacheDir = sys_get_temp_dir() . '/ayabid_cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
    
    // 캐시 파일이 존재하고 유효한 경우
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $data = file_get_contents($cacheFile);
        return unserialize($data);
    }
    
    // 콜백이 제공된 경우 새 데이터 생성
    if ($callback && is_callable($callback)) {
        $data = $callback();
        file_put_contents($cacheFile, serialize($data));
        return $data;
    }
    
    return null;
}

/**
 * 캐시 삭제
 * 
 * @param string $key 캐시 키
 * @return bool 삭제 성공 여부
 */
function clearCache(string $key): bool
{
    $cacheDir = sys_get_temp_dir() . '/ayabid_cache';
    $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
    
    if (file_exists($cacheFile)) {
        return unlink($cacheFile);
    }
    
    return true;
}

/**
 * 모든 캐시 삭제
 * 
 * @return bool 삭제 성공 여부
 */
function clearAllCache(): bool
{
    $cacheDir = sys_get_temp_dir() . '/ayabid_cache';
    
    if (!is_dir($cacheDir)) {
        return true;
    }
    
    $files = glob($cacheDir . '/*.cache');
    $success = true;
    
    foreach ($files as $file) {
        if (!unlink($file)) {
            $success = false;
        }
    }
    
    return $success;
}