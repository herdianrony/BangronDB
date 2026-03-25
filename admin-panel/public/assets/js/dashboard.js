/**
 * BangronDB Admin Panel Dashboard
 * Enhanced dashboard with real-time metrics, charts, and interactive features
 */

class DashboardManager {
  constructor() {
    this.charts = {};
    this.realTimeInterval = null;
    this.searchTimeout = null;
    this.init();
  }

  init() {
    this.initializeCharts();
    this.startRealTimeUpdates();
    this.initializeEventListeners();
    this.initializeTooltips();
    this.initializeModals();
    this.initializeFilters();
  }

  // Chart Initialization
  initializeCharts() {
    this.initializeActivityChart();
    this.initializePerformanceChart();
    this.initializeStorageChart();
    this.initializeUserActivityChart();
  }

  initializeActivityChart() {
    const ctx = document.getElementById("activityChart");
    if (!ctx) return;

    this.charts.activity = new Chart(ctx.getContext("2d"), {
      type: "line",
      data: {
        labels: this.getLast7Days(),
        datasets: [
          {
            label: "Documents Created",
            data: this.generateRandomData(7, 10, 50),
            borderColor: "#3b82f6",
            backgroundColor: "rgba(59, 130, 246, 0.1)",
            tension: 0.4,
            fill: true,
          },
          {
            label: "Documents Updated",
            data: this.generateRandomData(7, 5, 30),
            borderColor: "#8b5cf6",
            backgroundColor: "rgba(139, 92, 246, 0.1)",
            tension: 0.4,
            fill: true,
          },
          {
            label: "Documents Deleted",
            data: this.generateRandomData(7, 0, 20),
            borderColor: "#ef4444",
            backgroundColor: "rgba(239, 68, 68, 0.1)",
            tension: 0.4,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          intersect: false,
          mode: "index",
        },
        plugins: {
          legend: {
            labels: {
              color: "#9ca3af",
              usePointStyle: true,
              padding: 20,
            },
          },
          tooltip: {
            backgroundColor: "rgba(15, 23, 42, 0.9)",
            titleColor: "#f1f5f9",
            bodyColor: "#94a3b8",
            borderColor: "rgba(255, 255, 255, 0.1)",
            borderWidth: 1,
            cornerRadius: 8,
            displayColors: true,
            callbacks: {
              label: function (context) {
                return (
                  context.dataset.label + ": " + context.parsed.y + " documents"
                );
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: "#9ca3af",
              maxRotation: 45,
            },
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
              drawBorder: false,
            },
          },
          y: {
            ticks: {
              color: "#9ca3af",
              callback: function (value) {
                return value + " docs";
              },
            },
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
              drawBorder: false,
            },
          },
        },
      },
    });
  }

  initializePerformanceChart() {
    const ctx = document.getElementById("performanceChart");
    if (!ctx) return;

    this.charts.performance = new Chart(ctx.getContext("2d"), {
      type: "bar",
      data: {
        labels: ["00:00", "04:00", "08:00", "12:00", "16:00", "20:00", "24:00"],
        datasets: [
          {
            label: "Read Operations",
            data: this.generateRandomData(7, 50, 300),
            backgroundColor: "#10b981",
            borderRadius: 6,
            borderSkipped: false,
          },
          {
            label: "Write Operations",
            data: this.generateRandomData(7, 20, 150),
            backgroundColor: "#f59e0b",
            borderRadius: 6,
            borderSkipped: false,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              color: "#9ca3af",
              usePointStyle: true,
              padding: 20,
            },
          },
          tooltip: {
            backgroundColor: "rgba(15, 23, 42, 0.9)",
            titleColor: "#f1f5f9",
            bodyColor: "#94a3b8",
            borderColor: "rgba(255, 255, 255, 0.1)",
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
              label: function (context) {
                return context.dataset.label + ": " + context.parsed.y + " ops";
              },
            },
          },
        },
        scales: {
          x: {
            ticks: {
              color: "#9ca3af",
            },
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
              drawBorder: false,
            },
          },
          y: {
            ticks: {
              color: "#9ca3af",
              callback: function (value) {
                return value + " ops";
              },
            },
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
              drawBorder: false,
            },
          },
        },
      },
    });
  }

  initializeStorageChart() {
    const ctx = document.getElementById("storageChart");
    if (!ctx) return;

    this.charts.storage = new Chart(ctx.getContext("2d"), {
      type: "doughnut",
      data: {
        labels: ["Used Storage", "Available Storage"],
        datasets: [
          {
            data: [65, 35],
            backgroundColor: ["#3b82f6", "#1e293b"],
            borderWidth: 0,
            cutout: "70%",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              color: "#9ca3af",
              usePointStyle: true,
              padding: 20,
            },
          },
          tooltip: {
            backgroundColor: "rgba(15, 23, 42, 0.9)",
            titleColor: "#f1f5f9",
            bodyColor: "#94a3b8",
            borderColor: "rgba(255, 255, 255, 0.1)",
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
              label: function (context) {
                const label = context.label || "";
                const value = context.parsed || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((value / total) * 100).toFixed(1);
                return label + ": " + percentage + "%";
              },
            },
          },
        },
      },
    });
  }

  initializeUserActivityChart() {
    const ctx = document.getElementById("userActivityChart");
    if (!ctx) return;

    this.charts.userActivity = new Chart(ctx.getContext("2d"), {
      type: "radar",
      data: {
        labels: ["Read", "Write", "Delete", "Create", "Update", "Query"],
        datasets: [
          {
            label: "Today",
            data: [85, 70, 45, 90, 75, 80],
            borderColor: "#3b82f6",
            backgroundColor: "rgba(59, 130, 246, 0.2)",
            pointBackgroundColor: "#3b82f6",
            pointBorderColor: "#fff",
            pointHoverBackgroundColor: "#fff",
            pointHoverBorderColor: "#3b82f6",
          },
          {
            label: "Yesterday",
            data: [75, 65, 55, 80, 70, 75],
            borderColor: "#8b5cf6",
            backgroundColor: "rgba(139, 92, 246, 0.2)",
            pointBackgroundColor: "#8b5cf6",
            pointBorderColor: "#fff",
            pointHoverBackgroundColor: "#fff",
            pointHoverBorderColor: "#8b5cf6",
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              color: "#9ca3af",
              usePointStyle: true,
              padding: 20,
            },
          },
          tooltip: {
            backgroundColor: "rgba(15, 23, 42, 0.9)",
            titleColor: "#f1f5f9",
            bodyColor: "#94a3b8",
            borderColor: "rgba(255, 255, 255, 0.1)",
            borderWidth: 1,
            cornerRadius: 8,
          },
        },
        scales: {
          r: {
            angleLines: {
              color: "rgba(255, 255, 255, 0.1)",
              display: true,
            },
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
              circular: true,
            },
            pointLabels: {
              color: "#9ca3af",
              font: {
                size: 12,
              },
            },
            ticks: {
              color: "#9ca3af",
              backdropColor: "transparent",
              showLabelBackdrop: false,
            },
          },
        },
      },
    });
  }

  // Real-time Updates
  startRealTimeUpdates() {
    // Update metrics every 30 seconds
    this.realTimeInterval = setInterval(() => {
      this.updateSystemMetrics();
      this.updateCharts();
    }, 30000);

    // Initial update
    this.updateSystemMetrics();
  }

  updateSystemMetrics() {
    // Simulate real-time metric updates
    const metrics = this.generateSystemMetrics();

    // Update CPU usage
    this.updateProgressBar("cpu", metrics.cpu);

    // Update memory usage
    this.updateProgressBar("memory", metrics.memory);

    // Update disk usage
    this.updateProgressBar("disk", metrics.disk);

    // Update active connections
    this.updateConnections(metrics.connections);

    // Update activity count
    this.updateActivityCount(metrics.activity);

    // Update timestamp
    this.updateTimestamp();
  }

  updateProgressBar(type, value) {
    const progressBar = document.querySelector(
      `[data-metric="${type}"] .progress-bar-fill`,
    );
    const valueText = document.querySelector(
      `[data-metric="${type}"] .metric-value`,
    );

    if (progressBar) {
      progressBar.style.width = Math.min(value, 100) + "%";

      // Update color based on value
      let colorClass = "bg-green-500";
      if (value >= 70 && value < 90) colorClass = "bg-yellow-500";
      if (value >= 90) colorClass = "bg-red-500";

      progressBar.className = `progress-bar-fill ${colorClass}`;
    }

    if (valueText) {
      valueText.textContent = value + "%";
    }
  }

  updateConnections(count) {
    const connectionsElement = document.querySelector(
      '[data-metric="connections"] .metric-value',
    );
    if (connectionsElement) {
      connectionsElement.textContent = count;
    }

    const progressBar = document.querySelector(
      '[data-metric="connections"] .progress-bar-fill',
    );
    if (progressBar) {
      progressBar.style.width = Math.min((count / 1000) * 100, 100) + "%";
    }
  }

  updateActivityCount(count) {
    const activityElement = document.querySelector(
      '[data-metric="activity"] .metric-value',
    );
    if (activityElement) {
      activityElement.textContent = count;
    }
  }

  updateTimestamp() {
    const timestampElement = document.querySelector(".last-upplied");
    if (timestampElement) {
      const now = new Date();
      timestampElement.textContent =
        "Last updated: " + now.toLocaleTimeString("id-ID");
    }
  }

  updateCharts() {
    // Update activity chart with new data
    if (this.charts.activity) {
      const newData = this.generateRandomData(1, 10, 50);
      this.charts.activity.data.datasets[0].data.shift();
      this.charts.activity.data.datasets[0].data.push(newData[0]);

      this.charts.activity.data.datasets[1].data.shift();
      this.charts.activity.data.datasets[1].data.push(newData[1] - 5);

      this.charts.activity.update("none");
    }

    // Update performance chart
    if (this.charts.performance) {
      const newReadData = Math.floor(Math.random() * 250) + 50;
      const newWriteData = Math.floor(Math.random() * 130) + 20;

      this.charts.performance.data.datasets[0].data.shift();
      this.charts.performance.data.datasets[0].data.push(newReadData);

      this.charts.performance.data.datasets[1].data.shift();
      this.charts.performance.data.datasets[1].data.push(newWriteData);

      this.charts.performance.update("none");
    }
  }

  // Event Listeners
  initializeEventListeners() {
    // Search functionality
    const searchInput = document.querySelector("[data-search]");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        this.handleSearch(e.target.value);
      });
    }

    // Export buttons
    document.querySelectorAll("[data-export]").forEach((button) => {
      button.addEventListener("click", (e) => {
        this.handleExport(e.target.dataset.export);
      });
    });

    // Filter dropdowns
    document.querySelectorAll("[data-filter]").forEach((select) => {
      select.addEventListener("change", (e) => {
        this.handleFilter(e.target.dataset.filter, e.target.value);
      });
    });

    // Quick action buttons
    document.querySelectorAll("[data-action]").forEach((button) => {
      button.addEventListener("click", (e) => {
        this.handleQuickAction(e.target.dataset.action);
      });
    });

    // Notification bell
    const notificationBell = document.querySelector("[data-notifications]");
    if (notificationBell) {
      notificationBell.addEventListener("click", () => {
        this.toggleNotifications();
      });
    }
  }

  handleSearch(query) {
    clearTimeout(this.searchTimeout);

    this.searchTimeout = setTimeout(() => {
      if (query.length > 2) {
        this.performSearch(query);
      }
    }, 300);
  }

  performSearch(query) {
    // Implement search functionality
    console.log("Searching for:", query);

    // Show loading state
    this.showLoadingState();

    // Simulate search API call
    setTimeout(() => {
      this.hideLoadingState();
      this.displaySearchResults(query);
    }, 1000);
  }

  displaySearchResults(query) {
    // Update search results display
    const resultsContainer = document.querySelector("[data-search-results]");
    if (resultsContainer) {
      resultsContainer.innerHTML = `
                <div class="p-4 bg-blue-500/10 border border-blue-500/20 rounded-lg">
                    <p class="text-sm text-blue-400">
                        <i data-lucide="search" class="w-4 h-4 inline mr-2"></i>
                        Search results for "${query}"
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Found 3 results in databases, 5 results in collections</p>
                </div>
            `;
      lucide.createIcons();
    }
  }

  handleExport(type) {
    console.log("Exporting:", type);

    // Show loading state
    this.showLoadingState();

    // Simulate export
    setTimeout(() => {
      this.hideLoadingState();
      this.downloadReport(type);
    }, 1500);
  }

  downloadReport(type) {
    const reportData = this.generateReportData(type);
    const blob = new Blob([JSON.stringify(reportData, null, 2)], {
      type: "application/json",
    });
    const url = URL.createObjectURL(blob);

    const a = document.createElement("a");
    a.href = url;
    a.download = `bangrondb-${type}-report-${new Date().toISOString().split("T")[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  handleFilter(filterType, value) {
    console.log("Filter:", filterType, "=", value);

    // Update chart data based on filter
    if (filterType === "timeRange") {
      this.updateChartData(value);
    }
  }

  handleQuickAction(action) {
    console.log("Quick action:", action);

    // Navigate to appropriate page
    switch (action) {
      case "newDatabase":
        window.location.href = "/databases/create";
        break;
      case "newCollection":
        window.location.href = "/collections/create";
        break;
      case "newUser":
        window.location.href = "/users/create";
        break;
      case "runQuery":
        window.location.href = "/query-playground";
        break;
    }
  }

  toggleNotifications() {
    const notificationsPanel = document.querySelector(
      "[data-notifications-panel]",
    );
    if (notificationsPanel) {
      notificationsPanel.classList.toggle("hidden");
    }
  }

  // UI Components
  initializeTooltips() {
    // Initialize tooltips using Alpine.js or custom implementation
    document.querySelectorAll("[data-tooltip]").forEach((element) => {
      element.addEventListener("mouseenter", (e) => {
        this.showTooltip(e.target);
      });

      element.addEventListener("mouseleave", () => {
        this.hideTooltip();
      });
    });
  }

  showTooltip(element) {
    const tooltip = document.createElement("div");
    tooltip.className =
      "absolute bg-gray-800 text-white text-xs rounded px-2 py-1 z-50";
    tooltip.textContent = element.dataset.tooltip;

    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + "px";
    tooltip.style.top = rect.bottom + 5 + "px";

    document.body.appendChild(tooltip);
    element._tooltip = tooltip;
  }

  hideTooltip() {
    if (document.querySelector("[data-tooltip]")?._tooltip) {
      document.querySelector("[data-tooltip]")?._tooltip.remove();
    }
  }

  initializeModals() {
    // Initialize modal functionality
    document.querySelectorAll("[data-modal]").forEach((button) => {
      button.addEventListener("click", (e) => {
        const modalId = e.target.dataset.modal;
        this.openModal(modalId);
      });
    });

    document.querySelectorAll("[data-close-modal]").forEach((button) => {
      button.addEventListener("click", (e) => {
        this.closeModal();
      });
    });
  }

  openModal(modalId) {
    const modal = document.querySelector(`[data-modal-content="${modalId}"]`);
    if (modal) {
      modal.classList.remove("hidden");
      modal.classList.add("flex");
    }
  }

  closeModal() {
    const modals = document.querySelectorAll("[data-modal-content]");
    modals.forEach((modal) => {
      modal.classList.add("hidden");
      modal.classList.remove("flex");
    });
  }

  initializeFilters() {
    // Initialize advanced filtering
    document.querySelectorAll("[data-advanced-filter]").forEach((filter) => {
      filter.addEventListener("change", (e) => {
        this.applyAdvancedFilters();
      });
    });
  }

  applyAdvancedFilters() {
    // Apply multiple filters
    const filters = {};
    document.querySelectorAll("[data-advanced-filter]").forEach((filter) => {
      filters[filter.dataset.advancedFilter] = filter.value;
    });

    console.log("Applying filters:", filters);
    this.updateDashboardData(filters);
  }

  // Data Management
  updateDashboardData(filters) {
    // Show loading state
    this.showLoadingState();

    // Simulate API call
    setTimeout(() => {
      this.hideLoadingState();
      this.refreshDashboardData();
    }, 1000);
  }

  refreshDashboardData() {
    // Update all dashboard components with new data
    this.updateMetrics();
    this.updateCharts();
    this.updateActivityFeed();
    this.updateSystemStatus();
  }

  updateMetrics() {
    // Update all metric cards
    const metrics = this.generateSystemMetrics();

    document.querySelectorAll("[data-metric]").forEach((metricElement) => {
      const metricType = metricElement.dataset.metric;
      const value = metrics[metricType] || 0;

      const valueElement = metricElement.querySelector(".metric-value");
      if (valueElement) {
        valueElement.textContent = value;
      }
    });
  }

  updateActivityFeed() {
    // Simulate new activity
    const activities = this.generateRecentActivities();
    const feedContainer = document.querySelector("[data-activity-feed]");

    if (feedContainer && activities.length > 0) {
      const newActivity = activities[0];
      const activityElement = this.createActivityElement(newActivity);

      feedContainer.insertBefore(activityElement, feedContainer.firstChild);

      // Remove old activities if too many
      while (feedContainer.children.length > 10) {
        feedContainer.removeChild(feedContainer.lastChild);
      }
    }
  }

  createActivityElement(activity) {
    const element = document.createElement("div");
    element.className = "activity-item p-4 flex items-start gap-3 fade-in";

    const iconColor =
      activity.type === "create"
        ? "green"
        : activity.type === "update"
          ? "blue"
          : activity.type === "delete"
            ? "red"
            : "yellow";

    const icon =
      activity.type === "create"
        ? "plus"
        : activity.type === "update"
          ? "edit"
          : activity.type === "delete"
            ? "trash-2"
            : "key";

    element.innerHTML = `
            <div class="w-9 h-9 bg-${iconColor}-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                <i data-lucide="${icon}" class="w-4 h-4 text-${iconColor}-400"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm">
                    <span class="font-medium">${activity.user}</span> 
                    ${activity.action}
                    <span class="text-blue-400">${activity.target}</span>
                </p>
                <p class="text-xs text-gray-400 mt-1">${activity.timestamp}</p>
            </div>
        `;

    return element;
  }

  updateSystemStatus() {
    // Update system status indicators
    const statusElements = document.querySelectorAll("[data-system-status]");
    statusElements.forEach((statusElement) => {
      const service = statusElement.dataset.systemStatus;
      const status = this.getServiceStatus(service);

      const indicator = statusElement.querySelector(".status-indicator");
      const text = statusElement.querySelector(".status-text");

      if (indicator) {
        indicator.className = `w-2 h-2 rounded-full bg-${status.color}-500`;
      }

      if (text) {
        text.textContent = status.text;
        text.className = `text-xs text-${status.color}-400`;
      }
    });
  }

  // Utility Functions
  generateRandomData(count, min, max) {
    const data = [];
    for (let i = 0; i < count; i++) {
      data.push(Math.floor(Math.random() * (max - min + 1)) + min);
    }
    return data;
  }

  getLast7Days() {
    const days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    const result = [];
    const today = new Date();

    for (let i = 6; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      result.push(days[date.getDay()]);
    }

    return result;
  }

  generateSystemMetrics() {
    return {
      cpu: Math.floor(Math.random() * 100),
      memory: Math.floor(Math.random() * 100),
      disk: Math.floor(Math.random() * 100),
      connections: Math.floor(Math.random() * 1000),
      activity: Math.floor(Math.random() * 50) + 10,
    };
  }

  generateRecentActivities() {
    const activities = [
      {
        type: "create",
        user: "John Doe",
        action: "created new document in",
        target: "users",
        timestamp: "2 minutes ago",
      },
      {
        type: "update",
        user: "Alice Smith",
        action: "updated schema in",
        target: "products",
        timestamp: "15 minutes ago",
      },
      {
        type: "delete",
        user: "Bob Wilson",
        action: "deleted 5 documents from",
        target: "logs",
        timestamp: "1 hour ago",
      },
    ];

    return activities;
  }

  getServiceStatus(service) {
    const statuses = {
      database: { color: "green", text: "Healthy" },
      encryption: { color: "green", text: "Active" },
      backup: { color: "green", text: "Running" },
      cache: { color: "green", text: "Optimal" },
    };

    return statuses[service] || { color: "gray", text: "Unknown" };
  }

  generateReportData(type) {
    return {
      type: type,
      generatedAt: new Date().toISOString(),
      data: {
        summary: "Report generated successfully",
        metrics: this.generateSystemMetrics(),
        details: "Detailed report data would be included here",
      },
    };
  }

  updateChartData(timeRange) {
    // Update chart data based on selected time range
    let labels, dataPoints;

    switch (timeRange) {
      case "7days":
        labels = this.getLast7Days();
        dataPoints = 7;
        break;
      case "30days":
        labels = this.getLast30Days();
        dataPoints = 30;
        break;
      case "90days":
        labels = this.getLast90Days();
        dataPoints = 90;
        break;
      default:
        labels = this.getLast7Days();
        dataPoints = 7;
    }

    // Update chart with new data
    Object.values(this.charts).forEach((chart) => {
      if (chart && chart.data && chart.data.labels) {
        chart.data.labels = labels;
        chart.data.datasets.forEach((dataset) => {
          dataset.data = this.generateRandomData(dataPoints, 10, 100);
        });
        chart.update();
      }
    });
  }

  getLast30Days() {
    const result = [];
    const today = new Date();

    for (let i = 29; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      result.push(
        date.toLocaleDateString("id-ID", { month: "short", day: "numeric" }),
      );
    }

    return result;
  }

  getLast90Days() {
    const result = [];
    const today = new Date();

    for (let i = 89; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      result.push(
        date.toLocaleDateString("id-ID", { month: "short", day: "numeric" }),
      );
    }

    return result;
  }

  // Loading States
  showLoadingState() {
    const loadingOverlay = document.querySelector("[data-loading-overlay]");
    if (loadingOverlay) {
      loadingOverlay.classList.remove("hidden");
    }
  }

  hideLoadingState() {
    const loadingOverlay = document.querySelector("[data-loading-overlay]");
    if (loadingOverlay) {
      loadingOverlay.classList.add("hidden");
    }
  }

  // Cleanup
  destroy() {
    if (this.realTimeInterval) {
      clearInterval(this.realTimeInterval);
    }

    // Destroy all charts
    Object.values(this.charts).forEach((chart) => {
      if (chart && chart.destroy) {
        chart.destroy();
      }
    });

    this.charts = {};
  }
}

// Initialize dashboard when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  window.dashboardManager = new DashboardManager();
});

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = DashboardManager;
}
