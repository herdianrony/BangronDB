// Monitoring Dashboard JavaScript
class MonitoringDashboard {
  constructor() {
    this.socket = null;
    this.updateInterval = null;
    this.isWebSocketConnected = false;
    this.initializeMonitoring();
  }

  /**
   * Initialize monitoring dashboard
   */
  initializeMonitoring() {
    this.setupWebSocket();
    this.setupEventListeners();
    this.startAutoRefresh();
    this.initializeCharts();
    this.initializeRealTimeUpdates();
  }

  /**
   * Setup WebSocket connection for real-time updates
   */
  setupWebSocket() {
    // Check if WebSocket is supported
    if (typeof WebSocket !== "undefined") {
      const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
      const wsUrl = `${protocol}//${window.location.host}/ws-monitoring`;

      try {
        this.socket = new WebSocket(wsUrl);

        this.socket.onopen = () => {
          console.log("WebSocket connected");
          this.isWebSocketConnected = true;
          this.showNotification("Connected to real-time monitoring", "success");
        };

        this.socket.onmessage = (event) => {
          const data = JSON.parse(event.data);
          this.handleWebSocketMessage(data);
        };

        this.socket.onclose = () => {
          console.log("WebSocket disconnected");
          this.isWebSocketConnected = false;
          this.reconnectWebSocket();
        };

        this.socket.onerror = (error) => {
          console.error("WebSocket error:", error);
          this.showNotification("WebSocket connection error", "error");
        };
      } catch (error) {
        console.error("Failed to connect to WebSocket:", error);
        this.showNotification("Real-time monitoring unavailable", "warning");
      }
    } else {
      console.warn("WebSocket not supported, falling back to HTTP polling");
      this.startPolling();
    }
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
      default:
        console.warn("Unknown message type:", data.type);
    }
  }

  /**
   * Reconnect WebSocket if connection is lost
   */
  reconnectWebSocket() {
    setTimeout(() => {
      if (!this.isWebSocketConnected) {
        this.setupWebSocket();
      }
    }, 5000);
  }

  /**
   * Start HTTP polling as fallback
   */
  startPolling() {
    this.updateInterval = setInterval(() => {
      this.fetchMetrics();
    }, 10000); // Poll every 10 seconds
  }

  /**
   * Stop polling
   */
  stopPolling() {
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
      this.updateInterval = null;
    }
  }

  /**
   * Fetch metrics via HTTP
   */
  fetchMetrics() {
    fetch("/monitoring/realtime")
      .then((response) => response.json())
      .then((data) => {
        this.updateMetrics(data);
      })
      .catch((error) => {
        console.error("Error fetching metrics:", error);
      });
  }

  /**
   * Update metrics on the page
   */
  updateMetrics(data) {
    // Update health status
    if (data.health_status) {
      const healthElement = document.querySelector(".text-2xl.font-bold");
      if (healthElement) {
        healthElement.textContent = `${data.health_status.overall.toFixed(1)}%`;
      }
    }

    // Update system metrics
    if (data.system) {
      this.updateSystemMetrics(data.system);
    }

    // Update database metrics
    if (data.database) {
      this.updateDatabaseMetrics(data.database);
    }

    // Update performance metrics
    if (data.performance) {
      this.updatePerformanceMetrics(data.performance);
    }

    // Update security metrics
    if (data.security) {
      this.updateSecurityMetrics(data.security);
    }

    // Update user activity
    if (data.user_activity) {
      this.updateUserActivity(data.user_activity);
    }

    // Update alerts
    if (data.alerts) {
      this.updateAlerts(data.alerts);
    }

    // Update charts
    if (data.historical_data) {
      this.updateCharts(data.historical_data);
    }
  }

  /**
   * Update system metrics
   */
  updateSystemMetrics(metrics) {
    const cpuElement = document.querySelector(".text-blue-600");
    const memoryElement = document.querySelectorAll(".text-2xl.font-bold")[1];
    const diskElement = document.querySelectorAll(".text-2xl.font-bold")[2];

    if (cpuElement) {
      cpuElement.textContent = `${metrics.cpu_usage.toFixed(1)}%`;
    }
    if (memoryElement) {
      memoryElement.textContent = `${metrics.memory_usage.toFixed(1)}%`;
    }
    if (diskElement) {
      diskElement.textContent = `${metrics.disk_usage.toFixed(1)}%`;
    }

    // Update progress bars
    this.updateProgressBar("cpu", metrics.cpu_usage);
    this.updateProgressBar("memory", metrics.memory_usage);
    this.updateProgressBar("disk", metrics.disk_usage);
  }

  /**
   * Update database metrics
   */
  updateDatabaseMetrics(metrics) {
    const elements = {
      total_size: document.querySelector(
        ".font-mono.text-sm.font-medium.text-slate-900",
      ),
      page_count: document.querySelectorAll(
        ".font-mono.text-sm.font-medium.text-slate-900",
      )[1],
      page_size: document.querySelectorAll(
        ".font-mono.text-sm.font-medium.text-slate-900",
      )[2],
      fragmentation: document.querySelectorAll(
        ".font-mono.text-sm.font-medium.text-slate-900",
      )[3],
      index_count: document.querySelectorAll(
        ".font-mono.text-sm.font-medium.text-slate-900",
      )[4],
      connection_count: document.querySelectorAll(
        ".font-mono.text-sm.font-medium.text-slate-900",
      )[5],
      slow_queries: document.querySelectorAll(
        ".font-mono.text-sm.font-medium.text-slate-900",
      )[6],
      cache_hit_rate: document.querySelectorAll(
        ".font-mono.text-sm.font-medium.text-slate-900",
      )[7],
    };

    if (elements.total_size)
      elements.total_size.textContent = `${metrics.total_size.toFixed(1)} GB`;
    if (elements.page_count)
      elements.page_count.textContent = metrics.page_count.toLocaleString();
    if (elements.page_size)
      elements.page_size.textContent = `${metrics.page_size.toLocaleString()} B`;
    if (elements.fragmentation)
      elements.fragmentation.textContent = `${metrics.fragmentation.toFixed(1)}%`;
    if (elements.index_count)
      elements.index_count.textContent = metrics.index_count;
    if (elements.connection_count)
      elements.connection_count.textContent = metrics.connection_count;
    if (elements.slow_queries)
      elements.slow_queries.textContent = metrics.slow_queries;
    if (elements.cache_hit_rate)
      elements.cache_hit_rate.textContent = `${metrics.cache_hit_rate.toFixed(1)}%`;
  }

  /**
   * Update performance metrics
   */
  updatePerformanceMetrics(metrics) {
    const elements = {
      response_time: document.querySelector(".text-blue-600"),
      throughput: document.querySelectorAll(".text-green-600")[0],
      error_rate: document.querySelectorAll(".text-amber-600")[0],
      queue_length: document.querySelectorAll(".text-purple-600")[0],
    };

    if (elements.response_time)
      elements.response_time.textContent = `${metrics.response_time}ms`;
    if (elements.throughput)
      elements.throughput.textContent = `${metrics.throughput}/s`;
    if (elements.error_rate)
      elements.error_rate.textContent = `${metrics.error_rate}%`;
    if (elements.queue_length)
      elements.queue_length.textContent = metrics.queue_length;
  }

  /**
   * Update security metrics
   */
  updateSecurityMetrics(metrics) {
    const elements = {
      failed_login_attempts: document.querySelector(".text-red-600"),
      suspicious_activities: document.querySelector(".text-orange-600"),
      security_events: document.querySelector(".text-yellow-600"),
    };

    if (elements.failed_login_attempts) {
      elements.failed_login_attempts.textContent =
        metrics.failed_login_attempts;
    }
    if (elements.suspicious_activities) {
      elements.suspicious_activities.textContent =
        metrics.suspicious_activities;
    }
    if (elements.security_events) {
      elements.security_events.textContent = metrics.security_events;
    }
  }

  /**
   * Update user activity
   */
  updateUserActivity(activity) {
    const activeUsersElement = document.querySelector(".text-blue-600");
    const sessionCountElement = document.querySelector(".text-green-600");

    if (activeUsersElement) {
      activeUsersElement.textContent = activity.active_users;
    }
    if (sessionCountElement) {
      sessionCountElement.textContent = activity.session_count;
    }

    // Update recent user actions
    this.updateUserActions(activity.user_actions);
  }

  /**
   * Update user actions
   */
  updateUserActions(actions) {
    const actionsContainer = document.querySelector(".space-y-2");
    if (!actionsContainer) return;

    actionsContainer.innerHTML = "";
    actions.forEach((action) => {
      const actionElement = document.createElement("div");
      actionElement.className = "flex items-center justify-between text-sm";
      actionElement.innerHTML = `
                <span class="text-slate-600">${action.user}</span>
                <span class="text-slate-900">${action.action}</span>
                <span class="text-slate-500 text-xs">${this.formatTime(action.time)}</span>
            `;
      actionsContainer.appendChild(actionElement);
    });
  }

  /**
   * Update alerts
   */
  updateAlerts(alerts) {
    const alertsContainer = document.querySelector(
      ".divide-y.divide-slate-100",
    );
    if (!alertsContainer) return;

    alertsContainer.innerHTML = "";
    alerts.forEach((alert) => {
      const alertElement = document.createElement("div");
      alertElement.className = "px-6 py-4 flex items-center justify-between";
      alertElement.innerHTML = `
                <div class="flex items-center gap-4">
                    <div class="w-8 h-8 rounded-full bg-${alert.type}-100 flex items-center justify-center">
                        <i data-lucide="${alert.type === "critical" ? "alert-circle" : "alert-triangle"}" class="w-4 h-4 text-${alert.type}-600"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-900">${alert.message}</p>
                        <p class="text-xs text-slate-500">${alert.category} • ${alert.timestamp}</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-${alert.type}-100 text-${alert.type}-800">
                    ${alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)}
                </span>
            `;
      alertsContainer.appendChild(alertElement);
    });

    // Re-initialize Lucide icons
    lucide.createIcons();
  }

  /**
   * Update progress bar
   */
  updateProgressBar(type, value) {
    const progressBar = document.querySelector(
      `[data-metric="${type}"] .bg-${type}-500`,
    );
    if (progressBar) {
      progressBar.style.width = `${value}%`;
    }
  }

  /**
   * Handle alerts
   */
  handleAlert(alert) {
    this.showNotification(alert.message, alert.type);
    this.updateAlerts([alert]);
  }

  /**
   * Handle log messages
   */
  handleLog(log) {
    const logStream = document.getElementById("logStream");
    if (!logStream) return;

    const logElement = document.createElement("div");
    logElement.className = "text-slate-400";
    logElement.innerHTML = `
            <span class="text-slate-500">[${log.timestamp}]</span> 
            <span class="text-${log.level.toLowerCase()}-400">${log.level}</span>
            ${log.message}
        `;

    logStream.appendChild(logElement);
    logStream.scrollTop = logStream.scrollHeight;
  }

  /**
   * Update system status
   */
  updateSystemStatus(status) {
    const statusElement = document.querySelector(
      ".bg-emerald-100.text-emerald-800",
    );
    if (statusElement) {
      statusElement.textContent =
        status.status.charAt(0).toUpperCase() + status.status.slice(1);
    }
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Refresh button
    const refreshButton = document.querySelector(
      'button[onclick="refreshMetrics()"]',
    );
    if (refreshButton) {
      refreshButton.addEventListener("click", () => {
        this.refreshMetrics();
      });
    }

    // Log level filter
    const logLevelSelect = document.getElementById("logLevel");
    if (logLevelSelect) {
      logLevelSelect.addEventListener("change", (e) => {
        this.filterLogs(e.target.value);
      });
    }

    // Clear logs button
    const clearLogsButton = document.querySelector(
      'button[onclick="clearLogs()"]',
    );
    if (clearLogsButton) {
      clearLogsButton.addEventListener("click", () => {
        this.clearLogs();
      });
    }

    // Export logs buttons
    const exportButtons = document.querySelectorAll("button[data-export]");
    exportButtons.forEach((button) => {
      button.addEventListener("click", (e) => {
        this.exportLogs(e.target.dataset.export);
      });
    });
  }

  /**
   * Start auto-refresh
   */
  startAutoRefresh() {
    if (this.isWebSocketConnected) {
      // WebSocket handles real-time updates
      return;
    }

    // Fallback to HTTP polling
    this.startPolling();
  }

  /**
   * Refresh metrics manually
   */
  refreshMetrics() {
    if (this.isWebSocketConnected) {
      // Request fresh data from WebSocket
      this.socket.send(
        JSON.stringify({
          type: "request_metrics",
          timestamp: Date.now(),
        }),
      );
    } else {
      this.fetchMetrics();
    }

    this.showNotification("Metrics refreshed", "success");
  }

  /**
   * Initialize charts
   */
  initializeCharts() {
    this.initializeSystemChart();
    this.initializePerformanceChart();
    this.initializeDatabaseChart();
  }

  /**
   * Initialize system chart
   */
  initializeSystemChart() {
    const canvas = document.getElementById("systemChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    this.systemChart = new Chart(ctx, {
      type: "line",
      data: {
        labels: [],
        datasets: [
          {
            label: "CPU Usage",
            data: [],
            borderColor: "rgb(59, 130, 246)",
            backgroundColor: "rgba(59, 130, 246, 0.1)",
            tension: 0.1,
          },
          {
            label: "Memory Usage",
            data: [],
            borderColor: "rgb(147, 51, 234)",
            backgroundColor: "rgba(147, 51, 234, 0.1)",
            tension: 0.1,
          },
          {
            label: "Disk Usage",
            data: [],
            borderColor: "rgb(245, 158, 11)",
            backgroundColor: "rgba(245, 158, 11, 0.1)",
            tension: 0.1,
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
  }

  /**
   * Initialize performance chart
   */
  initializePerformanceChart() {
    const canvas = document.getElementById("performanceChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    this.performanceChart = new Chart(ctx, {
      type: "line",
      data: {
        labels: [],
        datasets: [
          {
            label: "Response Time",
            data: [],
            borderColor: "rgb(34, 197, 94)",
            backgroundColor: "rgba(34, 197, 94, 0.1)",
            tension: 0.1,
          },
          {
            label: "Throughput",
            data: [],
            borderColor: "rgb(168, 85, 247)",
            backgroundColor: "rgba(168, 85, 247, 0.1)",
            tension: 0.1,
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
  }

  /**
   * Initialize database chart
   */
  initializeDatabaseChart() {
    const canvas = document.getElementById("databaseChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    this.databaseChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: ["Queries", "Slow Queries", "Connections", "Cache Hit Rate"],
        datasets: [
          {
            label: "Database Metrics",
            data: [0, 0, 0, 0],
            backgroundColor: [
              "rgba(59, 130, 246, 0.8)",
              "rgba(245, 158, 11, 0.8)",
              "rgba(34, 197, 94, 0.8)",
              "rgba(168, 85, 247, 0.8)",
            ],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
      },
    });
  }

  /**
   * Update charts
   */
  updateCharts(data) {
    if (this.systemChart && data.system) {
      this.updateSystemChart(data.system);
    }
    if (this.performanceChart && data.performance) {
      this.updatePerformanceChart(data.performance);
    }
    if (this.databaseChart && data.database) {
      this.updateDatabaseChart(data.database);
    }
  }

  /**
   * Update system chart
   */
  updateSystemChart(data) {
    const now = new Date().toLocaleTimeString();

    // Add new data point
    this.systemChart.data.labels.push(now);
    this.systemChart.data.datasets[0].data.push(data.cpu_usage);
    this.systemChart.data.datasets[1].data.push(data.memory_usage);
    this.systemChart.data.datasets[2].data.push(data.disk_usage);

    // Keep only last 20 data points
    if (this.systemChart.data.labels.length > 20) {
      this.systemChart.data.labels.shift();
      this.systemChart.data.datasets.forEach((dataset) => {
        dataset.data.shift();
      });
    }

    this.systemChart.update();
  }

  /**
   * Update performance chart
   */
  updatePerformanceChart(data) {
    const now = new Date().toLocaleTimeString();

    // Add new data point
    this.performanceChart.data.labels.push(now);
    this.performanceChart.data.datasets[0].data.push(data.response_time);
    this.performanceChart.data.datasets[1].data.push(data.throughput);

    // Keep only last 20 data points
    if (this.performanceChart.data.labels.length > 20) {
      this.performanceChart.data.labels.shift();
      this.performanceChart.data.datasets.forEach((dataset) => {
        dataset.data.shift();
      });
    }

    this.performanceChart.update();
  }

  /**
   * Update database chart
   */
  updateDatabaseChart(data) {
    this.databaseChart.data.datasets[0].data = [
      data.query_count,
      data.slow_queries,
      data.connection_count,
      data.cache_hit_rate,
    ];
    this.databaseChart.update();
  }

  /**
   * Initialize real-time updates
   */
  initializeRealTimeUpdates() {
    // Set up periodic updates
    setInterval(() => {
      if (this.isWebSocketConnected) {
        this.socket.send(
          JSON.stringify({
            type: "ping",
            timestamp: Date.now(),
          }),
        );
      }
    }, 30000); // Ping every 30 seconds
  }

  /**
   * Filter logs by level
   */
  filterLogs(level) {
    const logs = document.querySelectorAll("#logStream > div");

    logs.forEach((log) => {
      if (level === "All Levels") {
        log.style.display = "block";
      } else {
        const logLevel = log.querySelector("span:nth-child(2)").textContent;
        log.style.display = logLevel === level ? "block" : "none";
      }
    });
  }

  /**
   * Clear logs
   */
  clearLogs() {
    const logStream = document.getElementById("logStream");
    if (logStream) {
      logStream.innerHTML = "";
    }
    this.showNotification("Logs cleared", "success");
  }

  /**
   * Export logs
   */
  exportLogs(format) {
    const params = new URLSearchParams({
      format: format,
      start_date: document.getElementById("startDate")?.value || "",
      end_date: document.getElementById("endDate")?.value || "",
    });

    fetch(`/monitoring/export-logs?${params}`)
      .then((response) => {
        if (response.ok) {
          const blob = response.blob();
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = url;
          a.download = `logs.${format}`;
          a.click();
          window.URL.revokeObjectURL(url);
          this.showNotification("Logs exported successfully", "success");
        } else {
          throw new Error("Export failed");
        }
      })
      .catch((error) => {
        console.error("Export error:", error);
        this.showNotification("Export failed", "error");
      });
  }

  /**
   * Show notification
   */
  showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-sm font-medium z-50 ${
      type === "success"
        ? "bg-green-100 text-green-800"
        : type === "error"
          ? "bg-red-100 text-red-800"
          : type === "warning"
            ? "bg-yellow-100 text-yellow-800"
            : "bg-blue-100 text-blue-800"
    }`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Remove notification after 3 seconds
    setTimeout(() => {
      notification.remove();
    }, 3000);
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
   * Cleanup resources
   */
  destroy() {
    if (this.socket) {
      this.socket.close();
      this.socket = null;
    }

    this.stopPolling();

    if (this.systemChart) {
      this.systemChart.destroy();
      this.systemChart = null;
    }

    if (this.performanceChart) {
      this.performanceChart.destroy();
      this.performanceChart = null;
    }

    if (this.databaseChart) {
      this.databaseChart.destroy();
      this.databaseChart = null;
    }
  }
}

// Initialize monitoring dashboard when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.monitoringDashboard = new MonitoringDashboard();
});

// Cleanup on page unload
window.addEventListener("beforeunload", () => {
  if (window.monitoringDashboard) {
    window.monitoringDashboard.destroy();
  }
});

// Chart.js library (if not already loaded)
if (typeof Chart === "undefined") {
  const script = document.createElement("script");
  script.src = "https://cdn.jsdelivr.net/npm/chart.js";
  script.onload = () => {
    console.log("Chart.js loaded");
  };
  document.head.appendChild(script);
}
