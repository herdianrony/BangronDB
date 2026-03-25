// Monitoring Configuration

// Global configuration
const MONITORING_CONFIG = {
  // API endpoints
  api: {
    base: "/monitoring",
    realtime: "/monitoring/realtime",
    historical: "/monitoring/historical",
    reports: "/monitoring/generate-report",
    alerts: "/monitoring/alert-config",
    logs: "/monitoring/log-config",
    export: "/monitoring/export-logs",
  },

  // WebSocket configuration
  websocket: {
    url: null, // Will be determined at runtime
    reconnectInterval: 5000,
    pingInterval: 30000,
    maxRetries: 5,
  },

  // Refresh intervals (in milliseconds)
  refresh: {
    realtime: 10000, // 10 seconds
    charts: 30000, // 30 seconds
    alerts: 60000, // 1 minute
  },

  // Chart configuration
  charts: {
    colors: {
      primary: "#3b82f6",
      secondary: "#8b5cf6",
      success: "#10b981",
      warning: "#f59e0b",
      danger: "#ef4444",
      info: "#06b6d4",
    },
    themes: {
      light: {
        background: "#ffffff",
        text: "#374151",
        grid: "#e5e7eb",
      },
      dark: {
        background: "#1f2937",
        text: "#f9fafb",
        grid: "#374151",
      },
    },
  },

  // Alert thresholds
  thresholds: {
    system: {
      cpu: {
        warning: 80,
        critical: 90,
      },
      memory: {
        warning: 85,
        critical: 95,
      },
      disk: {
        warning: 85,
        critical: 95,
      },
      temperature: {
        warning: 70,
        critical: 85,
      },
    },
    database: {
      slowQueries: {
        warning: 10,
        critical: 50,
      },
      fragmentation: {
        warning: 20,
        critical: 50,
      },
      connections: {
        warning: 80,
        critical: 95,
      },
      cacheHitRate: {
        warning: 70,
        critical: 50,
      },
    },
    performance: {
      responseTime: {
        warning: 200,
        critical: 500,
      },
      errorRate: {
        warning: 5,
        critical: 10,
      },
      throughput: {
        warning: 50,
        critical: 20,
      },
    },
    security: {
      failedLogins: {
        warning: 5,
        critical: 10,
      },
      suspiciousActivities: {
        warning: 3,
        critical: 10,
      },
    },
  },

  // Log levels
  logLevels: {
    DEBUG: 0,
    INFO: 1,
    WARNING: 2,
    ERROR: 3,
    CRITICAL: 4,
  },

  // Time ranges for historical data
  timeRanges: {
    "1h": {
      label: "Last Hour",
      interval: 60, // seconds
      points: 60,
    },
    "24h": {
      label: "Last 24 Hours",
      interval: 3600, // seconds
      points: 24,
    },
    "7d": {
      label: "Last 7 Days",
      interval: 86400, // seconds
      points: 7,
    },
    "30d": {
      label: "Last 30 Days",
      interval: 86400, // seconds
      points: 30,
    },
  },

  // Export formats
  exportFormats: {
    json: {
      label: "JSON",
      extension: "json",
      mimeType: "application/json",
    },
    csv: {
      label: "CSV",
      extension: "csv",
      mimeType: "text/csv",
    },
    pdf: {
      label: "PDF",
      extension: "pdf",
      mimeType: "application/pdf",
    },
    xml: {
      label: "XML",
      extension: "xml",
      mimeType: "application/xml",
    },
  },

  // Notification channels
  notifications: {
    email: {
      enabled: true,
      recipients: [],
      template: "monitoring-alert",
    },
    slack: {
      enabled: false,
      webhook: null,
      channel: "#alerts",
    },
    sms: {
      enabled: false,
      provider: null,
      recipients: [],
    },
    webhook: {
      enabled: false,
      url: null,
      headers: {},
    },
  },

  // Data retention policies
  retention: {
    metrics: {
      realtime: 3600, // 1 hour
      historical: 2592000, // 30 days
    },
    logs: {
      realtime: 86400, // 1 day
      historical: 2592000, // 30 days
    },
    alerts: {
      realtime: 604800, // 1 week
      historical: 2592000, // 30 days
    },
  },

  // Performance settings
  performance: {
    maxDataPoints: 100,
    chartUpdateInterval: 1000,
    debounceDelay: 300,
    lazyLoadThreshold: 200,
  },

  // UI settings
  ui: {
    theme: "light",
    animations: true,
    compactMode: false,
    showDetails: true,
    autoRefresh: true,
  },

  // Security settings
  security: {
    requireAuth: true,
    rateLimit: {
      enabled: true,
      requests: 100,
      window: 60000, // 1 minute
    },
    cors: {
      enabled: true,
      origins: ["*"],
      methods: ["GET", "POST", "PUT", "DELETE"],
      headers: ["Content-Type", "Authorization"],
    },
  },

  // Plugin system
  plugins: {
    enabled: [],
    directory: "/assets/js/plugins",
    autoload: true,
  },

  // Development settings
  development: {
    debug: false,
    logLevel: "info",
    mockData: false,
    slowNetwork: false,
  },
};

// Utility functions
const MonitoringUtils = {
  /**
   * Format bytes to human readable format
   */
  formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return "0 Bytes";

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];

    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i];
  },

  /**
   * Format duration to human readable format
   */
  formatDuration(seconds) {
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
  },

  /**
   * Format percentage
   */
  formatPercentage(value, decimals = 1) {
    return `${value.toFixed(decimals)}%`;
  },

  /**
   * Format number with commas
   */
  formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  },

  /**
   * Get status color based on value
   */
  getStatusColor(value, thresholds) {
    if (value >= thresholds.critical) return "danger";
    if (value >= thresholds.warning) return "warning";
    return "success";
  },

  /**
   * Get status text based on value
   */
  getStatusText(value, thresholds) {
    if (value >= thresholds.critical) return "Critical";
    if (value >= thresholds.warning) return "Warning";
    return "Normal";
  },

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
  },

  /**
   * Check if object
   */
  isObject(item) {
    return item && typeof item === "object" && !Array.isArray(item);
  },

  /**
   * Debounce function
   */
  debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        timeout = null;
        if (!immediate) func(...args);
      };
      const callNow = immediate && !timeout;
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
      if (callNow) func(...args);
    };
  },

  /**
   * Throttle function
   */
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
  },

  /**
   * Generate unique ID
   */
  generateId() {
    return Math.random().toString(36).substr(2, 9);
  },

  /**
   * Check if value is empty
   */
  isEmpty(value) {
    return (
      value === null ||
      value === undefined ||
      value === "" ||
      (Array.isArray(value) && value.length === 0)
    );
  },

  /**
   * Clone object
   */
  clone(obj) {
    return JSON.parse(JSON.stringify(obj));
  },

  /**
   * Get current timestamp
   */
  getTimestamp() {
    return new Date().toISOString();
  },

  /**
   * Parse time range string
   */
  parseTimeRange(range) {
    const match = range.match(/(\d+)([smhd])/);
    if (!match) return null;

    const value = parseInt(match[1]);
    const unit = match[2];

    switch (unit) {
      case "s":
        return value * 1000;
      case "m":
        return value * 60 * 1000;
      case "h":
        return value * 60 * 60 * 1000;
      case "d":
        return value * 24 * 60 * 60 * 1000;
      default:
        return null;
    }
  },

  /**
   * Validate configuration
   */
  validateConfig(config) {
    const errors = [];

    // Check required API endpoints
    if (!config.api || !config.api.base) {
      errors.push("API base endpoint is required");
    }

    // Check WebSocket configuration
    if (config.websocket && !config.websocket.url) {
      // URL will be set at runtime
    }

    // Check thresholds
    if (!config.thresholds) {
      errors.push("Thresholds configuration is required");
    }

    // Check notification channels
    if (config.notifications) {
      Object.keys(config.notifications).forEach((channel) => {
        if (
          config.notifications[channel].enabled &&
          !config.notifications[channel].url &&
          !config.notifications[channel].webhook
        ) {
          errors.push(
            `${channel} notification is enabled but no URL/webhook is configured`,
          );
        }
      });
    }

    return errors;
  },

  /**
   * Load configuration from localStorage
   */
  loadFromStorage() {
    try {
      const stored = localStorage.getItem("monitoring_config");
      if (stored) {
        const parsed = JSON.parse(stored);
        return this.deepMerge(MONITORING_CONFIG, parsed);
      }
    } catch (error) {
      console.error("Error loading monitoring config from storage:", error);
    }
    return MONITORING_CONFIG;
  },

  /**
   * Save configuration to localStorage
   */
  saveToStorage(config) {
    try {
      localStorage.setItem("monitoring_config", JSON.stringify(config));
    } catch (error) {
      console.error("Error saving monitoring config to storage:", error);
    }
  },

  /**
   * Reset configuration to defaults
   */
  resetToDefaults() {
    localStorage.removeItem("monitoring_config");
    return this.clone(MONITORING_CONFIG);
  },

  /**
   * Get WebSocket URL
   */
  getWebSocketUrl() {
    const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
    const host = window.location.host;
    return `${protocol}//${host}${MONITORING_CONFIG.api.base}/ws`;
  },

  /**
   * Check if browser supports WebSocket
   */
  supportsWebSocket() {
    return typeof WebSocket !== "undefined";
  },

  /**
   * Check if browser supports Service Worker
   */
  supportsServiceWorker() {
    return "serviceWorker" in navigator;
  },

  /**
   * Check if browser supports LocalStorage
   */
  supportsLocalStorage() {
    try {
      const testKey = "__test__";
      localStorage.setItem(testKey, testKey);
      localStorage.removeItem(testKey);
      return true;
    } catch (e) {
      return false;
    }
  },

  /**
   * Check if browser supports IndexedDB
   */
  supportsIndexedDB() {
    return "indexedDB" in window;
  },
};

// Export configuration
window.MONITORING_CONFIG = MONITORING_CONFIG;
window.MonitoringUtils = MonitoringUtils;

// Initialize configuration
document.addEventListener("DOMContentLoaded", () => {
  // Load saved configuration or use defaults
  const config = MonitoringUtils.loadFromStorage();

  // Validate configuration
  const errors = MonitoringUtils.validateConfig(config);
  if (errors.length > 0) {
    console.warn("Configuration validation errors:", errors);
  }

  // Apply configuration
  window.MONITORING_CONFIG = config;

  // Set up WebSocket URL if not already set
  if (!config.websocket.url) {
    config.websocket.url = MonitoringUtils.getWebSocketUrl();
  }

  // Save updated configuration
  MonitoringUtils.saveToStorage(config);
});
