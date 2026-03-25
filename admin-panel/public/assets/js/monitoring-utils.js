// Monitoring Utilities

class MonitoringUtils {
  constructor() {
    this.config = window.MONITORING_CONFIG;
    this.charts = new Map();
    this.websocket = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = this.config.websocket.maxRetries;
    this.isWebSocketConnected = false;
    this.metricsCache = new Map();
    this.eventListeners = new Map();
  }

  /**
   * Initialize monitoring utilities
   */
  initialize() {
    this.setupWebSocket();
    this.setupEventListeners();
    this.initializeCharts();
    this.startPeriodicUpdates();
  }

  /**
   * Setup WebSocket connection
   */
  setupWebSocket() {
    if (!this.config.websocket.url) {
      console.warn("WebSocket URL not configured");
      return;
    }

    if (!window.MonitoringUtils.supportsWebSocket()) {
      console.warn("WebSocket not supported");
      return;
    }

    this.websocket = new WebSocket(this.config.websocket.url);

    this.websocket.onopen = () => {
      console.log("WebSocket connected");
      this.isWebSocketConnected = true;
      this.reconnectAttempts = 0;
      this.emit("websocket:connected");
      this.startHeartbeat();
    };

    this.websocket.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        this.handleWebSocketMessage(data);
      } catch (error) {
        console.error("Error parsing WebSocket message:", error);
      }
    };

    this.websocket.onclose = () => {
      console.log("WebSocket disconnected");
      this.isWebSocketConnected = false;
      this.emit("websocket:disconnected");
      this.reconnectWebSocket();
    };

    this.websocket.onerror = (error) => {
      console.error("WebSocket error:", error);
      this.emit("websocket:error", error);
    };
  }

  /**
   * Handle WebSocket messages
   */
  handleWebSocketMessage(data) {
    switch (data.type) {
      case "metrics_update":
        this.updateMetrics(data.payload);
        break;
      case "alert":
        this.handleAlert(data.payload);
        break;
      case "log":
        this.handleLog(data.payload);
        break;
      case "system_status":
        this.updateSystemStatus(data.payload);
        break;
      case "heartbeat":
        this.handleHeartbeat(data.payload);
        break;
      default:
        console.warn("Unknown message type:", data.type);
    }
  }

  /**
   * Reconnect WebSocket
   */
  reconnectWebSocket() {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.warn("Max reconnection attempts reached");
      return;
    }

    this.reconnectAttempts++;
    const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);

    console.log(
      `Attempting to reconnect in ${delay}ms (attempt ${this.reconnectAttempts})`,
    );

    setTimeout(() => {
      this.setupWebSocket();
    }, delay);
  }

  /**
   * Start heartbeat
   */
  startHeartbeat() {
    this.heartbeatInterval = setInterval(() => {
      if (this.isWebSocketConnected) {
        this.websocket.send(
          JSON.stringify({
            type: "heartbeat",
            timestamp: Date.now(),
          }),
        );
      }
    }, this.config.websocket.pingInterval);
  }

  /**
   * Handle heartbeat response
   */
  handleHeartbeat(data) {
    // Update connection status based on heartbeat response
    this.emit("heartbeat:received", data);
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Listen for configuration changes
    window.addEventListener("storage", (event) => {
      if (event.key === "monitoring_config") {
        this.loadConfiguration();
      }
    });

    // Listen for visibility changes
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        this.pauseUpdates();
      } else {
        this.resumeUpdates();
      }
    });

    // Listen for online/offline events
    window.addEventListener("online", () => {
      this.emit("network:online");
    });

    window.addEventListener("offline", () => {
      this.emit("network:offline");
    });
  }

  /**
   * Initialize charts
   */
  initializeCharts() {
    // Initialize system metrics chart
    this.initializeSystemChart();

    // Initialize performance chart
    this.initializePerformanceChart();

    // Initialize database chart
    this.initializeDatabaseChart();

    // Initialize security chart
    this.initializeSecurityChart();

    // Initialize user activity chart
    this.initializeUserActivityChart();
  }

  /**
   * Initialize system metrics chart
   */
  initializeSystemChart() {
    const canvas = document.getElementById("systemMetricsChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const chart = new Chart(ctx, {
      type: "line",
      data: {
        labels: [],
        datasets: [
          {
            label: "CPU Usage",
            data: [],
            borderColor: this.config.charts.colors.primary,
            backgroundColor: this.hexToRgba(
              this.config.charts.colors.primary,
              0.1,
            ),
            tension: 0.1,
            fill: true,
          },
          {
            label: "Memory Usage",
            data: [],
            borderColor: this.config.charts.colors.secondary,
            backgroundColor: this.hexToRgba(
              this.config.charts.colors.secondary,
              0.1,
            ),
            tension: 0.1,
            fill: true,
          },
          {
            label: "Disk Usage",
            data: [],
            borderColor: this.config.charts.colors.warning,
            backgroundColor: this.hexToRgba(
              this.config.charts.colors.warning,
              0.1,
            ),
            tension: 0.1,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: {
              callback: function (value) {
                return value + "%";
              },
            },
          },
        },
        plugins: {
          legend: {
            display: true,
            position: "top",
          },
          tooltip: {
            mode: "index",
            intersect: false,
            callbacks: {
              label: function (context) {
                return (
                  context.dataset.label +
                  ": " +
                  context.parsed.y.toFixed(1) +
                  "%"
                );
              },
            },
          },
        },
        interaction: {
          mode: "nearest",
          axis: "x",
          intersect: false,
        },
      },
    });

    this.charts.set("system", chart);
  }

  /**
   * Initialize performance chart
   */
  initializePerformanceChart() {
    const canvas = document.getElementById("performanceChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const chart = new Chart(ctx, {
      type: "line",
      data: {
        labels: [],
        datasets: [
          {
            label: "Response Time",
            data: [],
            borderColor: this.config.charts.colors.success,
            backgroundColor: this.hexToRgba(
              this.config.charts.colors.success,
              0.1,
            ),
            tension: 0.1,
            fill: true,
            yAxisID: "y",
          },
          {
            label: "Throughput",
            data: [],
            borderColor: this.config.charts.colors.info,
            backgroundColor: this.hexToRgba(
              this.config.charts.colors.info,
              0.1,
            ),
            tension: 0.1,
            fill: true,
            yAxisID: "y1",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            type: "linear",
            display: true,
            position: "left",
            title: {
              display: true,
              text: "Response Time (ms)",
            },
          },
          y1: {
            type: "linear",
            display: true,
            position: "right",
            title: {
              display: true,
              text: "Throughput (req/s)",
            },
            grid: {
              drawOnChartArea: false,
            },
          },
        },
        plugins: {
          legend: {
            display: true,
            position: "top",
          },
          tooltip: {
            mode: "index",
            intersect: false,
          },
        },
      },
    });

    this.charts.set("performance", chart);
  }

  /**
   * Initialize database chart
   */
  initializeDatabaseChart() {
    const canvas = document.getElementById("databaseChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const chart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: ["Queries", "Slow Queries", "Connections", "Cache Hit Rate"],
        datasets: [
          {
            label: "Database Metrics",
            data: [0, 0, 0, 0],
            backgroundColor: [
              this.config.charts.colors.primary,
              this.config.charts.colors.warning,
              this.config.charts.colors.success,
              this.config.charts.colors.info,
            ],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
        plugins: {
          legend: {
            display: false,
          },
        },
      },
    });

    this.charts.set("database", chart);
  }

  /**
   * Initialize security chart
   */
  initializeSecurityChart() {
    const canvas = document.getElementById("securityChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const chart = new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: [
          "Failed Logins",
          "Suspicious Activities",
          "Security Events",
          "Normal",
        ],
        datasets: [
          {
            data: [0, 0, 0, 100],
            backgroundColor: [
              this.config.charts.colors.danger,
              this.config.charts.colors.warning,
              this.config.charts.colors.info,
              this.config.charts.colors.success,
            ],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "right",
          },
        },
      },
    });

    this.charts.set("security", chart);
  }

  /**
   * Initialize user activity chart
   */
  initializeUserActivityChart() {
    const canvas = document.getElementById("userActivityChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const chart = new Chart(ctx, {
      type: "line",
      data: {
        labels: [],
        datasets: [
          {
            label: "Active Users",
            data: [],
            borderColor: this.config.charts.colors.primary,
            backgroundColor: this.hexToRgba(
              this.config.charts.colors.primary,
              0.1,
            ),
            tension: 0.1,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
        plugins: {
          legend: {
            display: true,
            position: "top",
          },
        },
      },
    });

    this.charts.set("userActivity", chart);
  }

  /**
   * Update metrics
   */
  updateMetrics(metrics) {
    // Cache metrics
    this.metricsCache.set("system", metrics.system);
    this.metricsCache.set("database", metrics.database);
    this.metricsCache.set("performance", metrics.performance);
    this.metricsCache.set("security", metrics.security);
    this.metricsCache.set("userActivity", metrics.userActivity);

    // Update UI
    this.updateMetricCards(metrics);
    this.updateCharts(metrics);
    this.updateAlerts(metrics.alerts);
    this.updateLogs(metrics.logs);

    // Emit event
    this.emit("metrics:updated", metrics);
  }

  /**
   * Update metric cards
   */
  updateMetricCards(metrics) {
    // Update system metrics
    this.updateMetricCard("cpu-usage", metrics.system.cpu_usage, "%");
    this.updateMetricCard("memory-usage", metrics.system.memory_usage, "%");
    this.updateMetricCard("disk-usage", metrics.system.disk_usage, "%");
    this.updateMetricCard(
      "network-traffic",
      this.formatBytes(
        metrics.system.network_traffic.rx + metrics.system.network_traffic.tx,
      ),
    );

    // Update database metrics
    this.updateMetricCard(
      "total-size",
      this.formatBytes(metrics.database.total_size),
    );
    this.updateMetricCard("page-count", metrics.database.page_count);
    this.updateMetricCard("fragmentation", metrics.database.fragmentation, "%");

    // Update performance metrics
    this.updateMetricCard(
      "response-time",
      metrics.performance.response_time,
      "ms",
    );
    this.updateMetricCard("throughput", metrics.performance.throughput, "/s");
    this.updateMetricCard("error-rate", metrics.performance.error_rate, "%");

    // Update security metrics
    this.updateMetricCard(
      "failed-logins",
      metrics.security.failed_login_attempts,
    );
    this.updateMetricCard(
      "suspicious-activities",
      metrics.security.suspicious_activities,
    );

    // Update user activity
    this.updateMetricCard("active-users", metrics.userActivity.active_users);
    this.updateMetricCard("session-count", metrics.userActivity.session_count);
  }

  /**
   * Update metric card
   */
  updateMetricCard(id, value, suffix = "") {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value + suffix;
    }
  }

  /**
   * Update charts
   */
  updateCharts(metrics) {
    const now = new Date().toLocaleTimeString();

    // Update system chart
    const systemChart = this.charts.get("system");
    if (systemChart) {
      systemChart.data.labels.push(now);
      systemChart.data.datasets[0].data.push(metrics.system.cpu_usage);
      systemChart.data.datasets[1].data.push(metrics.system.memory_usage);
      systemChart.data.datasets[2].data.push(metrics.system.disk_usage);

      // Keep only last 20 data points
      if (systemChart.data.labels.length > 20) {
        systemChart.data.labels.shift();
        systemChart.data.datasets.forEach((dataset) => {
          dataset.data.shift();
        });
      }

      systemChart.update("none");
    }

    // Update performance chart
    const performanceChart = this.charts.get("performance");
    if (performanceChart) {
      performanceChart.data.labels.push(now);
      performanceChart.data.datasets[0].data.push(
        metrics.performance.response_time,
      );
      performanceChart.data.datasets[1].data.push(
        metrics.performance.throughput,
      );

      if (performanceChart.data.labels.length > 20) {
        performanceChart.data.labels.shift();
        performanceChart.data.datasets.forEach((dataset) => {
          dataset.data.shift();
        });
      }

      performanceChart.update("none");
    }

    // Update database chart
    const databaseChart = this.charts.get("database");
    if (databaseChart) {
      databaseChart.data.datasets[0].data = [
        metrics.database.query_count,
        metrics.database.slow_queries,
        metrics.database.connection_count,
        metrics.database.cache_hit_rate,
      ];
      databaseChart.update("none");
    }

    // Update security chart
    const securityChart = this.charts.get("security");
    if (securityChart) {
      const total =
        metrics.security.failed_login_attempts +
        metrics.security.suspicious_activities +
        metrics.security.security_events;

      securityChart.data.datasets[0].data = [
        metrics.security.failed_login_attempts,
        metrics.security.suspicious_activities,
        metrics.security.security_events,
        Math.max(0, 100 - total),
      ];
      securityChart.update("none");
    }

    // Update user activity chart
    const userActivityChart = this.charts.get("userActivity");
    if (userActivityChart) {
      userActivityChart.data.labels.push(now);
      userActivityChart.data.datasets[0].data.push(
        metrics.userActivity.active_users,
      );

      if (userActivityChart.data.labels.length > 20) {
        userActivityChart.data.labels.shift();
        userActivityChart.data.datasets.forEach((dataset) => {
          dataset.data.shift();
        });
      }

      userActivityChart.update("none");
    }
  }

  /**
   * Update alerts
   */
  updateAlerts(alerts) {
    const alertsContainer = document.getElementById("alerts-container");
    if (!alertsContainer) return;

    alertsContainer.innerHTML = "";

    alerts.forEach((alert) => {
      const alertElement = this.createAlertElement(alert);
      alertsContainer.appendChild(alertElement);
    });
  }

  /**
   * Create alert element
   */
  createAlertElement(alert) {
    const div = document.createElement("div");
    div.className = `alert alert-${alert.type} alert-dismissible fade show`;
    div.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="alert-icon">
                    <i class="fas fa-${alert.type === "critical" ? "exclamation-circle" : "exclamation-triangle"}"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="alert-title mb-1">${alert.message}</h6>
                    <div class="alert-text small text-muted">
                        ${alert.category} • ${this.formatTime(alert.timestamp)}
                    </div>
                </div>
                <div class="ms-3">
                    <span class="badge bg-${alert.type}">${alert.severity}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    return div;
  }

  /**
   * Update logs
   */
  updateLogs(logs) {
    const logsContainer = document.getElementById("logs-container");
    if (!logsContainer) return;

    logs.forEach((log) => {
      const logElement = this.createLogElement(log);
      logsContainer.appendChild(logElement);
    });

    // Auto-scroll to bottom
    logsContainer.scrollTop = logsContainer.scrollHeight;
  }

  /**
   * Create log element
   */
  createLogElement(log) {
    const div = document.createElement("div");
    div.className = `log log-${log.level.toLowerCase()}`;
    div.innerHTML = `
            <span class="log-timestamp">${this.formatTime(log.timestamp)}</span>
            <span class="log-level">${log.level}</span>
            <span class="log-message">${log.message}</span>
        `;
    return div;
  }

  /**
   * Handle alerts
   */
  handleAlert(alert) {
    this.showNotification(alert.message, alert.type);
    this.emit("alert:received", alert);
  }

  /**
   * Handle logs
   */
  handleLog(log) {
    this.emit("log:received", log);
  }

  /**
   * Update system status
   */
  updateSystemStatus(status) {
    const statusElement = document.getElementById("system-status");
    if (statusElement) {
      statusElement.className = `status status-${status.status}`;
      statusElement.textContent = status.status.toUpperCase();
    }
  }

  /**
   * Start periodic updates
   */
  startPeriodicUpdates() {
    // Update metrics periodically
    this.metricsInterval = setInterval(() => {
      if (this.isWebSocketConnected) {
        // WebSocket handles real-time updates
        return;
      }

      // Fallback to HTTP polling
      this.fetchMetrics();
    }, this.config.refresh.realtime);

    // Update charts periodically
    this.chartInterval = setInterval(() => {
      this.updateChartsFromCache();
    }, this.config.refresh.charts);

    // Update alerts periodically
    this.alertInterval = setInterval(() => {
      this.fetchAlerts();
    }, this.config.refresh.alerts);
  }

  /**
   * Update charts from cache
   */
  updateChartsFromCache() {
    const metrics = {
      system: this.metricsCache.get("system"),
      database: this.metricsCache.get("database"),
      performance: this.metricsCache.get("performance"),
      security: this.metricsCache.get("security"),
      userActivity: this.metricsCache.get("userActivity"),
    };

    if (
      metrics.system &&
      metrics.database &&
      metrics.performance &&
      metrics.security &&
      metrics.userActivity
    ) {
      this.updateCharts(metrics);
    }
  }

  /**
   * Fetch metrics via HTTP
   */
  async fetchMetrics() {
    try {
      const response = await fetch("/monitoring/realtime");
      const data = await response.json();
      this.updateMetrics(data);
    } catch (error) {
      console.error("Error fetching metrics:", error);
    }
  }

  /**
   * Fetch alerts
   */
  async fetchAlerts() {
    try {
      const response = await fetch("/monitoring/active-alerts");
      const alerts = await response.json();
      this.updateAlerts(alerts);
    } catch (error) {
      console.error("Error fetching alerts:", error);
    }
  }

  /**
   * Pause updates
   */
  pauseUpdates() {
    clearInterval(this.metricsInterval);
    clearInterval(this.chartInterval);
    clearInterval(this.alertInterval);
  }

  /**
   * Resume updates
   */
  resumeUpdates() {
    this.startPeriodicUpdates();
  }

  /**
   * Show notification
   */
  showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText =
      "top: 20px; right: 20px; z-index: 9999; max-width: 300px;";
    notification.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="alert-icon">
                    <i class="fas fa-${type === "success" ? "check-circle" : type === "error" ? "exclamation-circle" : "info-circle"}"></i>
                </div>
                <div class="flex-grow-1">
                    ${message}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
      notification.remove();
    }, 5000);
  }

  /**
   * Format bytes
   */
  formatBytes(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB", "TB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  /**
   * Format time
   */
  formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;

    if (diff < 60000) {
      return "Just now";
    } else if (diff < 3600000) {
      return `${Math.floor(diff / 60000)}m ago`;
    } else if (diff < 86400000) {
      return `${Math.floor(diff / 3600000)}h ago`;
    } else {
      return date.toLocaleDateString();
    }
  }

  /**
   * Convert hex to RGBA
   */
  hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  /**
   * Emit event
   */
  emit(event, data) {
    if (this.eventListeners.has(event)) {
      this.eventListeners.get(event).forEach((callback) => {
        try {
          callback(data);
        } catch (error) {
          console.error("Error in event callback:", error);
        }
      });
    }
  }

  /**
   * Add event listener
   */
  on(event, callback) {
    if (!this.eventListeners.has(event)) {
      this.eventListeners.set(event, []);
    }
    this.eventListeners.get(event).push(callback);
  }

  /**
   * Remove event listener
   */
  off(event, callback) {
    if (this.eventListeners.has(event)) {
      const callbacks = this.eventListeners.get(event);
      const index = callbacks.indexOf(callback);
      if (index > -1) {
        callbacks.splice(index, 1);
      }
    }
  }

  /**
   * Load configuration
   */
  loadConfiguration() {
    try {
      const stored = localStorage.getItem("monitoring_config");
      if (stored) {
        const config = JSON.parse(stored);
        this.config = this.deepMerge(this.config, config);
      }
    } catch (error) {
      console.error("Error loading configuration:", error);
    }
  }

  /**
   * Deep merge objects
   */
  deepMerge(target, source) {
    const output = Object.assign({}, target);
    if (this.isObject(target) && this.isObject(source)) {
      Object.keys(source).forEach((key) => {
        if (this.isObject(source[key])) {
          if (!(key in target)) Object.assign(output, { [key]: source[key] });
          else output[key] = this.deepMerge(target[key], source[key]);
        } else {
          Object.assign(output, { [key]: source[key] });
        }
      });
    }
    return output;
  }

  /**
   * Check if object
   */
  isObject(item) {
    return item && typeof item === "object" && !Array.isArray(item);
  }

  /**
   * Cleanup resources
   */
  destroy() {
    // Clear intervals
    clearInterval(this.metricsInterval);
    clearInterval(this.chartInterval);
    clearInterval(this.alertInterval);
    clearInterval(this.heartbeatInterval);

    // Close WebSocket
    if (this.websocket) {
      this.websocket.close();
    }

    // Clear charts
    this.charts.forEach((chart) => {
      chart.destroy();
    });
    this.charts.clear();

    // Clear cache
    this.metricsCache.clear();

    // Clear event listeners
    this.eventListeners.clear();
  }
}

// Export monitoring utilities
window.MonitoringUtils = MonitoringUtils;

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  const monitoringUtils = new MonitoringUtils();
  monitoringUtils.initialize();
  window.monitoringUtils = monitoringUtils;
});
