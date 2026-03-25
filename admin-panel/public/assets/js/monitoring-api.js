// Monitoring API Service

class MonitoringAPI {
  constructor() {
    this.baseURL = "/monitoring";
    this.headers = {
      "Content-Type": "application/json",
    };
    this.token = localStorage.getItem("auth_token");
    this.initializeAuth();
  }

  /**
   * Initialize authentication
   */
  initializeAuth() {
    if (this.token) {
      this.headers["Authorization"] = `Bearer ${this.token}`;
    }
  }

  /**
   * Set authentication token
   */
  setToken(token) {
    this.token = token;
    if (token) {
      this.headers["Authorization"] = `Bearer ${token}`;
      localStorage.setItem("auth_token", token);
    } else {
      delete this.headers["Authorization"];
      localStorage.removeItem("auth_token");
    }
  }

  /**
   * Generic API request method
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const config = {
      headers: { ...this.headers },
      ...options,
    };

    try {
      const response = await fetch(url, config);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("API request failed:", error);
      throw error;
    }
  }

  /**
   * GET request
   */
  async get(endpoint, params = {}) {
    const url = new URL(`${this.baseURL}${endpoint}`, window.location.origin);
    Object.keys(params).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.append(key, params[key]);
      }
    });

    return this.request(url.pathname + url.search, { method: "GET" });
  }

  /**
   * POST request
   */
  async post(endpoint, data = {}) {
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(data),
    });
  }

  /**
   * PUT request
   */
  async put(endpoint, data = {}) {
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(data),
    });
  }

  /**
   * DELETE request
   */
  async delete(endpoint) {
    return this.request(endpoint, { method: "DELETE" });
  }

  /**
   * Get real-time metrics
   */
  async getRealTimeMetrics() {
    return this.get("/realtime");
  }

  /**
   * Get historical metrics
   */
  async getHistoricalMetrics(period = "24h") {
    return this.get("/historical/" + period);
  }

  /**
   * Generate report
   */
  async generateReport(type = "summary", format = "json") {
    return this.get(`/generate-report/${type}/${format}`);
  }

  /**
   * Get alert configuration
   */
  async getAlertConfiguration() {
    return this.get("/alert-config");
  }

  /**
   * Update alert configuration
   */
  async updateAlertConfiguration(config) {
    return this.post("/alert-config", config);
  }

  /**
   * Get log configuration
   */
  async getLogConfiguration() {
    return this.get("/log-config");
  }

  /**
   * Update log configuration
   */
  async updateLogConfiguration(config) {
    return this.post("/log-config", config);
  }

  /**
   * Export logs
   */
  async exportLogs(format = "json", startDate = null, endDate = null) {
    const params = { format };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    const url = new URL("/monitoring/export-logs", window.location.origin);
    Object.keys(params).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.append(key, params[key]);
      }
    });

    try {
      const response = await fetch(url, {
        headers: this.headers,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return response;
    } catch (error) {
      console.error("Export logs failed:", error);
      throw error;
    }
  }

  /**
   * Get system health
   */
  async getSystemHealth() {
    return this.get("/system-health");
  }

  /**
   * Get database health
   */
  async getDatabaseHealth() {
    return this.get("/database-health");
  }

  /**
   * Get performance metrics
   */
  async getPerformanceMetrics() {
    return this.get("/performance-metrics");
  }

  /**
   * Get security metrics
   */
  async getSecurityMetrics() {
    return this.get("/security-metrics");
  }

  /**
   * Get user activity
   */
  async getUserActivity() {
    return this.get("/user-activity");
  }

  /**
   * Get active alerts
   */
  async getActiveAlerts() {
    return this.get("/active-alerts");
  }

  /**
   * Get recent logs
   */
  async getRecentLogs(limit = 50) {
    return this.get("/recent-logs", { limit });
  }

  /**
   * Get system information
   */
  async getSystemInfo() {
    return this.get("/system-info");
  }

  /**
   * Get process list
   */
  async getProcessList() {
    return this.get("/process-list");
  }

  /**
   * Get service status
   */
  async getServiceStatus() {
    return this.get("/service-status");
  }

  /**
   * Get network information
   */
  async getNetworkInfo() {
    return this.get("/network-info");
  }

  /**
   * Get disk usage
   */
  async getDiskUsage() {
    return this.get("/disk-usage");
  }

  /**
   * Get memory usage
   */
  async getMemoryUsage() {
    return this.get("/memory-usage");
  }

  /**
   * Get CPU usage
   */
  async getCpuUsage() {
    return this.get("/cpu-usage");
  }

  /**
   * Get load average
   */
  async getLoadAverage() {
    return this.get("/load-average");
  }

  /**
   * Get uptime
   */
  async getUptime() {
    return this.get("/uptime");
  }

  /**
   * Get temperature
   */
  async getTemperature() {
    return this.get("/temperature");
  }

  /**
   * Get performance history
   */
  async getPerformanceHistory(period = "24h") {
    return this.get("/performance-history", { period });
  }

  /**
   * Get error logs
   */
  async getErrorLogs(limit = 100) {
    return this.get("/error-logs", { limit });
  }

  /**
   * Get warning logs
   */
  async getWarningLogs(limit = 100) {
    return this.get("/warning-logs", { limit });
  }

  /**
   * Get info logs
   */
  async getInfoLogs(limit = 100) {
    return this.get("/info-logs", { limit });
  }

  /**
   * Get debug logs
   */
  async getDebugLogs(limit = 100) {
    return this.get("/debug-logs", { limit });
  }

  /**
   * Get log summary
   */
  async getLogSummary() {
    return this.get("/log-summary");
  }

  /**
   * Search logs
   */
  async searchLogs(
    query,
    level = null,
    startDate = null,
    endDate = null,
    limit = 100,
  ) {
    const params = { query, limit };
    if (level) params.level = level;
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    return this.get("/search-logs", params);
  }

  /**
   * Get alert history
   */
  async getAlertHistory(startDate = null, endDate = null, limit = 100) {
    const params = { limit };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    return this.get("/alert-history", params);
  }

  /**
   * Get alert summary
   */
  async getAlertSummary() {
    return this.get("/alert-summary");
  }

  /**
   * Get user session list
   */
  async getUserSessions() {
    return this.get("/user-sessions");
  }

  /**
   * Get user session details
   */
  async getUserSession(sessionId) {
    return this.get(`/user-sessions/${sessionId}`);
  }

  /**
   * Terminate user session
   */
  async terminateUserSession(sessionId) {
    return this.delete(`/user-sessions/${sessionId}`);
  }

  /**
   * Get user login history
   */
  async getUserLoginHistory(userId = null, limit = 100) {
    const params = { limit };
    if (userId) params.user_id = userId;

    return this.get("/user-login-history", params);
  }

  /**
   * Get system events
   */
  async getSystemEvents(startDate = null, endDate = null, limit = 100) {
    const params = { limit };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    return this.get("/system-events", params);
  }

  /**
   * Get security events
   */
  async getSecurityEvents(startDate = null, endDate = null, limit = 100) {
    const params = { limit };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    return this.get("/security-events", params);
  }

  /**
   * Get database events
   */
  async getDatabaseEvents(startDate = null, endDate = null, limit = 100) {
    const params = { limit };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    return this.get("/database-events", params);
  }

  /**
   * Get application events
   */
  async getApplicationEvents(startDate = null, endDate = null, limit = 100) {
    const params = { limit };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    return this.get("/application-events", params);
  }

  /**
   * Get event summary
   */
  async getEventSummary() {
    return this.get("/event-summary");
  }

  /**
   * Get system recommendations
   */
  async getSystemRecommendations() {
    return this.get("/system-recommendations");
  }

  /**
   * Get database recommendations
   */
  async getDatabaseRecommendations() {
    return this.get("/database-recommendations");
  }

  /**
   * Get security recommendations
   */
  async getSecurityRecommendations() {
    return this.get("/security-recommendations");
  }

  /**
   * Get performance recommendations
   */
  async getPerformanceRecommendations() {
    return this.get("/performance-recommendations");
  }

  /**
   * Get recommendations summary
   */
  async getRecommendationsSummary() {
    return this.get("/recommendations-summary");
  }

  /**
   * Execute system command
   */
  async executeSystemCommand(command, args = []) {
    return this.post("/execute-command", { command, args });
  }

  /**
   * Get system backup status
   */
  async getBackupStatus() {
    return this.get("/backup-status");
  }

  /**
   * Get backup history
   */
  async getBackupHistory(limit = 50) {
    return this.get("/backup-history", { limit });
  }

  /**
   * Trigger backup
   */
  async triggerBackup(type = "full") {
    return this.post("/trigger-backup", { type });
  }

  /**
   * Get restore points
   */
  async getRestorePoints() {
    return this.get("/restore-points");
  }

  /**
   * Create restore point
   */
  async createRestorePoint(name = null) {
    const data = name ? { name } : {};
    return this.post("/create-restore-point", data);
  }

  /**
   * Restore from point
   */
  async restoreFromPoint(pointId) {
    return this.post("/restore-from-point", { point_id: pointId });
  }

  /**
   * Get maintenance status
   */
  async getMaintenanceStatus() {
    return this.get("/maintenance-status");
  }

  /**
   * Start maintenance mode
   */
  async startMaintenanceMode(reason = null) {
    const data = reason ? { reason } : {};
    return this.post("/start-maintenance", data);
  }

  /**
   * Stop maintenance mode
   */
  async stopMaintenanceMode() {
    return this.post("/stop-maintenance");
  }

  /**
   * Get scheduled maintenance
   */
  async getScheduledMaintenance() {
    return this.get("/scheduled-maintenance");
  }

  /**
   * Schedule maintenance
   */
  async scheduleMaintenance(startTime, endTime, reason = null) {
    const data = { start_time: startTime, end_time: endTime };
    if (reason) data.reason = reason;

    return this.post("/schedule-maintenance", data);
  }

  /**
   * Cancel scheduled maintenance
   */
  async cancelScheduledMaintenance(maintenanceId) {
    return this.delete(`/scheduled-maintenance/${maintenanceId}`);
  }

  /**
   * Get system metrics export
   */
  async exportSystemMetrics(format = "json", startDate = null, endDate = null) {
    const params = { format };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    const url = new URL(
      "/monitoring/export-system-metrics",
      window.location.origin,
    );
    Object.keys(params).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.append(key, params[key]);
      }
    });

    try {
      const response = await fetch(url, {
        headers: this.headers,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return response;
    } catch (error) {
      console.error("Export system metrics failed:", error);
      throw error;
    }
  }

  /**
   * Get database metrics export
   */
  async exportDatabaseMetrics(
    format = "json",
    startDate = null,
    endDate = null,
  ) {
    const params = { format };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    const url = new URL(
      "/monitoring/export-database-metrics",
      window.location.origin,
    );
    Object.keys(params).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.append(key, params[key]);
      }
    });

    try {
      const response = await fetch(url, {
        headers: this.headers,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return response;
    } catch (error) {
      console.error("Export database metrics failed:", error);
      throw error;
    }
  }

  /**
   * Get performance metrics export
   */
  async exportPerformanceMetrics(
    format = "json",
    startDate = null,
    endDate = null,
  ) {
    const params = { format };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    const url = new URL(
      "/monitoring/export-performance-metrics",
      window.location.origin,
    );
    Object.keys(params).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.append(key, params[key]);
      }
    });

    try {
      const response = await fetch(url, {
        headers: this.headers,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return response;
    } catch (error) {
      console.error("Export performance metrics failed:", error);
      throw error;
    }
  }

  /**
   * Get security metrics export
   */
  async exportSecurityMetrics(
    format = "json",
    startDate = null,
    endDate = null,
  ) {
    const params = { format };
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;

    const url = new URL(
      "/monitoring/export-security-metrics",
      window.location.origin,
    );
    Object.keys(params).forEach((key) => {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.append(key, params[key]);
      }
    });

    try {
      const response = await fetch(url, {
        headers: this.headers,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return response;
    } catch (error) {
      console.error("Export security metrics failed:", error);
      throw error;
    }
  }
}

// Export API service
window.MonitoringAPI = MonitoringAPI;
