<?php
/**
 * Enterprise Configuration Management
 */

return [
    // Application Settings
    'app' => [
        'name' => 'Admini Enterprise',
        'version' => '2.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => $_ENV['APP_DEBUG'] ?? false,
        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
        'locale' => $_ENV['APP_LOCALE'] ?? 'en',
        'encryption_key' => $_ENV['APP_KEY'] ?? 'base64:' . base64_encode(random_bytes(32))
    ],
    
    // Database Configuration
    'database' => [
        'default' => $_ENV['DB_CONNECTION'] ?? 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? 3306,
                'database' => $_ENV['DB_DATABASE'] ?? 'admini',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            ],
            'read' => [
                'driver' => 'mysql',
                'host' => $_ENV['DB_READ_HOST'] ?? $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_READ_PORT'] ?? $_ENV['DB_PORT'] ?? 3306,
                'database' => $_ENV['DB_DATABASE'] ?? 'admini',
                'username' => $_ENV['DB_READ_USERNAME'] ?? $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_READ_PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci'
            ]
        ]
    ],
    
    // Redis Configuration
    'redis' => [
        'default' => [
            'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DB'] ?? 0,
            'timeout' => 5,
            'read_timeout' => 5
        ],
        'cache' => [
            'host' => $_ENV['REDIS_CACHE_HOST'] ?? $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => $_ENV['REDIS_CACHE_PORT'] ?? $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_CACHE_PASSWORD'] ?? $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_CACHE_DB'] ?? 1
        ],
        'sessions' => [
            'host' => $_ENV['REDIS_SESSION_HOST'] ?? $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => $_ENV['REDIS_SESSION_PORT'] ?? $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_SESSION_PASSWORD'] ?? $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_SESSION_DB'] ?? 2
        ]
    ],
    
    // Message Queue Configuration
    'queue' => [
        'default' => $_ENV['QUEUE_DRIVER'] ?? 'redis',
        'connections' => [
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 90,
                'block_for' => null
            ],
            'rabbitmq' => [
                'driver' => 'rabbitmq',
                'host' => $_ENV['RABBITMQ_HOST'] ?? 'localhost',
                'port' => $_ENV['RABBITMQ_PORT'] ?? 5672,
                'username' => $_ENV['RABBITMQ_USERNAME'] ?? 'guest',
                'password' => $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
                'vhost' => $_ENV['RABBITMQ_VHOST'] ?? '/',
                'exchange' => 'admini',
                'queue' => 'default'
            ]
        ],
        'workers' => [
            'max_workers' => $_ENV['QUEUE_MAX_WORKERS'] ?? 10,
            'worker_timeout' => $_ENV['QUEUE_WORKER_TIMEOUT'] ?? 300,
            'memory_limit' => $_ENV['QUEUE_MEMORY_LIMIT'] ?? '256M'
        ]
    ],
    
    // Security Configuration
    'security' => [
        'jwt' => [
            'secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key',
            'algorithm' => 'HS256',
            'expires_in' => 3600, // 1 hour
            'refresh_expires_in' => 604800 // 7 days
        ],
        'rate_limiting' => [
            'enabled' => $_ENV['RATE_LIMITING_ENABLED'] ?? true,
            'default_limit' => $_ENV['RATE_LIMIT_DEFAULT'] ?? 1000,
            'default_window' => $_ENV['RATE_LIMIT_WINDOW'] ?? 3600,
            'strategies' => [
                'token_bucket' => [
                    'capacity' => 100,
                    'refill_rate' => 10
                ],
                'sliding_window' => [
                    'limit' => 1000,
                    'window' => 3600
                ]
            ]
        ],
        'threat_detection' => [
            'enabled' => $_ENV['THREAT_DETECTION_ENABLED'] ?? true,
            'sensitivity' => $_ENV['THREAT_DETECTION_SENSITIVITY'] ?? 'medium',
            'auto_block' => $_ENV['THREAT_AUTO_BLOCK'] ?? true,
            'block_duration' => $_ENV['THREAT_BLOCK_DURATION'] ?? 3600
        ],
        'vulnerability_scanning' => [
            'enabled' => $_ENV['VULN_SCANNING_ENABLED'] ?? true,
            'schedule' => $_ENV['VULN_SCAN_SCHEDULE'] ?? '0 2 * * *', // Daily at 2 AM
            'scan_timeout' => $_ENV['VULN_SCAN_TIMEOUT'] ?? 1800
        ]
    ],
    
    // Monitoring Configuration
    'monitoring' => [
        'enabled' => $_ENV['MONITORING_ENABLED'] ?? true,
        'metrics_retention' => $_ENV['METRICS_RETENTION'] ?? 2592000, // 30 days
        'collection_interval' => $_ENV['METRICS_INTERVAL'] ?? 30, // seconds
        'alert_channels' => [
            'email' => [
                'enabled' => $_ENV['ALERT_EMAIL_ENABLED'] ?? true,
                'recipients' => explode(',', $_ENV['ALERT_EMAIL_RECIPIENTS'] ?? 'admin@admini.com')
            ],
            'webhook' => [
                'enabled' => $_ENV['ALERT_WEBHOOK_ENABLED'] ?? false,
                'url' => $_ENV['ALERT_WEBHOOK_URL'] ?? null
            ]
        ],
        'dashboards' => [
            'auto_refresh' => $_ENV['DASHBOARD_AUTO_REFRESH'] ?? 30,
            'data_points' => $_ENV['DASHBOARD_DATA_POINTS'] ?? 100
        ]
    ],
    
    // Analytics Configuration
    'analytics' => [
        'enabled' => $_ENV['ANALYTICS_ENABLED'] ?? true,
        'data_retention' => $_ENV['ANALYTICS_RETENTION'] ?? 7776000, // 90 days
        'real_time_enabled' => $_ENV['ANALYTICS_REALTIME'] ?? true,
        'batch_size' => $_ENV['ANALYTICS_BATCH_SIZE'] ?? 1000,
        'processing_interval' => $_ENV['ANALYTICS_INTERVAL'] ?? 300, // 5 minutes
        'machine_learning' => [
            'enabled' => $_ENV['ML_ENABLED'] ?? false,
            'model_path' => $_ENV['ML_MODEL_PATH'] ?? '/models',
            'training_schedule' => $_ENV['ML_TRAINING_SCHEDULE'] ?? '0 0 * * 0' // Weekly
        ]
    ],
    
    // Integration Framework
    'integrations' => [
        'enabled' => $_ENV['INTEGRATIONS_ENABLED'] ?? true,
        'webhook_timeout' => $_ENV['INTEGRATION_WEBHOOK_TIMEOUT'] ?? 30,
        'sync_batch_size' => $_ENV['INTEGRATION_BATCH_SIZE'] ?? 100,
        'retry_attempts' => $_ENV['INTEGRATION_RETRY_ATTEMPTS'] ?? 3,
        'providers' => [
            'salesforce' => [
                'enabled' => $_ENV['SALESFORCE_ENABLED'] ?? false,
                'client_id' => $_ENV['SALESFORCE_CLIENT_ID'] ?? null,
                'client_secret' => $_ENV['SALESFORCE_CLIENT_SECRET'] ?? null,
                'instance_url' => $_ENV['SALESFORCE_INSTANCE_URL'] ?? null
            ],
            'stripe' => [
                'enabled' => $_ENV['STRIPE_ENABLED'] ?? false,
                'public_key' => $_ENV['STRIPE_PUBLIC_KEY'] ?? null,
                'secret_key' => $_ENV['STRIPE_SECRET_KEY'] ?? null,
                'webhook_secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null
            ]
        ]
    ],
    
    // Workflow Engine
    'workflows' => [
        'enabled' => $_ENV['WORKFLOWS_ENABLED'] ?? true,
        'execution_timeout' => $_ENV['WORKFLOW_TIMEOUT'] ?? 3600,
        'max_concurrent' => $_ENV['WORKFLOW_MAX_CONCURRENT'] ?? 10,
        'log_retention' => $_ENV['WORKFLOW_LOG_RETENTION'] ?? 2592000, // 30 days
        'node_types' => [
            'start', 'end', 'condition', 'action', 'action_group',
            'delay', 'loop', 'parallel', 'merge', 'webhook',
            'email', 'notification', 'database', 'api_call',
            'script', 'approval', 'user_task', 'data_transform',
            'file_operation'
        ]
    ],
    
    // Mail Configuration
    'mail' => [
        'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port' => $_ENV['MAIL_PORT'] ?? 587,
        'username' => $_ENV['MAIL_USERNAME'] ?? null,
        'password' => $_ENV['MAIL_PASSWORD'] ?? null,
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'admin@admini.com',
            'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Admini Enterprise'
        ],
        'queue' => [
            'enabled' => $_ENV['MAIL_QUEUE_ENABLED'] ?? true,
            'queue_name' => $_ENV['MAIL_QUEUE_NAME'] ?? 'emails',
            'retry_attempts' => $_ENV['MAIL_RETRY_ATTEMPTS'] ?? 3
        ]
    ],
    
    // File Storage Configuration
    'storage' => [
        'default' => $_ENV['STORAGE_DRIVER'] ?? 'local',
        'disks' => [
            'local' => [
                'driver' => 'local',
                'root' => $_ENV['STORAGE_LOCAL_ROOT'] ?? '/var/www/storage'
            ],
            's3' => [
                'driver' => 's3',
                'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
                'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
                'bucket' => $_ENV['AWS_BUCKET'] ?? null,
                'endpoint' => $_ENV['AWS_ENDPOINT'] ?? null
            ],
            'gcs' => [
                'driver' => 'gcs',
                'project_id' => $_ENV['GOOGLE_CLOUD_PROJECT_ID'] ?? null,
                'key_file' => $_ENV['GOOGLE_CLOUD_KEY_FILE'] ?? null,
                'bucket' => $_ENV['GOOGLE_CLOUD_BUCKET'] ?? null
            ]
        ]
    ],
    
    // Backup Configuration
    'backup' => [
        'enabled' => $_ENV['BACKUP_ENABLED'] ?? true,
        'schedule' => $_ENV['BACKUP_SCHEDULE'] ?? '0 2 * * *', // Daily at 2 AM
        'retention' => $_ENV['BACKUP_RETENTION'] ?? 30, // days
        'compression' => $_ENV['BACKUP_COMPRESSION'] ?? 'gzip',
        'destinations' => [
            'local' => [
                'enabled' => true,
                'path' => $_ENV['BACKUP_LOCAL_PATH'] ?? '/backups'
            ],
            's3' => [
                'enabled' => $_ENV['BACKUP_S3_ENABLED'] ?? false,
                'bucket' => $_ENV['BACKUP_S3_BUCKET'] ?? null,
                'prefix' => $_ENV['BACKUP_S3_PREFIX'] ?? 'admini-backups'
            ]
        ]
    ],
    
    // Performance Configuration
    'performance' => [
        'caching' => [
            'enabled' => $_ENV['CACHE_ENABLED'] ?? true,
            'driver' => $_ENV['CACHE_DRIVER'] ?? 'redis',
            'ttl' => $_ENV['CACHE_TTL'] ?? 3600,
            'prefix' => $_ENV['CACHE_PREFIX'] ?? 'admini'
        ],
        'cdn' => [
            'enabled' => $_ENV['CDN_ENABLED'] ?? false,
            'provider' => $_ENV['CDN_PROVIDER'] ?? 'cloudflare',
            'url' => $_ENV['CDN_URL'] ?? null
        ],
        'compression' => [
            'enabled' => $_ENV['COMPRESSION_ENABLED'] ?? true,
            'level' => $_ENV['COMPRESSION_LEVEL'] ?? 6,
            'types' => ['text/html', 'text/css', 'text/javascript', 'application/json']
        ]
    ],
    
    // API Configuration
    'api' => [
        'version' => 'v1',
        'prefix' => 'api',
        'rate_limit' => [
            'enabled' => $_ENV['API_RATE_LIMIT_ENABLED'] ?? true,
            'requests_per_minute' => $_ENV['API_RATE_LIMIT'] ?? 100
        ],
        'authentication' => [
            'required' => $_ENV['API_AUTH_REQUIRED'] ?? true,
            'methods' => ['bearer', 'api_key']
        ],
        'versioning' => [
            'enabled' => $_ENV['API_VERSIONING_ENABLED'] ?? true,
            'supported_versions' => ['v1', 'v2']
        ]
    ],
    
    // Logging Configuration
    'logging' => [
        'default' => $_ENV['LOG_CHANNEL'] ?? 'stack',
        'channels' => [
            'stack' => [
                'driver' => 'stack',
                'channels' => ['single', 'daily'],
                'ignore_exceptions' => false
            ],
            'single' => [
                'driver' => 'single',
                'path' => $_ENV['LOG_SINGLE_PATH'] ?? '/var/log/admini/admini.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'debug'
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => $_ENV['LOG_DAILY_PATH'] ?? '/var/log/admini/admini.log',
                'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
                'days' => $_ENV['LOG_DAILY_DAYS'] ?? 14
            ],
            'syslog' => [
                'driver' => 'syslog',
                'level' => $_ENV['LOG_LEVEL'] ?? 'debug'
            ]
        ]
    ],
    
    // Session Configuration
    'session' => [
        'driver' => $_ENV['SESSION_DRIVER'] ?? 'redis',
        'lifetime' => $_ENV['SESSION_LIFETIME'] ?? 120,
        'expire_on_close' => $_ENV['SESSION_EXPIRE_ON_CLOSE'] ?? false,
        'encrypt' => $_ENV['SESSION_ENCRYPT'] ?? true,
        'cookie' => $_ENV['SESSION_COOKIE'] ?? 'admini_session',
        'path' => $_ENV['SESSION_PATH'] ?? '/',
        'domain' => $_ENV['SESSION_DOMAIN'] ?? null,
        'secure' => $_ENV['SESSION_SECURE_COOKIE'] ?? false,
        'http_only' => true,
        'same_site' => 'lax'
    ],
    
    // Enterprise Features
    'enterprise' => [
        'multi_tenant' => $_ENV['MULTI_TENANT_ENABLED'] ?? false,
        'white_label' => $_ENV['WHITE_LABEL_ENABLED'] ?? false,
        'advanced_analytics' => $_ENV['ADVANCED_ANALYTICS_ENABLED'] ?? true,
        'custom_branding' => $_ENV['CUSTOM_BRANDING_ENABLED'] ?? false,
        'sso' => [
            'enabled' => $_ENV['SSO_ENABLED'] ?? false,
            'provider' => $_ENV['SSO_PROVIDER'] ?? 'saml',
            'auto_provision' => $_ENV['SSO_AUTO_PROVISION'] ?? true
        ],
        'compliance' => [
            'gdpr' => $_ENV['GDPR_COMPLIANCE'] ?? true,
            'hipaa' => $_ENV['HIPAA_COMPLIANCE'] ?? false,
            'sox' => $_ENV['SOX_COMPLIANCE'] ?? false
        ]
    ]
];