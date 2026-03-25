<?php

namespace App\Services;

class ThreatDetectionService
{
    private $systemDb;
    private $config;
    private $cache;
    private $rulesEngine;

    public function __construct()
    {
        $this->systemDb = (new SystemService())->systemDb();
        $this->config = include __DIR__.'/../config/security.php';
        $this->cache = [];
        $this->rulesEngine = new ThreatRulesEngine();
    }

    /**
     * Initialize threat detection system.
     */
    public function initialize(): bool
    {
        try {
            // Create necessary collections if they don't exist
            $this->ensureCollections();

            // Load threat detection rules
            $this->loadThreatRules();

            // Initialize threat intelligence feeds
            $this->initializeThreatIntelligence();

            return true;
        } catch (Exception $e) {
            error_log('Failed to initialize threat detection: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Ensure necessary collections exist.
     */
    private function ensureCollections(): void
    {
        $collections = [
            'threat_detection' => [
                'event_type' => 'string',
                'severity' => 'string',
                'description' => 'string',
                'source_ip' => 'string',
                'user_agent' => 'string',
                'user_id' => 'string',
                'action' => 'string',
                'resource' => 'string',
                'context' => 'array',
                'detection_rules' => 'array',
                'confidence_score' => 'float',
                'status' => 'string',
                'created_at' => 'date',
                'updated_at' => 'date',
                'resolved_at' => 'date',
                'resolved_by' => 'string',
                'resolution_notes' => 'string',
            ],
            'threat_rules' => [
                'name' => 'string',
                'description' => 'string',
                'type' => 'string',
                'conditions' => 'array',
                'actions' => 'array',
                'enabled' => 'boolean',
                'severity' => 'string',
                'threshold' => 'integer',
                'time_window' => 'integer',
                'created_at' => 'date',
                'updated_at' => 'date',
                'created_by' => 'string',
            ],
            'threat_intelligence' => [
                'threat_type' => 'string',
                'severity' => 'string',
                'description' => 'string',
                'source' => 'string',
                'indicators' => 'array',
                'affected_systems' => 'array',
                'mitigation' => 'array',
                'confidence' => 'float',
                'first_seen' => 'date',
                'last_seen' => 'date',
                'active' => 'boolean',
                'created_at' => 'date',
                'updated_at' => 'date',
            ],
            'blocked_ips' => [
                'ip_address' => 'string',
                'reason' => 'string',
                'blocked_at' => 'date',
                'expires_at' => 'date',
                'status' => 'string',
                'attempts' => 'integer',
                'user_agent' => 'string',
            ],
            'suspicious_activities' => [
                'activity_type' => 'string',
                'severity' => 'string',
                'description' => 'string',
                'user_id' => 'string',
                'ip_address' => 'string',
                'context' => 'array',
                'patterns' => 'array',
                'confidence_score' => 'float',
                'status' => 'string',
                'created_at' => 'date',
                'investigated_at' => 'date',
                'investigated_by' => 'string',
                'resolution' => 'string',
            ],
            'security_incidents' => [
                'incident_type' => 'string',
                'severity' => 'string',
                'description' => 'string',
                'affected_systems' => 'array',
                'start_time' => 'date',
                'end_time' => 'date',
                'status' => 'string',
                'response_actions' => 'array',
                'resolved_at' => 'date',
                'resolved_by' => 'string',
                'lessons_learned' => 'string',
                'created_at' => 'date',
            ],
        ];

        foreach ($collections as $collectionName => $schema) {
            if (!$this->systemDb->listCollections()->findOne(['name' => $collectionName])) {
                $this->systemDb->createCollection($collectionName);
            }
        }
    }

    /**
     * Load threat detection rules.
     */
    private function loadThreatRules(): void
    {
        try {
            // Default threat detection rules
            $defaultRules = [
                [
                    'name' => 'Brute Force Detection',
                    'description' => 'Detect brute force attacks on authentication endpoints',
                    'type' => 'pattern_based',
                    'conditions' => [
                        [
                            'field' => 'event_type',
                            'operator' => 'equals',
                            'value' => 'failed_login',
                        ],
                        [
                            'field' => 'source_ip',
                            'operator' => 'ip_in_range',
                            'value' => '192.168.1.0/24',
                        ],
                    ],
                    'actions' => [
                        'block_ip',
                        'alert_admin',
                        'log_incident',
                    ],
                    'enabled' => true,
                    'severity' => 'high',
                    'threshold' => 5,
                    'time_window' => 900, // 15 minutes
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'created_by' => 'system',
                ],
                [
                    'name' => 'SQL Injection Detection',
                    'description' => 'Detect SQL injection attempts',
                    'type' => 'pattern_based',
                    'conditions' => [
                        [
                            'field' => 'query',
                            'operator' => 'regex',
                            'value' => '/(union\\s+select|or\\s+1\\s*=\\s*1|drop\\s+table|exec\\s*\\()/i',
                        ],
                    ],
                    'actions' => [
                        'block_request',
                        'alert_admin',
                        'log_incident',
                    ],
                    'enabled' => true,
                    'severity' => 'critical',
                    'threshold' => 1,
                    'time_window' => 60, // 1 minute
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'created_by' => 'system',
                ],
                [
                    'name' => 'XSS Detection',
                    'description' => 'Detect cross-site scripting attempts',
                    'type' => 'pattern_based',
                    'conditions' => [
                        [
                            'field' => 'input',
                            'operator' => 'regex',
                            'value' => '/(<script|javascript:|onload=|onerror=)/i',
                        ],
                    ],
                    'actions' => [
                        'sanitize_input',
                        'alert_admin',
                        'log_incident',
                    ],
                    'enabled' => true,
                    'severity' => 'high',
                    'threshold' => 1,
                    'time_window' => 60, // 1 minute
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'created_by' => 'system',
                ],
                [
                    'name' => 'Anomalous Data Access',
                    'description' => 'Detect unusual data access patterns',
                    'type' => 'behavioral',
                    'conditions' => [
                        [
                            'field' => 'user_id',
                            'operator' => 'not_equals',
                            'value' => 'system',
                        ],
                        [
                            'field' => 'access_count',
                            'operator' => 'greater_than',
                            'value' => 100,
                        ],
                        [
                            'field' => 'time_window',
                            'operator' => 'less_than',
                            'value' => 3600,
                        ],
                    ],
                    'actions' => [
                        'alert_admin',
                        'require_approval',
                        'monitor_activity',
                    ],
                    'enabled' => true,
                    'severity' => 'medium',
                    'threshold' => 100,
                    'time_window' => 3600, // 1 hour
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'created_by' => 'system',
                ],
                [
                    'name' => 'Privilege Escalation',
                    'description' => 'Detect privilege escalation attempts',
                    'type' => 'anomaly',
                    'conditions' => [
                        [
                            'field' => 'action',
                            'operator' => 'in',
                            'value' => ['admin_access', 'system_config', 'user_management'],
                        ],
                        [
                            'field' => 'user_role',
                            'operator' => 'not_in',
                            'value' => ['admin', 'super_admin'],
                        ],
                    ],
                    'actions' => [
                        'block_access',
                        'alert_admin',
                        'investigate_immediately',
                    ],
                    'enabled' => true,
                    'severity' => 'critical',
                    'threshold' => 1,
                    'time_window' => 60, // 1 minute
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'created_by' => 'system',
                ],
            ];

            // Insert default rules if they don't exist
            foreach ($defaultRules as $rule) {
                $existingRule = $this->systemDb->threat_rules->findOne(['name' => $rule['name']]);
                if (!$existingRule) {
                    $this->systemDb->threat_rules->insertOne($rule);
                }
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to load threat rules: '.$e->getMessage());
        }
    }

    /**
     * Initialize threat intelligence feeds.
     */
    private function initializeThreatIntelligence(): void
    {
        try {
            // Sample threat intelligence data
            $threatIntelligence = [
                [
                    'threat_type' => 'malware_campaign',
                    'severity' => 'high',
                    'description' => 'Active malware campaign targeting web applications',
                    'source' => 'threat_feed_1',
                    'indicators' => [
                        'ip_addresses' => ['192.168.100.100', '10.0.0.50'],
                        'domains' => ['malicious-domain.com', 'phishing-site.net'],
                        'hashes' => ['d41d8cd98f00b204e9800998ecf8427e', '5d41402abc4b2a76b9719d911017c592'],
                    ],
                    'affected_systems' => ['web_servers', 'application_servers'],
                    'mitigation' => [
                        'block_ip_addresses',
                        'update_firewall_rules',
                        'patch_systems',
                    ],
                    'confidence' => 0.95,
                    'first_seen' => new MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000),
                    'last_seen' => new MongoDB\BSON\UTCDateTime(),
                    'active' => true,
                    'created_at' => new MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000),
                    'updated_at' => new MongoDB\BSON\UTCDateTime(),
                ],
                [
                    'threat_type' => 'credential_stuffing',
                    'severity' => 'medium',
                    'description' => 'Credential stuffing attack using breached credentials',
                    'source' => 'threat_feed_2',
                    'indicators' => [
                        'user_agents' => ['Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)'],
                        'patterns' => ['sequential_login_attempts', 'multiple_failed_logins'],
                    ],
                    'affected_systems' => ['authentication_systems'],
                    'mitigation' => [
                        'implement_rate_limiting',
                        'enable_mfa',
                        'monitor_failed_logins',
                    ],
                    'confidence' => 0.85,
                    'first_seen' => new MongoDB\BSON\UTCDateTime(strtotime('-3 days') * 1000),
                    'last_seen' => new MongoDB\BSON\UTCDateTime(strtotime('-1 day') * 1000),
                    'active' => true,
                    'created_at' => new MongoDB\BSON\UTCDateTime(strtotime('-3 days') * 1000),
                    'updated_at' => new MongoDB\BSON\UTCDateTime(strtotime('-1 day') * 1000),
                ],
            ];

            // Insert threat intelligence data
            foreach ($threatIntelligence as $threat) {
                $existingThreat = $this->systemDb->threat_intelligence->findOne([
                    'threat_type' => $threat['threat_type'],
                    'source' => $threat['source'],
                ]);

                if (!$existingThreat) {
                    $this->systemDb->threat_intelligence->insertOne($threat);
                }
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to initialize threat intelligence: '.$e->getMessage());
        }
    }

    /**
     * Analyze event for threats.
     */
    public function analyzeEvent(array $event): array
    {
        try {
            $threats = [];

            // Check against threat detection rules
            $threats = $this->checkAgainstRules($event);

            // Check against threat intelligence
            $threats = array_merge($threats, $this->checkAgainstThreatIntelligence($event));

            // Check for anomalous behavior
            $threats = array_merge($threats, $this->detectAnomalousBehavior($event));

            // Check for suspicious patterns
            $threats = array_merge($threats, $this->detectSuspiciousPatterns($event));

            // Log the analysis
            $this->logThreatAnalysis($event, $threats);

            return $threats;
        } catch (Exception $e) {
            error_log('Failed to analyze event for threats: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Check event against threat detection rules.
     */
    private function checkAgainstRules(array $event): array
    {
        try {
            $threats = [];
            $rules = $this->systemDb->threat_rules->find(['enabled' => true])->toArray();

            foreach ($rules as $rule) {
                if ($this->evaluateRule($rule, $event)) {
                    $threat = [
                        'rule_id' => (string) $rule['_id'],
                        'rule_name' => $rule['name'],
                        'threat_type' => $rule['type'],
                        'severity' => $rule['severity'],
                        'description' => $rule['description'],
                        'confidence_score' => $this->calculateRuleConfidence($rule, $event),
                        'actions' => $rule['actions'],
                        'detected_at' => new MongoDB\BSON\UTCDateTime(),
                        'event_data' => $event,
                    ];

                    $threats[] = $threat;
                }
            }

            return $threats;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check against rules: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Evaluate a threat detection rule.
     */
    private function evaluateRule(array $rule, array $event): bool
    {
        try {
            $conditions = $rule['conditions'];
            $threshold = $rule['threshold'];
            $timeWindow = $rule['time_window'];

            // Check if event meets rule conditions
            $conditionMet = true;
            foreach ($conditions as $condition) {
                if (!$this->evaluateCondition($condition, $event)) {
                    $conditionMet = false;
                    break;
                }
            }

            if (!$conditionMet) {
                return false;
            }

            // Check threshold within time window
            $count = $this->countEventsInWindow($event, $timeWindow);

            return $count >= $threshold;
        } catch (Exception $e) {
            error_log('Failed to evaluate rule: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Evaluate a single condition.
     */
    private function evaluateCondition(array $condition, array $event): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Get field value from event
        $eventValue = $event[$field] ?? null;

        switch ($operator) {
            case 'equals':
                return $eventValue == $value;

            case 'not_equals':
                return $eventValue != $value;

            case 'in':
                return is_array($eventValue) ? in_array($value, $eventValue) : $eventValue == $value;

            case 'not_in':
                return is_array($eventValue) ? !in_array($value, $eventValue) : $eventValue != $value;

            case 'contains':
                return strpos($eventValue, $value) !== false;

            case 'not_contains':
                return strpos($eventValue, $value) === false;

            case 'starts_with':
                return strpos($eventValue, $value) === 0;

            case 'ends_with':
                return substr($eventValue, -strlen($value)) === $value;

            case 'greater_than':
                return $eventValue > $value;

            case 'less_than':
                return $eventValue < $value;

            case 'greater_equal':
                return $eventValue >= $value;

            case 'less_equal':
                return $eventValue <= $value;

            case 'regex':
                return @preg_match($value, $eventValue);

            case 'ip_in_range':
                return $this->checkIPInRange($eventValue, $value);

            case 'time_between':
                return $this->checkTimeBetween($eventValue, $value);

            default:
                return false;
        }
    }

    /**
     * Count events in time window.
     */
    private function countEventsInWindow(array $event, int $timeWindow): int
    {
        try {
            $currentTime = time();
            $windowStart = $currentTime - $timeWindow;

            $query = [
                'timestamp' => [
                    '$gte' => new MongoDB\BSON\UTCDateTime($windowStart * 1000),
                ],
                'source_ip' => $event['source_ip'] ?? null,
                'event_type' => $event['event_type'] ?? null,
            ];

            return $this->systemDb->audit_logs->count($query);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to count events in window: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Check event against threat intelligence.
     */
    private function checkAgainstThreatIntelligence(array $event): array
    {
        try {
            $threats = [];
            $threatIntelligence = $this->systemDb->threat_intelligence->find(['active' => true])->toArray();

            foreach ($threatIntelligence as $threat) {
                if ($this->matchesThreatIntelligence($threat, $event)) {
                    $threatMatch = [
                        'threat_id' => (string) $threat['_id'],
                        'threat_type' => $threat['threat_type'],
                        'severity' => $threat['severity'],
                        'description' => $threat['description'],
                        'confidence_score' => $threat['confidence'],
                        'source' => $threat['source'],
                        'mitigation' => $threat['mitigation'],
                        'detected_at' => new MongoDB\BSON\UTCDateTime(),
                        'event_data' => $event,
                    ];

                    $threats[] = $threatMatch;
                }
            }

            return $threats;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to check against threat intelligence: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Check if event matches threat intelligence.
     */
    private function matchesThreatIntelligence(array $threat, array $event): bool
    {
        $indicators = $threat['indicators'] ?? [];

        // Check IP addresses
        if (!empty($indicators['ip_addresses'])) {
            $ipAddress = $event['source_ip'] ?? null;
            if ($ipAddress && in_array($ipAddress, $indicators['ip_addresses'])) {
                return true;
            }
        }

        // Check domains
        if (!empty($indicators['domains'])) {
            $domain = $event['domain'] ?? null;
            if ($domain && in_array($domain, $indicators['domains'])) {
                return true;
            }
        }

        // Check hashes
        if (!empty($indicators['hashes'])) {
            $hash = $event['hash'] ?? null;
            if ($hash && in_array($hash, $indicators['hashes'])) {
                return true;
            }
        }

        // Check user agents
        if (!empty($indicators['user_agents'])) {
            $userAgent = $event['user_agent'] ?? null;
            if ($userAgent && in_array($userAgent, $indicators['user_agents'])) {
                return true;
            }
        }

        // Check patterns
        if (!empty($indicators['patterns'])) {
            foreach ($indicators['patterns'] as $pattern) {
                if ($this->matchesPattern($pattern, $event)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect anomalous behavior.
     */
    private function detectAnomalousBehavior(array $event): array
    {
        try {
            $threats = [];

            // Check for unusual login patterns
            if ($event['event_type'] === 'login') {
                $anomaly = $this->detectLoginAnomaly($event);
                if ($anomaly) {
                    $threats[] = $anomaly;
                }
            }

            // Check for unusual data access
            if ($event['event_type'] === 'data_access') {
                $anomaly = $this->detectDataAccessAnomaly($event);
                if ($anomaly) {
                    $threats[] = $anomaly;
                }
            }

            // Check for unusual system behavior
            if ($event['event_type'] === 'system_event') {
                $anomaly = $this->detectSystemAnomaly($event);
                if ($anomaly) {
                    $threats[] = $anomaly;
                }
            }

            return $threats;
        } catch (Exception $e) {
            error_log('Failed to detect anomalous behavior: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Detect login anomalies.
     */
    private function detectLoginAnomaly(array $event): ?array
    {
        try {
            $userId = $event['user_id'] ?? null;
            $ipAddress = $event['source_ip'] ?? null;
            $userAgent = $event['user_agent'] ?? null;
            $timestamp = $event['timestamp'] ?? null;

            // Check for login from unusual location
            if ($this->isUnusualLocation($userId, $ipAddress)) {
                return [
                    'threat_type' => 'unusual_location_login',
                    'severity' => 'medium',
                    'description' => 'Login from unusual location detected',
                    'confidence_score' => 0.7,
                    'actions' => ['require_mfa', 'alert_user'],
                    'detected_at' => new MongoDB\BSON\UTCDateTime(),
                    'event_data' => $event,
                ];
            }

            // Check for login with unusual device
            if ($this->isUnusualDevice($userId, $userAgent)) {
                return [
                    'threat_type' => 'unusual_device_login',
                    'severity' => 'medium',
                    'description' => 'Login from unusual device detected',
                    'confidence_score' => 0.8,
                    'actions' => ['require_mfa', 'alert_user'],
                    'detected_at' => new MongoDB\BSON\UTCDateTime(),
                    'event_data' => $event,
                ];
            }

            // Check for unusual login time
            if ($this->isUnusualTime($userId, $timestamp)) {
                return [
                    'threat_type' => 'unusual_time_login',
                    'severity' => 'low',
                    'description' => 'Login at unusual time detected',
                    'confidence_score' => 0.6,
                    'actions' => ['monitor_activity', 'alert_user'],
                    'detected_at' => new MongoDB\BSON\UTCDateTime(),
                    'event_data' => $event,
                ];
            }

            return null;
        } catch (Exception $e) {
            error_log('Failed to detect login anomaly: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Detect data access anomalies.
     */
    private function detectDataAccessAnomaly(array $event): ?array
    {
        try {
            $userId = $event['user_id'] ?? null;
            $resource = $event['resource'] ?? null;
            $action = $event['action'] ?? null;
            $timestamp = $event['timestamp'] ?? null;

            // Check for unusual data access pattern
            if ($this->isUnusualDataAccess($userId, $resource, $action)) {
                return [
                    'threat_type' => 'unusual_data_access',
                    'severity' => 'high',
                    'description' => 'Unusual data access pattern detected',
                    'confidence_score' => 0.8,
                    'actions' => ['require_approval', 'alert_admin', 'monitor_activity'],
                    'detected_at' => new MongoDB\BSON\UTCDateTime(),
                    'event_data' => $event,
                ];
            }

            return null;
        } catch (Exception $e) {
            error_log('Failed to detect data access anomaly: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Detect system anomalies.
     */
    private function detectSystemAnomaly(array $event): ?array
    {
        try {
            $eventType = $event['event_type'] ?? null;
            $system = $event['system'] ?? null;
            $timestamp = $event['timestamp'] ?? null;

            // Check for unusual system activity
            if ($this->isUnusualSystemActivity($eventType, $system, $timestamp)) {
                return [
                    'threat_type' => 'unusual_system_activity',
                    'severity' => 'high',
                    'description' => 'Unusual system activity detected',
                    'confidence_score' => 0.9,
                    'actions' => ['alert_admin', 'investigate_immediately', 'log_incident'],
                    'detected_at' => new MongoDB\BSON\UTCDateTime(),
                    'event_data' => $event,
                ];
            }

            return null;
        } catch (Exception $e) {
            error_log('Failed to detect system anomaly: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Detect suspicious patterns.
     */
    private function detectSuspiciousPatterns(array $event): array
    {
        try {
            $threats = [];

            // Check for rapid successive requests
            if ($this->isRapidSuccessiveRequests($event)) {
                $threats[] = [
                    'threat_type' => 'rapid_successive_requests',
                    'severity' => 'medium',
                    'description' => 'Rapid successive requests detected',
                    'confidence_score' => 0.7,
                    'actions' => ['rate_limit', 'alert_admin'],
                    'detected_at' => new MongoDB\BSON\UTCDateTime(),
                    'event_data' => $event,
                ];
            }

            // Check for directory traversal attempts
            if ($this->isDirectoryTraversalAttempt($event)) {
                $threats[] = [
                    'threat_type' => 'directory_traversal',
                    'severity' => 'high',
                    'description' => 'Directory traversal attempt detected',
                    'confidence_score' => 0.9,
                    'actions' => ['block_request', 'alert_admin', 'log_incident'],
                    'detected_at' => new MongoDB\BSON\UTCDateTime(),
                    'event_data' => $event,
                ];
            }

            // Check for protocol anomalies
            if ($this->isProtocolAnomaly($event)) {
                $threats[] = [
                    'threat_type' => 'protocol_anomaly',
                    'severity' => 'medium',
                    'description' => 'Protocol anomaly detected',
                    'confidence_score' => 0.6,
                    'actions' => ['monitor_activity', 'alert_admin'],
                    'detected_at' => new MongoDB\BSON\UTCDateTime(),
                    'event_data' => $event,
                ];
            }

            return $threats;
        } catch (Exception $e) {
            error_log('Failed to detect suspicious patterns: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Log threat analysis.
     */
    private function logThreatAnalysis(array $event, array $threats): void
    {
        try {
            $analysis = [
                'event_id' => $event['id'] ?? uniqid('event_', true),
                'event_type' => $event['event_type'] ?? 'unknown',
                'event_data' => $event,
                'threats_detected' => count($threats),
                'threats' => $threats,
                'analysis_timestamp' => new MongoDB\BSON\UTCDateTime(),
                'analysis_duration' => microtime(true) - $event['timestamp'] ?? microtime(true),
            ];

            $this->systemDb->threat_analysis->insertOne($analysis);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to log threat analysis: '.$e->getMessage());
        }
    }

    /**
     * Handle detected threats.
     */
    public function handleThreats(array $threats): array
    {
        try {
            $results = [];

            foreach ($threats as $threat) {
                $result = $this->executeThreatActions($threat);
                $results[] = $result;
            }

            return $results;
        } catch (Exception $e) {
            error_log('Failed to handle threats: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Execute threat actions.
     */
    private function executeThreatActions(array $threat): array
    {
        try {
            $actions = $threat['actions'] ?? [];
            $results = [];

            foreach ($actions as $action) {
                $result = $this->executeAction($action, $threat);
                $results[] = $result;
            }

            return [
                'threat_id' => $threat['threat_id'] ?? null,
                'threat_type' => $threat['threat_type'],
                'actions_executed' => $results,
                'handled_at' => new MongoDB\BSON\UTCDateTime(),
            ];
        } catch (Exception $e) {
            error_log('Failed to execute threat actions: '.$e->getMessage());

            return [
                'threat_type' => $threat['threat_type'],
                'error' => $e->getMessage(),
                'handled_at' => new MongoDB\BSON\UTCDateTime(),
            ];
        }
    }

    /**
     * Execute a single action.
     */
    private function executeAction(string $action, array $threat): array
    {
        try {
            switch ($action) {
                case 'block_ip':
                    return $this->blockIPAddress($threat['event_data']['source_ip'] ?? null);

                case 'alert_admin':
                    return $this->alertAdministrator($threat);

                case 'log_incident':
                    return $this->logSecurityIncident($threat);

                case 'block_request':
                    return ['action' => 'block_request', 'status' => 'blocked'];

                case 'require_mfa':
                    return $this->requireMultiFactorAuth($threat['event_data']['user_id'] ?? null);

                case 'rate_limit':
                    return $this->applyRateLimiting($threat['event_data']['source_ip'] ?? null);

                case 'require_approval':
                    return $this->requireApproval($threat);

                case 'monitor_activity':
                    return $this->monitorActivity($threat);

                case 'investigate_immediately':
                    return $this->investigateImmediately($threat);

                default:
                    return ['action' => $action, 'status' => 'unknown'];
            }
        } catch (Exception $e) {
            error_log("Failed to execute action $action: ".$e->getMessage());

            return ['action' => $action, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Block IP address.
     */
    private function blockIPAddress(string $ipAddress): array
    {
        try {
            if (!$ipAddress) {
                return ['action' => 'block_ip', 'status' => 'failed', 'error' => 'No IP address provided'];
            }

            $block = [
                'ip_address' => $ipAddress,
                'reason' => 'security violation',
                'blocked_at' => new MongoDB\BSON\UTCDateTime(),
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + 24 * 60 * 60) * 1000), // 24 hours
                'status' => 'active',
            ];

            $result = $this->systemDb->blocked_ips->insertOne($block);

            return [
                'action' => 'block_ip',
                'status' => 'success',
                'blocked_ip' => $ipAddress,
                'block_id' => (string) $result->getInsertedId(),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to block IP address: '.$e->getMessage());

            return ['action' => 'block_ip', 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Alert administrator.
     */
    private function alertAdministrator(array $threat): array
    {
        try {
            $alert = [
                'threat_type' => $threat['threat_type'],
                'severity' => $threat['severity'],
                'description' => $threat['description'],
                'event_data' => $threat['event_data'],
                'alerted_at' => new MongoDB\BSON\UTCDateTime(),
                'status' => 'active',
            ];

            $result = $this->systemDb->security_alerts->insertOne($alert);

            // In a real implementation, you would send email, SMS, or other notification
            // For now, we'll just log it
            error_log("Security alert: {$threat['threat_type']} - {$threat['description']}");

            return [
                'action' => 'alert_admin',
                'status' => 'success',
                'alert_id' => (string) $result->getInsertedId(),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to alert administrator: '.$e->getMessage());

            return ['action' => 'alert_admin', 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Log security incident.
     */
    private function logSecurityIncident(array $threat): array
    {
        try {
            $incident = [
                'incident_type' => $threat['threat_type'],
                'severity' => $threat['severity'],
                'description' => $threat['description'],
                'affected_systems' => $this->getAffectedSystems($threat),
                'start_time' => new MongoDB\BSON\UTCDateTime(),
                'status' => 'active',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
            ];

            $result = $this->systemDb->security_incidents->insertOne($incident);

            return [
                'action' => 'log_incident',
                'status' => 'success',
                'incident_id' => (string) $result->getInsertedId(),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to log security incident: '.$e->getMessage());

            return ['action' => 'log_incident', 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get affected systems from threat.
     */
    private function getAffectedSystems(array $threat): array
    {
        $systems = [];

        if (isset($threat['event_data']['resource'])) {
            $systems[] = $threat['event_data']['resource'];
        }

        if (isset($threat['event_data']['collection'])) {
            $systems[] = $threat['event_data']['collection'];
        }

        if (isset($threat['event_data']['database'])) {
            $systems[] = $threat['event_data']['database'];
        }

        return array_unique($systems);
    }

    /**
     * Get threat detection dashboard.
     */
    public function getThreatDetectionDashboard(): array
    {
        try {
            $today = new MongoDB\BSON\UTCDateTime(strtotime('today') * 1000);
            $weekAgo = new MongoDB\BSON\UTCDateTime((time() - 7 * 24 * 60 * 60) * 1000);

            return [
                'active_threats' => $this->systemDb->threat_detection->count([
                    'status' => 'active',
                    'created_at' => ['$gte' => $today],
                ]),
                'blocked_ips' => $this->systemDb->blocked_ips->count([
                    'status' => 'active',
                ]),
                'security_incidents' => $this->systemDb->security_incidents->count([
                    'status' => 'active',
                ]),
                'threats_today' => $this->systemDb->threat_detection->count([
                    'created_at' => ['$gte' => $today],
                ]),
                'threats_this_week' => $this->systemDb->threat_detection->count([
                    'created_at' => ['$gte' => $weekAgo],
                ]),
                'threat_types' => $this->getThreatTypes(),
                'recent_threats' => $this->getRecentThreats(10),
                'detection_rate' => $this->calculateDetectionRate(),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get threat detection dashboard: '.$e->getMessage());

            return [
                'error' => $e->getMessage(),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Get threat types breakdown.
     */
    private function getThreatTypes(): array
    {
        try {
            $pipeline = [
                [
                    '$group' => [
                        '_id' => '$threat_type',
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

            $result = $this->systemDb->threat_detection->aggregate($pipeline)->toArray();

            return array_map(function ($item) {
                return [
                    'type' => $item['_id'],
                    'count' => $item['count'],
                ];
            }, $result);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get threat types: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get recent threats.
     */
    private function getRecentThreats(int $limit): array
    {
        try {
            $threats = $this->systemDb->threat_detection
                ->find([])
                ->sort(['created_at' => -1])
                ->limit($limit)
                ->toArray();

            return array_map(function ($threat) {
                return [
                    'id' => (string) $threat['_id'],
                    'threat_type' => $threat['threat_type'],
                    'severity' => $threat['severity'],
                    'description' => $threat['description'],
                    'source_ip' => $threat['source_ip'] ?? null,
                    'confidence_score' => $threat['confidence_score'] ?? 0,
                    'status' => $threat['status'],
                    'created_at' => $threat['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                ];
            }, $threats);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get recent threats: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Calculate detection rate.
     */
    private function calculateDetectionRate(): float
    {
        try {
            $totalEvents = $this->systemDb->audit_logs->count([
                'created_at' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 7 * 24 * 60 * 60) * 1000)],
            ]);

            $detectedThreats = $this->systemDb->threat_detection->count([
                'created_at' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 7 * 24 * 60 * 60) * 1000)],
            ]);

            return $totalEvents > 0 ? round(($detectedThreats / $totalEvents) * 100, 2) : 0;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to calculate detection rate: '.$e->getMessage());

            return 0;
        }
    }

    // Helper methods (simplified implementations)
    private function calculateRuleConfidence(array $rule, array $event): float
    {
        // Simplified confidence calculation
        $baseConfidence = 0.5;
        $severityMultiplier = [
            'low' => 1.0,
            'medium' => 1.2,
            'high' => 1.5,
            'critical' => 2.0,
        ];

        return min(1.0, $baseConfidence * ($severityMultiplier[$rule['severity']] ?? 1.0));
    }

    private function checkIPInRange(string $ip, string $range): bool
    {
        // CIDR notation check
        if (strpos($range, '/') !== false) {
            return $this->ipCIDRCheck($ip, $range);
        }

        // IP range check
        if (strpos($range, '-') !== false) {
            list($start, $end) = explode('-', $range);

            return ip2long($ip) >= ip2long(trim($start)) && ip2long($ip) <= ip2long(trim($end));
        }

        // Single IP check
        return $ip === $range;
    }

    private function ipCIDRCheck(string $ip, string $cidr): bool
    {
        list($network, $mask) = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $maskLong = ~((1 << (32 - $mask)) - 1);

        return ($ipLong & $maskLong) === ($networkLong & $maskLong);
    }

    private function checkTimeBetween(string $time, string $range): bool
    {
        list($start, $end) = explode('-', $range);
        $currentTime = strtotime($time);
        $startTime = strtotime($start);
        $endTime = strtotime($end);

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    private function matchesPattern(string $pattern, array $event): bool
    {
        // Simplified pattern matching
        switch ($pattern) {
            case 'sequential_login_attempts':
                return $this->checkSequentialLoginAttempts($event);
            case 'multiple_failed_logins':
                return $this->checkMultipleFailedLogins($event);
            default:
                return false;
        }
    }

    private function checkSequentialLoginAttempts(array $event): bool
    {
        // Check for sequential login attempts from same IP
        $ipAddress = $event['source_ip'] ?? null;
        if (!$ipAddress) {
            return false;
        }

        $recentAttempts = $this->systemDb->audit_logs->count([
            'source_ip' => $ipAddress,
            'event_type' => 'login',
            'timestamp' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 300) * 1000)], // 5 minutes
        ]);

        return $recentAttempts >= 3;
    }

    private function checkMultipleFailedLogins(array $event): bool
    {
        // Check for multiple failed login attempts
        $ipAddress = $event['source_ip'] ?? null;
        if (!$ipAddress) {
            return false;
        }

        $failedAttempts = $this->systemDb->audit_logs->count([
            'source_ip' => $ipAddress,
            'event_type' => 'failed_login',
            'timestamp' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 900) * 1000)], // 15 minutes
        ]);

        return $failedAttempts >= 5;
    }

    private function isUnusualLocation(string $userId, string $ipAddress): bool
    {
        // Simplified unusual location detection
        // In a real implementation, you would use geolocation services
        return false;
    }

    private function isUnusualDevice(string $userId, string $userAgent): bool
    {
        // Check if user agent is unusual
        if (!$userAgent) {
            return true;
        }

        // Check for common bot user agents
        $botPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
        ];

        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    private function isUnusualTime(string $userId, string $timestamp): bool
    {
        // Check if login time is unusual for the user
        // This would require historical data analysis
        return false;
    }

    private function isUnusualDataAccess(string $userId, string $resource, string $action): bool
    {
        // Check for unusual data access patterns
        // This would require historical data analysis
        return false;
    }

    private function isUnusualSystemActivity(string $eventType, string $system, string $timestamp): bool
    {
        // Check for unusual system activity
        return false;
    }

    private function isRapidSuccessiveRequests(array $event): bool
    {
        // Check for rapid successive requests
        $ipAddress = $event['source_ip'] ?? null;
        if (!$ipAddress) {
            return false;
        }

        $requestCount = $this->systemDb->audit_logs->count([
            'source_ip' => $ipAddress,
            'timestamp' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 60) * 1000)], // 1 minute
        ]);

        return $requestCount >= 50; // More than 50 requests in a minute
    }

    private function isDirectoryTraversalAttempt(array $event): bool
    {
        // Check for directory traversal attempts
        $path = $event['path'] ?? $event['resource'] ?? null;
        if (!$path) {
            return false;
        }

        $traversalPatterns = [
            '../',
            '..\\',
            '/..',
            '\\..',
            '/etc/',
            '/etc\\',
            '/windows/',
            '/windows\\',
            '/c:/',
            '/c:\\',
        ];

        foreach ($traversalPatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isProtocolAnomaly(array $event): bool
    {
        // Check for protocol anomalies
        return false;
    }

    private function requireMultiFactorAuth(string $userId): array
    {
        // Require multi-factor authentication
        return ['action' => 'require_mfa', 'status' => 'success', 'user_id' => $userId];
    }

    private function applyRateLimiting(string $ipAddress): array
    {
        // Apply rate limiting
        return ['action' => 'rate_limit', 'status' => 'success', 'ip_address' => $ipAddress];
    }

    private function requireApproval(array $threat): array
    {
        // Require approval for action
        return ['action' => 'require_approval', 'status' => 'success'];
    }

    private function monitorActivity(array $threat): array
    {
        // Monitor activity
        return ['action' => 'monitor_activity', 'status' => 'success'];
    }

    private function investigateImmediately(array $threat): array
    {
        // Initiate immediate investigation
        return ['action' => 'investigate_immediately', 'status' => 'success'];
    }
}
