<?php

namespace App\Services;

class AuditService
{
    private $systemDb;

    public function __construct()
    {
        $this->systemDb = (new SystemService())->systemDb();
    }

    /**
     * Log user activity.
     */
    public function logActivity(string $userId, string $action, array $data = [], ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $activity = [
            'user_id' => $userId,
            'action' => $action,
            'data' => $data,
            'ip_address' => $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('c'),
            'session_id' => session_id(),
            'request_id' => uniqid('req_', true),
        ];

        try {
            $this->systemDb->audit_logs->insert($activity);
        } catch (\Throwable $e) {
            error_log('Failed to log activity: '.$e->getMessage());
        }
    }

    /**
     * Get user activity logs.
     */
    public function getUserActivity(string $userId, int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $query = ['user_id' => $userId];

        if (!empty($filters['action'])) {
            $query['action'] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $query['timestamp'] = ['$gte' => date('c', strtotime($filters['date_from']))];
        }

        if (!empty($filters['date_to'])) {
            $query['timestamp'] = ['$lte' => date('c', strtotime($filters['date_to'].' 23:59:59'))];
        }

        try {
            $logs = $this->systemDb->audit_logs
                ->find($query)
                ->sort(['timestamp' => -1])
                ->skip($offset)
                ->limit($limit)
                ->toArray();

            return array_map(function ($log) {
                return [
                    'id' => (string) $log['_id'],
                    'action' => $log['action'],
                    'data' => $log['data'] ?? [],
                    'ip_address' => $log['ip_address'] ?? 'unknown',
                    'user_agent' => $log['user_agent'] ?? 'unknown',
                    'timestamp' => $this->formatTimestamp($log['timestamp'] ?? null),
                    'session_id' => $log['session_id'] ?? '',
                    'request_id' => $log['request_id'] ?? '',
                ];
            }, $logs);
        } catch (\Throwable $e) {
            error_log('Failed to get user activity: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get system activity logs.
     */
    public function getSystemActivity(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $query = [];

        if (!empty($filters['action'])) {
            $query['action'] = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $query['user_id'] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $query['timestamp'] = ['$gte' => date('c', strtotime($filters['date_from']))];
        }

        if (!empty($filters['date_to'])) {
            $query['timestamp'] = ['$lte' => date('c', strtotime($filters['date_to'].' 23:59:59'))];
        }

        try {
            $logs = $this->systemDb->audit_logs
                ->find($query)
                ->sort(['timestamp' => -1])
                ->skip($offset)
                ->limit($limit)
                ->toArray();

            return array_map(function ($log) {
                return [
                    'id' => (string) $log['_id'],
                    'user_id' => $log['user_id'] ?? 'system',
                    'action' => $log['action'] ?? 'unknown',
                    'data' => $log['data'] ?? [],
                    'ip_address' => $log['ip_address'] ?? 'unknown',
                    'user_agent' => $log['user_agent'] ?? 'unknown',
                    'timestamp' => $this->formatTimestamp($log['timestamp'] ?? null),
                    'session_id' => $log['session_id'] ?? '',
                    'request_id' => $log['request_id'] ?? '',
                ];
            }, $logs);
        } catch (\Throwable $e) {
            error_log('Failed to get system activity: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get user login statistics.
     */
    public function getUserLoginStats(string $userId, string $period = '7d'): array
    {
        $days = $period === '7d' ? 7 : ($period === '30d' ? 30 : 1);
        $startDate = date('c', time() - ($days * 24 * 60 * 60));

        try {
            $rows = $this->systemDb->audit_logs->find([
                'user_id' => $userId,
                'action' => 'login',
                'timestamp' => ['$gte' => $startDate],
            ])->toArray();

            $bucket = [];
            foreach ($rows as $row) {
                $timestamp = (string) ($row['timestamp'] ?? '');
                $ts = strtotime($timestamp);
                if ($ts === false) {
                    continue;
                }
                $date = date('Y-m-d', $ts);
                $hour = (int) date('G', $ts);
                $key = $date . '|' . $hour;
                if (!isset($bucket[$key])) {
                    $bucket[$key] = ['date' => $date, 'hour' => $hour, 'count' => 0];
                }
                $bucket[$key]['count']++;
            }

            $result = array_values($bucket);
            usort($result, function (array $a, array $b): int {
                return [$a['date'], $a['hour']] <=> [$b['date'], $b['hour']];
            });

            return $result;
        } catch (\Throwable $e) {
            error_log('Failed to get login stats: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get system security events.
     */
    public function getSecurityEvents(int $limit = 50, array $filters = []): array
    {
        $securityActions = [
            'login_failed', 'password_reset', 'account_locked',
            'permission_denied', 'suspicious_activity', 'brute_force_attempt',
        ];

        $query = [
            'action' => ['$in' => $securityActions],
        ];

        if (!empty($filters['date_from'])) {
            $query['timestamp'] = ['$gte' => date('c', strtotime($filters['date_from']))];
        }

        if (!empty($filters['date_to'])) {
            $query['timestamp'] = ['$lte' => date('c', strtotime($filters['date_to'].' 23:59:59'))];
        }

        try {
            $logs = $this->systemDb->audit_logs
                ->find($query)
                ->sort(['timestamp' => -1])
                ->limit($limit)
                ->toArray();

            return array_map(function ($log) {
                return [
                    'id' => (string) $log['_id'],
                    'user_id' => $log['user_id'] ?? 'system',
                    'action' => $log['action'] ?? 'unknown',
                    'data' => $log['data'] ?? [],
                    'ip_address' => $log['ip_address'] ?? 'unknown',
                    'user_agent' => $log['user_agent'] ?? 'unknown',
                    'timestamp' => $this->formatTimestamp($log['timestamp'] ?? null),
                ];
            }, $logs);
        } catch (\Throwable $e) {
            error_log('Failed to get security events: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get dashboard activity summary.
     */
    public function getDashboardSummary(): array
    {
        try {
            $today = date('c', strtotime('today'));
            $weekAgo = date('c', time() - 7 * 24 * 60 * 60);

            // Get today's activity
            $todayActivity = $this->systemDb->audit_logs->count([
                'timestamp' => ['$gte' => $today],
            ]);

            // Get week activity
            $weekActivity = $this->systemDb->audit_logs->count([
                'timestamp' => ['$gte' => $weekAgo],
            ]);

            // Get unique users today (BangronDB collection does not provide distinct()).
            $loginRows = $this->systemDb->audit_logs->find([
                'timestamp' => ['$gte' => $today],
                'action' => 'login',
            ])->toArray();
            $uniqueUsersToday = [];
            foreach ($loginRows as $row) {
                $userId = (string) ($row['user_id'] ?? '');
                if ($userId !== '') {
                    $uniqueUsersToday[$userId] = true;
                }
            }

            // Get security events today
            $securityEvents = $this->systemDb->audit_logs->count([
                'timestamp' => ['$gte' => $today],
                'action' => ['$in' => ['login_failed', 'password_reset', 'account_locked']],
            ]);

            return [
                'today_activity' => $todayActivity,
                'week_activity' => $weekActivity,
                'unique_users_today' => count($uniqueUsersToday),
                'security_events_today' => $securityEvents,
                'top_actions' => $this->getTopActions(5, $weekAgo),
            ];
        } catch (\Throwable $e) {
            error_log('Failed to get dashboard summary: '.$e->getMessage());

            return [
                'today_activity' => 0,
                'week_activity' => 0,
                'unique_users_today' => 0,
                'security_events_today' => 0,
                'top_actions' => [],
            ];
        }
    }

    /**
     * Get top actions in a time period.
     */
    private function getTopActions(int $limit, string $since): array
    {
        try {
            $rows = $this->systemDb->audit_logs->find([
                'timestamp' => ['$gte' => $since],
            ])->toArray();

            $counts = [];
            foreach ($rows as $row) {
                $action = (string) ($row['action'] ?? 'unknown');
                if (!isset($counts[$action])) {
                    $counts[$action] = 0;
                }
                $counts[$action]++;
            }

            arsort($counts);
            $out = [];
            foreach ($counts as $action => $count) {
                $out[] = ['action' => $action, 'count' => $count];
                if (count($out) >= $limit) {
                    break;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('Failed to get top actions: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Clean old audit logs.
     */
    public function cleanupOldLogs(int $daysToKeep = 90): bool
    {
        try {
            $cutoffDate = date('c', time() - ($daysToKeep * 24 * 60 * 60));
            $deleted = $this->systemDb->audit_logs->remove(['timestamp' => ['$lt' => $cutoffDate]]);

            return $deleted > 0;
        } catch (\Throwable $e) {
            error_log('Failed to cleanup old logs: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Log user activity (alias untuk logActivity).
     */
    public function log(array $user, string $action, $targetId = null, $targetType = null, $oldData = null, array $newData = []): void
    {
        $this->logActivity(
            $user['_id'],
            $action,
            array_merge([
                'target_id' => $targetId,
                'target_type' => $targetType,
                'old_data' => $oldData,
                'new_data' => $newData,
            ]),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }

    /**
     * Get recent activity logs.
     */
    public function list(int $limit = 10): array
    {
        return $this->getSystemActivity($limit);
    }

    /**
     * Export activity logs.
     */
    public function exportActivityLogs(array $filters = []): string
    {
        $logs = $this->getSystemActivity(1000, 0, $filters);

        $csv = "User ID,Action,Data,IP Address,User Agent,Timestamp\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%s,%s,\"%s\",%s,\"%s\",%s\n",
                $log['user_id'],
                $log['action'],
                json_encode($log['data']),
                $log['ip_address'],
                str_replace('"', '""', $log['user_agent']),
                $log['timestamp']
            );
        }

        return $csv;
    }

    private function formatTimestamp($value): string
    {
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);
            if ($ts !== false) {
                return date('Y-m-d H:i:s', $ts);
            }

            return $value;
        }

        if (is_int($value)) {
            return date('Y-m-d H:i:s', $value);
        }

        if (is_object($value) && method_exists($value, 'toDateTime')) {
            return $value->toDateTime()->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s');
    }
}
