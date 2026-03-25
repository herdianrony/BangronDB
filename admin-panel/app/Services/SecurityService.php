<?php

namespace App\Services;

class SecurityService
{
    private $systemDb;
    private $encryptionService;
    private $auditService;
    private $config;

    public function __construct()
    {
        $this->systemDb = (new SystemService())->systemDb();
        $this->encryptionService = new EncryptionService();
        $this->auditService = new AuditService();
        $this->config = include __DIR__.'/../config/security.php';
    }

    /**
     * Security Dashboard Overview.
     */
    public function getSecurityDashboard(): array
    {
        try {
            $today = new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000);
            $weekAgo = new MongoDB\BSON\UTCDateTime((time() - 7 * 24 * 60 * 60) * 1000);

            // Security metrics
            $securityMetrics = [
                'total_encrypted_fields' => $this->getTotalEncryptedFields(),
                'active_encryption_keys' => $this->getActiveEncryptionKeys(),
                'security_events_today' => $this->systemDb->security_events->count([
                    'timestamp' => ['$gte' => $today],
                ]),
                'failed_login_attempts' => $this->systemDb->security_events->count([
                    'timestamp' => ['$gte' => $today],
                    'event_type' => 'failed_login',
                ]),
                'suspicious_activities' => $this->systemDb->security_events->count([
                    'timestamp' => ['$gte' => $today],
                    'event_type' => 'suspicious_activity',
                ]),
                'encryption_compliance' => $this->getEncryptionCompliance(),
                'access_control_status' => $this->getAccessControlStatus(),
                'threat_detection_status' => $this->getThreatDetectionStatus(),
            ];

            // Recent security events
            $recentEvents = $this->systemDb->security_events
                ->find([])
                ->sort(['timestamp' => -1])
                ->limit(10)
                ->toArray();

            // Encryption status by collection
            $encryptionStatus = $this->getEncryptionStatusByCollection();

            // Threat intelligence
            $threatIntelligence = $this->getThreatIntelligence();

            return [
                'metrics' => $securityMetrics,
                'recent_events' => array_map(function ($event) {
                    return [
                        'id' => (string) $event['_id'],
                        'event_type' => $event['event_type'],
                        'severity' => $event['severity'],
                        'description' => $event['description'],
                        'source_ip' => $event['source_ip'] ?? null,
                        'timestamp' => $event['timestamp']->toDateTime()->format('Y-m-d H:i:s'),
                        'status' => $event['status'] ?? 'pending',
                    ];
                }, $recentEvents),
                'encryption_status' => $encryptionStatus,
                'threat_intelligence' => $threatIntelligence,
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get security dashboard: '.$e->getMessage());

            return [
                'metrics' => [],
                'recent_events' => [],
                'encryption_status' => [],
                'threat_intelligence' => [],
                'last_updated' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get total encrypted fields.
     */
    private function getTotalEncryptedFields(): int
    {
        try {
            $total = 0;
            $collections = $this->systemDb->collections->find(['encrypted_fields' => ['$exists' => true]])->toArray();

            foreach ($collections as $collection) {
                if (isset($collection['encrypted_fields']) && is_array($collection['encrypted_fields'])) {
                    $total += count($collection['encrypted_fields']);
                }
            }

            return $total;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get total encrypted fields: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Get active encryption keys.
     */
    private function getActiveEncryptionKeys(): int
    {
        try {
            return $this->systemDb->encryption_keys->count([
                'status' => 'active',
                'expires_at' => ['$gt' => new MongoDB\BSON\UTCDateTime()],
            ]);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get active encryption keys: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Get encryption compliance status.
     */
    private function getEncryptionCompliance(): array
    {
        try {
            $compliance = [
                'gdpr' => $this->checkGDPRCompliance(),
                'hipaa' => $this->checkHIPAACompliance(),
                'pci_dss' => $this->checkPCIDSSCompliance(),
                'iso_27001' => $this->checkISO27001Compliance(),
            ];

            $overall = array_sum($compliance) / count($compliance);

            return [
                'overall_score' => round($overall, 2),
                'standards' => $compliance,
                'last_assessment' => date('Y-m-d H:i:s'),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get encryption compliance: '.$e->getMessage());

            return [
                'overall_score' => 0,
                'standards' => [],
                'last_assessment' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check GDPR compliance.
     */
    private function checkGDPRCompliance(): float
    {
        try {
            $checks = [
                'data_encryption' => $this->systemDb->encryption_keys->count(['type' => 'field_level']) > 0,
                'access_controls' => $this->systemDb->access_controls->count(['active' => true]) > 0,
                'audit_logging' => $this->systemDb->audit_logs->count(['timestamp' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 30 * 24 * 60 * 60) * 1000)]]) > 0,
                'data_subject_rights' => $this->systemDb->data_subject_requests->count() > 0,
            ];

            return (array_sum($checks) / count($checks)) * 100;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check GDPR compliance: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Check HIPAA compliance.
     */
    private function checkHIPAACompliance(): float
    {
        try {
            $checks = [
                'phi_encryption' => $this->systemDb->encryption_keys->count(['type' => 'phi']) > 0,
                'access_audits' => $this->systemDb->audit_logs->count(['action' => ['$in' => ['access_phi', 'modify_phi']]]) > 0,
                'security_training' => $this->systemDb->compliance_training->count(['type' => 'hipaa', 'completed' => true]) > 0,
                'risk_assessment' => $this->systemDb->risk_assessments->count(['standard' => 'hipaa']) > 0,
            ];

            return (array_sum($checks) / count($checks)) * 100;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check HIPAA compliance: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Check PCI DSS compliance.
     */
    private function checkPCIDSSCompliance(): float
    {
        try {
            $checks = [
                'card_data_encryption' => $this->systemDb->encryption_keys->count(['type' => 'card_data']) > 0,
                'access_restrictions' => $this->systemDb->access_controls->count(['pci_dss' => true, 'active' => true]) > 0,
                'network_security' => $this->systemDb->network_security->count(['compliant' => true]) > 0,
                'vulnerability_scans' => $this->systemDb->vulnerability_scans->count(['standard' => 'pci_dss', 'passed' => true]) > 0,
            ];

            return (array_sum($checks) / count($checks)) * 100;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check PCI DSS compliance: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Check ISO 27001 compliance.
     */
    private function checkISO27001Compliance(): float
    {
        try {
            $checks = [
                'information_security_policy' => $this->systemDb->security_policies->count(['standard' => 'iso_27001', 'approved' => true]) > 0,
                'risk_management' => $this->systemDb->risk_assessments->count(['standard' => 'iso_27001']) > 0,
                'incident_management' => $this->systemDb->incident_management->count(['compliant' => true]) > 0,
                'business_continuity' => $this->systemDb->business_continuity->count(['certified' => true]) > 0,
            ];

            return (array_sum($checks) / count($checks)) * 100;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check ISO 27001 compliance: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Get access control status.
     */
    private function getAccessControlStatus(): array
    {
        try {
            $totalUsers = $this->systemDb->users->count();
            $activeUsers = $this->systemDb->users->count(['status' => 'active']);
            $privilegedUsers = $this->systemDb->users->count(['roles' => ['$in' => ['admin', 'super_admin']]]);
            $mfaEnabled = $this->systemDb->users->count(['two_factor_enabled' => true]);

            return [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'privileged_users' => $privilegedUsers,
                'mfa_enabled' => $mfaEnabled,
                'mfa_coverage' => $totalUsers > 0 ? round(($mfaEnabled / $totalUsers) * 100, 2) : 0,
                'last_audit' => date('Y-m-d H:i:s'),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get access control status: '.$e->getMessage());

            return [
                'total_users' => 0,
                'active_users' => 0,
                'privileged_users' => 0,
                'mfa_enabled' => 0,
                'mfa_coverage' => 0,
                'last_audit' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get threat detection status.
     */
    private function getThreatDetectionStatus(): array
    {
        try {
            $activeThreats = $this->systemDb->threat_detection->count([
                'status' => 'active',
                'created_at' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 24 * 60 * 60) * 1000)],
            ]);

            $blockedAttempts = $this->systemDb->threat_detection->count([
                'action' => 'blocked',
                'created_at' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 24 * 60 * 60) * 1000)],
            ]);

            $rulesEnabled = $this->systemDb->threat_rules->count(['enabled' => true]);

            return [
                'active_threats' => $activeThreats,
                'blocked_attempts' => $blockedAttempts,
                'rules_enabled' => $rulesEnabled,
                'detection_rate' => $this->calculateDetectionRate(),
                'last_update' => date('Y-m-d H:i:s'),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get threat detection status: '.$e->getMessage());

            return [
                'active_threats' => 0,
                'blocked_attempts' => 0,
                'rules_enabled' => 0,
                'detection_rate' => 0,
                'last_update' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate threat detection rate.
     */
    private function calculateDetectionRate(): float
    {
        try {
            $totalThreats = $this->systemDb->threat_detection->count([
                'created_at' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 30 * 24 * 60 * 60) * 1000)],
            ]);

            $detectedThreats = $this->systemDb->threat_detection->count([
                'status' => 'detected',
                'created_at' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 30 * 24 * 60 * 60) * 1000)],
            ]);

            return $totalThreats > 0 ? round(($detectedThreats / $totalThreats) * 100, 2) : 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to calculate detection rate: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Get encryption status by collection.
     */
    private function getEncryptionStatusByCollection(): array
    {
        try {
            $collections = $this->systemDb->collections->find()->toArray();
            $status = [];

            foreach ($collections as $collection) {
                $collectionName = $collection['name'] ?? 'unknown';
                $encryptedFields = $collection['encrypted_fields'] ?? [];
                $totalFields = $collection['schema']['fields'] ?? [];

                $status[] = [
                    'collection' => $collectionName,
                    'total_fields' => count($totalFields),
                    'encrypted_fields' => count($encryptedFields),
                    'encryption_percentage' => count($totalFields) > 0 ? round((count($encryptedFields) / count($totalFields)) * 100, 2) : 0,
                    'last_encrypted' => $collection['last_encrypted'] ?? null,
                    'encryption_type' => $collection['encryption_type'] ?? 'none',
                ];
            }

            return $status;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get encryption status by collection: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get threat intelligence.
     */
    private function getThreatIntelligence(): array
    {
        try {
            $threats = $this->systemDb->threat_intelligence->find([
                'active' => true,
                'created_at' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 7 * 24 * 60 * 60) * 1000)],
            ])->toArray();

            return array_map(function ($threat) {
                return [
                    'id' => (string) $threat['_id'],
                    'threat_type' => $threat['threat_type'],
                    'severity' => $threat['severity'],
                    'description' => $threat['description'],
                    'source' => $threat['source'],
                    'first_seen' => $threat['first_seen']->toDateTime()->format('Y-m-d H:i:s'),
                    'last_seen' => $threat['last_seen']->toDateTime()->format('Y-m-d H:i:s'),
                    'affected_systems' => $threat['affected_systems'] ?? [],
                    'mitigation' => $threat['mitigation'] ?? [],
                ];
            }, $threats);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get threat intelligence: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Log security event.
     */
    public function logSecurityEvent(string $eventType, string $severity, string $description, array $context = []): void
    {
        try {
            $event = [
                'event_type' => $eventType,
                'severity' => $severity,
                'description' => $description,
                'context' => $context,
                'source_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'user_id' => $_SESSION['user_id'] ?? null,
                'session_id' => session_id(),
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'status' => 'pending',
                'handled_by' => null,
                'resolution_notes' => null,
            ];

            $this->systemDb->security_events->insertOne($event);

            // Trigger threat detection if needed
            $this->triggerThreatDetection($eventType, $context);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to log security event: '.$e->getMessage());
        }
    }

    /**
     * Trigger threat detection.
     */
    private function triggerThreatDetection(string $eventType, array $context): void
    {
        try {
            // Check for brute force patterns
            if ($eventType === 'failed_login') {
                $this->checkBruteForceAttack($context);
            }

            // Check for suspicious patterns
            if ($eventType === 'data_access') {
                $this->checkSuspiciousDataAccess($context);
            }

            // Check for SQL injection attempts
            if (strpos($eventType, 'sql') !== false) {
                $this->checkSQLInjection($context);
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to trigger threat detection: '.$e->getMessage());
        }
    }

    /**
     * Check for brute force attacks.
     */
    private function checkBruteForceAttack(array $context): void
    {
        try {
            $ipAddress = $context['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $timeWindow = 15 * 60; // 15 minutes
            $maxAttempts = 5;

            $attempts = $this->systemDb->security_events->count([
                'event_type' => 'failed_login',
                'source_ip' => $ipAddress,
                'timestamp' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - $timeWindow) * 1000)],
            ]);

            if ($attempts >= $maxAttempts) {
                $this->logSecurityEvent('brute_force_detected', 'high', 'Brute force attack detected from IP: '.$ipAddress, [
                    'ip_address' => $ipAddress,
                    'attempts' => $attempts,
                    'time_window' => $timeWindow,
                ]);

                // Block the IP
                $this->blockIPAddress($ipAddress);
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check brute force: '.$e->getMessage());
        }
    }

    /**
     * Check for suspicious data access.
     */
    private function checkSuspiciousDataAccess(array $context): void
    {
        try {
            $userId = $context['user_id'] ?? null;
            $collection = $context['collection'] ?? null;
            $operation = $context['operation'] ?? null;

            if ($userId && $collection && $operation) {
                // Check for unusual access patterns
                $recentAccess = $this->systemDb->security_events->count([
                    'user_id' => $userId,
                    'event_type' => 'data_access',
                    'context.collection' => $collection,
                    'timestamp' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 60 * 60) * 1000)], // 1 hour
                ]);

                if ($recentAccess > 100) {
                    $this->logSecurityEvent('suspicious_activity', 'medium', 'Unusual data access pattern detected', [
                        'user_id' => $userId,
                        'collection' => $collection,
                        'operation' => $operation,
                        'access_count' => $recentAccess,
                    ]);
                }
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check suspicious data access: '.$e->getMessage());
        }
    }

    /**
     * Check for SQL injection attempts.
     */
    private function checkSQLInjection(array $context): void
    {
        try {
            $query = $context['query'] ?? '';
            $suspiciousPatterns = [
                '/union\s+select/i',
                '/or\s+1\s*=\s*1/i',
                '/drop\s+table/i',
                '/exec\s*\(/i',
                '/--/i',
                '/\/\*/i',
                '/waitfor\s+delay/i',
            ];

            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $query)) {
                    $this->logSecurityEvent('sql_injection_attempt', 'high', 'SQL injection attempt detected', [
                        'query' => $query,
                        'pattern' => $pattern,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ]);
                    break;
                }
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check SQL injection: '.$e->getMessage());
        }
    }

    /**
     * Block IP address.
     */
    private function blockIPAddress(string $ipAddress): void
    {
        try {
            $block = [
                'ip_address' => $ipAddress,
                'blocked_at' => new MongoDB\BSON\UTCDateTime(),
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + 24 * 60 * 60) * 1000), // 24 hours
                'reason' => 'security violation',
                'status' => 'active',
            ];

            $this->systemDb->blocked_ips->insertOne($block);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to block IP address: '.$e->getMessage());
        }
    }

    /**
     * Get security events.
     */
    public function getSecurityEvents(int $limit = 50, array $filters = []): array
    {
        try {
            $query = [];

            if (!empty($filters['event_type'])) {
                $query['event_type'] = $filters['event_type'];
            }

            if (!empty($filters['severity'])) {
                $query['severity'] = $filters['severity'];
            }

            if (!empty($filters['date_from'])) {
                $query['timestamp'] = ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime($filters['date_from']) * 1000)];
            }

            if (!empty($filters['date_to'])) {
                $query['timestamp'] = ['$lte' => new MongoDB\BSON\UTCDateTime(strtotime($filters['date_to'].' 23:59:59') * 1000)];
            }

            if (!empty($filters['ip_address'])) {
                $query['source_ip'] = $filters['ip_address'];
            }

            if (!empty($filters['user_id'])) {
                $query['user_id'] = $filters['user_id'];
            }

            $events = $this->systemDb->security_events
                ->find($query)
                ->sort(['timestamp' => -1])
                ->limit($limit)
                ->toArray();

            return array_map(function ($event) {
                return [
                    'id' => (string) $event['_id'],
                    'event_type' => $event['event_type'],
                    'severity' => $event['severity'],
                    'description' => $event['description'],
                    'context' => $event['context'] ?? [],
                    'source_ip' => $event['source_ip'] ?? null,
                    'user_agent' => $event['user_agent'] ?? null,
                    'user_id' => $event['user_id'] ?? null,
                    'session_id' => $event['session_id'] ?? null,
                    'timestamp' => $event['timestamp']->toDateTime()->format('Y-m-d H:i:s'),
                    'status' => $event['status'] ?? 'pending',
                    'handled_by' => $event['handled_by'] ?? null,
                    'resolution_notes' => $event['resolution_notes'] ?? null,
                ];
            }, $events);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get security events: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Handle security event.
     */
    public function handleSecurityEvent(string $eventId, string $status, ?string $resolutionNotes = null, ?string $handledBy = null): bool
    {
        try {
            $update = [
                'status' => $status,
                'resolution_notes' => $resolutionNotes,
                'handled_by' => $handledBy,
                'handled_at' => new MongoDB\BSON\UTCDateTime(),
            ];

            $result = $this->systemDb->security_events->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($eventId)],
                ['$set' => $update]
            );

            return $result->getModifiedCount() > 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to handle security event: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Generate security report.
     */
    public function generateSecurityReport(array $filters = []): array
    {
        try {
            $report = [
                'generated_at' => date('Y-m-d H:i:s'),
                'period' => $filters,
                'summary' => $this->getSecurityDashboard()['metrics'],
                'events' => $this->getSecurityEvents(100, $filters),
                'compliance' => $this->getEncryptionCompliance(),
                'threat_intelligence' => $this->getThreatIntelligence(),
                'recommendations' => $this->generateSecurityRecommendations(),
            ];

            return $report;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to generate security report: '.$e->getMessage());

            return [
                'generated_at' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate security recommendations.
     */
    private function generateSecurityRecommendations(): array
    {
        try {
            $recommendations = [];

            // Check encryption coverage
            $encryptionStatus = $this->getEncryptionStatusByCollection();
            $lowEncryptionCollections = array_filter($encryptionStatus, function ($collection) {
                return $collection['encryption_percentage'] < 50;
            });

            if (!empty($lowEncryptionCollections)) {
                $recommendations[] = [
                    'type' => 'encryption',
                    'priority' => 'high',
                    'title' => 'Low Encryption Coverage',
                    'description' => 'Some collections have less than 50% encryption coverage',
                    'collections' => array_column($lowEncryptionCollections, 'collection'),
                    'action' => 'Increase encryption coverage for sensitive data',
                ];
            }

            // Check MFA coverage
            $accessStatus = $this->getAccessControlStatus();
            if ($accessStatus['mfa_coverage'] < 80) {
                $recommendations[] = [
                    'type' => 'authentication',
                    'priority' => 'high',
                    'title' => 'Multi-Factor Authentication',
                    'description' => 'Multi-factor authentication coverage is below 80%',
                    'current_coverage' => $accessStatus['mfa_coverage'].'%',
                    'action' => 'Enable MFA for more users',
                ];
            }

            // Check threat detection
            $threatStatus = $this->getThreatDetectionStatus();
            if ($threatStatus['detection_rate'] < 90) {
                $recommendations[] = [
                    'type' => 'threat_detection',
                    'priority' => 'medium',
                    'title' => 'Threat Detection Rate',
                    'description' => 'Threat detection rate is below 90%',
                    'current_rate' => $threatStatus['detection_rate'].'%',
                    'action' => 'Improve threat detection rules and monitoring',
                ];
            }

            // Check compliance
            $compliance = $this->getEncryptionCompliance();
            if ($compliance['overall_score'] < 90) {
                $recommendations[] = [
                    'type' => 'compliance',
                    'priority' => 'medium',
                    'title' => 'Compliance Score',
                    'description' => 'Overall compliance score is below 90%',
                    'current_score' => $compliance['overall_score'].'%',
                    'action' => 'Address compliance gaps for all standards',
                ];
            }

            return $recommendations;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to generate security recommendations: '.$e->getMessage());

            return [];
        }
    }
}
