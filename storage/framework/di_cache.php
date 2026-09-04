<?php
// ADVANCED OPTIMIZATION: Compiled DI Graph to bypass Reflection.
return array (
  'Oshim\\Database\\DatabaseServiceProvider' => 
  array (
  ),
  'Oshim\\Database\\ORM\\ModelQueryProxy' => 
  array (
    0 => 'Oshim\\Database\\ORM\\Model',
    1 => 'Oshim\\Database\\Query\\QueryBuilder',
  ),
  'Oshim\\Database\\Drivers\\PostgresDriver' => 
  array (
  ),
  'Oshim\\Database\\Drivers\\SqliteDriver' => 
  array (
  ),
  'Oshim\\Database\\Drivers\\MysqlDriver' => 
  array (
  ),
  'Oshim\\Database\\ConnectionManager' => 
  array (
  ),
  'Oshim\\Database\\Migrations\\Migrator' => 
  array (
    0 => 'Oshim\\Database\\Migrations\\MigrationRepository',
    1 => 'Oshim\\Database\\Connection',
  ),
  'Oshim\\Database\\Migrations\\MigrationRepository' => 
  array (
    0 => 'Oshim\\Database\\Connection',
  ),
  'Oshim\\Database\\DB' => 
  array (
  ),
  'Oshim\\Database\\Schema\\Schema' => 
  array (
  ),
  'Oshim\\Database\\Schema\\Compilers\\MysqlSchemaCompiler' => 
  array (
  ),
  'Oshim\\Database\\Schema\\Compilers\\PostgresSchemaCompiler' => 
  array (
  ),
  'Oshim\\Database\\Schema\\Compilers\\SqliteSchemaCompiler' => 
  array (
  ),
  'Oshim\\Database\\Query\\Compilers\\SqliteCompiler' => 
  array (
  ),
  'Oshim\\Database\\Query\\Compilers\\PostgresCompiler' => 
  array (
  ),
  'Oshim\\Database\\Query\\Compilers\\MysqlCompiler' => 
  array (
  ),
  'Oshim\\Database\\Query\\QueryBuilder' => 
  array (
    0 => 'Oshim\\Database\\Connection',
    1 => 'Oshim\\Database\\Query\\Compilers\\CompilerInterface',
  ),
  'Oshim\\App\\AppGenerator' => 
  array (
  ),
  'Oshim\\App\\UniversalAppEngine' => 
  array (
  ),
  'Oshim\\Auth\\Auth' => 
  array (
  ),
  'Oshim\\Auth\\Password\\PasswordHasher' => 
  array (
  ),
  'Oshim\\Auth\\AuthManager' => 
  array (
    0 => 'Oshim\\Http\\Session\\Session',
    1 => 'Oshim\\Http\\Request',
  ),
  'Oshim\\Async\\Socket\\AsyncTcpClient' => 
  array (
  ),
  'Oshim\\Async\\Socket\\AsyncUdpServer' => 
  array (
    0 => 'Oshim\\Async\\EventLoop',
  ),
  'Oshim\\Async\\Socket\\AsyncTcpServer' => 
  array (
    0 => 'Oshim\\Async\\EventLoop',
  ),
  'Oshim\\Async\\Timer\\TimerQueue' => 
  array (
  ),
  'Oshim\\Async\\IocpEngine' => 
  array (
  ),
  'Oshim\\Async\\Async' => 
  array (
  ),
  'Oshim\\Async\\FiberScheduler' => 
  array (
    0 => 'Oshim\\Async\\EventLoop',
  ),
  'Oshim\\Async\\KqueueEngine' => 
  array (
  ),
  'Oshim\\Async\\AsyncServiceProvider' => 
  array (
  ),
  'Oshim\\Async\\ZeroCopyStream' => 
  array (
  ),
  'Oshim\\Async\\EventLoop' => 
  array (
  ),
  'Oshim\\Async\\Task' => 
  array (
    0 => 'Fiber',
  ),
  'Oshim\\Async\\SharedMemoryCache' => 
  array (
  ),
  'Oshim\\Kernel\\UniversalKernel' => 
  array (
  ),
  'Oshim\\Kernel\\RouteParameterExtractor' => 
  array (
  ),
  'Oshim\\Kernel\\MicroKernel' => 
  array (
  ),
  'Oshim\\Kernel\\Drivers\\GenericPortableDriver' => 
  array (
  ),
  'Oshim\\Kernel\\Drivers\\WindowsKernelDriver' => 
  array (
  ),
  'Oshim\\Kernel\\Drivers\\LinuxKernelDriver' => 
  array (
  ),
  'Oshim\\Kernel\\Drivers\\BsdKernelDriver' => 
  array (
  ),
  'Oshim\\Kernel\\Drivers\\DarwinKernelDriver' => 
  array (
  ),
  'Oshim\\Cron\\Scheduler' => 
  array (
  ),
  'Oshim\\Autoloader' => 
  array (
  ),
  'Oshim\\GraphQL\\GraphQLServer' => 
  array (
  ),
  'Oshim\\Ai\\Vector\\DocumentChunker' => 
  array (
  ),
  'Oshim\\Ai\\Tokenizer\\GgufTokenizer' => 
  array (
  ),
  'Oshim\\Ai\\Rag\\HybridSearchEngine' => 
  array (
    0 => 'Oshim\\Ai\\Vector\\VectorStore',
  ),
  'Oshim\\Ai\\Tools\\ToolRegistry' => 
  array (
  ),
  'Oshim\\Ai\\Tensor\\MatrixMath' => 
  array (
  ),
  'Oshim\\Ai\\Agents\\AgentGraph' => 
  array (
  ),
  'Oshim\\Ai\\Canvas\\GraphSerializer' => 
  array (
  ),
  'Oshim\\Ai\\Canvas\\AiCanvasController' => 
  array (
  ),
  'Oshim\\Ai\\Canvas\\CanvasRenderer' => 
  array (
  ),
  'Oshim\\Ai\\Schema\\StructuredOutputExtractor' => 
  array (
  ),
  'Oshim\\Ai\\Healing\\CodePatcher' => 
  array (
  ),
  'Oshim\\Ai\\Healing\\SyntaxValidator' => 
  array (
  ),
  'Oshim\\Ai\\OshimAi' => 
  array (
  ),
  'Oshim\\Ai\\Embedding\\TfIdfEmbedder' => 
  array (
  ),
  'Oshim\\Storage\\Storage' => 
  array (
  ),
  'Oshim\\Storage\\S3\\ReplicationManager' => 
  array (
  ),
  'Oshim\\Storage\\S3\\S3Server' => 
  array (
  ),
  'Oshim\\Http\\Sse\\SseStreamer' => 
  array (
  ),
  'Oshim\\Http\\HttpServiceProvider' => 
  array (
  ),
  'Oshim\\Http\\Router\\RouteMatcher' => 
  array (
  ),
  'Oshim\\Http\\Router\\Router' => 
  array (
    0 => 'Oshim\\Container\\Container',
  ),
  'Oshim\\Http\\Router\\RouteFacade' => 
  array (
  ),
  'Oshim\\Http\\Middleware\\RbacMiddleware' => 
  array (
  ),
  'Oshim\\Http\\Middleware\\SecurityHeadersMiddleware' => 
  array (
  ),
  'Oshim\\Http\\Middleware\\SessionMiddleware' => 
  array (
    0 => 'Oshim\\Container\\Container',
  ),
  'Oshim\\Http\\Middleware\\RateLimitMiddleware' => 
  array (
  ),
  'Oshim\\Http\\Middleware\\Pipeline' => 
  array (
    0 => 'Oshim\\Container\\Container',
  ),
  'Oshim\\Http\\Middleware\\CsrfMiddleware' => 
  array (
  ),
  'Oshim\\Http\\Middleware\\CorsMiddleware' => 
  array (
  ),
  'Oshim\\Http\\Middleware\\AuthMiddleware' => 
  array (
  ),
  'Oshim\\Http\\WebSocket\\WebSocketServer' => 
  array (
  ),
  'Oshim\\Http\\Session\\MemorySessionStore' => 
  array (
  ),
  'Oshim\\Virtualization\\Node\\NodeSecurityCodec' => 
  array (
  ),
  'Oshim\\Virtualization\\Node\\JsonRpcProtocol' => 
  array (
  ),
  'Oshim\\Virtualization\\Driver\\LxcDriver' => 
  array (
    0 => 'Oshim\\Virtualization\\Syscall\\SyscallInterface',
    1 => 'Oshim\\Virtualization\\Cgroup\\CgroupV2Manager',
    2 => 'Oshim\\Virtualization\\Storage\\OverlayFsManager',
    3 => 'Oshim\\Virtualization\\Storage\\SnapshotManager',
    4 => 'Oshim\\Virtualization\\Network\\BridgeManager',
    5 => 'Oshim\\Virtualization\\Network\\VethManager',
    6 => 'Oshim\\Virtualization\\Network\\IpamService',
    7 => 'Oshim\\Virtualization\\Network\\NatManager',
  ),
  'Oshim\\Virtualization\\Driver\\NativeLinuxDriver' => 
  array (
    0 => 'Oshim\\Virtualization\\Syscall\\SyscallInterface',
    1 => 'Oshim\\Virtualization\\Cgroup\\CgroupV2Manager',
    2 => 'Oshim\\Virtualization\\Storage\\OverlayFsManager',
    3 => 'Oshim\\Virtualization\\Storage\\SnapshotManager',
    4 => 'Oshim\\Virtualization\\Network\\BridgeManager',
    5 => 'Oshim\\Virtualization\\Network\\VethManager',
    6 => 'Oshim\\Virtualization\\Network\\IpamService',
    7 => 'Oshim\\Virtualization\\Network\\NatManager',
  ),
  'Oshim\\Virtualization\\LiveMigrationManager' => 
  array (
  ),
  'Oshim\\Virtualization\\Storage\\StorageQuotaManager' => 
  array (
    0 => 'Oshim\\Virtualization\\Storage\\OverlayFsManager',
  ),
  'Oshim\\Virtualization\\Syscall\\LinuxConstants' => 
  array (
  ),
  'Oshim\\Virtualization\\Syscall\\MockSyscall' => 
  array (
  ),
  'Oshim\\Virtualization\\Syscall\\LinuxSyscall' => 
  array (
  ),
  'Oshim\\Virtualization\\MicroVmManager' => 
  array (
  ),
  'Oshim\\Virtualization\\VirtualizationEnvironment' => 
  array (
  ),
  'Oshim\\Virtualization\\Kvm\\KvmHardwareDriver' => 
  array (
  ),
  'Oshim\\Virtualization\\Network\\VethManager' => 
  array (
  ),
  'Oshim\\Virtualization\\Network\\NatManager' => 
  array (
  ),
  'Oshim\\Virtualization\\Network\\SimulatedNatRouter' => 
  array (
  ),
  'Oshim\\Virtualization\\Network\\TapManager' => 
  array (
  ),
  'Oshim\\Virtualization\\VirtualizationServiceProvider' => 
  array (
  ),
  'Oshim\\Virtualization\\ContainerState' => 
  array (
  ),
  'Oshim\\Virtualization\\CloudInit\\CloudInitService' => 
  array (
  ),
  'Oshim\\Security\\PasswordHasher' => 
  array (
  ),
  'Oshim\\Security\\Cipher' => 
  array (
  ),
  'Oshim\\Security\\TokenManager' => 
  array (
  ),
  'Oshim\\Security\\AntiDdos\\XdpFilter' => 
  array (
  ),
  'Oshim\\Security\\AntiDdos\\RateLimiterShield' => 
  array (
  ),
  'Oshim\\Security\\Totp\\TotpEngine' => 
  array (
  ),
  'Oshim\\Security\\RateLimiter' => 
  array (
  ),
  'Oshim\\Security\\Rbac' => 
  array (
  ),
  'Oshim\\Security\\Sanitizer' => 
  array (
  ),
  'Oshim\\Security\\Ssl\\CertificateManager' => 
  array (
  ),
  'Oshim\\Security\\SecurityServiceProvider' => 
  array (
  ),
  'Oshim\\Security\\Csrf' => 
  array (
  ),
  'Oshim\\Security\\Ed25519Signer' => 
  array (
  ),
  'Oshim\\Container\\Container' => 
  array (
  ),
  'Oshim\\Wasm\\WasmEngine' => 
  array (
  ),
  'Oshim\\Wasm\\WasmModule' => 
  array (
  ),
  'Oshim\\WebRtc\\IceCandidateRouter' => 
  array (
  ),
  'Oshim\\WebRtc\\WebRtcSignalingServer' => 
  array (
    0 => 'Oshim\\Http\\WebSocket\\WebSocketServer',
    1 => 'Oshim\\WebRtc\\MediaRoomManager',
    2 => 'Oshim\\WebRtc\\IceCandidateRouter',
    3 => 'Oshim\\Async\\EventLoop',
  ),
  'Oshim\\WebRtc\\MediaRoomManager' => 
  array (
  ),
  'Oshim\\Turbo\\PerfectHashRouter' => 
  array (
  ),
  'Oshim\\Tenant\\TenantManager' => 
  array (
  ),
  'Oshim\\Tenant\\TenantResolver' => 
  array (
  ),
  'Oshim\\Lifecycle\\ServiceState' => 
  array (
  ),
  'Oshim\\Bootstrap' => 
  array (
  ),
  'Oshim\\Cache\\Drivers\\MemoryCacheDriver' => 
  array (
  ),
  'Oshim\\Cache\\Cache' => 
  array (
  ),
  'Oshim\\Ui\\Layouts\\LandingPageLayout' => 
  array (
  ),
  'Oshim\\Ui\\UiManager' => 
  array (
    0 => 'Oshim\\Ui\\ComponentRegistry',
    1 => 'Oshim\\Ui\\DiffEngine',
  ),
  'Oshim\\Ui\\Dsl\\Table' => 
  array (
  ),
  'Oshim\\Ui\\Dsl\\Style' => 
  array (
  ),
  'Oshim\\Ui\\Dsl\\Html' => 
  array (
  ),
  'Oshim\\Ui\\Dsl\\Div' => 
  array (
  ),
  'Oshim\\Ui\\Dsl\\Section' => 
  array (
  ),
  'Oshim\\Ui\\Dsl\\Option' => 
  array (
  ),
  'Oshim\\Ui\\Showcase\\SovereignShowcaseLayout' => 
  array (
  ),
  'Oshim\\Ui\\Animation\\MotionRuntime' => 
  array (
  ),
  'Oshim\\Ui\\Animation\\MotionTimeline' => 
  array (
    0 => 'Oshim\\Ui\\Animation\\Spring',
  ),
  'Oshim\\Ui\\Animation\\Motion' => 
  array (
  ),
  'Oshim\\Ui\\Canvas3D\\Serialization\\ThreeJsSerializer' => 
  array (
  ),
  'Oshim\\Ui\\Widgets\\FooterWidget' => 
  array (
  ),
  'Oshim\\Ui\\Router\\AppRouter' => 
  array (
  ),
  'Oshim\\Ui\\Headless\\HeadlessRuntime' => 
  array (
  ),
  'Oshim\\Ui\\Headless\\Support\\Aria' => 
  array (
  ),
  'Oshim\\Ui\\LiveDom\\Directives\\DirectiveParser' => 
  array (
  ),
  'Oshim\\Ui\\LiveDom\\LiveDomClient' => 
  array (
  ),
  'Oshim\\Ui\\LiveDom\\LiveDom' => 
  array (
  ),
  'Oshim\\Ui\\LiveDom\\LiveDomManager' => 
  array (
    0 => 'Oshim\\Ui\\LiveDom\\MorphEngine',
  ),
  'Oshim\\Ui\\LiveDom\\MorphEngine' => 
  array (
  ),
  'Oshim\\Ui\\Multiplayer\\MultiplayerHub' => 
  array (
  ),
  'Oshim\\Ui\\Css\\TailwindJitCompiler' => 
  array (
  ),
  'Oshim\\Ui\\Reactive\\ActionRegistry' => 
  array (
  ),
  'Oshim\\Ui\\Reactive\\DomMorphDiff' => 
  array (
  ),
  'Oshim\\Ui\\ComponentRegistry' => 
  array (
  ),
  'Oshim\\Ui\\Runtime\\OshimClientRuntime' => 
  array (
  ),
  'Oshim\\Ui\\Signals\\SignalDomBinder' => 
  array (
  ),
  'Oshim\\Ui\\Theme\\CyberThemeEngine' => 
  array (
  ),
  'Oshim\\Ui\\Theme\\OshimTheme' => 
  array (
  ),
  'Oshim\\Ui\\Docs\\DocsPortalLayout' => 
  array (
  ),
  'Oshim\\Ui\\DiffEngine' => 
  array (
  ),
  'Oshim\\Ui\\UiServiceProvider' => 
  array (
  ),
  'Oshim\\Plugins\\PluginValidator' => 
  array (
  ),
  'Oshim\\Plugins\\PluginSandbox' => 
  array (
  ),
  'Oshim\\Testing\\TestSuite' => 
  array (
  ),
  'Oshim\\Testing\\Assert' => 
  array (
  ),
  'Oshim\\Testing\\TestRunner' => 
  array (
    0 => 'Oshim\\Cli\\Output',
  ),
  'Oshim\\Testing\\TestResponse' => 
  array (
    0 => 'Oshim\\Http\\Response',
  ),
  'Oshim\\Compiler\\UniversalPackager' => 
  array (
  ),
  'Oshim\\Billing\\TaxCalculator' => 
  array (
  ),
  'Oshim\\Billing\\Invoice' => 
  array (
  ),
  'Oshim\\Billing\\Pdf\\PdfInvoiceBuilder' => 
  array (
  ),
  'Oshim\\Billing\\BillingCycle' => 
  array (
  ),
  'Oshim\\Billing\\Promotion\\CouponEngine' => 
  array (
  ),
  'Oshim\\Billing\\Currency' => 
  array (
  ),
  'Oshim\\Oshim' => 
  array (
  ),
  'Oshim\\Cli\\CliApplication' => 
  array (
    0 => 'Oshim\\Container\\Container',
  ),
  'Oshim\\Cli\\Commands\\DnsServeCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\NodeStartCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\WebRtcServeCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\OptimizeAutoloaderCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\KeyGenerateCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\PluginVerifyCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\BillingCronCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\LedgerAuditCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\MakeComponentCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\TotpQrCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\AiTeamCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\SeedCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\TurboServeCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\WasmRunCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\SwarmInitCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\AiCanvasCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\AppRunCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\VmSpawnCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\TestCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\SslIssueCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\SelfUpdateCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\AppBundleCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\ServeCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\TurboBenchCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\SwarmStatusCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\DesktopBuildCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\MakeMigrationCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\DnsStartCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\DesktopServeCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\MakeCrudCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\UniversalInfoCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\SwarmLeaveCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\AiRagCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\S3ServeCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\SelfHealCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\MakeModelCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\ScheduleRunCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\CreateProjectCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\SwarmJoinCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\PackStandaloneCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\CacheClearCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\PdfInvoiceCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\AiChatCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\QueueWorkCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\PluginInstallCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\MigrateCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\MobileBuildCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\AppCreateCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\RollbackCommand' => 
  array (
  ),
  'Oshim\\Cli\\Commands\\MakeControllerCommand' => 
  array (
  ),
  'Oshim\\Cli\\Terminal' => 
  array (
  ),
  'Oshim\\Desktop\\DesktopAppEngine' => 
  array (
  ),
  'Oshim\\Queue\\Worker\\QueueWorker' => 
  array (
    0 => 'Oshim\\Queue\\QueueManager',
  ),
  'Oshim\\Queue\\Queue' => 
  array (
  ),
  'Oshim\\Queue\\Drivers\\SyncQueueDriver' => 
  array (
  ),
  'Oshim\\Mobile\\PwaBundleGenerator' => 
  array (
  ),
  'Oshim\\Mobile\\MobileAppEngine' => 
  array (
  ),
  'Oshim\\Dns\\Records\\RecordType' => 
  array (
  ),
  'Oshim\\Dns\\Records\\Codec\\RecordDataCodec' => 
  array (
  ),
  'Oshim\\Dns\\Server\\DnsServer' => 
  array (
    0 => 'Oshim\\Dns\\Zone\\ZoneRepositoryInterface',
    1 => 'Oshim\\Dns\\Server\\DnsServerConfig',
    2 => 'Oshim\\Dns\\Resolver\\AuthoritativeResolver',
  ),
  'Oshim\\Dns\\GeoRouting\\GeoRouter' => 
  array (
  ),
  'Oshim\\Dns\\Resolver\\AuthoritativeResolver' => 
  array (
    0 => 'Oshim\\Dns\\Zone\\ZoneRepositoryInterface',
  ),
  'Oshim\\Dns\\Wire\\DnsCodec' => 
  array (
  ),
  'Oshim\\Dns\\Parser\\BindZoneSerializer' => 
  array (
  ),
  'Oshim\\Dns\\Parser\\BindZoneParser' => 
  array (
  ),
  'Oshim\\Dns\\DnsServiceProvider' => 
  array (
  ),
  'Oshim\\Epp\\EppServiceProvider' => 
  array (
  ),
  'Oshim\\Epp\\Xml\\EppXmlParser' => 
  array (
  ),
  'Oshim\\Epp\\Xml\\EppXmlBuilder' => 
  array (
  ),
  'Oshim\\Epp\\Codec\\EppFrameCodec' => 
  array (
  ),
  'Oshim\\Epp\\EppClient' => 
  array (
    0 => 'Oshim\\Epp\\Transport\\EppTransportInterface',
  ),
  'Oshim\\Mail\\Mailable' => 
  array (
  ),
  'Oshim\\Mail\\Mailer' => 
  array (
    0 => 'Oshim\\Mail\\Transport\\TransportInterface',
  ),
  'Oshim\\Mail\\Mail' => 
  array (
  ),
  'Oshim\\Mail\\Transport\\ArrayTransport' => 
  array (
  ),
  'Oshim\\Swarm\\DistributedStateSync' => 
  array (
  ),
  'Oshim\\Swarm\\SwarmProtocol' => 
  array (
  ),
  'Oshim\\Swarm\\SwarmLoadBalancer' => 
  array (
  ),
  'App\\Controllers\\AuthController' => 
  array (
  ),
  'App\\Controllers\\AppController' => 
  array (
  ),
  'App\\Controllers\\ItemController' => 
  array (
  ),
  'App\\Controllers\\DashboardController' => 
  array (
  ),
  'App\\Controllers\\ShowcaseController' => 
  array (
  ),
  'App\\Controllers\\LandingController' => 
  array (
  ),
  'App\\Views\\Layout' => 
  array (
  ),
  'Plugins\\OshimMarketplaceDemo\\Plugin' => 
  array (
  ),
  'Plugins\\OshimAnalytics\\AnalyticsMiddleware' => 
  array (
    0 => 'Plugins\\OshimAnalytics\\AnalyticsTracker',
  ),
  'Plugins\\OshimAnalytics\\Plugin' => 
  array (
  ),
  'Plugins\\OshimAnalytics\\AnalyticsTracker' => 
  array (
  ),
  'Plugins\\OshimBilling\\Plugin' => 
  array (
  ),
  'Plugins\\OshimBilling\\WebhookController' => 
  array (
  ),
  'Plugins\\OshimAuth\\Plugin' => 
  array (
  ),
);
