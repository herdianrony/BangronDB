/**
 * BangronDB User Experience Enhancements
 * Implements advanced UI/UX improvements for better user experience
 */

class UserExperienceEnhancer {
  constructor() {
    this.config = {
      enableSmoothAnimations: true,
      enableLoadingStates: true,
      enableSkeletonScreens: true,
      enableProgressIndicators: true,
      enableTooltips: true,
      enableKeyboardShortcuts: true,
      enableVoiceSearch: true,
      enablePredictiveSearch: true,
      enableAutoSave: true,
      enableUndoRedo: true,
      enableGestureSupport: true,
      enableMobileOptimization: true,
      enableAccessibility: true,
      enableDarkMode: true,
      enableNotifications: true,
      enableErrorHandling: true,
      enablePerformanceMetrics: true,
    };

    this.state = {
      currentTheme: "dark",
      keyboardShortcuts: {},
      undoStack: [],
      redoStack: [],
      autoSaveTimer: null,
      voiceRecognition: null,
      predictiveSearch: null,
      gestureDetector: null,
      notificationQueue: [],
      accessibilityMode: false,
    };

    this.components = {
      tooltips: new Map(),
      modals: new Map(),
      loadingIndicators: new Map(),
      progressBars: new Map(),
      notifications: new Map(),
    };

    this.metrics = {
      interactions: 0,
      errors: 0,
      loadingTimes: [],
      userActions: [],
      accessibilityEvents: [],
    };

    this.init();
  }

  init() {
    this.initializeTheme();
    this.initializeKeyboardShortcuts();
    this.initializeTooltips();
    this.initializeLoadingStates();
    this.initializeSkeletonScreens();
    this.initializeProgressIndicators();
    this.initializeAutoSave();
    this.initializeUndoRedo();
    this.initializeVoiceSearch();
    this.initializePredictiveSearch();
    this.initializeGestureSupport();
    this.initializeMobileOptimization();
    this.initializeAccessibility();
    this.initializeNotifications();
    this.initializeErrorHandling();
    this.initializePerformanceMetrics();
  }

  // Theme Management
  initializeTheme() {
    if (!this.config.enableDarkMode) return;

    // Load saved theme
    const savedTheme = localStorage.getItem("bangrondb_theme") || "dark";
    this.setTheme(savedTheme);

    // Setup theme toggle
    this.setupThemeToggle();
  }

  setTheme(theme) {
    this.state.currentTheme = theme;
    document.documentElement.classList.toggle("dark", theme === "dark");
    localStorage.setItem("bangrondb_theme", theme);

    // Update theme-specific styles
    this.updateThemeStyles();

    // Send analytics
    this.sendThemeAnalytics(theme);
  }

  updateThemeStyles() {
    // Update CSS variables based on theme
    const root = document.documentElement;

    if (this.state.currentTheme === "dark") {
      root.style.setProperty("--bg-primary", "#0f172a");
      root.style.setProperty("--bg-secondary", "#1e293b");
      root.style.setProperty("--text-primary", "#f1f5f9");
      root.style.setProperty("--text-secondary", "#94a3b8");
    } else {
      root.style.setProperty("--bg-primary", "#ffffff");
      root.style.setProperty("--bg-secondary", "#f8fafc");
      root.style.setProperty("--text-primary", "#1e293b");
      root.style.setProperty("--text-secondary", "#64748b");
    }
  }

  setupThemeToggle() {
    const themeToggle = document.querySelector("[data-theme-toggle]");
    if (themeToggle) {
      themeToggle.addEventListener("click", () => {
        const newTheme = this.state.currentTheme === "dark" ? "light" : "dark";
        this.setTheme(newTheme);
      });
    }
  }

  sendThemeAnalytics(theme) {
    if (navigator.sendBeacon) {
      const analytics = {
        type: "theme_change",
        theme: theme,
        timestamp: Date.now(),
      };
      navigator.sendBeacon("/api/analytics/theme", JSON.stringify(analytics));
    }
  }

  // Keyboard Shortcuts
  initializeKeyboardShortcuts() {
    if (!this.config.enableKeyboardShortcuts) return;

    this.setupDefaultShortcuts();
    this.setupCustomShortcuts();
    this.setupKeyboardNavigation();
  }

  setupDefaultShortcuts() {
    const shortcuts = {
      "ctrl+k": () => this.enableSearch(),
      "ctrl+/": () => this.toggleKeyboardHelp(),
      "ctrl+s": () => this.handleSave(),
      "ctrl+z": () => this.undo(),
      "ctrl+shift+z": () => this.redo(),
      "ctrl+f": () => this.enableSearch(),
      esc: () => this.closeAllModals(),
      tab: () => this.handleTabNavigation(),
      "shift+tab": () => this.handleReverseTabNavigation(),
    };

    Object.entries(shortcuts).forEach(([keys, handler]) => {
      this.registerShortcut(keys, handler);
    });
  }

  setupCustomShortcuts() {
    // Allow custom shortcuts to be registered
    window.addEventListener("shortcut-registered", (event) => {
      this.registerShortcut(event.detail.keys, event.detail.handler);
    });
  }

  registerShortcut(keys, handler) {
    this.state.keyboardShortcuts[keys] = handler;
  }

  setupKeyboardNavigation() {
    document.addEventListener("keydown", (event) => {
      const key = this.getKeyCombo(event);

      if (this.state.keyboardShortcuts[key]) {
        event.preventDefault();
        this.state.keyboardShortcuts[key]();
        this.trackUserAction("keyboard_shortcut", { key: key });
      }
    });
  }

  getKeyCombo(event) {
    const keys = [];
    if (event.ctrlKey || event.metaKey) keys.push("ctrl");
    if (event.shiftKey) keys.push("shift");
    if (event.altKey) keys.push("alt");
    keys.push(event.key.toLowerCase());

    return keys.join("+");
  }

  // Tooltips
  initializeTooltips() {
    if (!this.config.enableTooltips) return;

    this.setupTooltips();
    this.setupDynamicTooltips();
  }

  setupTooltips() {
    document.querySelectorAll("[data-tooltip]").forEach((element) => {
      this.createTooltip(element);
    });
  }

  createTooltip(element) {
    const tooltip = document.createElement("div");
    tooltip.className = "tooltip";
    tooltip.textContent = element.dataset.tooltip;

    document.body.appendChild(tooltip);

    this.components.tooltips.set(element, tooltip);

    // Position tooltip
    element.addEventListener("mouseenter", () =>
      this.positionTooltip(element, tooltip),
    );
    element.addEventListener("mouseleave", () => this.hideTooltip(tooltip));
  }

  positionTooltip(element, tooltip) {
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + "px";
    tooltip.style.top = rect.bottom + 5 + "px";
    tooltip.style.display = "block";
  }

  hideTooltip(tooltip) {
    tooltip.style.display = "none";
  }

  setupDynamicTooltips() {
    // Setup tooltips for dynamically created elements
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === 1 && node.dataset.tooltip) {
            this.createTooltip(node);
          }
        });
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  // Loading States
  initializeLoadingStates() {
    if (!this.config.enableLoadingStates) return;

    this.setupLoadingIndicators();
    this.setupProgressBars();
  }

  setupLoadingIndicators() {
    document.querySelectorAll("[data-loading]").forEach((element) => {
      this.createLoadingIndicator(element);
    });
  }

  createLoadingIndicator(element) {
    const indicator = document.createElement("div");
    indicator.className = "loading-indicator";
    indicator.innerHTML = `
      <div class="spinner"></div>
      <div class="loading-text">Loading...</div>
    `;

    element.appendChild(indicator);
    this.components.loadingIndicators.set(element, indicator);
  }

  showLoading(element, message = "Loading...") {
    const indicator = this.components.loadingIndicators.get(element);
    if (indicator) {
      indicator.querySelector(".loading-text").textContent = message;
      indicator.style.display = "flex";
    }
  }

  hideLoading(element) {
    const indicator = this.components.loadingIndicators.get(element);
    if (indicator) {
      indicator.style.display = "none";
    }
  }

  // Progress Indicators
  initializeProgressIndicators() {
    if (!this.config.enableProgressIndicators) return;

    this.setupProgressBars();
    this.setupFileUploadProgress();
  }

  setupProgressBars() {
    document.querySelectorAll("[data-progress]").forEach((element) => {
      this.createProgressBar(element);
    });
  }

  createProgressBar(element) {
    const progressContainer = document.createElement("div");
    progressContainer.className = "progress-container";

    const progressBar = document.createElement("div");
    progressBar.className = "progress-bar";

    const progressText = document.createElement("div");
    progressText.className = "progress-text";

    progressContainer.appendChild(progressBar);
    progressContainer.appendChild(progressText);
    element.appendChild(progressContainer);

    this.components.progressBars.set(element, {
      container: progressContainer,
      bar: progressBar,
      text: progressText,
    });
  }

  updateProgress(element, percentage, message = "") {
    const progress = this.components.progressBars.get(element);
    if (progress) {
      progress.bar.style.width = percentage + "%";
      progress.text.textContent = message || `${percentage}%`;
    }
  }

  // Skeleton Screens
  initializeSkeletonScreens() {
    if (!this.config.enableSkeletonScreens) return;

    this.setupSkeletonScreens();
  }

  setupSkeletonScreens() {
    document.querySelectorAll("[data-skeleton]").forEach((element) => {
      this.createSkeletonScreen(element);
    });
  }

  createSkeletonScreen(element) {
    const skeleton = document.createElement("div");
    skeleton.className = "skeleton-screen";

    // Create skeleton based on element type
    if (element.tagName === "TABLE") {
      skeleton.innerHTML = this.createTableSkeleton();
    } else if (element.tagName === "CARD") {
      skeleton.innerHTML = this.createCardSkeleton();
    } else {
      skeleton.innerHTML = this.createGenericSkeleton();
    }

    element.style.display = "none";
    element.parentNode.insertBefore(skeleton, element);

    // Simulate loading
    setTimeout(
      () => {
        element.style.display = "block";
        skeleton.remove();
      },
      1000 + Math.random() * 2000,
    );
  }

  createTableSkeleton() {
    return `
      <div class="skeleton-table">
        <div class="skeleton-header">
          <div class="skeleton-cell"></div>
          <div class="skeleton-cell"></div>
          <div class="skeleton-cell"></div>
        </div>
        <div class="skeleton-row">
          <div class="skeleton-cell"></div>
          <div class="skeleton-cell"></div>
          <div class="skeleton-cell"></div>
        </div>
        <div class="skeleton-row">
          <div class="skeleton-cell"></div>
          <div class="skeleton-cell"></div>
          <div class="skeleton-cell"></div>
        </div>
      </div>
    `;
  }

  createCardSkeleton() {
    return `
      <div class="skeleton-card">
        <div class="skeleton-header"></div>
        <div class="skeleton-content"></div>
        <div class="skeleton-footer"></div>
      </div>
    `;
  }

  createGenericSkeleton() {
    return `
      <div class="skeleton-content">
        <div class="skeleton-line"></div>
        <div class="skeleton-line"></div>
        <div class="skeleton-line"></div>
      </div>
    `;
  }

  // Auto Save
  initializeAutoSave() {
    if (!this.config.enableAutoSave) return;

    this.setupAutoSave();
    this.setupAutoSaveIndicators();
  }

  setupAutoSave() {
    let saveTimeout;

    document.addEventListener("input", (event) => {
      if (event.target.dataset.autoSave) {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
          this.autoSave(event.target);
        }, 3000); // Save after 3 seconds of inactivity
      }
    });
  }

  autoSave(element) {
    const data = this.getElementData(element);
    const key = `autosave_${element.dataset.autoSave}`;

    localStorage.setItem(
      key,
      JSON.stringify({
        data: data,
        timestamp: Date.now(),
      }),
    );

    this.showNotification("Auto-saved", "success");
    this.trackUserAction("autosave", { element: element.tagName });
  }

  getElementData(element) {
    if (element.tagName === "FORM") {
      const formData = new FormData(element);
      return Object.fromEntries(formData);
    } else if (element.tagName === "TEXTAREA" || element.tagName === "INPUT") {
      return element.value;
    }
    return null;
  }

  setupAutoSaveIndicators() {
    // Show auto-save status
    const autoSaveIndicator = document.createElement("div");
    autoSaveIndicator.className = "auto-save-indicator";
    autoSaveIndicator.innerHTML = `
      <span class="auto-save-status">Auto-save enabled</span>
      <span class="auto-save-time"></span>
    `;

    document.body.appendChild(autoSaveIndicator);

    // Update auto-save time
    setInterval(() => {
      const now = new Date();
      autoSaveIndicator.querySelector(".auto-save-time").textContent =
        `Last saved: ${now.toLocaleTimeString()}`;
    }, 1000);
  }

  // Undo/Redo
  initializeUndoRedo() {
    if (!this.config.enableUndoRedo) return;

    this.setupUndoRedoListeners();
    this.setupUndoRedoUI();
  }

  setupUndoRedoListeners() {
    document.addEventListener("input", (event) => {
      if (event.target.dataset.undoable) {
        this.addToUndoStack(event.target);
      }
    });
  }

  addToUndoStack(element) {
    const currentState = this.getElementData(element);
    const state = {
      element: element,
      state: currentState,
      timestamp: Date.now(),
    };

    this.state.undoStack.push(state);

    // Limit undo stack size
    if (this.state.undoStack.length > 50) {
      this.state.undoStack.shift();
    }

    // Clear redo stack when new action is performed
    this.state.redoStack = [];
  }

  undo() {
    if (this.state.undoStack.length === 0) return;

    const lastState = this.state.undoStack.pop();
    const currentState = this.getElementData(lastState.element);

    // Save current state to redo stack
    this.state.redoStack.push({
      element: lastState.element,
      state: currentState,
      timestamp: Date.now(),
    });

    // Restore previous state
    this.setElementData(lastState.element, lastState.state);

    this.showNotification("Undo", "info");
    this.trackUserAction("undo", { element: lastState.element.tagName });
  }

  redo() {
    if (this.state.redoStack.length === 0) return;

    const nextState = this.state.redoStack.pop();
    const currentState = this.getElementData(nextState.element);

    // Save current state to undo stack
    this.state.undoStack.push({
      element: nextState.element,
      state: currentState,
      timestamp: Date.now(),
    });

    // Restore next state
    this.setElementData(nextState.element, nextState.state);

    this.showNotification("Redo", "info");
    this.trackUserAction("redo", { element: nextState.element.tagName });
  }

  setElementData(element, data) {
    if (element.tagName === "FORM") {
      Object.entries(data).forEach(([key, value]) => {
        const field = element.querySelector(`[name="${key}"]`);
        if (field) {
          field.value = value;
        }
      });
    } else if (element.tagName === "TEXTAREA" || element.tagName === "INPUT") {
      element.value = data;
    }
  }

  setupUndoRedoUI() {
    const undoButton = document.querySelector("[data-undo]");
    const redoButton = document.querySelector("[data-redo]");

    if (undoButton) {
      undoButton.addEventListener("click", () => this.undo());
    }

    if (redoButton) {
      redoButton.addEventListener("click", () => this.redo());
    }
  }

  // Voice Search
  initializeVoiceSearch() {
    if (!this.config.enableVoiceSearch) return;

    this.setupVoiceSearch();
  }

  setupVoiceSearch() {
    const voiceButton = document.querySelector("[data-voice-search]");
    if (!voiceButton) return;

    // Check for speech recognition support
    if ("webkitSpeechRecognition" in window || "SpeechRecognition" in window) {
      const SpeechRecognition =
        window.SpeechRecognition || window.webkitSpeechRecognition;
      this.state.voiceRecognition = new SpeechRecognition();

      this.state.voiceRecognition.continuous = false;
      this.state.voiceRecognition.interimResults = true;
      this.state.voiceRecognition.lang = "en-US";

      this.state.voiceRecognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        this.handleVoiceSearch(transcript);
      };

      this.state.voiceRecognition.onerror = (event) => {
        this.showNotification("Voice search error", "error");
      };

      voiceButton.addEventListener("click", () => {
        this.state.voiceRecognition.start();
        voiceButton.classList.add("recording");
      });

      this.state.voiceRecognition.onend = () => {
        voiceButton.classList.remove("recording");
      };
    }
  }

  handleVoiceSearch(transcript) {
    const searchInput = document.querySelector("[data-search]");
    if (searchInput) {
      searchInput.value = transcript;
      this.performSearch(transcript);
    }
  }

  // Predictive Search
  initializePredictiveSearch() {
    if (!this.config.enablePredictiveSearch) return;

    this.setupPredictiveSearch();
  }

  setupPredictiveSearch() {
    const searchInput = document.querySelector("[data-search]");
    if (!searchInput) return;

    let searchTimeout;

    searchInput.addEventListener("input", (event) => {
      clearTimeout(searchTimeout);

      const query = event.target.value.trim();
      if (query.length > 2) {
        searchTimeout = setTimeout(() => {
          this.performPredictiveSearch(query);
        }, 300);
      }
    });
  }

  performPredictiveSearch(query) {
    // Simulate predictive search
    const suggestions = [
      `${query} in databases`,
      `${query} in collections`,
      `${query} in documents`,
      `${query} in users`,
    ];

    this.showPredictiveSuggestions(suggestions);
  }

  showPredictiveSuggestions(suggestions) {
    // Remove existing suggestions
    const existingSuggestions = document.querySelector(
      ".predictive-suggestions",
    );
    if (existingSuggestions) {
      existingSuggestions.remove();
    }

    // Create suggestions container
    const container = document.createElement("div");
    container.className = "predictive-suggestions";

    suggestions.forEach((suggestion) => {
      const item = document.createElement("div");
      item.className = "predictive-suggestion";
      item.textContent = suggestion;
      item.addEventListener("click", () => {
        this.selectPredictiveSuggestion(suggestion);
      });
      container.appendChild(item);
    });

    document.body.appendChild(container);
  }

  selectPredictiveSuggestion(suggestion) {
    const searchInput = document.querySelector("[data-search]");
    if (searchInput) {
      searchInput.value = suggestion;
      this.performSearch(suggestion);
    }

    // Remove suggestions
    const container = document.querySelector(".predictive-suggestions");
    if (container) {
      container.remove();
    }
  }

  // Gesture Support
  initializeGestureSupport() {
    if (!this.config.enableGestureSupport) return;

    this.setupGestureSupport();
  }

  setupGestureSupport() {
    let touchStartX = 0;
    let touchStartY = 0;

    document.addEventListener("touchstart", (event) => {
      touchStartX = event.touches[0].clientX;
      touchStartY = event.touches[0].clientY;
    });

    document.addEventListener("touchend", (event) => {
      const touchEndX = event.changedTouches[0].clientX;
      const touchEndY = event.changedTouches[0].clientY;

      const deltaX = touchEndX - touchStartX;
      const deltaY = touchEndY - touchStartY;

      // Handle swipe gestures
      if (Math.abs(deltaX) > Math.abs(deltaY)) {
        // Horizontal swipe
        if (deltaX > 50) {
          this.handleSwipeRight();
        } else if (deltaX < -50) {
          this.handleSwipeLeft();
        }
      } else {
        // Vertical swipe
        if (deltaY > 50) {
          this.handleSwipeDown();
        } else if (deltaY < -50) {
          this.handleSwipeUp();
        }
      }
    });
  }

  handleSwipeRight() {
    this.showNotification("Swipe right detected", "info");
  }

  handleSwipeLeft() {
    this.showNotification("Swipe left detected", "info");
  }

  handleSwipeUp() {
    this.showNotification("Swipe up detected", "info");
  }

  handleSwipeDown() {
    this.showNotification("Swipe down detected", "info");
  }

  // Mobile Optimization
  initializeMobileOptimization() {
    if (!this.config.enableMobileOptimization) return;

    this.setupMobileOptimization();
  }

  setupMobileOptimization() {
    // Detect mobile device
    const isMobile =
      /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent,
      );

    if (isMobile) {
      this.applyMobileOptimizations();
    }
  }

  applyMobileOptimizations() {
    // Adjust touch targets
    document
      .querySelectorAll('button, a, [role="button"]')
      .forEach((element) => {
        if (element.offsetWidth < 44 || element.offsetHeight < 44) {
          element.style.minWidth = "44px";
          element.style.minHeight = "44px";
        }
      });

    // Enable touch scrolling
    document.querySelectorAll(".scrollable").forEach((element) => {
      element.style.webkitOverflowScrolling = "touch";
    });

    // Adjust font sizes
    document.documentElement.style.fontSize = "16px";
  }

  // Accessibility
  initializeAccessibility() {
    if (!this.config.enableAccessibility) return;

    this.setupAccessibility();
  }

  setupAccessibility() {
    // Setup keyboard navigation
    this.setupKeyboardNavigation();

    // Setup screen reader support
    this.setupScreenReaderSupport();

    // Setup high contrast mode
    this.setupHighContrastMode();

    // Setup reduced motion
    this.setupReducedMotion();
  }

  setupScreenReaderSupport() {
    document.querySelectorAll("[aria-label]").forEach((element) => {
      element.addEventListener("focus", () => {
        this.announceToScreenReader(element.getAttribute("aria-label"));
      });
    });
  }

  setupHighContrastMode() {
    const highContrastButton = document.querySelector("[data-high-contrast]");
    if (highContrastButton) {
      highContrastButton.addEventListener("click", () => {
        document.body.classList.toggle("high-contrast");
        this.state.accessibilityMode =
          document.body.classList.contains("high-contrast");
      });
    }
  }

  setupReducedMotion() {
    const prefersReducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    );
    if (prefersReducedMotion.matches) {
      document.body.classList.add("reduced-motion");
    }
  }

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

  // Notifications
  initializeNotifications() {
    if (!this.config.enableNotifications) return;

    this.setupNotifications();
  }

  setupNotifications() {
    // Setup notification container
    const container = document.createElement("div");
    container.className = "notification-container";
    document.body.appendChild(container);

    // Process notification queue
    setInterval(() => {
      this.processNotificationQueue();
    }, 1000);
  }

  showNotification(message, type = "info", duration = 3000) {
    const notification = {
      message: message,
      type: type,
      duration: duration,
      timestamp: Date.now(),
    };

    this.state.notificationQueue.push(notification);
  }

  processNotificationQueue() {
    const container = document.querySelector(".notification-container");
    if (!container || this.state.notificationQueue.length === 0) return;

    const notification = this.state.notificationQueue.shift();
    const element = this.createNotificationElement(notification);

    container.appendChild(element);

    // Auto-remove notification
    setTimeout(() => {
      element.remove();
    }, notification.duration);
  }

  createNotificationElement(notification) {
    const element = document.createElement("div");
    element.className = `notification notification-${notification.type}`;
    element.innerHTML = `
      <div class="notification-content">
        <div class="notification-message">${notification.message}</div>
        <div class="notification-close">×</div>
      </div>
    `;

    element
      .querySelector(".notification-close")
      .addEventListener("click", () => {
        element.remove();
      });

    return element;
  }

  // Error Handling
  initializeErrorHandling() {
    if (!this.config.enableErrorHandling) return;

    this.setupErrorHandling();
  }

  setupErrorHandling() {
    // Handle global errors
    window.addEventListener("error", (event) => {
      this.handleError(event.error);
    });

    // Handle unhandled promise rejections
    window.addEventListener("unhandledrejection", (event) => {
      this.handleError(event.reason);
    });
  }

  handleError(error) {
    this.metrics.errors++;

    // Show user-friendly error message
    this.showNotification("An error occurred. Please try again.", "error");

    // Log error for debugging
    console.error("Error:", error);

    // Send error analytics
    this.sendErrorAnalytics(error);
  }

  sendErrorAnalytics(error) {
    if (navigator.sendBeacon) {
      const analytics = {
        type: "error",
        message: error.message,
        stack: error.stack,
        timestamp: Date.now(),
      };
      navigator.sendBeacon("/api/analytics/error", JSON.stringify(analytics));
    }
  }

  // Performance Metrics
  initializePerformanceMetrics() {
    if (!this.config.enablePerformanceMetrics) return;

    this.setupPerformanceMetrics();
  }

  setupPerformanceMetrics() {
    // Track page load performance
    window.addEventListener("load", () => {
      this.trackPageLoadPerformance();
    });

    // Track user interactions
    document.addEventListener("click", (event) => {
      this.trackUserInteraction("click", event);
    });

    document.addEventListener("input", (event) => {
      this.trackUserInteraction("input", event);
    });
  }

  trackPageLoadPerformance() {
    const navigation = performance.getEntriesByType("navigation")[0];
    if (navigation) {
      const metrics = {
        loadTime: navigation.loadEventEnd - navigation.fetchStart,
        domContentLoaded:
          navigation.domContentLoadedEventEnd - navigation.fetchStart,
        firstPaint: performance.getEntriesByType("paint")[0]?.startTime || 0,
      };

      this.sendPerformanceAnalytics(metrics);
    }
  }

  trackUserInteraction(type, event) {
    this.metrics.interactions++;

    const action = {
      type: type,
      target: event.target.tagName,
      timestamp: Date.now(),
    };

    this.metrics.userActions.push(action);

    // Keep only last 1000 actions
    if (this.metrics.userActions.length > 1000) {
      this.metrics.userActions.shift();
    }
  }

  sendPerformanceAnalytics(metrics) {
    if (navigator.sendBeacon) {
      const analytics = {
        type: "performance",
        metrics: metrics,
        timestamp: Date.now(),
      };
      navigator.sendBeacon(
        "/api/analytics/performance",
        JSON.stringify(analytics),
      );
    }
  }

  // Public API
  getAccessibilityMode() {
    return this.state.accessibilityMode;
  }

  setAccessibilityMode(enabled) {
    this.state.accessibilityMode = enabled;
    document.body.classList.toggle("reduced-motion", enabled);
    document.body.classList.toggle("high-contrast", enabled);
  }

  getTheme() {
    return this.state.currentTheme;
  }

  setTheme(theme) {
    this.setTheme(theme);
  }

  getUserActions() {
    return this.metrics.userActions;
  }

  getPerformanceMetrics() {
    return {
      interactions: this.metrics.interactions,
      errors: this.metrics.errors,
      userActions: this.metrics.userActions.length,
    };
  }

  // Cleanup
  destroy() {
    // Clear all components
    this.components.tooltips.clear();
    this.components.modals.clear();
    this.components.loadingIndicators.clear();
    this.components.progressBars.clear();
    this.components.notifications.clear();

    // Clear state
    this.state.undoStack = [];
    this.state.redoStack = [];
    this.state.notificationQueue = [];

    // Remove event listeners
    document.removeEventListener("keydown", this.handleKeyboardShortcuts);
    document.removeEventListener("error", this.handleError);
    document.removeEventListener("unhandledrejection", this.handleError);
  }
}

// Initialize user experience enhancer
window.userExperienceEnhancer = new UserExperienceEnhancer();

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = UserExperienceEnhancer;
}
