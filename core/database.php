<?php

declare(strict_types=1);

/**
 * 아야비드 발급키 관리 시스템 - 데이터베이스 클래스
 * PSR-12 준수, MySQLi 기반, 연결 풀 관리
 * 
 * @author 시스템 관리자
 * @version 1.0.0
 * @since 2025-06-01
 */

class Database
{
    private static ?mysqli $mainConnection = null;
    private static array $clientConnections = [];
    private static array $connectionStats = [];
    private static int $maxRetries = MAX_CONNECTION_RETRY;
    
    /**
     * 메인 데이터베이스 연결 반환
     * 
     * @return mysqli
     * @throws Exception 연결 실패 시
     */
    public static function getMainConnection(): mysqli
    {
        if (self::$mainConnection === null || !self::$mainConnection->ping()) {
            self::$mainConnection = self::createMainConnection();
        }
        
        return self::$mainConnection;
    }
    
    /**
     * 메인 데이터베이스 연결 생성
     * 
     * @return mysqli
     * @throws Exception 연결 실패 시
     */
    private static function createMainConnection(): mysqli
    {
        $attempts = 0;
        $lastError = '';
        
        while ($attempts < self::$maxRetries) {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                
                $connection = new mysqli(
                    MAIN_DB_HOST,
                    MAIN_DB_USER,
                    MAIN_DB_PASS,
                    MAIN_DB_NAME
                );
                
                // 연결 설정
                $connection->set_charset(MAIN_DB_CHARSET);
                $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, CONNECTION_TIMEOUT);
                $connection->query("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
                $connection->query("SET time_zone = '+09:00'");
                
                // 연결 성공 로그
                writeLog('INFO', 'Main database connection established', [
                    'host' => MAIN_DB_HOST,
                    'database' => MAIN_DB_NAME
                ]);
                
                return $connection;
                
            } catch (mysqli_sql_exception $e) {
                $attempts++;
                $lastError = $e->getMessage();
                
                writeLog('ERROR', 'Main database connection failed', [
                    'attempt' => $attempts,
                    'error' => $lastError,
                    'host' => MAIN_DB_HOST,
                    'database' => MAIN_DB_NAME
                ]);
                
                if ($attempts < self::$maxRetries) {
                    usleep(1000000 * $attempts); // 지수 백오프
                }
            }
        }
        
        throw new Exception("메인 데이터베이스 연결 실패: {$lastError}");
    }
    
    /**
     * 클라이언트 데이터베이스 연결 반환
     * 
     * @param string $dbName 데이터베이스명
     * @return mysqli
     * @throws Exception 연결 실패 시
     */
    public static function getClientConnection(string $dbName): mysqli
    {
        if (empty($dbName)) {
            throw new InvalidArgumentException('데이터베이스명이 비어있습니다.');
        }
        
        // 기존 연결 확인
        if (isset(self::$clientConnections[$dbName])) {
            $connection = self::$clientConnections[$dbName];
            if ($connection->ping()) {
                return $connection;
            } else {
                // 연결이 끊어진 경우 제거
                unset(self::$clientConnections[$dbName]);
            }
        }
        
        // 새 연결 생성
        self::$clientConnections[$dbName] = self::createClientConnection($dbName);
        return self::$clientConnections[$dbName];
    }
    
    /**
     * 클라이언트 데이터베이스 연결 생성
     * 
     * @param string $dbName 데이터베이스명
     * @return mysqli
     * @throws Exception 연결 실패 시
     */
    private static function createClientConnection(string $dbName): mysqli
    {
        $attempts = 0;
        $lastError = '';
        
        while ($attempts < self::$maxRetries) {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                
                $connection = new mysqli(
                    CLIENT_DB_HOST,
                    $dbName, // 사용자명과 DB명이 동일
                    CLIENT_DB_PASS,
                    $dbName
                );
                
                // 연결 설정
                $connection->set_charset(CLIENT_DB_CHARSET);
                $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, CONNECTION_TIMEOUT);
                $connection->query("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
                $connection->query("SET time_zone = '+09:00'");
                
                // 연결 통계 업데이트
                self::updateConnectionStats($dbName, 'SUCCESS', microtime(true) * 1000);
                
                writeLog('INFO', 'Client database connection established', [
                    'database' => $dbName,
                    'host' => CLIENT_DB_HOST
                ]);
                
                return $connection;
                
            } catch (mysqli_sql_exception $e) {
                $attempts++;
                $lastError = $e->getMessage();
                
                writeLog('ERROR', 'Client database connection failed', [
                    'database' => $dbName,
                    'attempt' => $attempts,
                    'error' => $lastError
                ]);
                
                if ($attempts < self::$maxRetries) {
                    usleep(1000000 * $attempts);
                }
            }
        }
        
        // 연결 실패 통계 업데이트
        self::updateConnectionStats($dbName, 'FAILED', 0, $lastError);
        
        throw new Exception("클라이언트 데이터베이스 연결 실패 ({$dbName}): {$lastError}");
    }
    
    /**
     * 데이터베이스 연결 테스트
     * 
     * @param string $dbName 데이터베이스명
     * @return array 연결 결과
     */
    public static function testConnection(string $dbName): array
    {
        $startTime = microtime(true);
        
        try {
            $connection = self::createClientConnection($dbName);
            $connection->close();
            
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'success' => true,
                'connection_time' => round($connectionTime, 2),
                'error_message' => null
            ];
            
        } catch (Exception $e) {
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'success' => false,
                'connection_time' => round($connectionTime, 2),
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 안전한 쿼리 실행
     * 
     * @param mysqli $connection 데이터베이스 연결
     * @param string $sql SQL 쿼리
     * @param array $params 바인딩 파라미터
     * @return mysqli_result|bool 쿼리 결과
     * @throws Exception 쿼리 실행 실패 시
     */
    public static function executeQuery(mysqli $connection, string $sql, array $params = [])
    {
        try {
            if (empty($params)) {
                return $connection->query($sql);
            }
            
            $stmt = $connection->prepare($sql);
            if (!$stmt) {
                throw new Exception("쿼리 준비 실패: " . $connection->error);
            }
            
            if (!empty($params)) {
                $types = self::getParamTypes($params);
                $stmt->bind_param($types, ...$params);
            }
            
            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception("쿼리 실행 실패: " . $stmt->error);
            }
            
            // SELECT 쿼리인 경우 결과 반환
            if (stripos(trim($sql), 'SELECT') === 0) {
                return $stmt->get_result();
            }
            
            // INSERT/UPDATE/DELETE 쿼리인 경우 영향받은 행 수 반환
            return $stmt->affected_rows;
            
        } catch (mysqli_sql_exception $e) {
            writeLog('ERROR', 'Query execution failed', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("쿼리 실행 오류: " . $e->getMessage());
        }
    }
    
    /**
     * 트랜잭션 시작
     * 
     * @param mysqli $connection 데이터베이스 연결
     * @return bool
     */
    public static function beginTransaction(mysqli $connection): bool
    {
        return $connection->begin_transaction();
    }
    
    /**
     * 트랜잭션 커밋
     * 
     * @param mysqli $connection 데이터베이스 연결
     * @return bool
     */
    public static function commit(mysqli $connection): bool
    {
        return $connection->commit();
    }
    
    /**
     * 트랜잭션 롤백
     * 
     * @param mysqli $connection 데이터베이스 연결
     * @return bool
     */
    public static function rollback(mysqli $connection): bool
    {
        return $connection->rollback();
    }
    
    /**
     * 파라미터 타입 추정
     * 
     * @param array $params 파라미터 배열
     * @return string 타입 문자열
     */
    private static function getParamTypes(array $params): string
    {
        $types = '';
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        
        return $types;
    }
    
    /**
     * 연결 통계 업데이트
     * 
     * @param string $dbName 데이터베이스명
     * @param string $result 연결 결과
     * @param float $connectionTime 연결 시간
     * @param string|null $errorMessage 오류 메시지
     */
    private static function updateConnectionStats(
        string $dbName,
        string $result,
        float $connectionTime,
        ?string $errorMessage = null
    ): void {
        try {
            $mainConnection = self::getMainConnection();
            
            $sql = "CALL sp_update_db_connection_status(?, ?, ?, ?)";
            $params = [
                $dbName,
                $result,
                (int) round($connectionTime),
                $errorMessage
            ];
            
            self::executeQuery($mainConnection, $sql, $params);
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to update connection stats', [
                'database' => $dbName,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 특정 클라이언트 연결 닫기
     * 
     * @param string $dbName 데이터베이스명
     */
    public static function closeConnection(string $dbName): void
    {
        if (isset(self::$clientConnections[$dbName])) {
            self::$clientConnections[$dbName]->close();
            unset(self::$clientConnections[$dbName]);
            
            writeLog('INFO', 'Client database connection closed', [
                'database' => $dbName
            ]);
        }
    }
    
    /**
     * 모든 연결 닫기
     */
    public static function closeAllConnections(): void
    {
        // 클라이언트 연결 닫기
        foreach (self::$clientConnections as $dbName => $connection) {
            $connection->close();
            unset(self::$clientConnections[$dbName]);
        }
        
        // 메인 연결 닫기
        if (self::$mainConnection !== null) {
            self::$mainConnection->close();
            self::$mainConnection = null;
        }
        
        writeLog('INFO', 'All database connections closed');
    }
    
    /**
     * 연결된 데이터베이스 목록 반환
     * 
     * @return array 데이터베이스 목록
     */
    public static function getConnectedDatabases(): array
    {
        try {
            $connection = self::getMainConnection();
            $sql = "SELECT DISTINCT db_name FROM license_keys WHERE db_name IS NOT NULL AND status = 'ACTIVE' ORDER BY db_name";
            $result = self::executeQuery($connection, $sql);
            
            $databases = [];
            while ($row = $result->fetch_assoc()) {
                $databases[] = $row['db_name'];
            }
            
            return $databases;
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to get connected databases', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * 데이터베이스 연결 상태 조회
     * 
     * @return array 연결 상태 목록
     */
    public static function getConnectionStatus(): array
    {
        try {
            $connection = self::getMainConnection();
            $sql = "SELECT * FROM db_connection_status ORDER BY last_checked_at DESC";
            $result = self::executeQuery($connection, $sql);
            
            $status = [];
            while ($row = $result->fetch_assoc()) {
                $status[] = $row;
            }
            
            return $status;
            
        } catch (Exception $e) {
            writeLog('ERROR', 'Failed to get connection status', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * 마지막 삽입 ID 반환
     * 
     * @param mysqli $connection 데이터베이스 연결
     * @return int 마지막 삽입 ID
     */
    public static function getLastInsertId(mysqli $connection): int
    {
        return $connection->insert_id;
    }
    
    /**
     * 영향받은 행 수 반환
     * 
     * @param mysqli $connection 데이터베이스 연결
     * @return int 영향받은 행 수
     */
    public static function getAffectedRows(mysqli $connection): int
    {
        return $connection->affected_rows;
    }
    
    /**
     * SQL 문자열 이스케이프
     * 
     * @param mysqli $connection 데이터베이스 연결
     * @param string $string 이스케이프할 문자열
     * @return string 이스케이프된 문자열
     */
    public static function escapeString(mysqli $connection, string $string): string
    {
        return $connection->real_escape_string($string);
    }
}

// 스크립트 종료 시 모든 연결 정리
register_shutdown_function([Database::class, 'closeAllConnections']);