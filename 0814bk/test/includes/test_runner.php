<?php
/**
 * RTSP_Reader Test Framework - TestRunner
 * 
 * テスト実行エンジン
 */

require_once __DIR__ . '/test_logger.php';

class TestRunner {
    private $testModules = [];
    private $results = [];
    private $logger;
    
    /**
     * TestRunnerコンストラクタ
     *
     * @param TestLogger $logger ロガーインスタンス
     */
    public function __construct($logger = null) {
        $this->logger = $logger ?: new TestLogger();
    }
    
    /**
     * テストモジュールを登録
     *
     * @param string $name モジュール名
     * @param object $module テストモジュールのインスタンス
     */
    public function registerTestModule($name, $module) {
        $this->testModules[$name] = $module;
        $this->logger->info("テストモジュール登録: {$name}");
    }
    
    /**
     * 利用可能なすべてのテストモジュールと対応するテストメソッドを取得
     *
     * @return array モジュールとテストメソッドの配列
     */
    public function getAvailableTests() {
        $tests = [];
        
        foreach ($this->testModules as $moduleName => $module) {
            $tests[$moduleName] = [];
            
            $methods = get_class_methods($module);
            foreach ($methods as $method) {
                // testで始まるメソッドのみテストメソッドとして扱う
                if (strpos($method, 'test') === 0) {
                    // テストメソッドの説明をリフレクションから取得（もしあれば）
                    $reflection = new ReflectionMethod($module, $method);
                    $docComment = $reflection->getDocComment();
                    $description = '';
                    
                    if ($docComment) {
                        preg_match('/@description\s+(.+)/', $docComment, $matches);
                        if (isset($matches[1])) {
                            $description = trim($matches[1]);
                        }
                    }
                    
                    $tests[$moduleName][$method] = [
                        'name' => $method,
                        'description' => $description
                    ];
                }
            }
        }
        
        return $tests;
    }
    
    /**
     * 単一のテストを実行
     *
     * @param string $moduleName モジュール名
     * @param string $testName テスト名
     * @param array $params テストパラメータ
     * @return array テスト結果
     * @throws Exception モジュールまたはテストが見つからない場合
     */
    public function runTest($moduleName, $testName, $params = []) {
        if (!isset($this->testModules[$moduleName])) {
            throw new Exception("テストモジュール '{$moduleName}' が見つかりません");
        }
        
        $module = $this->testModules[$moduleName];
        if (!method_exists($module, $testName)) {
            throw new Exception("テスト '{$testName}' がモジュール '{$moduleName}' に見つかりません");
        }
        
        $this->logger->info("テスト開始: {$moduleName} - {$testName}", ['params' => $params]);
        $startTime = microtime(true);
        
        try {
            $result = call_user_func_array([$module, $testName], $params);
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            if (!is_array($result)) {
                $result = ['status' => $result ? 'success' : 'failed'];
            }
            
            $this->results[$moduleName][$testName] = [
                'status' => $result['status'] ?? 'unknown',
                'message' => $result['message'] ?? '',
                'details' => $result['details'] ?? [],
                'execution_time' => $executionTime,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $logLevel = ($this->results[$moduleName][$testName]['status'] === 'success') ? 
                        TestLogger::INFO : TestLogger::ERROR;
            
            $this->logger->log($logLevel, "テスト完了: {$moduleName} - {$testName} ({$executionTime}ms) - {$this->results[$moduleName][$testName]['status']}", $result);
            
            return $this->results[$moduleName][$testName];
        } catch (Exception $e) {
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            $this->results[$moduleName][$testName] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'details' => [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                'execution_time' => $executionTime,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $this->logger->error("テストエラー: {$moduleName} - {$testName}", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return $this->results[$moduleName][$testName];
        }
    }
    
    /**
     * 指定されたモジュールのすべてのテストを実行
     *
     * @param string $moduleName モジュール名
     * @param array $params テストパラメータ（すべてのテストに適用）
     * @return array テスト結果
     * @throws Exception モジュールが見つからない場合
     */
    public function runModuleTests($moduleName, $params = []) {
        if (!isset($this->testModules[$moduleName])) {
            throw new Exception("テストモジュール '{$moduleName}' が見つかりません");
        }
        
        $this->logger->info("モジュールテスト開始: {$moduleName}");
        $startTime = microtime(true);
        
        $module = $this->testModules[$moduleName];
        $methods = get_class_methods($module);
        $testMethods = [];
        
        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0) {
                $testMethods[] = $method;
            }
        }
        
        $moduleResults = [];
        $success = 0;
        $failed = 0;
        $error = 0;
        
        foreach ($testMethods as $method) {
            $result = $this->runTest($moduleName, $method, $params);
            $moduleResults[$method] = $result;
            
            if ($result['status'] === 'success') {
                $success++;
            } elseif ($result['status'] === 'error') {
                $error++;
            } else {
                $failed++;
            }
        }
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        $summary = [
            'module' => $moduleName,
            'tests_count' => count($testMethods),
            'success' => $success,
            'failed' => $failed,
            'error' => $error,
            'execution_time' => $executionTime,
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $moduleResults
        ];
        
        $this->logger->info("モジュールテスト完了: {$moduleName}", [
            'total' => count($testMethods),
            'success' => $success,
            'failed' => $failed,
            'error' => $error,
            'execution_time' => $executionTime
        ]);
        
        return $summary;
    }
    
    /**
     * すべてのテストモジュールのすべてのテストを実行
     *
     * @param array $params テストパラメータ（すべてのテストに適用）
     * @return array テスト結果
     */
    public function runAllTests($params = []) {
        $this->logger->info("全テスト開始");
        $startTime = microtime(true);
        
        $allResults = [];
        $totalTests = 0;
        $totalSuccess = 0;
        $totalFailed = 0;
        $totalError = 0;
        
        foreach (array_keys($this->testModules) as $moduleName) {
            $moduleResult = $this->runModuleTests($moduleName, $params);
            $allResults[$moduleName] = $moduleResult;
            
            $totalTests += $moduleResult['tests_count'];
            $totalSuccess += $moduleResult['success'];
            $totalFailed += $moduleResult['failed'];
            $totalError += $moduleResult['error'];
        }
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        $summary = [
            'modules_count' => count($this->testModules),
            'tests_count' => $totalTests,
            'success' => $totalSuccess,
            'failed' => $totalFailed,
            'error' => $totalError,
            'execution_time' => $executionTime,
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $allResults
        ];
        
        $this->logger->info("全テスト完了", [
            'modules' => count($this->testModules),
            'total' => $totalTests,
            'success' => $totalSuccess,
            'failed' => $totalFailed,
            'error' => $totalError,
            'execution_time' => $executionTime
        ]);
        
        return $summary;
    }
    
    /**
     * テスト結果を取得
     *
     * @return array テスト結果
     */
    public function getResults() {
        return $this->results;
    }
    
    /**
     * ロガーを取得
     *
     * @return TestLogger ロガーインスタンス
     */
    public function getLogger() {
        return $this->logger;
    }
    
    /**
     * フォーマット済みのHTML結果表を生成
     *
     * @param array $results テスト結果
     * @return string HTML形式の結果表
     */
    public function formatResultsAsHtml($results) {
        $html = '<div class="test-results">';
        
        // サマリー表示
        if (isset($results['modules_count'])) {
            // 全テスト結果のサマリー
            $html .= $this->formatSummaryHtml($results);
        } elseif (isset($results['module'])) {
            // モジュールテスト結果のサマリー
            $html .= $this->formatModuleSummaryHtml($results);
        } elseif (isset($results['status'])) {
            // 単一テスト結果
            $html .= $this->formatTestResultHtml($results, null, null);
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * サマリー結果のHTML形式
     *
     * @param array $summary サマリー情報
     * @return string HTML形式のサマリー
     */
    private function formatSummaryHtml($summary) {
        $successRate = ($summary['tests_count'] > 0) ? 
                      round(($summary['success'] / $summary['tests_count']) * 100, 1) : 0;
                      
        $statusClass = ($successRate >= 90) ? 'success' : 
                      (($successRate >= 70) ? 'warning' : 'danger');
        
        $html = '<div class="summary">';
        $html .= '<h3>テスト実行サマリー</h3>';
        $html .= '<div class="summary-stats">';
        $html .= "<div class=\"stat\">モジュール数: <span>{$summary['modules_count']}</span></div>";
        $html .= "<div class=\"stat\">テスト数: <span>{$summary['tests_count']}</span></div>";
        $html .= "<div class=\"stat\">成功: <span class=\"text-success\">{$summary['success']}</span></div>";
        $html .= "<div class=\"stat\">失敗: <span class=\"text-warning\">{$summary['failed']}</span></div>";
        $html .= "<div class=\"stat\">エラー: <span class=\"text-danger\">{$summary['error']}</span></div>";
        $html .= "<div class=\"stat\">実行時間: <span>{$summary['execution_time']}ms</span></div>";
        $html .= "<div class=\"stat\">成功率: <span class=\"text-{$statusClass}\">{$successRate}%</span></div>";
        $html .= '</div>';
        
        // モジュール結果
        $html .= '<div class="modules-results">';
        foreach ($summary['results'] as $moduleName => $moduleResult) {
            $html .= '<div class="module-result">';
            $html .= $this->formatModuleSummaryHtml($moduleResult, $moduleName);
            $html .= '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * モジュールサマリー結果のHTML形式
     *
     * @param array $moduleResult モジュール結果
     * @param string $moduleName モジュール名（nullの場合、結果から取得）
     * @return string HTML形式のモジュールサマリー
     */
    private function formatModuleSummaryHtml($moduleResult, $moduleName = null) {
        $moduleName = $moduleName ?: $moduleResult['module'];
        
        $successRate = ($moduleResult['tests_count'] > 0) ? 
                      round(($moduleResult['success'] / $moduleResult['tests_count']) * 100, 1) : 0;
                      
        $statusClass = ($successRate >= 90) ? 'success' : 
                      (($successRate >= 70) ? 'warning' : 'danger');
                      
        $collapsedId = 'module_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $moduleName);
        
        $html = '<div class="module-summary">';
        $html .= "<h4>{$moduleName}</h4>";
        $html .= '<div class="module-stats">';
        $html .= "<div class=\"stat\">テスト数: <span>{$moduleResult['tests_count']}</span></div>";
        $html .= "<div class=\"stat\">成功: <span class=\"text-success\">{$moduleResult['success']}</span></div>";
        $html .= "<div class=\"stat\">失敗: <span class=\"text-warning\">{$moduleResult['failed']}</span></div>";
        $html .= "<div class=\"stat\">エラー: <span class=\"text-danger\">{$moduleResult['error']}</span></div>";
        $html .= "<div class=\"stat\">実行時間: <span>{$moduleResult['execution_time']}ms</span></div>";
        $html .= "<div class=\"stat\">成功率: <span class=\"text-{$statusClass}\">{$successRate}%</span></div>";
        $html .= '</div>';
        
        // 詳細を折りたたみ可能に
        $html .= "<div class=\"module-details\">";
        $html .= "<button class=\"btn btn-sm btn-outline-primary\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#{$collapsedId}\" aria-expanded=\"false\" aria-controls=\"{$collapsedId}\">詳細表示</button>";
        
        $html .= "<div class=\"collapse\" id=\"{$collapsedId}\">";
        $html .= "<div class=\"card card-body mt-2\">";
        $html .= '<table class="table table-sm table-striped">';
        $html .= '<thead><tr><th>テスト名</th><th>状態</th><th>メッセージ</th><th>実行時間</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($moduleResult['results'] as $testName => $testResult) {
            $statusClass = $this->getStatusClass($testResult['status']);
            $html .= "<tr class=\"{$statusClass}\">";
            $html .= "<td>{$testName}</td>";
            $html .= "<td>{$testResult['status']}</td>";
            $html .= "<td>" . htmlspecialchars($testResult['message']) . "</td>";
            $html .= "<td>{$testResult['execution_time']}ms</td>";
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div></div>';
        $html .= '</div>';
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * 単一テスト結果のHTML形式
     *
     * @param array $testResult テスト結果
     * @param string $moduleName モジュール名
     * @param string $testName テスト名
     * @return string HTML形式のテスト結果
     */
    private function formatTestResultHtml($testResult, $moduleName = null, $testName = null) {
        $statusClass = $this->getStatusClass($testResult['status']);
        
        $html = '<div class="test-result">';
        
        if ($moduleName !== null && $testName !== null) {
            $html .= "<h4>{$moduleName} - {$testName}</h4>";
        }
        
        $html .= "<div class=\"test-status {$statusClass}\">";
        $html .= "<strong>状態:</strong> {$testResult['status']}";
        $html .= '</div>';
        
        if (!empty($testResult['message'])) {
            $html .= '<div class="test-message">';
            $html .= '<strong>メッセージ:</strong> ' . htmlspecialchars($testResult['message']);
            $html .= '</div>';
        }
        
        if (!empty($testResult['details'])) {
            $html .= '<div class="test-details">';
            $html .= '<strong>詳細:</strong>';
            $html .= '<pre>' . htmlspecialchars(json_encode($testResult['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            $html .= '</div>';
        }
        
        $html .= "<div class=\"test-meta\">";
        $html .= "<div><strong>実行時間:</strong> {$testResult['execution_time']}ms</div>";
        $html .= "<div><strong>実行日時:</strong> {$testResult['timestamp']}</div>";
        $html .= '</div>';
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * テスト状態に応じたCSSクラスを取得
     *
     * @param string $status テスト状態
     * @return string CSSクラス
     */
    private function getStatusClass($status) {
        switch (strtolower($status)) {
            case 'success':
                return 'table-success';
            case 'failed':
            case 'failure':
            case 'fail':
                return 'table-warning';
            case 'error':
                return 'table-danger';
            default:
                return 'table-secondary';
        }
    }
} 