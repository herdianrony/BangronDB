/**
 * Collection Management Configuration
 * Configuration for enhanced BangronDB Admin Panel features
 */

class CollectionManagementConfig {
  constructor() {
    this.config = {
      // Feature flags
      features: {
        schemaBuilder: true,
        bulkOperations: true,
        advancedSearch: true,
        realTimeCollaboration: false,
        versionHistory: false,
        documentComparison: true,
        autoSave: true,
        performanceMonitoring: true,
      },

      // UI Configuration
      ui: {
        theme: "dark",
        animations: true,
        compactMode: false,
        showTooltips: true,
        autoCollapse: false,
      },

      // Editor Configuration
      editor: {
        defaultMode: "json", // 'json', 'form', 'split'
        autoFormat: true,
        syntaxHighlighting: true,
        wordWrap: true,
        fontSize: 14,
        fontFamily: "Monaco, Menlo, Ubuntu Mono, monospace",
      },

      // Search Configuration
      search: {
        debounceTime: 500,
        maxResults: 100,
        enableRegex: true,
        enableFuzzy: false,
        saveHistory: true,
        maxHistory: 10,
      },

      // Bulk Operations Configuration
      bulk: {
        maxSelection: 1000,
        batchSize: 100,
        enableProgress: true,
        confirmActions: true,
      },

      // Performance Configuration
      performance: {
        lazyLoad: true,
        virtualScroll: true,
        pagination: {
          defaultLimit: 50,
          maxLimit: 200,
        },
        caching: {
          enabled: true,
          ttl: 300000, // 5 minutes
        },
      },

      // Security Configuration
      security: {
        encryptFields: true,
        auditLog: true,
        permissionChecks: true,
        rateLimiting: {
          enabled: true,
          requests: 100,
          window: 60000, // 1 minute
        },
      },

      // Export/Import Configuration
      exportImport: {
        formats: ["json", "csv", "xml"],
        maxFileSize: 10485760, // 10MB
        chunkSize: 1048576, // 1MB
        compression: true,
      },
    };

    this.initialize();
  }

  /**
   * Initialize configuration
   */
  initialize() {
    this.loadConfiguration();
    this.setupEventListeners();
    this.initializeFeatures();
  }

  /**
   * Load configuration from localStorage or defaults
   */
  loadConfiguration() {
    try {
      const saved = localStorage.getItem("bangrondb_collection_config");
      if (saved) {
        const savedConfig = JSON.parse(saved);
        this.config = this.mergeConfig(this.config, savedConfig);
      }
    } catch (error) {
      console.warn(
        "Failed to load collection management configuration:",
        error,
      );
    }
  }

  /**
   * Merge configuration objects
   */
  mergeConfig(defaultConfig, userConfig) {
    const merged = { ...defaultConfig };

    for (const key in userConfig) {
      if (userConfig.hasOwnProperty(key)) {
        if (
          typeof userConfig[key] === "object" &&
          !Array.isArray(userConfig[key])
        ) {
          merged[key] = this.mergeConfig(merged[key] || {}, userConfig[key]);
        } else {
          merged[key] = userConfig[key];
        }
      }
    }

    return merged;
  }

  /**
   * Save configuration to localStorage
   */
  saveConfiguration() {
    try {
      localStorage.setItem(
        "bangrondb_collection_config",
        JSON.stringify(this.config),
      );
    } catch (error) {
      console.warn(
        "Failed to save collection management configuration:",
        error,
      );
    }
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Listen for configuration changes
    window.addEventListener("bangrondb:config-change", (event) => {
      this.updateConfiguration(event.detail);
    });

    // Listen for theme changes
    window.addEventListener("bangrondb:theme-change", (event) => {
      this.config.ui.theme = event.detail.theme;
      this.saveConfiguration();
      this.applyTheme();
    });
  }

  /**
   * Initialize features based on configuration
   */
  initializeFeatures() {
    // Initialize features based on feature flags
    if (this.config.features.schemaBuilder) {
      this.initializeSchemaBuilder();
    }

    if (this.config.features.bulkOperations) {
      this.initializeBulkOperations();
    }

    if (this.config.features.advancedSearch) {
      this.initializeAdvancedSearch();
    }

    if (this.config.features.realTimeCollaboration) {
      this.initializeRealTimeCollaboration();
    }

    if (this.config.features.versionHistory) {
      this.initializeVersionHistory();
    }

    if (this.config.features.documentComparison) {
      this.initializeDocumentComparison();
    }

    if (this.config.features.autoSave) {
      this.initializeAutoSave();
    }

    if (this.config.features.performanceMonitoring) {
      this.initializePerformanceMonitoring();
    }

    // Apply theme
    this.applyTheme();
  }

  /**
   * Initialize schema builder
   */
  initializeSchemaBuilder() {
    // Add schema builder specific initialization
    console.log("Initializing schema builder...");
    // This would initialize the schema builder functionality
  }

  /**
   * Initialize bulk operations
   */
  initializeBulkOperations() {
    // Configure bulk operation limits
    window.BULK_CONFIG = {
      maxSelection: this.config.bulk.maxSelection,
      batchSize: this.config.bulk.batchSize,
      enableProgress: this.config.bulk.enableProgress,
      confirmActions: this.config.bulk.confirmActions,
    };
  }

  /**
   * Initialize advanced search
   */
  initializeAdvancedSearch() {
    // Configure search parameters
    window.SEARCH_CONFIG = {
      debounceTime: this.config.search.debounceTime,
      maxResults: this.config.search.maxResults,
      enableRegex: this.config.search.enableRegex,
      enableFuzzy: this.config.search.enableFuzzy,
      saveHistory: this.config.search.saveHistory,
      maxHistory: this.config.search.maxHistory,
    };
  }

  /**
   * Initialize real-time collaboration
   */
  initializeRealTimeCollaboration() {
    // Initialize WebSocket connection for real-time features
    console.log("Initializing real-time collaboration...");
    // This would set up WebSocket connections
  }

  /**
   * Initialize version history
   */
  initializeVersionHistory() {
    // Configure version tracking
    console.log("Initializing version history...");
    // This would set up document versioning
  }

  /**
   * Initialize document comparison
   */
  initializeDocumentComparison() {
    // Configure comparison settings
    window.COMPARISON_CONFIG = {
      diffAlgorithm: " Myers",
      ignoreWhitespace: true,
      showLineNumbers: true,
      syntaxHighlighting: true,
    };
  }

  /**
   * Initialize auto-save
   */
  initializeAutoSave() {
    // Configure auto-save parameters
    window.AUTOSAVE_CONFIG = {
      enabled: this.config.features.autoSave,
      interval: 30000, // 30 seconds
      debounceTime: 2000,
      showNotification: true,
    };
  }

  /**
   * Initialize performance monitoring
   */
  initializePerformanceMonitoring() {
    // Configure performance tracking
    window.PERFORMANCE_CONFIG = {
      enabled: this.config.features.performanceMonitoring,
      metrics: ["renderTime", "apiResponseTime", "memoryUsage"],
      samplingRate: 0.1, // 10% of requests
      reportInterval: 60000, // 1 minute
    };
  }

  /**
   * Apply theme configuration
   */
  applyTheme() {
    const theme = this.config.ui.theme;
    const root = document.documentElement;

    if (theme === "dark") {
      root.classList.add("dark");
    } else {
      root.classList.remove("dark");
    }

    // Apply custom CSS variables based on theme
    this.updateThemeVariables();
  }

  /**
   * Update theme CSS variables
   */
  updateThemeVariables() {
    const root = document.documentElement;
    const theme = this.config.ui.theme;

    if (theme === "dark") {
      root.style.setProperty("--bg-primary", "#0f172a");
      root.style.setProperty("--bg-secondary", "#1e293b");
      root.style.setProperty("--text-primary", "#f1f5f9");
      root.style.setProperty("--text-secondary", "#94a3b8");
      root.style.setProperty("--border-color", "#334155");
      root.style.setProperty("--accent-color", "#3b82f6");
    } else {
      root.style.setProperty("--bg-primary", "#ffffff");
      root.style.setProperty("--bg-secondary", "#f8fafc");
      root.style.setProperty("--text-primary", "#0f172a");
      root.style.setProperty("--text-secondary", "#64748b");
      root.style.setProperty("--border-color", "#e2e8f0");
      root.style.setProperty("--accent-color", "#3b82f6");
    }
  }

  /**
   * Update configuration
   */
  updateConfiguration(newConfig) {
    this.config = this.mergeConfig(this.config, newConfig);
    this.saveConfiguration();
    this.initializeFeatures();
  }

  /**
   * Get configuration value
   */
  get(path) {
    return path.split(".").reduce((obj, key) => obj && obj[key], this.config);
  }

  /**
   * Set configuration value
   */
  set(path, value) {
    const keys = path.split(".");
    const lastKey = keys.pop();
    const target = keys.reduce((obj, key) => {
      if (!obj[key]) obj[key] = {};
      return obj[key];
    }, this.config);

    target[lastKey] = value;
    this.saveConfiguration();

    // Trigger configuration change event
    window.dispatchEvent(
      new CustomEvent("bangrondb:config-change", {
        detail: { path, value },
      }),
    );
  }

  /**
   * Enable feature
   */
  enableFeature(featureName) {
    if (this.config.features.hasOwnProperty(featureName)) {
      this.set(`features.${featureName}`, true);
    }
  }

  /**
   * Disable feature
   */
  disableFeature(featureName) {
    if (this.config.features.hasOwnProperty(featureName)) {
      this.set(`features.${featureName}`, false);
    }
  }

  /**
   * Check if feature is enabled
   */
  isFeatureEnabled(featureName) {
    return this.get(`features.${featureName}`) === true;
  }

  /**
   * Reset configuration to defaults
   */
  resetToDefaults() {
    this.config = this.constructor.getDefaultConfig();
    this.saveConfiguration();
    this.initializeFeatures();
  }

  /**
   * Get default configuration
   */
  static getDefaultConfig() {
    return {
      features: {
        schemaBuilder: true,
        bulkOperations: true,
        advancedSearch: true,
        realTimeCollaboration: false,
        versionHistory: false,
        documentComparison: true,
        autoSave: true,
        performanceMonitoring: true,
      },
      ui: {
        theme: "dark",
        animations: true,
        compactMode: false,
        showTooltips: true,
        autoCollapse: false,
      },
      editor: {
        defaultMode: "json",
        autoFormat: true,
        syntaxHighlighting: true,
        wordWrap: true,
        fontSize: 14,
        fontFamily: "Monaco, Menlo, Ubuntu Mono, monospace",
      },
      search: {
        debounceTime: 500,
        maxResults: 100,
        enableRegex: true,
        enableFuzzy: false,
        saveHistory: true,
        maxHistory: 10,
      },
      bulk: {
        maxSelection: 1000,
        batchSize: 100,
        enableProgress: true,
        confirmActions: true,
      },
      performance: {
        lazyLoad: true,
        virtualScroll: true,
        pagination: {
          defaultLimit: 50,
          maxLimit: 200,
        },
        caching: {
          enabled: true,
          ttl: 300000,
        },
      },
      security: {
        encryptFields: true,
        auditLog: true,
        permissionChecks: true,
        rateLimiting: {
          enabled: true,
          requests: 100,
          window: 60000,
        },
      },
      exportImport: {
        formats: ["json", "csv", "xml"],
        maxFileSize: 10485760,
        chunkSize: 1048576,
        compression: true,
      },
    };
  }

  /**
   * Export configuration
   */
  exportConfiguration() {
    return {
      version: "1.0.0",
      timestamp: new Date().toISOString(),
      config: this.config,
    };
  }

  /**
   * Import configuration
   */
  importConfiguration(configData) {
    try {
      if (configData.version && configData.config) {
        this.config = this.mergeConfig(this.config, configData.config);
        this.saveConfiguration();
        this.initializeFeatures();
        return true;
      }
      return false;
    } catch (error) {
      console.error("Failed to import configuration:", error);
      return false;
    }
  }
}

// Initialize configuration when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.collectionConfig = new CollectionManagementConfig();
});

// Make configuration globally available
if (typeof module !== "undefined" && module.exports) {
  module.exports = CollectionManagementConfig;
}
