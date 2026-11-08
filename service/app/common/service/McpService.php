<?php

namespace app\common\service;

use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\HttpServerTransport;
use PhpMcp\Server\Transports\StdioServerTransport;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use support\Log;
use support\think\Db;
use think\Exception;

/**
 * MCP(Model Context Protocol)服务类
 * 提供与AI模型交互的上下文协议服务
 */
class McpService extends AbstractService
{
    /**
     * MCP服务器实例
     * @var Server|null
     */
    protected $server = null;

    /**
     * 日志记录器
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 超时配置（毫秒）
     * @var int
     */
    protected $timeout = 600000;

    /**
     * 连接超时配置（毫秒）
     * @var int
     */
    protected $connectTimeout = 30000;

    /**
     * 读取超时配置（毫秒）
     * @var int
     */
    protected $readTimeout = 30000;

    /**
     * 重试次数
     * @var int
     */
    protected $retryAttempts = 3;

    /**
     * 重试延迟（毫秒）
     * @var int
     */
    protected $retryDelay = 1000;

    /**
     * 调试模式
     * @var bool
     */
    protected $debug = false;

    /**
     * 服务名称
     * @var string
     */
    protected $name = 'mcp';

    /**
     * 服务版本
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * 内存限制
     * @var string
     */
    protected $memoryLimit;

    /**
     * 缓冲区大小
     * @var int
     */
    protected $bufferSize;

    /**
     * 心跳机制启用
     * @var bool
     */
    protected $heartbeatEnabled;

    /**
     * 心跳间隔（秒）
     * @var int
     */
    protected $heartbeatInterval;

    /**
     * 处理数据库查询
     * @param string $query SQL查询语句
     * @param array $params 查询参数
     * @return array
     */
    public function handleDbQuery(string $query, array $params = []): array
    {
        try {
            if (empty($query)) {
                throw new Exception('SQL查询语句不能为空');
            }

            // 安全检查：只允许SELECT查询
            if (!preg_match('/^\s*select\s+/i', trim($query))) {
                throw new Exception('出于安全考虑，只允许执行SELECT查询');
            }

            // 使用数据库组件
            $result = Db::query($query, $params);

            return [
                'success' => true,
                'data' => $result,
                'count' => count($result),
                'message' => '查询执行成功'
            ];

        } catch (Exception $e) {
            Log::error('MCP数据库查询错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理健康检查
     * @return array
     */
    public function handleHealthCheck(): array
    {
        $info = $this->getServiceInfo();

        return [
            'success' => true,
            'data' => [
                'status' => $info['status'],
                'service' => $info['name'],
                'version' => $info['version'],
                'timestamp' => time(),
                'database' => $info['database']
            ]
        ];
    }

    /**
     * 获取服务信息
     * @return array
     */
    public function getServiceInfo(): array
    {
        // 检查数据库连接
        $dbStatus = 'ok';
        $dbError = '';

        try {
            Db::query('SELECT 1 AS ping');
        } catch (Exception $e) {
            $dbStatus = 'error';
            $dbError = $e->getMessage();
            Log::error('MCP服务数据库连接检查失败: ' . $e->getMessage());
        }

        return [
            'name' => $this->name,
            'version' => $this->version,
            'tools' => 17, // 已更新为17个工具（包含健康检查）
            'resources' => 3, // 3个资源
            'prompt' => 1, // 1个提示词
            'status' => 'ready',
            'database' => [
                'status' => $dbStatus,
                'error' => $dbError
            ],
            'config' => $this->getConfig()
        ];
    }

    /**
     * 获取当前配置信息
     * @return array
     */
    public function getConfig()
    {
        return [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'read_timeout' => $this->readTimeout,
            'retry_attempts' => $this->retryAttempts,
            'retry_delay' => $this->retryDelay,
            'debug' => $this->debug
        ];
    }

    /**
     * 处理配置获取
     * @param string $key 配置键名（可选）
     * @return array
     */
    public function handleSysConfig(string $key = ''): array
    {
        try {
            if (empty($key)) {
                // 返回常用配置的概览
                return [
                    'app' => [
                        'debug' => config('app.debug'),
                        'default_timezone' => config('app.default_timezone'),
                        'default_lang' => config('app.default_lang'),
                    ],
                    'database' => [
                        'type' => config('database.default.type'),
                        'hostname' => config('database.default.hostname'),
                        'database' => config('database.default.database'),
                    ],
                    'cache' => [
                        'default' => config('cache.default'),
                        'stores' => array_keys(config('cache.stores', [])),
                    ]
                ];
            } else {
                return ['value' => config($key), 'key' => $key];
            }

        } catch (Exception $e) {
            Log::error('MCP配置获取错误: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理文件操作
     * @param string $operation 操作类型
     * @param string $filepath 文件路径
     * @return array
     */
    public function handleFileOperation(string $operation, string $filepath): array
    {
        try {
            if (empty($operation) || empty($filepath)) {
                throw new Exception('操作类型和文件路径不能为空');
            }

            // 安全检查：限制文件路径范围
            $allowedPaths = [
                runtime_path(),
                public_path() . '/uploads/',
                config_path(),
            ];

            $isAllowed = false;
            $realFilePath = realpath($filepath);
            if ($realFilePath) {
                foreach ($allowedPaths as $allowedPath) {
                    $realAllowedPath = realpath($allowedPath);
                    if ($realAllowedPath && strpos($realFilePath, $realAllowedPath) === 0) {
                        $isAllowed = true;
                        break;
                    }
                }
            }

            if (!$isAllowed) {
                throw new Exception('文件路径不在允许的范围内');
            }

            switch ($operation) {
                case 'read':
                    if (!file_exists($filepath)) {
                        throw new Exception('文件不存在');
                    }
                    return [
                        'content' => file_get_contents($filepath),
                        'size' => filesize($filepath),
                        'modified' => date('Y-m-d H:i:s', filemtime($filepath))
                    ];

                case 'exists':
                    return ['exists' => file_exists($filepath)];

                case 'info':
                    if (!file_exists($filepath)) {
                        throw new Exception('文件不存在');
                    }
                    return [
                        'size' => filesize($filepath),
                        'modified' => date('Y-m-d H:i:s', filemtime($filepath)),
                        'is_file' => is_file($filepath),
                        'is_dir' => is_dir($filepath),
                        'is_readable' => is_readable($filepath),
                        'is_writable' => is_writable($filepath)
                    ];

                default:
                    throw new Exception('不支持的操作类型: ' . $operation);
            }

        } catch (Exception $e) {
            Log::error('MCP文件操作错误: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理用户管理
     * @param string $action 操作类型
     * @param int $userId 用户ID（可选）
     * @param int $limit 返回数量限制（可选）
     * @return array
     */
    public function handleUserManagement(string $action, int $userId = 0, int $limit = 10): array
    {
        try {
            switch ($action) {
                case 'list':
                    $users = Db::name('admin')
                        ->field('id,username,email,mobile,create_time,status')
                        ->limit($limit)
                        ->select();

                    return [
                        'users' => $users,
                        'count' => count($users)
                    ];

                case 'info':
                    if (!$userId) {
                        throw new Exception('用户ID不能为空');
                    }

                    $user = Db::name('admin')
                        ->field('id,username,nickname,email,mobile,create_time,status')
                        ->where('id', $userId)
                        ->find();

                    if (!$user) {
                        throw new Exception('用户不存在');
                    }

                    return $user;

                case 'count':
                    $total = Db::name('admin')->count();
                    $active = Db::name('admin')->where('status', 1)->count();

                    return [
                        'total' => $total,
                        'active' => $active,
                        'inactive' => $total - $active
                    ];

                default:
                    throw new Exception('不支持的操作类型: ' . $action);
            }

        } catch (Exception $e) {
            Log::error('MCP用户管理错误: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理系统信息
     * @param string $type 信息类型
     * @return array
     */
    public function handleSystemInfo(string $type = 'general'): array
    {
        try {
            switch ($type) {
                case 'general':
                    return [
                        'php_version' => PHP_VERSION,
                        'framework' => 'Webman',
                        'framework_version' => class_exists('Webman\\App') ? (defined('Webman\\App::VERSION') ? constant('Webman\\App::VERSION') : 'unknown') : (class_exists('support\\App') ? 'Webman-Support' : 'unknown'),
                        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                        'memory_limit' => ini_get('memory_limit'),
                        'max_execution_time' => ini_get('max_execution_time'),
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size'),
                    ];

                case 'database':
                    $version = 'Unknown';
                    try {
                        $versionResult = Db::query('SELECT VERSION() as version');
                        $version = $versionResult[0]['version'] ?? 'Unknown';
                    } catch (Exception $e) {
                        // 数据库连接失败时使用默认值
                    }

                    return [
                        'type' => config('database.default.type'),
                        'version' => $version,
                        'charset' => config('database.default.charset'),
                        'collation' => config('database.default.collate'),
                    ];

                case 'performance':
                    return [
                        'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                        'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
                        'included_files' => count(get_included_files()),
                    ];

                case 'cache':
                    return [
                        'default_driver' => config('cache.default'),
                        'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
                        'redis_available' => extension_loaded('redis'),
                        'memcached_available' => extension_loaded('memcached'),
                    ];

                default:
                    throw new Exception('不支持的系统信息类型: ' . $type);
            }

        } catch (Exception $e) {
            Log::error('MCP系统信息错误: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 格式化字节数
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * 处理配置资源
     * @return string
     */
    public function handleConfigResource(): string
    {
        $configs = [
            'app' => config('app'),
            'database' => [
                'type' => config('database.default.type'),
                'charset' => config('database.default.charset'),
                'debug' => config('database.debug'),
            ],
            'cache' => config('cache'),
            'session' => config('session'),
            'log' => config('log'),
        ];

        return json_encode($configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 处理数据库模式资源
     * @return string
     */
    public function handleSchemaResource(): string
    {
        try {
            // 获取所有表名
            $tables = Db::query('SHOW TABLES');
            $schema = [];

            foreach ($tables as $table) {
                $tableName = array_values($table)[0];

                // 获取表结构
                $columns = Db::query("SHOW COLUMNS FROM `{$tableName}`");
                $schema[$tableName] = $columns;
            }

            return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            Log::error('MCP数据库模式获取错误: ' . $e->getMessage());
            return json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 设置日志记录器
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 启动MCP服务器（STDIO传输）
     */
    public function startWithStdio()
    {
        try {
            // 启动心跳机制
            $this->startHeartbeat();

            $server = $this->buildServer();
            $transport = new StdioServerTransport();

            Log::info('MCP STDIO服务器启动成功');
            $server->listen($transport);

        } catch (Exception $e) {
            Log::error('MCP STDIO服务器启动失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 启动心跳机制
     */
    protected function startHeartbeat()
    {
        if (!$this->heartbeatEnabled) {
            return;
        }

        // 在后台启动心跳线程
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid == 0) {
                // 子进程执行心跳
                $this->heartbeatLoop();
                exit(0);
            }
        } else {
            // Windows系统使用定时器
            $this->scheduleHeartbeat();
        }
    }

    /**
     * 心跳循环
     */
    protected function heartbeatLoop()
    {
        while (true) {
            try {
                // 发送心跳信号
                $this->sendHeartbeat();

                // 等待下次心跳
                sleep($this->heartbeatInterval);

            } catch (Exception $e) {
                Log::error('心跳发送失败: ' . $e->getMessage());
                sleep(5); // 失败后等待5秒再重试
            }
        }
    }

    /**
     * 发送心跳信号
     */
    protected function sendHeartbeat()
    {
        // 记录心跳日志
        if ($this->debug) {
            Log::debug('发送心跳信号', [
                'timestamp' => time(),
                'memory_usage' => memory_get_usage(true)
            ]);
        }

        // 这里可以添加实际的心跳逻辑
        // 比如向客户端发送ping消息
    }

    /**
     * 调度心跳（Windows系统）
     */
    protected function scheduleHeartbeat()
    {
        // Windows系统下的心跳调度
        if (function_exists('register_tick_function')) {
            register_tick_function([$this, 'sendHeartbeat']);
            declare(ticks=1);
        }
    }

    /**
     * 构建MCP服务器
     */
    protected function buildServer()
    {
        if ($this->server !== null) {
            return $this->server;
        }

        // 创建容器并注册服务实例
        $container = new BasicContainer();
        $container->set(LoggerInterface::class, $this->logger);
        $container->set(self::class, $this);

        $this->server = Server::make()
            ->withServerInfo($this->name, $this->version)
            ->withLogger($this->logger)
            ->withContainer($container)
            ->withTool([self::class, 'handleHealthCheck'], 'mcp-health-check', '检查服务健康状态，包括数据库连接')
            ->withTool([self::class, 'handleDbQuery'], 'mcp-db-query', '执行数据库查询操作（仅支持SELECT语句）')
            ->withTool([self::class, 'handleSysConfig'], 'mcp-sys-config', '获取系统配置信息')
            ->withTool([self::class, 'handleWriteLog'], 'mcp-write-log', '写入系统日志')
            ->withTool([self::class, 'handleFileOperation'], 'mcp-file-operation', '文件读写操作')
            ->withTool([self::class, 'handleUserManagement'], 'mcp-user-management', '用户管理相关操作')
            ->withTool([self::class, 'handleSystemInfo'], 'mcp-system-info', '获取系统运行信息')
            ->withTool([self::class, 'handleCreateController'], 'mcp-controller', '生成控制器文件')
            ->withTool([self::class, 'handleCreateModel'], 'mcp-model', '生成模型文件')
            ->withTool([self::class, 'handleCreateView'], 'mcp-view', '生成视图文件')
            ->withTool([self::class, 'handleCreateJs'], 'mcp-js', '生成JS文件')
            ->withTool([self::class, 'handleCreateApi'], 'mcp-api', '生成API接口文件')
            ->withTool([self::class, 'handleCurd'], 'mcp-curd', '生成CURD模块')
            ->withTool([self::class, 'handleAddon'], 'mcp-addon', '生成插件模块')
            ->withTool([self::class, 'handleMenu'], 'mcp-menu', '生成菜单模块')
            ->withTool([self::class, 'handleCreateTable'], 'mcp-table', '创建数据库表格，支持字段信息、类型、注释等')
            ->withTool([self::class, 'handleThinkCommand'], 'mcp-command', '执行内置命令')
            ->withPrompt([self::class, 'handleWithPrompt'], 'mcp-with-prompt', '通过自然语言描述生成控制器、模型、路由等')
            ->withResource([self::class, 'handleConfigResource'], 'config://system', 'mcp-config-system', '系统配置信息资源', 'application/json')
            ->withResource([self::class, 'handleSchemaResource'], 'schema://database', 'mcp-schema-database', '数据库表结构信息资源', 'application/json')
            ->build();
        return $this->server;
    }

    /**
     * 使用指定传输启动MCP服务器
     */
    public function startWithTransport($transport)
    {
        try {
            // 启动心跳机制
            $this->startHeartbeat();

            $server = $this->buildServer();
            $server->listen($transport);

            Log::info('MCP服务器启动成功');

        } catch (Exception $e) {
            Log::error('MCP服务器启动失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 使用SSE传输启动MCP服务器
     */
    public function startWithSse(string $host = '127.0.0.1', int $port = 8080, string $mcpPath = 'webman-mcp')
    {
        try {
            // 启动心跳机制
            $this->startHeartbeat();

            $server = $this->buildServer();
            $transport = new StreamableHttpServerTransport($host, $port, $mcpPath);

            Log::info("MCP SSE服务器启动成功，监听地址: http://{$host}:{$port}/{$mcpPath}");
            $server->listen($transport);

        } catch (Exception $e) {
            Log::error('MCP SSE服务器启动失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 使用HTTP传输启动MCP服务器
     */
    public function startWithHttp(string $host = '127.0.0.1', int $port = 8080, string $mcpPath = 'webman-mcp')
    {
        try {
            // 启动心跳机制
            $this->startHeartbeat();

            $server = $this->buildServer();
            $transport = new HttpServerTransport($host, $port, $mcpPath);

            Log::info("MCP HTTP服务器启动成功，监听地址: http://{$host}:{$port}/{$mcpPath}");
            $server->listen($transport);

        } catch (Exception $e) {
            Log::error('MCP HTTP服务器启动失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取服务器实例
     * @return Server|null
     */
    public function getServer()
    {
        return $this->buildServer();
    }

    /**
     * 通过自然语言描述生成相关内容
     * @param string $prompt 自然语言描述
     * @param string $type 生成类型 (table/controller/model/js/api/view/all)
     * @return array
     */
    public function handleWithPrompt(string $prompt, string $type = 'all'): array
    {
        try {
            if (empty($prompt)) {
                throw new Exception('描述不能为空');
            }

            // 解析提示词
            $parsedData = $this->parsePrompt($prompt);

            $results = [];

            // 根据类型生成相应的内容
            if (in_array($type, ['table', 'all'])) {
                if (!empty($parsedData['table'])) {
                    $tableResult = $this->handleCreateTable(
                        $parsedData['table']['name'],
                        $parsedData['table']['fields'],
                        $parsedData['table']['comment'] ?? '',
                        'InnoDB',
                        'utf8mb4'
                    );
                    $results['table'] = $tableResult;
                }
            }

            if (in_array($type, ['controller', 'all'])) {
                if (!empty($parsedData['controller'])) {
                    $controllerResult = $this->handleCreateController(
                        $parsedData['controller']['module'] ?? 'admin',
                        $parsedData['controller']['name'],
                        $parsedData['controller']['fields'] ?? [],
                        $parsedData['controller']['description'] ?? ''
                    );
                    $results['controller'] = $controllerResult;
                }
            }

            if (in_array($type, ['model', 'all'])) {
                if (!empty($parsedData['model'])) {
                    $modelResult = $this->handleCreateModel(
                        $parsedData['model']['name'],
                        $parsedData['model']['fields'] ?? [],
                        $parsedData['model']['table'] ?? '',
                        $parsedData['model']['description'] ?? ''
                    );
                    $results['model'] = $modelResult;
                }
            }

            if (in_array($type, ['js', 'all'])) {
                if (!empty($parsedData['js'])) {
                    $jsResult = $this->handleCreateJs(
                        $parsedData['js']['module'] ?? 'admin',
                        $parsedData['js']['name'],
                        $parsedData['js']['fields'] ?? [],
                        $parsedData['js']['description'] ?? ''
                    );
                    $results['js'] = $jsResult;
                }
            }

            if (in_array($type, ['api', 'all'])) {
                if (!empty($parsedData['api'])) {
                    $apiResult = $this->handleCreateApi(
                        $parsedData['api']['module'] ?? 'api',
                        $parsedData['api']['name'],
                        $parsedData['api']['fields'] ?? [],
                        $parsedData['api']['description'] ?? ''
                    );
                    $results['api'] = $apiResult;
                }
            }

            if (in_array($type, ['view', 'all'])) {
                if (!empty($parsedData['view'])) {
                    $viewResult = $this->handleCreateView(
                        $parsedData['view']['module'] ?? 'admin',
                        $parsedData['view']['name'],
                        $parsedData['view']['fields'] ?? [],
                        $parsedData['view']['description'] ?? ''
                    );
                    $results['view'] = $viewResult;
                }
            }

            if (in_array($type, ['addon', 'all'])) {
                if (!empty($parsedData['addon'])) {
                    $addonResult = $this->handleAddon(
                        'create',
                        $parsedData['addon']['name'],
                        $parsedData['addon']['options'] ?? []
                    );
                    $results['addon'] = $addonResult;
                }
            }

            if (in_array($type, ['curd', 'all'])) {
                if (!empty($parsedData['curd'])) {
                    $curdResult = $this->handleCurd(
                        $parsedData['curd']['name'],
                        $parsedData['curd']['module'] ?? 'admin',
                        $parsedData['curd']['fields'] ?? [],
                        $parsedData['curd']['description'] ?? '',
                        $parsedData['curd']['options'] ?? []
                    );
                    $results['curd'] = $curdResult;
                }
            }

            if (in_array($type, ['menu', 'all'])) {
                if (!empty($parsedData['menu'])) {
                    $menuResult = $this->handleMenu(
                        'create',
                        $parsedData['menu']['data'] ?? [],
                        $parsedData['menu']['options'] ?? []
                    );
                    $results['menu'] = $menuResult;
                }
            }

            //如果这里面都没有 那么执行其他操作
            if (empty($results)) {
                $results = $this->handleOtherOperation($prompt);
            }

            return [
                'success' => true,
                'message' => '通过提示词生成成功',
                'parsed_data' => $parsedData,
                'results' => $results
            ];

        } catch (Exception $e) {
            Log::error('WebmanMCP withPrompt错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 解析自然语言提示词
     * @param string $prompt
     * @return array
     */
    private function parsePrompt(string $prompt): array
    {
        $parsedData = [
            'table' => null,
            'controller' => null,
            'model' => null,
            'js' => null,
            'api' => null,
            'view' => null,
            'addon' => null,
            'curd' => null,
            'menu' => null
        ];

        // 转换为小写便于匹配
        $lowerPrompt = strtolower($prompt);

        // 提取表名
        $tableName = null;
        if (preg_match('/(?:创建|生成|建立).*?(?:表|table).*?[名为|叫|是]\s*([a-zA-Z_][a-zA-Z0-9_]*)/', $lowerPrompt, $matches)) {
            $tableName = $matches[1];
        } elseif (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:表|table)/', $lowerPrompt, $matches)) {
            $tableName = $matches[1];
        } elseif (preg_match('/(?:创建|生成|建立).*?([a-zA-Z_][a-zA-Z0-9_]*)/', $lowerPrompt, $matches)) {
            $tableName = $matches[1];
        }

        // 提取字段信息
        $fields = $this->extractFieldsFromPrompt($prompt);

        // 提取表注释
        $tableComment = $this->extractTableComment($prompt);

        // 构建表数据
        if ($tableName) {
            $parsedData['table'] = [
                'name' => $tableName,
                'fields' => $fields,
                'comment' => $tableComment
            ];

            // 构建控制器数据
            $controllerName = ucfirst($tableName) . 'Controller';
            $parsedData['controller'] = [
                'module' => 'admin',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建模型数据
            $modelName = ucfirst($tableName);
            $parsedData['model'] = [
                'name' => $modelName,
                'fields' => $fields,
                'table' => $tableName,
                'description' => $tableComment ?: $modelName
            ];

            // 构建JS数据
            $parsedData['js'] = [
                'module' => 'admin',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建API数据
            $parsedData['api'] = [
                'module' => 'api',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建视图数据
            $parsedData['view'] = [
                'module' => 'admin',
                'name' => $controllerName,
                'fields' => $fields,
                'description' => $tableComment ?: $controllerName
            ];

            // 构建CRUD数据
            $parsedData['curd'] = [
                'name' => $tableName,
                'module' => 'admin',
                'fields' => $fields,
                'description' => $tableComment ?: $tableName,
                'options' => []
            ];

            // 构建菜单数据
            $parsedData['menu'] = [
                'data' => [
                    'controller' => $controllerName,
                    'app' => 'admin',
                    'menuname' => $tableComment ?: $tableName
                ],
                'options' => []
            ];
        }

        // 解析特殊操作
        $this->parseSpecialOperations($lowerPrompt, $parsedData);

        return $parsedData;
    }

    /**
     * 从提示词中提取字段信息
     * @param string $prompt
     * @return array
     */
    private function extractFieldsFromPrompt(string $prompt): array
    {
        $fields = [];

        // 常见的字段模式匹配
        $fieldPatterns = [
            // 用户相关字段
            'username' => ['用户名', 'username', '用户名称'],
            'email' => ['邮箱', 'email', '邮件'],
            'phone' => ['手机', 'phone', '电话', '手机号'],
            'password' => ['密码', 'password'],
            'nickname' => ['昵称', 'nickname', '昵名'],
            'avatar' => ['头像', 'avatar', '照片'],
            'gender' => ['性别', 'gender'],
            'birthday' => ['生日', 'birthday', '出生日期'],
            'address' => ['地址', 'address', '住址'],
            'status' => ['状态', 'status'],

            // 通用字段
            'title' => ['标题', 'title', '名称'],
            'content' => ['内容', 'content', '描述'],
            'description' => ['描述', 'description', '说明'],
            'price' => ['价格', 'price', '金额'],
            'amount' => ['数量', 'amount', '数量'],
            'category_id' => ['分类', 'category', '分类ID'],
            'sort' => ['排序', 'sort', '顺序'],
            'remark' => ['备注', 'remark', '说明'],

            // 时间相关字段
            'create_time' => ['创建时间', 'create_time'],
            'update_time' => ['更新时间', 'update_time'],
            'publish_time' => ['发布时间', 'publish_time'],
            'expire_time' => ['过期时间', 'expire_time']
        ];

        foreach ($fieldPatterns as $fieldName => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($prompt, $pattern) !== false) {
                    $fields[] = $this->CreateFieldByType($fieldName);
                    break;
                }
            }
        }

        // 如果没有找到字段，添加默认字段
        if (empty($fields)) {
            $fields = [
                [
                    'name' => 'title',
                    'type' => 'varchar(255)',
                    'comment' => '标题',
                    'null' => false,
                    'default' => ''
                ],
                [
                    'name' => 'content',
                    'type' => 'text',
                    'comment' => '内容',
                    'null' => true
                ],
                [
                    'name' => 'status',
                    'type' => 'tinyint(1)',
                    'comment' => '状态：0=禁用，1=启用',
                    'null' => false,
                    'default' => 1
                ]
            ];
        }

        return $fields;
    }

    /**
     * 根据字段名生成字段配置
     * @param string $fieldName
     * @return array
     */
    private function CreateFieldByType(string $fieldName): array
    {
        $fieldConfigs = [
            'username' => [
                'name' => 'username',
                'type' => 'varchar(50)',
                'comment' => '用户名',
                'null' => false,
                'default' => ''
            ],
            'email' => [
                'name' => 'email',
                'type' => 'varchar(100)',
                'comment' => '邮箱',
                'null' => false,
                'default' => ''
            ],
            'phone' => [
                'name' => 'phone',
                'type' => 'varchar(20)',
                'comment' => '手机号',
                'null' => true
            ],
            'password' => [
                'name' => 'password',
                'type' => 'varchar(255)',
                'comment' => '密码',
                'null' => false,
                'default' => ''
            ],
            'nickname' => [
                'name' => 'nickname',
                'type' => 'varchar(50)',
                'comment' => '昵称',
                'null' => true
            ],
            'avatar' => [
                'name' => 'avatar',
                'type' => 'varchar(255)',
                'comment' => '头像',
                'null' => true
            ],
            'gender' => [
                'name' => 'gender',
                'type' => 'tinyint(1)',
                'comment' => '性别：0=未知，1=男，2=女',
                'null' => false,
                'default' => 0
            ],
            'birthday' => [
                'name' => 'birthday',
                'type' => 'date',
                'comment' => '生日',
                'null' => true
            ],
            'address' => [
                'name' => 'address',
                'type' => 'text',
                'comment' => '地址',
                'null' => true
            ],
            'status' => [
                'name' => 'status',
                'type' => 'tinyint(1)',
                'comment' => '状态：0=禁用，1=启用',
                'null' => false,
                'default' => 1
            ],
            'title' => [
                'name' => 'title',
                'type' => 'varchar(255)',
                'comment' => '标题',
                'null' => false,
                'default' => ''
            ],
            'content' => [
                'name' => 'content',
                'type' => 'text',
                'comment' => '内容',
                'null' => true
            ],
            'description' => [
                'name' => 'description',
                'type' => 'text',
                'comment' => '描述',
                'null' => true
            ],
            'price' => [
                'name' => 'price',
                'type' => 'decimal(10,2)',
                'comment' => '价格',
                'null' => false,
                'default' => 0.00
            ],
            'amount' => [
                'name' => 'amount',
                'type' => 'int(11)',
                'comment' => '数量',
                'null' => false,
                'default' => 0
            ],
            'category_id' => [
                'name' => 'category_id',
                'type' => 'int(11)',
                'comment' => '分类ID',
                'null' => false,
                'default' => 0
            ],
            'sort' => [
                'name' => 'sort',
                'type' => 'int(11)',
                'comment' => '排序',
                'null' => false,
                'default' => 0
            ],
            'remark' => [
                'name' => 'remark',
                'type' => 'varchar(255)',
                'comment' => '备注',
                'null' => true
            ],
            'publish_time' => [
                'name' => 'publish_time',
                'type' => 'datetime',
                'comment' => '发布时间',
                'null' => true
            ],
            'expire_time' => [
                'name' => 'expire_time',
                'type' => 'datetime',
                'comment' => '过期时间',
                'null' => true
            ]
        ];

        return $fieldConfigs[$fieldName] ?? [
            'name' => $fieldName,
            'type' => 'varchar(255)',
            'comment' => $fieldName,
            'null' => true
        ];
    }

    /**
     * 从提示词中提取表注释
     * @param string $prompt
     * @return string
     */
    private function extractTableComment(string $prompt): string
    {
        // 提取表注释的模式
        $patterns = [
            '/(?:用于|用来|存储|管理).*?(?:信息|数据|记录)/',
            '/(?:.*?)(?:表|table)/',
            '/(?:.*?)(?:管理|系统|模块)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                return trim($matches[0]) . '表';
            }
        }

        return '';
    }

    /**
     * 解析特殊操作
     * @param string $lowerPrompt
     * @param array &$parsedData
     */
    private function parseSpecialOperations(string $lowerPrompt, array &$parsedData): void
    {
        // 解析JS相关操作
        if (strpos($lowerPrompt, 'js') !== false || strpos($lowerPrompt, 'javascript') !== false || strpos($lowerPrompt, '前端') !== false) {
            if (!empty($parsedData['js'])) {
                $parsedData['js']['description'] = '前端JS文件';
            }
        }

        // 解析API相关操作
        if (strpos($lowerPrompt, 'api') !== false || strpos($lowerPrompt, '接口') !== false || strpos($lowerPrompt, '接口文件') !== false) {
            if (!empty($parsedData['api'])) {
                $parsedData['api']['description'] = 'API接口文件';
            }
        }

        // 解析视图相关操作
        if (strpos($lowerPrompt, 'view') !== false || strpos($lowerPrompt, '视图') !== false || strpos($lowerPrompt, '页面') !== false) {
            if (!empty($parsedData['view'])) {
                $parsedData['view']['description'] = '视图文件';
            }
        }

        // 解析插件相关操作
        if (strpos($lowerPrompt, 'addon') !== false || strpos($lowerPrompt, '插件') !== false) {
            if (preg_match('/(?:创建|生成|建立).*?(?:插件|addon).*?[名为|叫|是]\s*([a-zA-Z_][a-zA-Z0-9_]*)/', $lowerPrompt, $matches)) {
                $addonName = $matches[1];
                $parsedData['addon'] = [
                    'name' => $addonName,
                    'options' => [
                        'title' => $addonName,
                        'description' => $addonName . '插件',
                        'author' => 'Webman',
                        'version' => '1.0.0',
                        'requires' => '1.0.0'
                    ]
                ];
            }
        }

        // 解析菜单相关操作
        if (strpos($lowerPrompt, 'menu') !== false || strpos($lowerPrompt, '菜单') !== false) {
            if (!empty($parsedData['menu'])) {
                $parsedData['menu']['description'] = '菜单权限';
            }
        }

        // 解析CRUD相关操作
        if (strpos($lowerPrompt, 'curd') !== false || strpos($lowerPrompt, 'crud') !== false || strpos($lowerPrompt, '增删改查') !== false) {
            if (!empty($parsedData['curd'])) {
                $parsedData['curd']['description'] = 'CRUD模块';
            }
        }

        // 解析模块类型
        if (strpos($lowerPrompt, 'admin') !== false || strpos($lowerPrompt, '后台') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'admin';
            }
            if (!empty($parsedData['js'])) {
                $parsedData['js']['module'] = 'admin';
            }
            if (!empty($parsedData['view'])) {
                $parsedData['view']['module'] = 'admin';
            }
        }

        if (strpos($lowerPrompt, 'frontend') !== false || strpos($lowerPrompt, '前台') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'frontend';
            }
            if (!empty($parsedData['js'])) {
                $parsedData['js']['module'] = 'frontend';
            }
            if (!empty($parsedData['view'])) {
                $parsedData['view']['module'] = 'frontend';
            }
        }

        if (strpos($lowerPrompt, 'api') !== false) {
            if (!empty($parsedData['controller'])) {
                $parsedData['controller']['module'] = 'api';
            }
            if (!empty($parsedData['js'])) {
                $parsedData['js']['module'] = 'api';
            }
            if (!empty($parsedData['view'])) {
                $parsedData['view']['module'] = 'api';
            }
        }
    }

    /**
     * 创建数据库表格 - 使用Webman数据库组件
     * @param string $tableName 表名
     * @param array $fields 字段信息数组
     * @param string $tableComment 表注释
     * @param string $engine 存储引擎 (默认 InnoDB)
     * @param string $charset 字符集 (默认 utf8mb4)
     * @return array
     */
    public function handleCreateTable(string $tableName, array $fields, string $tableComment = '', string $engine = 'InnoDB', string $charset = 'utf8mb4'): array
    {
        try {
            // 验证表名
            if (empty($tableName)) {
                throw new Exception('表名不能为空');
            }

            // 验证字段信息
            if (empty($fields) || !is_array($fields)) {
                throw new Exception('字段信息不能为空且必须是数组');
            }

            // 检查表是否已存在
            $existingTables = Db::query("SHOW TABLES LIKE '{$tableName}'");
            if (!empty($existingTables)) {
                return [
                    'success' => false,
                    'error' => "表 {$tableName} 已存在"
                ];
            }

            // 生成建表SQL
            $createSql = $this->CreateCreateTableSql($tableName, $fields, $tableComment, $engine, $charset);

            // 执行建表SQL
            $result = Db::execute($createSql);

            if ($result !== false) {
                Log::info("Webman数据库表创建成功: {$tableName}");
                return [
                    'success' => true,
                    'message' => '表创建成功',
                    'table_name' => $tableName,
                    'sql' => $createSql,
                    'fields_count' => count($fields)
                ];
            } else {
                throw new Exception('建表SQL执行失败');
            }

        } catch (Exception $e) {
            Log::error('Webman创建表格错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成建表SQL
     * @param string $tableName 表名
     * @param array $fields 字段信息
     * @param string $tableComment 表注释
     * @param string $engine 存储引擎
     * @param string $charset 字符集
     * @return string
     */
    private function CreateCreateTableSql(string $tableName, array $fields, string $tableComment = '', string $engine = 'InnoDB', string $charset = 'utf8mb4'): string
    {
        $sql = "CREATE TABLE `{$tableName}` (\n";

        $fieldDefinitions = [];
        $primaryKey = null;

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'varchar(255)';
            $fieldLength = $field['length'] ?? '';
            $fieldDefault = $field['default'] ?? '';
            $fieldComment = $field['comment'] ?? '';
            $fieldNull = isset($field['null']) && $field['null'] ? 'NULL' : 'NOT NULL';
            $fieldAutoIncrement = isset($field['auto_increment']) && $field['auto_increment'] ? 'AUTO_INCREMENT' : '';
            $fieldPrimary = isset($field['primary']) && $field['primary'] ? 'PRIMARY KEY' : '';

            // 构建字段定义
            $fieldDef = "    `{$fieldName}` {$fieldType}";

            // 添加长度
            if (!empty($fieldLength) && !in_array(strtolower($fieldType), ['text', 'longtext', 'mediumtext', 'tinytext', 'blob', 'longblob', 'mediumblob', 'tinyblob'])) {
                $fieldDef .= "({$fieldLength})";
            }

            // 添加默认值
            if ($fieldDefault !== '') {
                if (is_string($fieldDefault)) {
                    $fieldDef .= " DEFAULT '{$fieldDefault}'";
                } else {
                    $fieldDef .= " DEFAULT {$fieldDefault}";
                }
            }

            // 添加NULL/NOT NULL
            $fieldDef .= " {$fieldNull}";

            // 添加自增
            if (!empty($fieldAutoIncrement)) {
                $fieldDef .= " {$fieldAutoIncrement}";
            }

            // 添加注释
            if (!empty($fieldComment)) {
                $fieldDef .= " COMMENT '{$fieldComment}'";
            }

            // 添加主键
            if (!empty($fieldPrimary)) {
                $fieldDef .= " {$fieldPrimary}";
                $primaryKey = $fieldName;
            }

            $fieldDefinitions[] = $fieldDef;
        }

        // 添加默认字段（如果不存在）
        $hasId = false;
        $hasCreateTime = false;
        $hasUpdateTime = false;

        foreach ($fields as $field) {
            if ($field['name'] === 'id') $hasId = true;
            if ($field['name'] === 'create_time') $hasCreateTime = true;
            if ($field['name'] === 'update_time') $hasUpdateTime = true;
        }

        // 如果没有ID字段，添加默认ID字段
        if (!$hasId) {
            array_unshift($fieldDefinitions, "    `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID'");
        }

        // 添加默认时间字段
        if (!$hasCreateTime) {
            $fieldDefinitions[] = "    `create_time` int(11) DEFAULT NULL COMMENT '创建时间'";
        }
        if (!$hasUpdateTime) {
            $fieldDefinitions[] = "    `update_time` int(11) DEFAULT NULL COMMENT '更新时间'";
        }

        $sql .= implode(",\n", $fieldDefinitions);
        $sql .= "\n)";

        // 添加表注释
        if (!empty($tableComment)) {
            $sql .= " COMMENT='{$tableComment}'";
        }

        // 添加存储引擎和字符集
        $sql .= " ENGINE={$engine} DEFAULT CHARSET={$charset}";

        return $sql;
    }

    /**
     * 生成控制器文件
     * @param string $module 模块名称 (admin/api/frontend等)
     * @param string $controller 控制器名称
     * @param array $fields 字段信息 (可选)
     * @param string $description 控制器描述 (可选)
     * @return array
     */
    public function handleCreateController(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        try {
            // 生成控制器类名
            $controllerClass = ucfirst($controller);
            $controllerPath = "app/{$module}/controller/{$controllerClass}.php";

            // 检查文件是否已存在
            if (file_exists($controllerPath)) {
                return [
                    'success' => false,
                    'error' => "控制器文件 {$controllerPath} 已存在"
                ];
            }

            // 生成控制器内容
            $controllerContent = $this->CreateControllerContent($module, $controllerClass, $fields, $description);

            // 确保目录存在
            $dir = dirname($controllerPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            if (file_put_contents($controllerPath, $controllerContent)) {
                Log::info("控制器生成成功: {$controllerPath}");
                return [
                    'success' => true,
                    'message' => '控制器生成成功',
                    'file_path' => $controllerPath,
                    'content' => $controllerContent
                ];
            } else {
                throw new Exception('文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('控制器生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成控制器内容
     * @param string $module 模块名称
     * @param string $controllerClass 控制器类名
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function CreateControllerContent(string $module, string $controllerClass, array $fields = [], string $description = ''): string
    {
        $namespace = "app\\{$module}\\controller";
        $baseController = $module === 'api' ? 'Api' : 'Base'; // Webman通常使用BaseController
        $useStatement = $module === 'api' ? 'use app\\common\\controller\\Api;' : 'use app\\BaseController;';

        $description = $description ?: $controllerClass;

        $content = "<?php
/**
 * Webman
 * ============================================================================
 * 版权所有 2017-2028 Webman，并保留所有权利。
 * 网站地址: http://www.Webman.com
 * ----------------------------------------------------------------------------
 * 采用最新Webman实现
 * ============================================================================
 * Author: AI Assistant
 * Date: " . date('Y/m/d') . "
 */
namespace {$namespace};

{$useStatement}
use support\Request;
use support\Response;

/**
 * {$description}控制器
 * Class {$controllerClass}
 * @package {$namespace}
 */
class {$controllerClass} extends {$baseController}
{

    /**
     * 列表
     * @param Request \$request
     * @return Response
     */
    public function index(Request \$request): Response
    {
        // 获取查询参数
        \$params = \$request->all();
        
        // 执行查询逻辑
        \$data = [];
        
        return \$this->jsonSuccess([
            'list' => \$data,
            'count' => count(\$data)
        ], '获取成功');
    }

    /**
     * 详情
     * @param Request \$request
     * @param int \$id
     * @return Response
     */
    public function read(Request \$request, int \$id): Response
    {
        // 查询单条记录
        \$data = [];
        
        if (empty(\$data)) {
            return \$this->jsonError('记录不存在');
        }
        
        return \$this->jsonSuccess(\$data, '获取成功');
    }

    /**
     * 添加
     * @param Request \$request
     * @return Response
     */
    public function save(Request \$request): Response
    {
        \$params = \$request->post();
        
        // 执行添加逻辑
        
        return \$this->jsonSuccess([], '添加成功');
    }

    /**
     * 更新
     * @param Request \$request
     * @param int \$id
     * @return Response
     */
    public function update(Request \$request, int \$id): Response
    {
        \$params = \$request->post();
        
        // 执行更新逻辑
        
        return \$this->jsonSuccess([], '更新成功');
    }

    /**
     * 删除
     * @param Request \$request
     * @param int \$id
     * @return Response
     */
    public function delete(Request \$request, int \$id): Response
    {
        // 执行删除逻辑
        
        return \$this->jsonSuccess([], '删除成功');
    }
}";

        return $content;
    }

    /**
     * 生成模型文件
     * @param string $modelName 模型名称
     * @param array $fields 字段信息 (可选)
     * @param string $tableName 表名 (可选，默认使用模型名)
     * @param string $description 模型描述 (可选)
     * @return array
     */
    public function handleCreateModel(string $modelName, array $fields = [], string $tableName = '', string $description = ''): array
    {
        try {
            // 生成模型类名
            $modelClass = ucfirst($modelName);
            $modelPath = "app/common/model/{$modelClass}.php";

            // 检查文件是否已存在
            if (file_exists($modelPath)) {
                return [
                    'success' => false,
                    'error' => "模型文件 {$modelPath} 已存在"
                ];
            }

            // 如果没有指定表名，使用模型名
            if (empty($tableName)) {
                $tableName = strtolower($modelName);
            }

            // 生成模型内容
            $modelContent = $this->CreateModelContent($modelClass, $tableName, $fields, $description);

            // 确保目录存在
            $dir = dirname($modelPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            if (file_put_contents($modelPath, $modelContent)) {
                Log::info("Webman模型生成成功: {$modelPath}");
                return [
                    'success' => true,
                    'message' => '模型生成成功',
                    'file_path' => $modelPath,
                    'content' => $modelContent
                ];
            } else {
                throw new Exception('文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('模型生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成模型内容
     * @param string $modelClass 模型类名
     * @param string $tableName 表名
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function CreateModelContent(string $modelClass, string $tableName, array $fields = [], string $description = ''): string
    {
        $description = $description ?: $modelClass;

        // 生成字段定义
        $fieldDefinitions = '';
        if (!empty($fields)) {
            $fieldDefinitions = "    // 字段定义\n";
            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? '';
                $fieldType = $field['type'] ?? 'string';
                $fieldComment = $field['comment'] ?? '';
                if ($fieldName) {
                    $fieldDefinitions .= "    protected \$" . $fieldName . " = ''; // {$fieldComment}\n";
                }
            }
        }

        $content = "<?php
/**
 * Webman
 * ============================================================================
 * 版权所有 2017-2028 Webman，并保留所有权利。
 * 网站地址: http://www.Webman.com
 * ----------------------------------------------------------------------------
 * 采用最新Webman实现
 * ============================================================================
 * Author: AI Assistant
 * Date: " . date('Y/m/d') . "
 */
namespace app\\common\\model;

use support\Model;

/**
 * {$description}模型
 * Class {$modelClass}
 * @package app\\common\\model
 */
class {$modelClass} extends Model
{
    /**
     * 数据表名
     * @var string
     */
    protected \$table = '{$tableName}';

{$fieldDefinitions}

}";

        return $content;
    }

    /**
     * 生成JS文件
     * @param string $module 模块名称 (admin/api/frontend等)
     * @param string $controller 控制器名称
     * @param array $fields 字段信息 (可选)
     * @param string $description 描述 (可选)
     * @return array
     */
    public function handleCreateJs(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        try {
            // 生成JS文件名
            $jsFileName = strtolower($controller);
            $jsPath = "public/static/{$module}/js/{$jsFileName}.js";

            // 检查文件是否已存在
            if (file_exists($jsPath)) {
                return [
                    'success' => false,
                    'error' => "JS文件 {$jsPath} 已存在"
                ];
            }

            // 生成JS内容
            $jsContent = $this->generateJsContent($module, $controller, $fields, $description);

            // 确保目录存在
            $dir = dirname($jsPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            if (file_put_contents($jsPath, $jsContent)) {
                Log::info("JS文件生成成功: {$jsPath}");
                return [
                    'success' => true,
                    'message' => 'JS文件生成成功',
                    'file_path' => $jsPath,
                    'content' => $jsContent
                ];
            } else {
                throw new Exception('文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('JS文件生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成JS文件内容
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function generateJsContent(string $module, string $controller, array $fields = [], string $description = ''): string
    {
        $controllerLower = strtolower($controller);
        $description = $description ?: $controller;

        // 生成表格列配置
        $jsCols = $this->generateJsCols($fields);

        $content = "define(['table', 'form'], function (Table, Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: '{$controllerLower}/index',
                    add_url: '{$controllerLower}/save',
                    edit_url: '{$controllerLower}/update',
                    del_url: '{$controllerLower}/delete',
                    multi_url: '{$controllerLower}/multi',
                    table_url: '{$controllerLower}/table',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh', 'add', 'destroy', 'import', 'export'],
                cols: [[
                    {type: 'checkbox'},
                    {field: 'id', title: 'ID', width: 80, sort: true},
{$jsCols}
                    {field: 'create_time', title: '创建时间', width: 180, sort: true},
                    {field: 'update_time', title: '更新时间', width: 180, sort: true},
                    {title: '操作', width: 250, align: 'center', operat: ['index_url','copy', 'destroy']}
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true,
                done: function (res, curr, count) {
                    // 表格渲染完成后的回调
                }
            });
            Table.api.bindEvent(Table.init.tableId);
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        copy:function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    return Controller;
});";

        return $content;
    }

    /**
     * 生成JS表格列配置
     * @param array $fields 字段信息
     * @return string
     */
    private function generateJsCols(array $fields): string
    {
        if (empty($fields)) {
            return "                    {field: 'title', title: '标题', width: 200},";
        }

        $cols = [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldComment = $field['comment'] ?? $fieldName;
            $fieldType = $field['type'] ?? 'varchar';

            // 跳过系统字段
            if (in_array($fieldName, ['id', 'create_time', 'update_time'])) {
                continue;
            }

            // 根据字段类型设置不同的显示方式
            $width = 200;
            $templet = '';

            if (strpos($fieldType, 'text') !== false) {
                $width = 300;
            } elseif (strpos($fieldType, 'int') !== false) {
                $width = 100;
            } elseif (strpos($fieldType, 'datetime') !== false || strpos($fieldType, 'timestamp') !== false) {
                $width = 180;
                $templet = ", templet: function(d) { return d.{$fieldName} ? layui.util.toDateString(d.{$fieldName} * 1000) : ''; }";
            } elseif (strpos($fieldType, 'tinyint') !== false) {
                $width = 100;
                $templet = ", templet: function(d) { return d.{$fieldName} == 1 ? '<span class=\"layui-badge layui-bg-green\">启用</span>' : '<span class=\"layui-badge layui-bg-gray\">禁用</span>'; }";
            }

            $cols[] = "                    {field: '{$fieldName}', title: '{$fieldComment}', width: {$width}{$templet},";
        }

        return implode("\n", $cols);
    }

    /**
     * 生成API接口文件
     * @param string $controller 控制器名称
     * @param string $module 模块名称 (api等)
     * @param array $fields 字段信息 (可选)
     * @param string $description 描述 (可选)
     * @return array
     */
    public function handleCreateApi(string $controller, string $module = 'api', array $fields = [], string $description = ''): array
    {
        try {
            // 生成API控制器类名
            $controllerClass = ucfirst($controller);
            $apiPath = "app/{$module}/controller/{$controllerClass}.php";

            // 检查文件是否已存在
            if (file_exists($apiPath)) {
                return [
                    'success' => false,
                    'error' => "API文件 {$apiPath} 已存在"
                ];
            }

            // 生成API内容
            $apiContent = $this->generateApiContent($module, $controllerClass, $fields, $description);

            // 确保目录存在
            $dir = dirname($apiPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 写入文件
            if (file_put_contents($apiPath, $apiContent)) {
                Log::info("Webman API文件生成成功: {$apiPath}");
                return [
                    'success' => true,
                    'message' => 'API文件生成成功',
                    'file_path' => $apiPath,
                    'content' => $apiContent
                ];
            } else {
                throw new Exception('文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('API文件生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成API文件内容
     * @param string $module 模块名称
     * @param string $controllerClass 控制器类名
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function generateApiContent(string $module, string $controllerClass, array $fields = [], string $description = ''): string
    {
        $namespace = "app\\{$module}\\controller";
        $description = $description ?: $controllerClass;

        // 生成字段验证规则
        $validationRules = $this->generateApiValidationRules($fields);

        $content = "<?php
/**
 * Webman
 * ============================================================================
 * 版权所有 2017-2028 Webman，并保留所有权利。
 * 网站地址: http://www.Webman.com
 * ----------------------------------------------------------------------------
 * 采用最新Webman实现
 * ============================================================================
 * Author: AI Assistant
 * Date: " . date('Y/m/d') . "
 */
namespace {$namespace};

use app\\common\\controller\\Api;
use support\\Request;
use support\\Response;

/**
 * {$description}API控制器
 * Class {$controllerClass}
 * @package {$namespace}
 */
class {$controllerClass} extends Api
{
    /**
     * 列表
     */
    public function index(Request \$request): Response
    {
        \$params = \$request->all();
        
        \$list = [];
        
        return \$this->jsonSuccess([
            'list' => \$list,
            'count' => count(\$list)
        ], '获取成功');
    }

    /**
     * 详情
     */
    public function read(Request \$request, int \$id): Response
    {
        \$row = [];
        if (empty(\$row)) {
            return \$this->jsonError('记录不存在');
        }
        return \$this->jsonSuccess(\$row, '获取成功');
    }

    /**
     * 新增
     */
    public function save(Request \$request): Response
    {
        \$params = \$request->post();
        
        // 验证数据
        try {
            // 验证逻辑
        } catch (\\Exception \$e) {
            return \$this->jsonError(\$e->getMessage());
        }
        
        // 保存逻辑
        
        return \$this->jsonSuccess([], '添加成功');
    }

    /**
     * 编辑
     */
    public function update(Request \$request, int \$id): Response
    {
        \$row = [];
        if (empty(\$row)) {
            return \$this->jsonError('记录不存在');
        }
        
        \$params = \$request->post();
        
        // 验证数据
        try {
            // 验证逻辑
        } catch (\\Exception \$e) {
            return \$this->jsonError(\$e->getMessage());
        }
        
        // 更新逻辑
        
        return \$this->jsonSuccess([], '更新成功');
    }

    /**
     * 删除
     */
    public function delete(Request \$request, int \$id): Response
    {
        \$row = [];
        if (empty(\$row)) {
            return \$this->jsonError('记录不存在');
        }
        
        // 删除逻辑
        
        return \$this->jsonSuccess([], '删除成功');
    }
}";

        return $content;
    }

    /**
     * 生成API验证规则
     * @param array $fields 字段信息
     * @return string
     */
    private function generateApiValidationRules(array $fields): string
    {
        if (empty($fields)) {
            return "'title' => 'require|max:255',
                'content' => 'require',";
        }

        $rules = [];
        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'varchar';

            if (empty($fieldName) || in_array($fieldName, ['id', 'create_time', 'update_time'])) {
                continue;
            }

            $rule = "'{$fieldName}' => '";

            // 根据字段类型设置验证规则
            if (strpos($fieldType, 'varchar') !== false) {
                $maxLength = 255;
                if (preg_match('/varchar\((\d+)\)/', $fieldType, $matches)) {
                    $maxLength = $matches[1];
                }
                $rule .= "max:{$maxLength}";
            } elseif (strpos($fieldType, 'text') !== false) {
                $rule .= "require";
            } elseif (strpos($fieldType, 'int') !== false) {
                $rule .= "number";
            } elseif (strpos($fieldType, 'datetime') !== false || strpos($fieldType, 'timestamp') !== false) {
                $rule .= "date";
            } else {
                $rule .= "require";
            }

            $rule .= "'";
            $rules[] = $rule;
        }

        return implode(",\n                ", $rules);
    }

    /**
     * 生成视图文件
     * @param string $module 模块名称 (admin/api/frontend等)
     * @param string $controller 控制器名称
     * @param array $fields 字段信息 (可选)
     * @param string $description 描述 (可选)
     * @return array
     */
    public function handleCreateView(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        try {
            // 生成视图文件名
            $viewFileName = strtolower($controller);
            $viewPath = "app/{$module}/view/{$viewFileName}";

            // 检查目录是否已存在
            if (is_dir($viewPath)) {
                return [
                    'success' => false,
                    'error' => "视图目录 {$viewPath} 已存在"
                ];
            }

            // 生成视图内容
            $viewFiles = $this->generateViewFiles($module, $controller, $fields, $description);

            // 确保目录存在
            if (!is_dir($viewPath)) {
                mkdir($viewPath, 0755, true);
            }

            $generatedFiles = [];
            foreach ($viewFiles as $viewFile) {
                $filePath = $viewPath . '/' . $viewFile['name'];
                if (file_put_contents($filePath, $viewFile['content'])) {
                    $generatedFiles[] = $filePath;
                    Log::info("Webman 视图文件生成成功: {$filePath}");
                }
            }

            if (!empty($generatedFiles)) {
                return [
                    'success' => true,
                    'message' => '视图文件生成成功',
                    'file_paths' => $generatedFiles,
                    'files_count' => count($generatedFiles)
                ];
            } else {
                throw new Exception('视图文件写入失败');
            }

        } catch (Exception $e) {
            Log::error('视图文件生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 生成视图文件
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return array
     */
    private function generateViewFiles(string $module, string $controller, array $fields = [], string $description = ''): array
    {
        $controllerLower = strtolower($controller);
        $description = $description ?: $controller;

        $viewFiles = [];

        // 生成 index.html
        $indexContent = $this->generateIndexView($module, $controllerLower, $fields, $description);
        $viewFiles[] = [
            'name' => 'index.html',
            'content' => $indexContent
        ];

        // 生成 add.html
        $addContent = $this->generateAddView($module, $controllerLower, $fields, $description);
        $viewFiles[] = [
            'name' => 'add.html',
            'content' => $addContent
        ];

        return $viewFiles;
    }

    /**
     * 生成index视图
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function generateIndexView(string $module, string $controller, array $fields = [], string $description = ''): string
    {
        $content = "<!-- {$description}列表页面 -->
<div class=\"layuimini-container\">
    <div class=\"layuimini-main\">
        <table class=\"layui-table\" id=\"{$controller}-table\" lay-filter=\"{$controller}-table\"></table>
    </div>
</div>";

        return $content;
    }

    /**
     * 生成add视图
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param array $fields 字段信息
     * @param string $description 描述
     * @return string
     */
    private function generateAddView(string $module, string $controller, array $fields = [], string $description = ''): string
    {
        $content = "<!-- {$description}添加/编辑页面 -->
<form class=\"layui-form layuimini-form\" id=\"{$controller}-form\">
";

        foreach ($fields as $field) {
            if (in_array($field['name'], ['id', 'create_time', 'update_time'])) {
                continue;
            }
            $fieldName = $field['name'];
            $fieldComment = $field['comment'] ?? $fieldName;
            $fieldType = $field['type'] ?? 'varchar';

            // 根据字段类型生成不同的表单控件
            if (strpos($fieldType, 'text') !== false || strpos($fieldType, 'longtext') !== false) {
                // 文本域
                $content .= "    <div class=\"layui-form-item layui-form-text\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <textarea name=\"{$fieldName}\" placeholder=\"请输入{$fieldComment}\" class=\"layui-textarea\"></textarea>\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif (strpos($fieldType, 'tinyint') !== false && in_array($fieldName, ['status', 'is_show', 'is_enable'])) {
                // 开关控件 - 状态类字段
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <input type=\"checkbox\" name=\"{$fieldName}\" lay-skin=\"switch\" lay-text=\"启用|禁用\">\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif ($this->shouldUseSelect($fieldName, $fieldComment, $fieldType)) {
                // 下拉选择框 - 根据字段名称、注释、类型判断
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <select name=\"{$fieldName}\" lay-verify=\"required\">\n";
                $content .= "                <option value=\"\">请选择{$fieldComment}</option>\n";
                $content .= "            </select>\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif (strpos($fieldType, 'decimal') !== false || strpos($fieldType, 'float') !== false) {
                // 数字输入框
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <input type=\"number\" name=\"{$fieldName}\" placeholder=\"请输入{$fieldComment}\" class=\"layui-input\">\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif ($fieldName === 'password') {
                // 密码框
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <input type=\"password\" name=\"{$fieldName}\" placeholder=\"请输入{$fieldComment}\" class=\"layui-input\">\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif ($fieldName === 'email') {
                // 邮箱输入框
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <input type=\"email\" name=\"{$fieldName}\" placeholder=\"请输入{$fieldComment}\" class=\"layui-input\">\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif ($fieldName === 'tel' || $fieldName === 'phone' || $fieldName === 'mobile') {
                // 电话号码输入框
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <input type=\"tel\" name=\"{$fieldName}\" placeholder=\"请输入{$fieldComment}\" class=\"layui-input\">\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif (strpos($fieldName, 'time') !== false || strpos($fieldName, 'date') !== false) {
                // 日期时间选择器
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <input type=\"text\" name=\"{$fieldName}\" id=\"{$fieldName}\" placeholder=\"请选择{$fieldComment}\" class=\"layui-input\">\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif (strpos($fieldName, 'image') !== false || strpos($fieldName, 'avatar') !== false || strpos($fieldName, 'photo') !== false) {
                // 图片上传
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <button type=\"button\" class=\"layui-btn\" id=\"upload-{$fieldName}\"><i class=\"layui-icon\">&#xe67c;</i>{$fieldComment}</button>\n";
                $content .= "            <div class=\"layui-upload-list\" id=\"{$fieldName}-list\"></div>\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } elseif (strpos($fieldName, 'file') !== false) {
                // 文件上传
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <button type=\"button\" class=\"layui-btn layui-btn-normal\" id=\"upload-{$fieldName}\"><i class=\"layui-icon\">&#xe67c;</i>{$fieldComment}</button>\n";
                $content .= "            <div class=\"layui-upload-list\" id=\"{$fieldName}-list\"></div>\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            } else {
                // 默认文本输入框
                $content .= "    <div class=\"layui-form-item\">\n";
                $content .= "        <label class=\"layui-form-label\">{$fieldComment}</label>\n";
                $content .= "        <div class=\"layui-input-block\">\n";
                $content .= "            <input type=\"text\" name=\"{$fieldName}\" placeholder=\"请输入{$fieldComment}\" class=\"layui-input\" value=\"\">\n";
                $content .= "        </div>\n";
                $content .= "    </div>\n";
            }
        }

        // 添加提交按钮
        $content .= "    <div class=\"layui-form-item\">\n";
        $content .= "        <div class=\"layui-input-block\">\n";
        $content .= "            <button class=\"layui-btn\" lay-submit lay-filter=\"*\">立即提交</button>\n";
        $content .= "        </div>\n";
        $content .= "    </div>\n";
        $content .= "</form>\n";

        return $content;
    }

    /**
     * 判断是否应该使用下拉选择框
     * @param string $fieldName 字段名称
     * @param string $fieldComment 字段注释
     * @param string $fieldType 字段类型
     * @return bool
     */
    private function shouldUseSelect(string $fieldName, string $fieldComment, string $fieldType): bool
    {
        // 1. 根据字段注释判断
        $commentKeywords = [
            '选择', '类型', '分类', '等级', '级别', '状态', '方式', '模式', '种类',
            '下拉', '列表', '枚举', '选项', '菜单', '角色', '权限', '部门',
            '省份', '城市', '地区', '国家', '行业', '职业', '学历', '婚姻',
            '1:', '2:', '3:', '|', '，', ',', '/', '\\', // 包含选项分隔符的注释
        ];

        foreach ($commentKeywords as $keyword) {
            if (strpos($fieldComment, $keyword) !== false) {
                return true;
            }
        }

        // 2. 根据字段名称判断
        $nameKeywords = [
            '_id', 'type', 'category', 'level', 'grade', 'status', 'state',
            'kind', 'class', 'group', 'dept', 'role', 'auth', 'mode',
            'method', 'way', 'style', 'format', 'gender', 'sex',
            'province', 'city', 'area', 'region', 'country', 'nation'
        ];

        foreach ($nameKeywords as $keyword) {
            if (strpos($fieldName, $keyword) !== false) {
                return true;
            }
        }

        // 3. 根据字段类型判断
        if (strpos($fieldType, 'enum') !== false) {
            return true;
        }

        // 4. 小范围的整数字段可能是选择字段
        if (strpos($fieldType, 'tinyint') !== false || strpos($fieldType, 'smallint') !== false) {
            // 排除明确的布尔字段（is_开头的字段）
            if (strpos($fieldName, 'is_') !== 0 && !in_array($fieldName, ['status', 'sort', 'weigh', 'createtime', 'updatetime'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 处理插件管理
     * @param string $action 操作类型（create/install/uninstall/enable/disable）
     * @param string $addonName 插件名称
     * @param array $options 其他选项
     * @return array
     */
    public function handleAddon(string $action, string $addonName = '', array $options = []): array
    {
        try {
            // 暂时返回不支持的信息
            return [
                'success' => false,
                'error' => "插件管理功能暂未完全实现",
                'data' => [
                    'action' => $action,
                    'addon_name' => $addonName,
                    'options' => $options
                ]
            ];

        } catch (Exception $e) {
            Log::error('插件管理错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理CRUD生成
     * @param string $tableName 表名
     * @param string $module 模块名（admin/frontend/api）
     * @param array $fields 字段信息
     * @param string $description 描述
     * @param array $options 其他选项
     * @return array
     */
    public function handleCurd(string $tableName, string $module = 'admin', array $fields = [], string $description = '', array $options = []): array
    {
        try {
            // 暂时返回不支持的信息
            return [
                'success' => false,
                'error' => "CRUD生成功能暂未完全实现",
                'data' => [
                    'table' => $tableName,
                    'module' => $module,
                    'fields' => $fields,
                    'description' => $description,
                    'options' => $options
                ]
            ];

        } catch (Exception $e) {
            Log::error('CRUD生成错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理菜单管理
     * @param string $action 操作类型（create/delete）
     * @param array $menuData 菜单数据
     * @param array $options 其他选项
     * @return array
     */
    public function handleMenu(string $action, array $menuData = [], array $options = []): array
    {
        try {
            // 暂时返回不支持的信息
            return [
                'success' => false,
                'error' => "菜单管理功能暂未完全实现",
                'data' => [
                    'action' => $action,
                    'menu_data' => $menuData,
                    'options' => $options
                ]
            ];

        } catch (Exception $e) {
            Log::error('菜单管理错误: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理其他操作
     * @param string $prompt 自然语言描述
     * @return array
     */
    private function handleOtherOperation(string $prompt): array
    {
        $lowerPrompt = strtolower($prompt);
        $results = [];

        // 处理数据库查询操作
        if (strpos($lowerPrompt, '查询') !== false || strpos($lowerPrompt, 'select') !== false) {
            $results['webman_db_query'] = [
                'success' => true,
                'message' => '检测到数据库查询操作',
                'suggestion' => '请使用 mcp-db-query 工具执行数据库查询'
            ];
        }

        // 处理系统配置操作
        if (strpos($lowerPrompt, '配置') !== false || strpos($lowerPrompt, 'config') !== false) {
            $results['webman_sys_config'] = [
                'success' => true,
                'message' => '检测到系统配置操作',
                'suggestion' => '请使用 mcp-sys-config 工具获取系统配置'
            ];
        }

        // 处理日志操作
        if (strpos($lowerPrompt, '日志') !== false || strpos($lowerPrompt, 'log') !== false) {
            $results['webman_write_log'] = [
                'success' => true,
                'message' => '检测到日志操作',
                'suggestion' => '请使用 webman-write-log 工具写入系统日志'
            ];
        }

        // 处理文件操作
        if (strpos($lowerPrompt, '文件') !== false || strpos($lowerPrompt, 'file') !== false) {
            $results['webman_file_operation'] = [
                'success' => true,
                'message' => '检测到文件操作',
                'suggestion' => '请使用 webman-file-operation 工具进行文件读写操作'
            ];
        }

        // 处理用户管理操作
        if (strpos($lowerPrompt, '用户') !== false || strpos($lowerPrompt, 'user') !== false) {
            $results['webman_user_management'] = [
                'success' => true,
                'message' => '检测到用户管理操作',
                'suggestion' => '请使用 webman-user-management 工具进行用户管理'
            ];
        }

        // 处理系统信息操作
        if (strpos($lowerPrompt, '系统信息') !== false || strpos($lowerPrompt, 'system') !== false) {
            $results['webman_system_info'] = [
                'success' => true,
                'message' => '检测到系统信息操作',
                'suggestion' => '请使用 webman-system-info 工具获取系统运行信息'
            ];
        }

        // 处理控制器生成操作
        if (strpos($lowerPrompt, '控制器') !== false || strpos($lowerPrompt, 'controller') !== false) {
            $results['webman_controller'] = [
                'success' => true,
                'message' => '检测到控制器生成操作',
                'suggestion' => '请使用 mcp-controller 工具生成控制器文件'
            ];
        }

        // 处理模型生成操作
        if (strpos($lowerPrompt, '模型') !== false || strpos($lowerPrompt, 'model') !== false) {
            $results['webman_model'] = [
                'success' => true,
                'message' => '检测到模型生成操作',
                'suggestion' => '请使用 mcp-model 工具生成模型文件'
            ];
        }

        // 处理数据库表创建操作
        if (strpos($lowerPrompt, '数据库表') !== false || strpos($lowerPrompt, 'table') !== false) {
            $results['webman_table'] = [
                'success' => true,
                'message' => '检测到数据库表创建操作',
                'suggestion' => '请使用 mcp-table 工具创建数据库表'
            ];
        }

        // 处理插件操作
        if (strpos($lowerPrompt, '插件') !== false || strpos($lowerPrompt, 'addon') !== false) {
            $results['webman_addon'] = [
                'success' => true,
                'message' => '检测到插件操作',
                'suggestion' => '请使用 mcp-addon 工具进行插件管理'
            ];
        }

        // 处理菜单操作
        if (strpos($lowerPrompt, '菜单') !== false || strpos($lowerPrompt, 'menu') !== false) {
            $results['webman_menu'] = [
                'success' => true,
                'message' => '检测到菜单操作',
                'suggestion' => '请使用 mcp-menu 工具进行菜单管理'
            ];
        }

        // 处理CRUD操作
        if (strpos($lowerPrompt, 'crud') !== false || strpos($lowerPrompt, '增删改查') !== false) {
            $results['webman_curd'] = [
                'success' => true,
                'message' => '检测到CRUD操作',
                'suggestion' => '请使用 mcp-curd 工具生成CRUD模块'
            ];
        }

        // 处理JS文件生成操作
        if (strpos($lowerPrompt, 'js') !== false || strpos($lowerPrompt, 'javascript') !== false || strpos($lowerPrompt, '前端') !== false) {
            $results['webman_js'] = [
                'success' => true,
                'message' => '检测到JS文件生成操作',
                'suggestion' => '请使用 mcp-js 工具生成前端JS文件'
            ];
        }

        // 处理API接口生成操作
        if (strpos($lowerPrompt, 'api') !== false || strpos($lowerPrompt, '接口') !== false) {
            $results['webman_api'] = [
                'success' => true,
                'message' => '检测到API接口生成操作',
                'suggestion' => '请使用 mcp-api 工具生成API接口文件'
            ];
        }

        // 处理视图文件生成操作
        if (strpos($lowerPrompt, '视图') !== false || strpos($lowerPrompt, 'view') !== false || strpos($lowerPrompt, '页面') !== false) {
            $results['webman_view'] = [
                'success' => true,
                'message' => '检测到视图文件生成操作',
                'suggestion' => '请使用 mcp-view 工具生成视图文件'
            ];
        }

        // 如果没有匹配到任何操作，返回通用建议
        if (empty($results)) {
            $results['general'] = [
                'success' => false,
                'message' => '未能识别具体的操作类型',
                'suggestion' => '请尝试以下操作：' . "\n" .
                    '- 创建数据库表：包含表名和字段信息' . "\n" .
                    '- 生成控制器：指定模块和控制器名称' . "\n" .
                    '- 生成模型：指定模型名称和字段信息' . "\n" .
                    '- 生成JS文件：指定模块和控制器名称' . "\n" .
                    '- 生成API接口：指定模块和控制器名称' . "\n" .
                    '- 生成视图文件：指定模块和控制器名称' . "\n" .
                    '- 创建插件：指定插件名称' . "\n" .
                    '- 创建菜单：指定菜单信息' . "\n" .
                    '- 生成CRUD模块：指定表名和字段信息' . "\n" .
                    '- 数据库查询：使用SELECT语句' . "\n" .
                    '- 系统配置：获取系统配置信息' . "\n" .
                    '- 文件操作：进行文件读写操作' . "\n" .
                    '- 用户管理：进行用户相关操作' . "\n" .
                    '- 系统信息：获取系统运行信息'
            ];
        }

        return $results;
    }

    /**
     * 执行内置命令
     * @param string $command 命令名称
     * @param array $params 命令参数 (可选)
     * @param array $options 命令选项 (可选)
     * @return array
     */
    public function handleThinkCommand(string $command, array $params = [], array $options = []): array
    {
        try {
            // 验证命令是否为安全的内置命令
            $allowedCommands = [
                // 基础命令
                'list', 'help', 'version', 'config',
                // 路由命令
                'route:list',
                // 服务发现命令
                'service:discover',
                // vendor发布命令
                'vendor:publish',
                // 自定义命令
                'make:command', 'make:controller', 'make:middleware', 'make:service', 'make:validate',
                'make:listener', 'make:event', 'make:model', 'make:provider', 'make:job', 'make:mail',
                'make:notification', 'make:request', 'make:resource', 'make:observer', 'make:policy',
                'make:factory', 'make:migration', 'make:seeder',
            ];

            if (!in_array($command, $allowedCommands)) {
                return [
                    'success' => false,
                    'message' => '不支持的命令或命令不安全',
                    'allowed_commands' => $allowedCommands
                ];
            }

            // 构建完整的命令
            $fullCommand = 'php mcp ' . $command;

            // 添加参数
            if (!empty($params)) {
                foreach ($params as $param) {
                    $fullCommand .= ' ' . escapeshellarg($param);
                }
            }

            // 添加选项
            if (!empty($options)) {
                foreach ($options as $option => $value) {
                    if (is_numeric($option)) {
                        // 简单选项，如 --verbose
                        $fullCommand .= ' --' . $value;
                    } else {
                        // 带值选项，如 --name=value
                        $fullCommand .= ' --' . $option . '=' . escapeshellarg($value);
                    }
                }
            }

            // 切换到项目根目录执行命令
            $rootPath = base_path();
            $originalDir = getcwd();

            if ($originalDir !== $rootPath) {
                chdir($rootPath);
            }

            // 执行命令并捕获输出
            $output = [];
            $returnCode = 0;
            exec($fullCommand . ' 2>&1', $output, $returnCode);

            // 恢复原始目录
            if ($originalDir !== $rootPath) {
                chdir($originalDir);
            }

            // 记录命令执行日志
            $this->handleWriteLog("执行命令: {$fullCommand}", 'info', [
                'return_code' => $returnCode,
                'output_lines' => count($output)
            ]);

            return [
                'success' => $returnCode === 0,
                'message' => $returnCode === 0 ? '命令执行成功' : '命令执行失败',
                'command' => $fullCommand,
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
                'output_lines' => $output
            ];

        } catch (Exception $e) {
            $this->handleWriteLog('执行命令时出错: ' . $e->getMessage(), 'error', [
                'command' => $command,
                'params' => $params,
                'options' => $options,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => '命令执行异常: ' . $e->getMessage(),
                'command' => $command,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理日志写入
     * @param string $message 日志消息
     * @param string $level 日志级别
     * @param array $context 上下文数据
     * @return string
     */
    public function handleWriteLog(string $message, string $level = 'info', array $context = []): string
    {
        try {
            if (empty($message)) {
                throw new Exception('日志消息不能为空');
            }

            // 支持的日志级别
            $allowedLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

            if (!in_array($level, $allowedLevels)) {
                $level = 'info';
            }

            Log::log($level, $message, $context);

            return "日志记录成功 [级别: {$level}]";

        } catch (Exception $e) {
            Log::error('MCP日志写入错误: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 初始化MCP服务
     */
    protected function initialize()
    {
        parent::initialize();
        $this->logger = new NullLogger(); // 默认使用空日志记录器

        // 读取MCP配置文件
        $this->loadWebmanMcpConfig();

        return $this;
    }

    /**
     * 加载MCP配置
     */
    protected function loadWebmanMcpConfig()
    {
        try {
            // 对于长时间运行的服务器，设置为无限制
            ini_set('max_execution_time', 0);
            set_time_limit(0);

            // 忽略用户中断，保持服务器运行
            ignore_user_abort(true);

            $webmanMcpConfig = config('mcp', []);

            // 设置超时配置
            if (isset($webmanMcpConfig['timeout']) && $webmanMcpConfig['timeout'] > 0) {
                $this->timeout = $webmanMcpConfig['timeout'];
            }

            if (isset($webmanMcpConfig['connect_timeout']) && $webmanMcpConfig['connect_timeout'] > 0) {
                $this->connectTimeout = $webmanMcpConfig['connect_timeout'];
            }

            if (isset($webmanMcpConfig['read_timeout']) && $webmanMcpConfig['read_timeout'] > 0) {
                $this->readTimeout = $webmanMcpConfig['read_timeout'];
            }

            // 设置重试配置
            if (isset($webmanMcpConfig['retry_attempts']) && $webmanMcpConfig['retry_attempts'] > 0) {
                $this->retryAttempts = $webmanMcpConfig['retry_attempts'];
            }

            if (isset($webmanMcpConfig['retry_delay']) && $webmanMcpConfig['retry_delay'] > 0) {
                $this->retryDelay = $webmanMcpConfig['retry_delay'];
            }

            // 设置调试模式
            if (isset($webmanMcpConfig['debug'])) {
                $this->debug = $webmanMcpConfig['debug'];
            }

            // 设置内存限制
            if (isset($webmanMcpConfig['memory_limit'])) {
                ini_set('memory_limit', $webmanMcpConfig['memory_limit']);
            }

            // 设置缓冲区大小
            if (isset($webmanMcpConfig['buffer_size'])) {
                $this->bufferSize = $webmanMcpConfig['buffer_size'];
            }

            // 设置心跳配置
            if (isset($webmanMcpConfig['heartbeat_enabled'])) {
                $this->heartbeatEnabled = $webmanMcpConfig['heartbeat_enabled'];
            }

            if (isset($webmanMcpConfig['heartbeat_interval'])) {
                $this->heartbeatInterval = $webmanMcpConfig['heartbeat_interval'];
            }

            Log::info('MCP配置加载成功', [
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'read_timeout' => $this->readTimeout,
                'retry_attempts' => $this->retryAttempts,
                'retry_delay' => $this->retryDelay,
                'heartbeat_enabled' => $this->heartbeatEnabled ?? false,
                'heartbeat_interval' => $this->heartbeatInterval ?? 30
            ]);

        } catch (Exception $e) {
            Log::warning('MCP配置加载失败，使用默认配置: ' . $e->getMessage());
        }
    }

    /**
     * 带重试机制的操作执行
     */
    protected function executeWithRetry(callable $operation, string $operationName = 'operation')
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->retryAttempts) {
            try {
                $attempts++;
                Log::info("执行{$operationName}，第{$attempts}次尝试");

                $result = $operation();

                if ($attempts > 1) {
                    Log::info("{$operationName}在第{$attempts}次尝试后成功");
                }

                return $result;

            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("{$operationName}第{$attempts}次尝试失败: " . $e->getMessage());

                if ($attempts < $this->retryAttempts) {
                    $delay = $this->retryDelay * pow(1.5, $attempts - 1); // 指数退避
                    Log::info("等待{$delay}ms后重试");
                    usleep($delay * 1000);
                }
            }
        }

        Log::error("{$operationName}在{$this->retryAttempts}次尝试后仍然失败");
        throw $lastException;
    }
}