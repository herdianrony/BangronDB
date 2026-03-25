<?php

use App\Controllers\AuditController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\CollectionController;
use App\Controllers\DashboardController;
use App\Controllers\DatabaseController;
use App\Controllers\DocumentController;
use App\Controllers\MonitoringController;
use App\Controllers\QueryController;
use App\Controllers\RoleController;
use App\Controllers\SchemaBuilderController;
use App\Controllers\SecurityNativeController;
use App\Controllers\SetupController;
use App\Controllers\StudioController;
use App\Controllers\UserController;

$auth = new AuthController();
$setup = new SetupController();
$dashboard = new DashboardController();
$databases = new DatabaseController();
$collections = new CollectionController();
$documents = new DocumentController();
$audit = new AuditController();
$users = new UserController();
$roles = new RoleController();
$query = new QueryController();
$api = new ApiController();
$studio = new StudioController();
$schemaBuilder = new SchemaBuilderController();
$security = new SecurityNativeController();

Flight::route('GET /setup', [$setup, 'show']);
Flight::route('POST /setup', [$setup, 'submit']);

Flight::route('GET /login', [$auth, 'showLogin']);
Flight::route('POST /login', [$auth, 'login']);
Flight::route('POST /logout', [$auth, 'logout']);

// Enhanced Authentication Routes
Flight::route('GET /2fa-verify', [$auth, 'showTwoFactorVerify']);
Flight::route('POST /2fa-verify', [$auth, 'verifyTwoFactor']);
Flight::route('GET /forgot-password', [$auth, 'showForgotPassword']);
Flight::route('POST /forgot-password', [$auth, 'requestPasswordReset']);
Flight::route('GET /reset-password', [$auth, 'showResetPassword']);
Flight::route('POST /reset-password', [$auth, 'resetPassword']);
Flight::route('GET /change-password', [$auth, 'showChangePassword']);
Flight::route('POST /change-password', [$auth, 'changePassword']);
Flight::route('GET /profile', [$auth, 'showProfile']);
Flight::route('POST /profile', [$auth, 'updateProfile']);

Flight::route('GET /', [$dashboard, 'index']);
Flight::route('GET /dashboard', [$dashboard, 'index']);

// User Management Routes
Flight::route('GET /users', [$users, 'index']);
Flight::route('GET /users/create', [$users, 'create']);
Flight::route('POST /users/create', [$users, 'store']);
Flight::route('GET /users/@id', [$users, 'show']);
Flight::route('GET /users/@id/edit', [$users, 'edit']);
Flight::route('POST /users/@id/edit', [$users, 'update']);
Flight::route('POST /users/@id/delete', [$users, 'delete']);
Flight::route('POST /users/bulk-delete', [$users, 'bulkDelete']);
Flight::route('GET /users/export', [$users, 'export']);
Flight::route('POST /users/@id/reset-password', [$users, 'resetPassword']);
Flight::route('POST /users/@id/toggle-status', [$users, 'toggleStatus']);
Flight::route('GET /users/@id/permissions', [$users, 'permissions']);
Flight::route('POST /users/@id/permissions', [$users, 'updatePermissions']);

// Role Management Routes
Flight::route('GET /roles', [$roles, 'index']);
Flight::route('GET /roles/create', [$roles, 'create']);
Flight::route('POST /roles/create', [$roles, 'store']);
Flight::route('GET /roles/@id', [$roles, 'show']);
Flight::route('GET /roles/@id/edit', [$roles, 'edit']);
Flight::route('POST /roles/@id/edit', [$roles, 'update']);
Flight::route('POST /roles/@id/delete', [$roles, 'delete']);
Flight::route('GET /roles/@id/permissions', [$roles, 'permissions']);
Flight::route('POST /roles/@id/permissions', [$roles, 'updatePermissions']);
Flight::route('GET /roles/hierarchy', [$roles, 'hierarchy']);
Flight::route('POST /roles/hierarchy', [$roles, 'updateHierarchy']);
Flight::route('GET /roles/export-matrix', [$roles, 'exportMatrix']);
Flight::route('GET /roles/import', [$roles, 'import']);
Flight::route('POST /roles/import', [$roles, 'importFile']);
Flight::route('POST /roles/create-from-template', [$roles, 'createFromTemplate']);
Flight::route('POST /roles/bulk-update', [$roles, 'bulkUpdate']);

Flight::route('GET /databases', [$databases, 'index']);
Flight::route('POST /databases/create', [$databases, 'create']);
Flight::route('GET /databases/@name', [$databases, 'show']);
Flight::route('POST /databases/@name/permissions', [$databases, 'updatePermissions']);
Flight::route('GET /databases/@name/settings', [$databases, 'settings']);
Flight::route('POST /databases/@name/settings', [$databases, 'saveSettings']);
Flight::route('POST /databases/@name/backup', [$databases, 'createBackup']);
Flight::route('POST /databases/@name/restore', [$databases, 'restoreBackup']);

Flight::route('POST /collections/@name/create', [$collections, 'create']);
Flight::route('POST /collections/@name/rename', [$collections, 'rename']);
Flight::route('POST /collections/@name/drop', [$collections, 'drop']);
Flight::route('GET /collections/@collection/settings', [$collections, 'settings']);
Flight::route('POST /collections/@collection/settings', [$collections, 'saveSettings']);
Flight::route('GET /collections/@collection/schema-builder', [$schemaBuilder, 'index']);
Flight::route('POST /collections/@collection/schema-builder/save', [$schemaBuilder, 'save']);
Flight::route('POST /collections/@collection/schema-builder/test', [$schemaBuilder, 'test']);

Flight::route('GET /documents/@collection', [$documents, 'index']);
Flight::route('POST /documents/@collection/create', [$documents, 'create']);
Flight::route('POST /documents/@collection/update', [$documents, 'update']);
Flight::route('POST /documents/@collection/delete', [$documents, 'delete']);
Flight::route('GET /documents/@collection/export', [$documents, 'export']);
Flight::route('POST /documents/@collection/import', [$documents, 'import']);

Flight::route('GET /query-playground', [$query, 'playground']);
Flight::route('POST /query/run', [$query, 'run']);
Flight::route('GET /query/history', [$query, 'history']);

// Audit & Monitoring Routes
Flight::route('GET /audit', [$audit, 'showDashboard']);
Flight::route('GET /audit/dashboard', [$audit, 'showDashboard']);
Flight::route('GET /audit/dashboard-summary', [$audit, 'getDashboardSummaryData']);
Flight::route('GET /audit/user-activity', [$audit, 'showUserActivity']);
Flight::route('GET /audit/user-activity-data', [$audit, 'getUserActivityData']);
Flight::route('GET /audit/system-activity', [$audit, 'showSystemActivity']);
Flight::route('GET /audit/system-activity-data', [$audit, 'getSystemActivityData']);
Flight::route('GET /audit/security-events', [$audit, 'showSecurityEvents']);
Flight::route('GET /audit/export', [$audit, 'exportActivity']);

// Native security routes (BangronDB)
Flight::route('GET /security', [$security, 'index']);
Flight::route('GET /security/status', [$security, 'status']);
Flight::route('POST /security/key/rotate', [$security, 'rotateKey']);
Flight::route('POST /security/policy/save', [$security, 'savePolicy']);

// Monitoring Routes
$monitoring = new MonitoringController();
Flight::route('GET /monitoring', [$monitoring, 'index']);
Flight::route('GET /monitoring/realtime', [$monitoring, 'getRealTimeMetrics']);
Flight::route('GET /monitoring/historical/@period', [$monitoring, 'getHistoricalMetrics']);
Flight::route('GET /monitoring/generate-report/@type/@format', [$monitoring, 'generateReport']);
Flight::route('GET /monitoring/alert-config', [$monitoring, 'getAlertConfiguration']);
Flight::route('POST /monitoring/alert-config', [$monitoring, 'updateAlertConfiguration']);
Flight::route('GET /monitoring/log-config', [$monitoring, 'getLogConfiguration']);
Flight::route('POST /monitoring/log-config', [$monitoring, 'updateLogConfiguration']);
Flight::route('GET /monitoring/export-logs', [$monitoring, 'exportLogs']);

// API polling endpoints
Flight::route('GET /api/dashboard/summary', [$api, 'dashboardSummary']);
Flight::route('GET /api/monitoring/realtime', [$api, 'monitoringRealtime']);
Flight::route('GET /api/audit/recent', [$api, 'auditRecent']);
Flight::route('GET /api/notifications', [$api, 'notifications']);

// Studio pages mapped from html_ui_examples
Flight::route('GET /notifications', [$studio, 'notifications']);
Flight::route('GET /logs', [$studio, 'logs']);
Flight::route('GET /terminal', [$studio, 'terminal']);
Flight::route('POST /terminal/run', [$studio, 'terminalRun']);
Flight::route('GET /api-docs', [$studio, 'apiDocs']);
Flight::route('GET /settings', [$studio, 'settings']);
Flight::route('GET /backup', [$studio, 'backup']);
Flight::route('GET /hooks', [$studio, 'hooks']);
Flight::route('GET /relationships', [$studio, 'relationships']);
Flight::route('GET /import-export', [$studio, 'importExport']);

// Backup management helpers
Flight::route('GET /databases/@name/backup/list', [$databases, 'listBackups']);
Flight::route('POST /databases/@name/backup/prune', [$databases, 'pruneBackups']);
