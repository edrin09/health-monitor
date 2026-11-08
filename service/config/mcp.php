<?php
/**
 * MCP服务配置文件
 * 用于配置MCP(Model Context Protocol)服务的各种参数
 * ============================================================================
 */

return [
    // 传输协议配置
    'transport' => env('MCP_TRANSPORT', 'sse'), // 传输协议: http, sse
    'host' => env('MCP_HOST', '127.0.0.1'),    // 监听主机
    'port' => (int)env('MCP_PORT', 8082),      // 监听端口
    'path' => env('MCP_PATH', 'mcp'),          // 访问路径

    // 超时配置
    'timeout' => (int)env('MCP_TIMEOUT', 600000),         // 总超时时间
    'connect_timeout' => (int)env('MCP_CONNECT_TIMEOUT', 30000), // 连接超时
    'read_timeout' => (int)env('MCP_READ_TIMEOUT', 30000),       // 读取超时

    // 重试配置
    'retry_attempts' => (int)env('MCP_RETRY_ATTEMPTS', 3),  // 重试次数
    'retry_delay' => (int)env('MCP_RETRY_DELAY', 1000),     // 重试延迟（毫秒）

    // 服务配置
    'debug' => env('MCP_DEBUG', true),                    // 调试模式
    'memory_limit' => env('MCP_MEMORY_LIMIT', '512M'),     // 内存限制
    'buffer_size' => (int)env('MCP_BUFFER_SIZE', 8192),    // 缓冲区大小
    'heartbeat_enabled' => env('MCP_HEARTBEAT_ENABLED', true),  // 启用心跳
    'heartbeat_interval' => (int)env('MCP_HEARTBEAT_INTERVAL', 30), // 心跳间隔（秒）

    // 功能开关配置
    'features' => [
        // 数据库功能
        'db' => [
            'enable' => env('MCP_DB_ENABLE', true),  // 是否启用数据库功能
            'type' => env('MCP_DB_TYPE', 'mysql'),   // 数据库类型
            'connection' => env('MCP_DB_CONNECTION', 'mysql'), // 连接名称
        ],

        // 日志功能
        'log' => [
            'enable' => env('MCP_LOG_ENABLE', true),
            'level' => env('MCP_LOG_LEVEL', 'info'),
            'file' => env('MCP_LOG_FILE', runtime_path() . '/logs/mcp.log'),
        ],

        // 文件操作功能
        'file' => [
            'enable' => env('MCP_FILE_ENABLE', true),
            'allowed_paths' => [
                runtime_path(),
                public_path() . '/storage/',
                config_path(),
            ],
        ],
    ],
];