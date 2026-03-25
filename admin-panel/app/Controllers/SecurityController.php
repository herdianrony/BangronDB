<?php

namespace App\Controllers;

use App\Services\AccessControlService;
use App\Services\EncryptionService;
use App\Services\SecurityService;

class SecurityController
{
    private $securityService;
    private $encryptionService;
    private $accessControlService;

    public function __construct()
    {
        $this->securityService = new SecurityService();
        $this->encryptionService = new EncryptionService();
        $this->accessControlService = new AccessControlService();
    }

    /**
     * Show security dashboard.
     */
    public function showSecurityDashboard()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'security_dashboard')) {
                \Flight::halt(403, 'Access denied');
            }

            $dashboard = $this->securityService->getSecurityDashboard();

            \Flight::render('security/index', [
                'dashboard' => $dashboard,
                'page_title' => 'Security Dashboard',
            ], 'content');

            \Flight::render('layouts/main', [
                'page_title' => 'Security Dashboard - BangronDB Admin',
                'content' => \Flight::get('content'),
            ]);
        } catch (Exception $e) {
            \Flight::halt(500, 'Internal server error');
        }
    }

    /**
     * Get security dashboard data.
     */
    public function getSecurityDashboardData()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'security_dashboard')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $dashboard = $this->securityService->getSecurityDashboard();
            \Flight::json($dashboard);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get security events.
     */
    public function getSecurityEvents()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'security_events')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $limit = \Flight::request()->query->limit ?? 50;
            $filters = \Flight::request()->query->getData();

            $events = $this->securityService->getSecurityEvents($limit, $filters);
            \Flight::json($events);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle security event.
     */
    public function handleSecurityEvent()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'security_events')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $eventId = $data->eventId;
            $status = $data->status;
            $resolutionNotes = $data->resolutionNotes ?? null;
            $handledBy = $_SESSION['user_id'];

            $result = $this->securityService->handleSecurityEvent($eventId, $status, $resolutionNotes, $handledBy);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'Security event handled successfully']);
            } else {
                \Flight::json(['error' => 'Failed to handle security event'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Generate security report.
     */
    public function generateSecurityReport()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'security_reports')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $filters = \Flight::request()->query->getData();
            $report = $this->securityService->generateSecurityReport($filters);

            \Flight::json($report);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get encryption keys status.
     */
    public function getEncryptionKeysStatus()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'encryption_keys')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $status = $this->encryptionService->getEncryptionKeysStatus();
            \Flight::json($status);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Generate encryption key.
     */
    public function generateEncryptionKey()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'encryption_keys')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $type = $data->type;
            $collection = $data->collection ?? null;
            $field = $data->field ?? null;

            $key = null;

            switch ($type) {
                case 'collection':
                    $key = $this->encryptionService->generateCollectionKey($collection, $data->fields ?? []);
                    break;

                case 'field':
                    $key = $this->encryptionService->generateFieldKey($collection, $field);
                    break;

                case 'client_side':
                    $key = $this->encryptionService->createClientSideKey($_SESSION['user_id'], $data->permissions ?? []);
                    break;

                default:
                    \Flight::json(['error' => 'Invalid key type'], 400);

                    return;
            }

            \Flight::json(['success' => true, 'key' => $key]);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Failed to generate encryption key: '.$e->getMessage()], 500);
        }
    }

    /**
     * Rotate encryption keys.
     */
    public function rotateEncryptionKeys()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'encryption_keys')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $keyIds = $data->keyIds ?? [];

            $results = $this->encryptionService->rotateKeys($keyIds);

            \Flight::json(['success' => true, 'results' => $results]);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Failed to rotate encryption keys: '.$e->getMessage()], 500);
        }
    }

    /**
     * Apply encryption to collection.
     */
    public function applyEncryptionToCollection()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'encryption')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $collection = $data->collection;
            $fields = $data->fields;

            $results = $this->encryptionService->applyEncryptionToCollection($collection, $fields);

            \Flight::json(['success' => true, 'results' => $results]);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Failed to apply encryption: '.$e->getMessage()], 500);
        }
    }

    /**
     * Encrypt field.
     */
    public function encryptField()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'encryption')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $fieldData = $data->data;
            $collection = $data->collection;
            $field = $data->field;
            $keyId = $data->keyId ?? null;

            $encrypted = $this->encryptionService->encryptField($fieldData, $collection, $field, $keyId);

            \Flight::json(['success' => true, 'encrypted' => $encrypted]);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Failed to encrypt field: '.$e->getMessage()], 500);
        }
    }

    /**
     * Decrypt field.
     */
    public function decryptField()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'encryption')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $encryptedData = $data->encryptedData;
            $keyId = $data->keyId ?? null;

            $decrypted = $this->encryptionService->decryptField($encryptedData, $keyId);

            \Flight::json(['success' => true, 'decrypted' => $decrypted]);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Failed to decrypt field: '.$e->getMessage()], 500);
        }
    }

    /**
     * Mask data.
     */
    public function maskData()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'encryption')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $fieldData = $data->data;
            $maskingType = $data->maskingType ?? 'partial';

            $masked = $this->encryptionService->maskData($fieldData, $maskingType);

            \Flight::json(['success' => true, 'masked' => $masked]);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Failed to mask data: '.$e->getMessage()], 500);
        }
    }

    /**
     * Check user permission.
     */
    public function checkPermission()
    {
        try {
            $data = \Flight::request()->data;
            $userId = $data->userId ?? $_SESSION['user_id'];
            $action = $data->action;
            $resource = $data->resource;
            $context = $data->context ?? [];

            $hasPermission = $this->accessControlService->checkPermission($userId, $action, $resource, $context);

            \Flight::json(['hasPermission' => $hasPermission]);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Grant user permission.
     */
    public function grantUserPermission()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'permissions')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $userId = $data->userId;
            $action = $data->action;
            $resource = $data->resource;
            $conditions = $data->conditions ?? [];

            $result = $this->accessControlService->grantUserPermission($userId, $action, $resource, $conditions);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'Permission granted successfully']);
            } else {
                \Flight::json(['error' => 'Failed to grant permission'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Revoke user permission.
     */
    public function revokeUserPermission()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'permissions')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $userId = $data->userId;
            $action = $data->action;
            $resource = $data->resource;

            $result = $this->accessControlService->revokeUserPermission($userId, $action, $resource);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'Permission revoked successfully']);
            } else {
                \Flight::json(['error' => 'Failed to revoke permission'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Grant user role.
     */
    public function grantUserRole()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'roles')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $userId = $data->userId;
            $roleId = $data->roleId;

            $result = $this->accessControlService->grantUserRole($userId, $roleId);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'Role granted successfully']);
            } else {
                \Flight::json(['error' => 'Failed to grant role'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Revoke user role.
     */
    public function revokeUserRole()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'roles')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $userId = $data->userId;
            $roleId = $data->roleId;

            $result = $this->accessControlService->revokeUserRole($userId, $roleId);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'Role revoked successfully']);
            } else {
                \Flight::json(['error' => 'Failed to revoke role'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Create ABAC policy.
     */
    public function createABACPolicy()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'policies')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;

            $result = $this->accessControlService->createABACPolicy($data);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'ABAC policy created successfully']);
            } else {
                \Flight::json(['error' => 'Failed to create ABAC policy'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update ABAC policy.
     */
    public function updateABACPolicy()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'policies')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $policyId = $data->policyId;

            $result = $this->accessControlService->updateABACPolicy($policyId, $data);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'ABAC policy updated successfully']);
            } else {
                \Flight::json(['error' => 'Failed to update ABAC policy'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Delete ABAC policy.
     */
    public function deleteABACPolicy()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'policies')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $policyId = $data->policyId;

            $result = $this->accessControlService->deleteABACPolicy($policyId);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'ABAC policy deleted successfully']);
            } else {
                \Flight::json(['error' => 'Failed to delete ABAC policy'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Request just-in-time access.
     */
    public function requestJustInTimeAccess()
    {
        try {
            $data = \Flight::request()->data;
            $userId = $data->userId ?? $_SESSION['user_id'];
            $action = $data->action;
            $resource = $data->resource;
            $context = $data->context ?? [];
            $reason = $data->reason ?? null;

            $result = $this->accessControlService->requestJustInTimeAccess($userId, $action, $resource, $context, $reason);

            \Flight::json($result);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Approve just-in-time access.
     */
    public function approveJustInTimeAccess()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'access_requests')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $requestId = $data->requestId;
            $approvedBy = $_SESSION['user_id'];
            $durationHours = $data->durationHours ?? 24;

            $result = $this->accessControlService->approveJustInTimeAccess($requestId, $approvedBy, $durationHours);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'Access request approved successfully']);
            } else {
                \Flight::json(['error' => 'Failed to approve access request'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Deny just-in-time access.
     */
    public function denyJustInTimeAccess()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'write', 'access_requests')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->data;
            $requestId = $data->requestId;
            $deniedBy = $_SESSION['user_id'];
            $reason = $data->reason ?? null;

            $result = $this->accessControlService->denyJustInTimeAccess($requestId, $deniedBy, $reason);

            if ($result) {
                \Flight::json(['success' => true, 'message' => 'Access request denied successfully']);
            } else {
                \Flight::json(['error' => 'Failed to deny access request'], 400);
            }
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get access control dashboard.
     */
    public function getAccessControlDashboard()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'access_control')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $dashboard = $this->accessControlService->getAccessControlDashboard();
            \Flight::json($dashboard);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get user permissions summary.
     */
    public function getUserPermissionsSummary()
    {
        try {
            // Check if user has permission
            if (!$this->accessControlService->checkPermission($_SESSION['user_id'], 'read', 'permissions')) {
                \Flight::json(['error' => 'Access denied'], 403);

                return;
            }

            $data = \Flight::request()->query;
            $userId = $data->userId ?? $_SESSION['user_id'];

            $summary = $this->accessControlService->getUserPermissionsSummary($userId);
            \Flight::json($summary);
        } catch (Exception $e) {
            \Flight::json(['error' => 'Internal server error'], 500);
        }
    }
}
