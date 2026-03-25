<?php

namespace App\Services;

class AccessControlService
{
    private $systemDb;
    private $config;
    private $cache;

    public function __construct()
    {
        $this->systemDb = (new SystemService())->systemDb();
        $this->config = include __DIR__.'/../config/security.php';
        $this->cache = [];
    }

    /**
     * Check if user has permission for specific action.
     */
    public function checkPermission(string $userId, string $action, string $resource, array $context = []): bool
    {
        try {
            // Check cache first
            $cacheKey = $userId.'_'.$action.'_'.$resource;
            if (isset($this->cache[$cacheKey])) {
                return $this->cache[$cacheKey];
            }

            // Get user roles
            $userRoles = $this->getUserRoles($userId);

            // Check permissions through roles
            foreach ($userRoles as $role) {
                if ($this->checkRolePermission($role['role_id'], $action, $resource, $context)) {
                    $this->cache[$cacheKey] = true;

                    return true;
                }
            }

            // Check direct user permissions
            $directPermission = $this->checkDirectUserPermission($userId, $action, $resource, $context);
            if ($directPermission) {
                $this->cache[$cacheKey] = true;

                return true;
            }

            // Check ABAC policies
            if ($this->checkAttributeBasedAccess($userId, $action, $resource, $context)) {
                $this->cache[$cacheKey] = true;

                return true;
            }

            // Log permission denied
            $this->logPermissionDenied($userId, $action, $resource, $context);

            $this->cache[$cacheKey] = false;

            return false;
        } catch (Exception $e) {
            error_log('Failed to check permission: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get user roles.
     */
    private function getUserRoles(string $userId): array
    {
        try {
            $userRoles = $this->systemDb->user_roles->find(['user_id' => $userId])->toArray();

            return array_map(function ($userRole) {
                return [
                    'user_id' => $userRole['user_id'],
                    'role_id' => $userRole['role_id'],
                    'assigned_at' => $userRole['assigned_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'assigned_by' => $userRole['assigned_by'],
                ];
            }, $userRoles);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get user roles: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Check role-based permission.
     */
    private function checkRolePermission(string $roleId, string $action, string $resource, array $context = []): bool
    {
        try {
            // Get role permissions
            $rolePermissions = $this->systemDb->role_permissions->find(['role_id' => $roleId])->toArray();

            foreach ($rolePermissions as $permission) {
                if ($this->evaluatePermission($permission, $action, $resource, $context)) {
                    return true;
                }
            }

            return false;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check role permission: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check direct user permission.
     */
    private function checkDirectUserPermission(string $userId, string $action, string $resource, array $context = []): bool
    {
        try {
            $userPermissions = $this->systemDb->user_permissions->find([
                'user_id' => $userId,
                'active' => true,
            ])->toArray();

            foreach ($userPermissions as $permission) {
                if ($this->evaluatePermission($permission, $action, $resource, $context)) {
                    return true;
                }
            }

            return false;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check direct user permission: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Evaluate permission rule.
     */
    private function evaluatePermission(array $permission, string $action, string $resource, array $context = []): bool
    {
        // Check action
        if (!empty($permission['action']) && $permission['action'] !== $action) {
            return false;
        }

        // Check resource
        if (!empty($permission['resource']) && $permission['resource'] !== $resource) {
            return false;
        }

        // Check conditions
        if (!empty($permission['conditions'])) {
            if (!$this->evaluateConditions($permission['conditions'], $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate permission conditions.
     */
    private function evaluateConditions(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            $result = $this->evaluateCondition($condition, $context);
            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate single condition.
     */
    private function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Get field value from context
        $contextValue = $context[$field] ?? null;

        switch ($operator) {
            case 'equals':
                return $contextValue == $value;

            case 'not_equals':
                return $contextValue != $value;

            case 'in':
                return is_array($contextValue) ? in_array($value, $contextValue) : $contextValue == $value;

            case 'not_in':
                return is_array($contextValue) ? !in_array($value, $contextValue) : $contextValue != $value;

            case 'contains':
                return strpos($contextValue, $value) !== false;

            case 'not_contains':
                return strpos($contextValue, $value) === false;

            case 'starts_with':
                return strpos($contextValue, $value) === 0;

            case 'ends_with':
                return substr($contextValue, -strlen($value)) === $value;

            case 'greater_than':
                return $contextValue > $value;

            case 'less_than':
                return $contextValue < $value;

            case 'greater_equal':
                return $contextValue >= $value;

            case 'less_equal':
                return $contextValue <= $value;

            case 'regex':
                return preg_match($value, $contextValue);

            case 'ip_in_range':
                return $this->checkIPInRange($contextValue, $value);

            case 'time_between':
                return $this->checkTimeBetween($contextValue, $value);

            default:
                return false;
        }
    }

    /**
     * Check IP in range.
     */
    private function checkIPInRange(string $ip, string $range): bool
    {
        // Check if range is CIDR notation
        if (strpos($range, '/') !== false) {
            return $this->ipCIDRCheck($ip, $range);
        }

        // Check if range is IP range
        if (strpos($range, '-') !== false) {
            list($start, $end) = explode('-', $range);

            return ip2long($ip) >= ip2long(trim($start)) && ip2long($ip) <= ip2long(trim($end));
        }

        // Check single IP
        return $ip === $range;
    }

    /**
     * Check IP in CIDR range.
     */
    private function ipCIDRCheck(string $ip, string $cidr): bool
    {
        list($network, $mask) = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $maskLong = ~((1 << (32 - $mask)) - 1);

        return ($ipLong & $maskLong) === ($networkLong & $maskLong);
    }

    /**
     * Check time between.
     */
    private function checkTimeBetween(string $time, string $range): bool
    {
        list($start, $end) = explode('-', $range);
        $currentTime = strtotime($time);
        $startTime = strtotime($start);
        $endTime = strtotime($end);

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * Check attribute-based access control.
     */
    private function checkAttributeBasedAccess(string $userId, string $action, string $resource, array $context = []): bool
    {
        try {
            // Get ABAC policies
            $policies = $this->systemDb->abac_policies->find([
                'active' => true,
                'enabled' => true,
            ])->toArray();

            foreach ($policies as $policy) {
                if ($this->evaluateABACPolicy($policy, $userId, $action, $resource, $context)) {
                    return true;
                }
            }

            return false;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check ABAC: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Evaluate ABAC policy.
     */
    private function evaluateABACPolicy(array $policy, string $userId, string $action, string $resource, array $context = []): bool
    {
        // Check policy conditions
        if (!empty($policy['conditions'])) {
            $userAttributes = $this->getUserAttributes($userId);
            $combinedContext = array_merge($context, $userAttributes);

            if (!$this->evaluateConditions($policy['conditions'], $combinedContext)) {
                return false;
            }
        }

        // Check policy actions
        if (!empty($policy['actions'])) {
            if (!in_array($action, $policy['actions'])) {
                return false;
            }
        }

        // Check policy resources
        if (!empty($policy['resources'])) {
            if (!in_array($resource, $policy['resources'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user attributes for ABAC.
     */
    private function getUserAttributes(string $userId): array
    {
        try {
            // Get user attributes from database
            $user = $this->systemDb->users->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);

            if (!$user) {
                return [];
            }

            $attributes = [];

            // Basic user attributes
            $attributes['user_id'] = $userId;
            $attributes['username'] = $user['username'] ?? '';
            $attributes['email'] = $user['email'] ?? '';
            $attributes['role'] = $user['role'] ?? 'user';
            $attributes['status'] = $user['status'] ?? 'active';

            // Department and location
            $attributes['department'] = $user['department'] ?? '';
            $attributes['location'] = $user['location'] ?? '';
            $attributes['job_title'] = $user['job_title'] ?? '';

            // Time-based attributes
            $attributes['current_time'] = date('H:i');
            $attributes['current_day'] = date('l');
            $attributes['current_date'] = date('Y-m-d');

            // IP-based attributes
            $attributes['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $attributes['ip_country'] = $this->getIPCountry($attributes['ip_address']);

            // Session attributes
            $attributes['session_id'] = session_id();
            $attributes['session_created'] = $_SESSION['created_at'] ?? date('Y-m-d H:i:s');

            return $attributes;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get user attributes: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get IP country (mock implementation).
     */
    private function getIPCountry(string $ip): string
    {
        // In real implementation, use IP geolocation service
        return 'US';
    }

    /**
     * Log permission denied.
     */
    private function logPermissionDenied(string $userId, string $action, string $resource, array $context = []): void
    {
        try {
            $log = [
                'user_id' => $userId,
                'action' => $action,
                'resource' => $resource,
                'context' => $context,
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'status' => 'denied',
                'reason' => 'permission_denied',
            ];

            $this->systemDb->access_logs->insertOne($log);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to log permission denied: '.$e->getMessage());
        }
    }

    /**
     * Grant permission to user.
     */
    public function grantUserPermission(string $userId, string $action, string $resource, array $conditions = [], ?string $grantedBy = null): bool
    {
        try {
            $permission = [
                'user_id' => $userId,
                'action' => $action,
                'resource' => $resource,
                'conditions' => $conditions,
                'granted_at' => new MongoDB\BSON\UTCDateTime(),
                'granted_by' => $grantedBy ?? $_SESSION['user_id'] ?? 'system',
                'active' => true,
            ];

            $result = $this->systemDb->user_permissions->insertOne($permission);

            // Clear cache
            $this->clearUserCache($userId);

            return $result->getInsertedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to grant user permission: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Revoke permission from user.
     */
    public function revokeUserPermission(string $userId, string $action, string $resource): bool
    {
        try {
            $result = $this->systemDb->user_permissions->deleteOne([
                'user_id' => $userId,
                'action' => $action,
                'resource' => resource,
            ]);

            // Clear cache
            $this->clearUserCache($userId);

            return $result->getDeletedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to revoke user permission: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Grant role to user.
     */
    public function grantUserRole(string $userId, string $roleId, ?string $assignedBy = null): bool
    {
        try {
            $userRole = [
                'user_id' => $userId,
                'role_id' => $roleId,
                'assigned_at' => new MongoDB\BSON\UTCDateTime(),
                'assigned_by' => $assignedBy ?? $_SESSION['user_id'] ?? 'system',
            ];

            $result = $this->systemDb->user_roles->insertOne($userRole);

            // Clear cache
            $this->clearUserCache($userId);

            return $result->getInsertedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to grant user role: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Revoke role from user.
     */
    public function revokeUserRole(string $userId, string $roleId): bool
    {
        try {
            $result = $this->systemDb->user_roles->deleteOne([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);

            // Clear cache
            $this->clearUserCache($userId);

            return $result->getDeletedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to revoke user role: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Create ABAC policy.
     */
    public function createABACPolicy(array $policyData): bool
    {
        try {
            $policy = [
                'name' => $policyData['name'],
                'description' => $policyData['description'],
                'conditions' => $policyData['conditions'] ?? [],
                'actions' => $policyData['actions'] ?? [],
                'resources' => $policyData['resources'] ?? [],
                'active' => $policyData['active'] ?? true,
                'enabled' => $policyData['enabled'] ?? true,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => $_SESSION['user_id'] ?? 'system',
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_by' => $_SESSION['user_id'] ?? 'system',
            ];

            $result = $this->systemDb->abac_policies->insertOne($policy);

            return $result->getInsertedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to create ABAC policy: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Update ABAC policy.
     */
    public function updateABACPolicy(string $policyId, array $policyData): bool
    {
        try {
            $update = [
                '$set' => [
                    'name' => $policyData['name'],
                    'description' => $policyData['description'],
                    'conditions' => $policyData['conditions'] ?? [],
                    'actions' => $policyData['actions'] ?? [],
                    'resources' => $policyData['resources'] ?? [],
                    'active' => $policyData['active'] ?? true,
                    'enabled' => $policyData['enabled'] ?? true,
                    'updated_at' => new MongoDB\BSON\UTCDateTime(),
                    'updated_by' => $_SESSION['user_id'] ?? 'system',
                ],
            ];

            $result = $this->systemDb->abac_policies->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($policyId)],
                $update
            );

            return $result->getModifiedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to update ABAC policy: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Delete ABAC policy.
     */
    public function deleteABACPolicy(string $policyId): bool
    {
        try {
            $result = $this->systemDb->abac_policies->deleteOne([
                '_id' => new MongoDB\BSON\ObjectId($policyId),
            ]);

            return $result->getDeletedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to delete ABAC policy: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get user permissions summary.
     */
    public function getUserPermissionsSummary(string $userId): array
    {
        try {
            $userRoles = $this->getUserRoles($userId);
            $rolePermissions = [];

            // Get role permissions
            foreach ($userRoles as $userRole) {
                $permissions = $this->systemDb->role_permissions->find([
                    'role_id' => $userRole['role_id'],
                ])->toArray();

                foreach ($permissions as $permission) {
                    $rolePermissions[] = [
                        'type' => 'role',
                        'role_id' => $userRole['role_id'],
                        'action' => $permission['action'],
                        'resource' => $permission['resource'],
                        'conditions' => $permission['conditions'] ?? [],
                        'granted_at' => $userRole['assigned_at'],
                    ];
                }
            }

            // Get direct user permissions
            $directPermissions = $this->systemDb->user_permissions->find([
                'user_id' => $userId,
                'active' => true,
            ])->toArray();

            $directPermissionList = [];
            foreach ($directPermissions as $permission) {
                $directPermissionList[] = [
                    'type' => 'direct',
                    'action' => $permission['action'],
                    'resource' => $permission['resource'],
                    'conditions' => $permission['conditions'] ?? [],
                    'granted_at' => $permission['granted_at'],
                ];
            }

            // Get ABAC policies
            $abacPolicies = $this->systemDb->abac_policies->find([
                'active' => true,
                'enabled' => true,
            ])->toArray();

            return [
                'user_id' => $userId,
                'roles' => array_column($userRoles, 'role_id'),
                'role_permissions' => $rolePermissions,
                'direct_permissions' => $directPermissionList,
                'abac_policies' => array_map(function ($policy) {
                    return [
                        'name' => $policy['name'],
                        'description' => $policy['description'],
                        'actions' => $policy['actions'],
                        'resources' => $policy['resources'],
                        'conditions' => $policy['conditions'],
                    ];
                }, $abacPolicies),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get user permissions summary: '.$e->getMessage());

            return [
                'error' => $e->getMessage(),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Just-in-time access request.
     */
    public function requestJustInTimeAccess(string $userId, string $action, string $resource, array $context = [], ?string $reason = null): array
    {
        try {
            $request = [
                'user_id' => $userId,
                'action' => $action,
                'resource' => $resource,
                'context' => $context,
                'reason' => $reason,
                'status' => 'pending',
                'requested_at' => new MongoDB\BSON\UTCDateTime(),
                'requested_by' => $userId,
                'approved_by' => null,
                'approved_at' => null,
                'denied_by' => null,
                'denied_at' => null,
                'denied_reason' => null,
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + 24 * 60 * 60) * 1000), // 24 hours
            ];

            $result = $this->systemDb->access_requests->insertOne($request);

            return [
                'request_id' => (string) $result->getInsertedId(),
                'status' => 'pending',
                'message' => 'Access request submitted for approval',
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to request JIT access: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Failed to submit access request',
            ];
        }
    }

    /**
     * Approve just-in-time access request.
     */
    public function approveJustInTimeAccess(string $requestId, string $approvedBy, int $durationHours = 24): bool
    {
        try {
            $update = [
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => new MongoDB\BSON\UTCDateTime(),
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + $durationHours * 60 * 60) * 1000),
            ];

            $result = $this->systemDb->access_requests->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($requestId)],
                ['$set' => $update]
            );

            // Grant temporary permission
            if ($result->getModifiedCount() > 0) {
                $request = $this->systemDb->access_requests->findOne(['_id' => new MongoDB\BSON\ObjectId($requestId)]);
                if ($request) {
                    $this->grantUserPermission(
                        $request['user_id'],
                        $request['action'],
                        $request['resource'],
                        [],
                        $approvedBy
                    );
                }
            }

            return $result->getModifiedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to approve JIT access: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Deny just-in-time access request.
     */
    public function denyJustInTimeAccess(string $requestId, string $deniedBy, ?string $reason = null): bool
    {
        try {
            $update = [
                'status' => 'denied',
                'denied_by' => $deniedBy,
                'denied_at' => new MongoDB\BSON\UTCDateTime(),
                'denied_reason' => $reason,
            ];

            $result = $this->systemDb->access_requests->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($requestId)],
                ['$set' => $update]
            );

            return $result->getModifiedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to deny JIT access: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Clear user cache.
     */
    private function clearUserCache(string $userId): void
    {
        $keysToRemove = array_keys($this->cache, $userId);
        foreach ($keysToRemove as $key) {
            unset($this->cache[$key]);
        }
    }

    /**
     * Get access control dashboard.
     */
    public function getAccessControlDashboard(): array
    {
        try {
            $today = new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000);
            $weekAgo = new MongoDB\BSON\UTCDateTime((time() - 7 * 24 * 60 * 60) * 1000);

            return [
                'total_users' => $this->systemDb->users->count(),
                'active_users' => $this->systemDb->users->count(['status' => 'active']),
                'total_roles' => $this->systemDb->roles->count(),
                'total_permissions' => $this->systemDb->role_permissions->count(),
                'access_requests_today' => $this->systemDb->access_requests->count([
                    'requested_at' => ['$gte' => $today],
                ]),
                'access_requests_week' => $this->systemDb->access_requests->count([
                    'requested_at' => ['$gte' => $weekAgo],
                ]),
                'abac_policies' => $this->systemDb->abac_policies->count(['active' => true]),
                'recent_access_logs' => $this->getRecentAccessLogs(10),
                'permission_usage' => $this->getPermissionUsageStats(),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get access control dashboard: '.$e->getMessage());

            return [
                'error' => $e->getMessage(),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Get recent access logs.
     */
    private function getRecentAccessLogs(int $limit): array
    {
        try {
            $logs = $this->systemDb->access_logs
                ->find([])
                ->sort(['timestamp' => -1])
                ->limit($limit)
                ->toArray();

            return array_map(function ($log) {
                return [
                    'user_id' => $log['user_id'],
                    'action' => $log['action'],
                    'resource' => $log['resource'],
                    'timestamp' => $log['timestamp']->toDateTime()->format('Y-m-d H:i:s'),
                    'status' => $log['status'],
                ];
            }, $logs);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get recent access logs: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get permission usage statistics.
     */
    private function getPermissionUsageStats(): array
    {
        try {
            $pipeline = [
                [
                    '$group' => [
                        '_id' => [
                            'action' => '$action',
                            'resource' => '$resource',
                        ],
                        'count' => ['$sum' => 1],
                    ],
                ],
                [
                    '$sort' => ['count' => -1],
                ],
                [
                    '$limit' => 10,
                ],
            ];

            $result = $this->systemDb->access_logs->aggregate($pipeline)->toArray();

            return array_map(function ($item) {
                return [
                    'action' => $item['_id']['action'],
                    'resource' => $item['_id']['resource'],
                    'count' => $item['count'],
                ];
            }, $result);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get permission usage stats: '.$e->getMessage());

            return [];
        }
    }
}
