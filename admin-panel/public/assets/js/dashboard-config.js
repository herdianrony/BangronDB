/**
 * BangronDB Dashboard Configuration
 * Central configuration for dashboard settings, API endpoints, and features
 */

const DashboardConfig = {
  // API Configuration
  api: {
    baseUrl: "/api/v1",
    endpoints: {
      dashboard: "/dashboard",
      databases: "/databases",
      collections: "/collections",
      users: "/users",
      audit: "/audit",
      metrics: "/metrics",
      system: "/system",
      search: "/search",
    },
    timeout: 30000,
    retryAttempts: 3,
    retryDelay: 1000,
  },

  // Chart Configuration
  charts: {
    defaultOptions: {
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
          displayColors: true,
        },
      },
      scales: {
        x: {
          ticks: { color: "#9ca3af" },
          grid: { color: "rgba(255, 255, 255, 0.1)" },
        },
        y: {
          ticks: { color: "#9ca3af" },
          grid: { color: "rgba(255, 255, 255, 0.1)" },
        },
      },
    },

    colors: {
      primary: "#3b82f6",
      secondary: "#8b5cf6",
      success: "#10b981",
      warning: "#f59e0b",
      error: "#ef4444",
      info: "#06b6d4",
    },

    themes: {
      light: {
        background: "#ffffff",
        text: "#1f2937",
        grid: "#e5e7eb",
      },
      dark: {
        background: "rgba(30, 41, 59, 0.5)",
        text: "#f1f5f9",
        grid: "rgba(255, 255, 255, 0.1)",
      },
    },
  },

  // Real-time Configuration
  realTime: {
    enabled: true,
    updateInterval: 30000, // 30 seconds
    retryInterval: 5000,
    connectionTimeout: 10000,
    reconnectAttempts: 5,
    reconnectDelay: 2000,
  },

  // Search Configuration
  search: {
    enabled: true,
    debounceDelay: 300,
    minLength: 2,
    maxResults: 10,
    searchTypes: ["databases", "collections", "documents", "users"],
    recentSearches: 5,
    cacheTimeout: 300000, // 5 minutes
  },

  // Export Configuration
  export: {
    enabled: true,
    formats: ["json", "csv", "pdf"],
    maxRecords: 10000,
    chunkSize: 1000,
    timeout: 60000,
  },

  // Notification Configuration
  notifications: {
    enabled: true,
    maxVisible: 5,
    maxTotal: 50,
    types: ["info", "success", "warning", "error"],
    autoHide: true,
    hideDelay: 5000,
    position: "top-right",
  },

  // Performance Configuration
  performance: {
    lazyLoad: true,
    preloadImages: true,
    optimizeAnimations: true,
    chartFrameRate: 30,
    memoryLimit: 100, // MB
    cacheTimeout: 3600000, // 1 hour
  },

  // Security Configuration
  security: {
    csrfProtection: true,
    rateLimit: {
      enabled: true,
      requests: 100,
      window: 60000, // 1 minute
    },
    auditLog: {
      enabled: true,
      sensitiveActions: ["delete", "update", "create"],
      logLevel: "info",
    },
  },

  // UI Configuration
  ui: {
    theme: "dark",
    animations: {
      enabled: true,
      duration: 300,
      easing: "ease-out",
    },
    responsive: {
      breakpoints: {
        sm: 640,
        md: 768,
        lg: 1024,
        xl: 1280,
        "2xl": 1536,
      },
    },
    components: {
      cards: {
        elevation: "md",
        hover: true,
        shadows: true,
      },
      charts: {
        animations: true,
        interaction: true,
      },
    },
  },

  // Feature Flags
  features: {
    realTimeMetrics: true,
    advancedSearch: true,
    dataExport: true,
    userActivity: true,
    systemAlerts: true,
    performanceMonitoring: true,
    encryptionStatus: true,
    backupStatus: true,
    cacheStatus: true,
    queryAnalytics: true,
  },

  // Localization
  localization: {
    enabled: true,
    defaultLanguage: "id",
    supportedLanguages: ["id", "en"],
    dateFormat: "DD/MM/YYYY",
    timeFormat: "HH:mm",
    numberFormat: {
      decimal: ",",
      thousands: ".",
      precision: 2,
    },
    currencyFormat: {
      symbol: "Rp",
      position: "prefix",
    },
  },

  // Analytics Configuration
  analytics: {
    enabled: true,
    trackingId: "UA-XXXXXXXXX-X",
    events: {
      dashboardView: "dashboard_view",
      metricView: "metric_view",
      search: "search_performed",
      export: "export_initiated",
      userAction: "user_action",
    },
    sessionTimeout: 1800000, // 30 minutes
  },

  // Cache Configuration
  cache: {
    enabled: true,
    storage: "localStorage",
    keys: {
      dashboardData: "dashboard_data",
      searchResults: "search_results",
      userPreferences: "user_preferences",
      systemMetrics: "system_metrics",
    },
    ttl: {
      dashboardData: 300000, // 5 minutes
      searchResults: 600000, // 10 minutes
      userPreferences: 86400000, // 24 hours
      systemMetrics: 60000, // 1 minute
    },
  },

  // Error Handling Configuration
  errorHandling: {
    enabled: true,
    logging: true,
    reportErrors: true,
    maxLogSize: 100,
    fallbackMessages: {
      network: "Network connection error. Please try again.",
      server: "Server error. Please contact support.",
      timeout: "Request timed out. Please try again.",
      unauthorized: "Unauthorized access. Please login again.",
      forbidden: "Access denied. You do not have permission.",
      notFound: "Resource not found.",
      validation: "Validation error. Please check your input.",
      unknown: "An unknown error occurred.",
    },
  },

  // Accessibility Configuration
  accessibility: {
    enabled: true,
    keyboardNavigation: true,
    screenReader: true,
    highContrast: false,
    reducedMotion: false,
    fontSize: "normal",
  },

  // Development Configuration
  development: {
    enabled: false,
    debug: false,
    mockData: true,
    mockDelay: 1000,
    consoleLogging: true,
    performanceMonitoring: false,
  },
};

// Utility functions for configuration management
class ConfigManager {
  constructor() {
    this.config = DashboardConfig;
    this.userPreferences = this.loadUserPreferences();
    this.initializeConfig();
  }

  initializeConfig() {
    // Load user preferences and override default config
    this.applyUserPreferences();

    // Apply environment-specific settings
    this.applyEnvironmentSettings();

    // Initialize feature flags
    this.initializeFeatureFlags();

    // Set up event listeners
    this.setupEventListeners();
  }

  loadUserPreferences() {
    try {
      const stored = localStorage.getItem("dashboard_preferences");
      return stored ? JSON.parse(stored) : {};
    } catch (error) {
      console.warn("Failed to load user preferences:", error);
      return {};
    }
  }

  saveUserPreferences() {
    try {
      localStorage.setItem(
        "dashboard_preferences",
        JSON.stringify(this.userPreferences),
      );
    } catch (error) {
      console.warn("Failed to save user preferences:", error);
    }
  }

  applyUserPreferences() {
    // Apply theme preference
    if (this.userPreferences.theme) {
      this.config.ui.theme = this.userPreferences.theme;
      this.applyTheme();
    }

    // Apply animation preferences
    if (this.userPreferences.animations !== undefined) {
      this.config.ui.animations.enabled = this.userPreferences.animations;
    }

    // Apply language preference
    if (this.userPreferences.language) {
      this.config.localization.defaultLanguage = this.userPreferences.language;
    }

    // Apply accessibility preferences
    if (this.userPreferences.accessibility) {
      this.config.accessibility = {
        ...this.config.accessibility,
        ...this.userPreferences.accessibility,
      };
    }
  }

  applyEnvironmentSettings() {
    // Apply development settings
    if (process.env.NODE_ENV === "development") {
      this.config.development.enabled = true;
      this.config.development.debug = true;
    }

    // Apply production settings
    if (process.env.NODE_ENV === "production") {
      this.config.performance.optimizeAnimations = true;
      this.config.errorHandling.reportErrors = true;
    }
  }

  initializeFeatureFlags() {
    // Check feature flags from user preferences or external source
    if (this.userPreferences.featureFlags) {
      this.config.features = {
        ...this.config.features,
        ...this.userPreferences.featureFlags,
      };
    }
  }

  setupEventListeners() {
    // Listen for system preference changes
    window
      .matchMedia("(prefers-color-scheme: dark)")
      .addEventListener("change", (e) => {
        if (!this.userPreferences.theme) {
          this.config.ui.theme = e.matches ? "dark" : "light";
          this.applyTheme();
        }
      });

    window
      .matchMedia("(prefers-reduced-motion: reduce)")
      .addEventListener("change", (e) => {
        this.config.accessibility.reducedMotion = e.matches;
      });
  }

  applyTheme() {
    const root = document.documentElement;
    if (this.config.ui.theme === "dark") {
      root.classList.add("dark");
    } else {
      root.classList.remove("dark");
    }
  }

  // Configuration getters
  get(key) {
    return this.getNestedValue(this.config, key);
  }

  set(key, value) {
    this.setNestedValue(this.config, key, value);
    this.saveUserPreferences();
  }

  getNestedValue(obj, path) {
    return path.split(".").reduce((current, key) => current?.[key], obj);
  }

  setNestedValue(obj, path, value) {
    const keys = path.split(".");
    const lastKey = keys.pop();
    const target = keys.reduce((current, key) => current?.[key], obj);
    if (target) {
      target[lastKey] = value;
    }
  }

  // User preference management
  setUserPreference(key, value) {
    this.userPreferences[key] = value;
    this.saveUserPreferences();
    this.applyUserPreferences();
  }

  getUserPreference(key) {
    return this.userPreferences[key];
  }

  // Feature flag management
  isFeatureEnabled(feature) {
    return this.config.features[feature] === true;
  }

  enableFeature(feature) {
    this.config.features[feature] = true;
    this.saveUserPreferences();
  }

  disableFeature(feature) {
    this.config.features[feature] = false;
    this.saveUserPreferences();
  }

  // API configuration helpers
  getApiUrl(endpoint) {
    return `${this.config.api.baseUrl}${this.config.api.endpoints[endpoint]}`;
  }

  getApiOptions(method = "GET", data = null) {
    const options = {
      method,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
    };

    if (this.config.security.csrfProtection) {
      const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
      if (csrfToken) {
        options.headers["X-CSRF-Token"] = csrfToken;
      }
    }

    if (data) {
      options.body = JSON.stringify(data);
    }

    return options;
  }

  // Chart configuration helpers
  getChartOptions(type, customOptions = {}) {
    const baseOptions = this.config.charts.defaultOptions;

    // Apply theme-specific options
    const themeOptions = this.config.charts.themes[this.config.ui.theme];

    return {
      ...baseOptions,
      ...themeOptions,
      ...customOptions,
      type,
    };
  }

  getChartColors() {
    return this.config.charts.colors;
  }

  // Real-time configuration helpers
  getRealTimeConfig() {
    return this.config.realTime;
  }

  // Search configuration helpers
  getSearchConfig() {
    return this.config.search;
  }

  // Export configuration helpers
  getExportConfig() {
    return this.config.export;
  }

  // Performance configuration helpers
  getPerformanceConfig() {
    return this.config.performance;
  }

  // Error handling helpers
  getErrorMessage(errorType) {
    return (
      this.config.errorHandling.fallbackMessages[errorType] ||
      this.config.errorHandling.fallbackMessages.unknown
    );
  }

  // Localization helpers
  formatDate(date, format = null) {
    const dateFormat = format || this.config.localization.dateFormat;
    return new Date(date).toLocaleDateString(
      this.config.localization.defaultLanguage,
      {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      },
    );
  }

  formatTime(date) {
    return new Date(date).toLocaleTimeString(
      this.config.localization.defaultLanguage,
      {
        hour: "2-digit",
        minute: "2-digit",
      },
    );
  }

  formatNumber(number) {
    return number.toLocaleString(this.config.localization.defaultLanguage, {
      minimumFractionDigits: this.config.localization.numberFormat.precision,
      maximumFractionDigits: this.config.localization.numberFormat.precision,
      useGrouping: true,
    });
  }

  formatCurrency(amount) {
    const { symbol, position } = this.config.localization.currencyFormat;
    const formattedAmount = this.formatNumber(amount);

    if (position === "prefix") {
      return `${symbol}${formattedAmount}`;
    } else {
      return `${formattedAmount}${symbol}`;
    }
  }

  // Analytics helpers
  trackEvent(eventName, data = {}) {
    if (!this.config.analytics.enabled) return;

    const eventData = {
      event: eventName,
      timestamp: new Date().toISOString(),
      ...data,
    };

    // Send to analytics service
    if (typeof gtag === "function") {
      gtag("event", eventName, data);
    }

    // Custom analytics tracking
    console.log("Analytics Event:", eventData);
  }

  // Cache helpers
  getCacheKey(key) {
    const cacheKey = this.config.cache.keys[key];
    if (!cacheKey) {
      throw new Error(`Cache key not found: ${key}`);
    }
    return cacheKey;
  }

  getCacheTTL(key) {
    const ttlKey = this.config.cache.ttl[key];
    return ttlKey || this.config.cache.ttl.default;
  }

  // Export configuration as JSON
  exportConfiguration() {
    return {
      config: this.config,
      userPreferences: this.userPreferences,
      exportedAt: new Date().toISOString(),
    };
  }

  // Import configuration from JSON
  importConfiguration(configData) {
    try {
      this.config = { ...this.config, ...configData.config };
      this.userPreferences = {
        ...this.userPreferences,
        ...configData.userPreferences,
      };
      this.saveUserPreferences();
      this.applyUserPreferences();
      return true;
    } catch (error) {
      console.error("Failed to import configuration:", error);
      return false;
    }
  }
}

// Global configuration instance
window.dashboardConfig = new ConfigManager();

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = { DashboardConfig, ConfigManager };
}
