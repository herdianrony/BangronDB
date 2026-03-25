/**
 * BangronDB Dashboard Utilities
 * Utility functions for dashboard operations, data formatting, and common tasks
 */

class DashboardUtils {
  constructor() {
    this.config = window.dashboardConfig;
  }

  // Data formatting utilities
  formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return "0 Bytes";

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];

    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i];
  }

  formatNumber(num, decimals = 0) {
    if (num === null || num === undefined) return "0";

    const number = parseFloat(num);
    if (isNaN(number)) return "0";

    return number.toLocaleString("id-ID", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
      useGrouping: true,
    });
  }

  formatPercentage(value, decimals = 1) {
    return this.formatNumber(value, decimals) + "%";
  }

  formatCurrency(amount, currency = "IDR") {
    const number = parseFloat(amount) || 0;
    const formatter = new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    });

    return formatter.format(number);
  }

  formatTime(seconds) {
    if (!seconds || seconds === 0) return "0s";

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    if (hours > 0) {
      return `${hours}h ${minutes}m ${secs}s`;
    } else if (minutes > 0) {
      return `${minutes}m ${secs}s`;
    } else {
      return `${secs}s`;
    }
  }

  formatDate(date, format = "short") {
    const d = new Date(date);

    if (isNaN(d.getTime())) return "Invalid date";

    const options = {
      short: { day: "numeric", month: "short", year: "numeric" },
      medium: {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      },
      long: { weekday: "long", year: "numeric", month: "long", day: "numeric" },
      time: { hour: "2-digit", minute: "2-digit", second: "2-digit" },
    };

    return d.toLocaleDateString("id-ID", options[format] || options.short);
  }

  formatRelativeTime(date) {
    const now = new Date();
    const past = new Date(date);
    const diffMs = now - past;

    const diffMinutes = Math.floor(diffMs / (1000 * 60));
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffMinutes < 1) return "Just now";
    if (diffMinutes < 60)
      return `${diffMinutes} minute${diffMinutes > 1 ? "s" : ""} ago`;
    if (diffHours < 24)
      return `${diffHours} hour${diffHours > 1 ? "s" : ""} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? "s" : ""} ago`;

    return this.formatDate(date);
  }

  // Color utilities
  getStatusColor(status, type = "default") {
    const colorMap = {
      default: {
        active: "green",
        inactive: "gray",
        pending: "yellow",
        error: "red",
        warning: "yellow",
        success: "green",
        info: "blue",
      },
      database: {
        healthy: "green",
        warning: "yellow",
        critical: "red",
        maintenance: "blue",
      },
      system: {
        online: "green",
        offline: "red",
        degraded: "yellow",
        maintenance: "blue",
      },
      security: {
        secure: "green",
        warning: "yellow",
        vulnerable: "red",
        unknown: "gray",
      },
    };

    const colors = colorMap[type] || colorMap.default;
    return colors[status] || "gray";
  }

  getProgressBarColor(value, type = "percentage") {
    if (type === "percentage") {
      if (value < 50) return "green";
      if (value < 80) return "yellow";
      return "red";
    }

    if (type === "memory") {
      if (value < 60) return "green";
      if (value < 85) return "yellow";
      return "red";
    }

    if (type === "cpu") {
      if (value < 40) return "green";
      if (value < 70) return "yellow";
      return "red";
    }

    return "blue";
  }

  // Data processing utilities
  calculateGrowth(current, previous) {
    if (!previous || previous === 0) return current > 0 ? 100 : 0;

    const growth = ((current - previous) / previous) * 100;
    return Math.round(growth * 10) / 10;
  }

  calculateTrend(data, periods = 7) {
    if (!data || data.length < 2) return "stable";

    const recent = data.slice(-periods);
    const older = data.slice(-periods * 2, -periods);

    if (older.length === 0) return "stable";

    const recentAvg = recent.reduce((a, b) => a + b, 0) / recent.length;
    const olderAvg = older.reduce((a, b) => a + b, 0) / older.length;

    const change = ((recentAvg - olderAvg) / olderAvg) * 100;

    if (change > 5) return "increasing";
    if (change < -5) return "decreasing";
    return "stable";
  }

  aggregateData(data, groupBy, aggregateBy = "sum") {
    if (!data || data.length === 0) return {};

    const grouped = data.reduce((acc, item) => {
      const key = item[groupBy];
      if (!acc[key]) {
        acc[key] = {
          count: 0,
          sum: 0,
          avg: 0,
          min: Infinity,
          max: -Infinity,
        };
      }

      acc[key].count++;
      const value = parseFloat(item[aggregateBy]) || 0;
      acc[key].sum += value;
      acc[key].min = Math.min(acc[key].min, value);
      acc[key].max = Math.max(acc[key].max, value);
      acc[key].avg = acc[key].sum / acc[key].count;

      return acc;
    }, {});

    return grouped;
  }

  // Chart data utilities
  prepareChartData(data, type = "line") {
    if (!data || data.length === 0) return { labels: [], datasets: [] };

    const labels = data.map((item) => item.label || item.date || item.name);
    const values = data.map((item) => item.value || item.count || item.amount);

    const dataset = {
      label: data[0]?.dataset || "Data",
      data: values,
      borderColor: this.config.getChartColors().primary,
      backgroundColor: this.config.getChartColors().primary + "20",
      tension: 0.4,
      fill: type === "area",
    };

    return {
      labels,
      datasets: [dataset],
    };
  }

  prepareMultiChartData(datasets, options = {}) {
    const colors = [
      this.config.getChartColors().primary,
      this.config.getChartColors().secondary,
      this.config.getChartColors().success,
      this.config.getChartColors().warning,
      this.config.getChartColors().error,
    ];

    const chartDatasets = datasets.map((dataset, index) => ({
      label: dataset.label,
      data: dataset.data,
      borderColor: colors[index % colors.length],
      backgroundColor: colors[index % colors.length] + "20",
      tension: 0.4,
      fill: options.fill || false,
    }));

    return {
      labels:
        datasets[0]?.data?.map(
          (_, index) => options.labels?.[index] || `Item ${index + 1}`,
        ) || [],
      datasets: chartDatasets,
    };
  }

  // Search and filter utilities
  fuzzySearch(query, data, searchFields = []) {
    if (!query || query.length < 2) return data;

    const searchTerm = query.toLowerCase();

    return data.filter((item) => {
      return searchFields.some((field) => {
        const value = item[field];
        if (!value) return false;

        const stringValue = String(value).toLowerCase();
        return stringValue.includes(searchTerm);
      });
    });
  }

  advancedFilter(data, filters) {
    return data.filter((item) => {
      return Object.keys(filters).every((key) => {
        const filterValue = filters[key];
        const itemValue = item[key];

        if (
          filterValue === null ||
          filterValue === undefined ||
          filterValue === ""
        ) {
          return true;
        }

        // Handle different filter types
        if (typeof filterValue === "string") {
          return String(itemValue)
            .toLowerCase()
            .includes(filterValue.toLowerCase());
        }

        if (typeof filterValue === "object" && filterValue.type === "range") {
          const min = filterValue.min;
          const max = filterValue.max;
          const numValue = parseFloat(itemValue);

          if (min !== undefined && numValue < min) return false;
          if (max !== undefined && numValue > max) return false;
          return true;
        }

        if (typeof filterValue === "object" && filterValue.type === "exact") {
          return itemValue === filterValue.value;
        }

        return itemValue === filterValue;
      });
    });
  }

  // Validation utilities
  validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  validateUrl(url) {
    try {
      new URL(url);
      return true;
    } catch {
      return false;
    }
  }

  validatePassword(password) {
    // Basic password validation
    if (password.length < 8) return false;
    if (!/[A-Z]/.test(password)) return false;
    if (!/[a-z]/.test(password)) return false;
    if (!/[0-9]/.test(password)) return false;
    return true;
  }

  // Security utilities
  sanitizeInput(input) {
    if (typeof input !== "string") return input;

    return input
      .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "")
      .replace(/javascript:/gi, "")
      .replace(/on\w+\s*=/gi, "");
  }

  generateId(prefix = "", length = 8) {
    const chars =
      "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    let result = prefix;

    for (let i = 0; i < length; i++) {
      result += chars.charAt(Math.floor(Math.random() * chars.length));
    }

    return result;
  }

  // Export utilities
  exportToJSON(data, filename = "export.json") {
    const jsonString = JSON.stringify(data, null, 2);
    const blob = new Blob([jsonString], { type: "application/json" });
    this.downloadFile(blob, filename);
  }

  exportToCSV(data, filename = "export.csv") {
    if (!data || data.length === 0) return;

    const headers = Object.keys(data[0]);
    const csvContent = [
      headers.join(","),
      ...data.map((row) =>
        headers
          .map((header) => {
            const value = row[header];
            return typeof value === "string" && value.includes(",")
              ? `"${value.replace(/"/g, '""')}"`
              : value;
          })
          .join(","),
      ),
    ].join("\n");

    const blob = new Blob([csvContent], { type: "text/csv" });
    this.downloadFile(blob, filename);
  }

  downloadFile(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  // Notification utilities
  showNotification(message, type = "info", duration = 5000) {
    const notification = document.createElement("div");
    notification.className = `notification notification-${type} fade-in`;
    notification.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="notification-icon">
                    ${this.getNotificationIcon(type)}
                </div>
                <div class="notification-content">
                    <p class="notification-message">${message}</p>
                </div>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        `;

    document.body.appendChild(notification);
    lucide.createIcons();

    if (duration > 0) {
      setTimeout(() => {
        notification.remove();
      }, duration);
    }
  }

  getNotificationIcon(type) {
    const icons = {
      info: '<i data-lucide="info" class="w-5 h-5 text-blue-400"></i>',
      success:
        '<i data-lucide="check-circle" class="w-5 h-5 text-green-400"></i>',
      warning:
        '<i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-400"></i>',
      error: '<i data-lucide="x-circle" class="w-5 h-5 text-red-400"></i>',
    };

    return icons[type] || icons.info;
  }

  // Loading utilities
  showLoading(element, message = "Loading...") {
    const existingLoader = element.querySelector(".loader");
    if (existingLoader) {
      existingLoader.remove();
    }

    const loader = document.createElement("div");
    loader.className = "loader";
    loader.innerHTML = `
            <div class="loading-spinner w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full"></div>
            <span class="loading-text">${message}</span>
        `;

    element.appendChild(loader);
  }

  hideLoading(element) {
    const loader = element.querySelector(".loader");
    if (loader) {
      loader.remove();
    }
  }

  // Performance utilities
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  throttle(func, limit) {
    let inThrottle;
    return function () {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => (inThrottle = false), limit);
      }
    };
  }

  // Cache utilities
  setCache(key, value, ttl = 300000) {
    const cacheData = {
      value,
      timestamp: Date.now(),
      ttl,
    };

    try {
      localStorage.setItem(`cache_${key}`, JSON.stringify(cacheData));
    } catch (error) {
      console.warn("Cache storage failed:", error);
    }
  }

  getCache(key) {
    try {
      const cached = localStorage.getItem(`cache_${key}`);
      if (!cached) return null;

      const cacheData = JSON.parse(cached);
      const now = Date.now();

      if (now - cacheData.timestamp > cacheData.ttl) {
        localStorage.removeItem(`cache_${key}`);
        return null;
      }

      return cacheData.value;
    } catch (error) {
      console.warn("Cache retrieval failed:", error);
      return null;
    }
  }

  clearCache() {
    try {
      const keys = Object.keys(localStorage);
      keys.forEach((key) => {
        if (key.startsWith("cache_")) {
          localStorage.removeItem(key);
        }
      });
    } catch (error) {
      console.warn("Cache clear failed:", error);
    }
  }

  // Error handling utilities
  handleError(error, context = "") {
    console.error(`Error in ${context}:`, error);

    let userMessage = "An error occurred. Please try again.";

    if (error instanceof APIError) {
      userMessage = this.config.getErrorMessage(error.status);
    } else if (error.message) {
      userMessage = error.message;
    }

    this.showNotification(userMessage, "error");
  }

  // Analytics utilities
  trackEvent(eventName, data = {}) {
    if (this.config.isFeatureEnabled("analytics")) {
      this.config.trackEvent(eventName, data);
    }
  }

  trackUserAction(action, target = null) {
    this.trackEvent("user_action", {
      action,
      target,
      timestamp: new Date().toISOString(),
    });
  }

  // Accessibility utilities
  announceToScreenReader(message) {
    const announcement = document.createElement("div");
    announcement.setAttribute("aria-live", "polite");
    announcement.setAttribute("aria-atomic", "true");
    announcement.className = "sr-only";
    announcement.textContent = message;

    document.body.appendChild(announcement);

    setTimeout(() => {
      announcement.remove();
    }, 1000);
  }

  setFocus(element) {
    if (element && typeof element.focus === "function") {
      element.focus();
    }
  }

  // Device utilities
  isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
      navigator.userAgent,
    );
  }

  isTablet() {
    return /iPad|Android/i.test(navigator.userAgent) && !this.isMobile();
  }

  isDesktop() {
    return !this.isMobile() && !this.isTablet();
  }

  getDeviceInfo() {
    return {
      mobile: this.isMobile(),
      tablet: this.isTablet(),
      desktop: this.isDesktop(),
      userAgent: navigator.userAgent,
      screenResolution: `${screen.width}x${screen.height}`,
      viewportSize: `${window.innerWidth}x${window.innerHeight}`,
    };
  }

  // Storage utilities
  setLocalStorage(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      console.warn("LocalStorage set failed:", error);
    }
  }

  getLocalStorage(key, defaultValue = null) {
    try {
      const value = localStorage.getItem(key);
      return value ? JSON.parse(value) : defaultValue;
    } catch (error) {
      console.warn("LocalStorage get failed:", error);
      return defaultValue;
    }
  }

  removeLocalStorage(key) {
    try {
      localStorage.removeItem(key);
    } catch (error) {
      console.warn("LocalStorage remove failed:", error);
    }
  }

  // Initialize utility functions globally
  init() {
    // Add utility functions to global scope
    window.dashboardUtils = this;

    // Add helper functions to String prototype
    String.prototype.formatBytes = function (decimals = 2) {
      return dashboardUtils.formatBytes(parseFloat(this), decimals);
    };

    String.prototype.formatNumber = function (decimals = 0) {
      return dashboardUtils.formatNumber(parseFloat(this), decimals);
    };

    // Add helper functions to Number prototype
    Number.prototype.formatBytes = function (decimals = 2) {
      return dashboardUtils.formatBytes(this, decimals);
    };

    Number.prototype.formatNumber = function (decimals = 0) {
      return dashboardUtils.formatNumber(this, decimals);
    };
  }
}

// Initialize dashboard utilities
const dashboardUtils = new DashboardUtils();
dashboardUtils.init();

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = DashboardUtils;
}
