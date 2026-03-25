/**
 * BangronDB Admin Panel - Global JavaScript
 * Version: 2.0.0
 * Enhanced with dashboard functionality
 */

// Import dashboard modules
import "./dashboard-config.js";
import "./dashboard-utils.js";
import "./dashboard.js";
import "./api-service.js";

// Import performance optimization modules
import "./performance-optimization.js";
import "./caching-strategy.js";
import "./database-optimization.js";
import "./user-experience-enhancements.js";
import "./performance-monitoring.js";

// ==================== SIDEBAR ====================
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const mainContent = document.querySelector(".main-content");

  sidebar.classList.toggle("collapsed");

  if (mainContent) {
    mainContent.classList.toggle("sidebar-collapsed");
  }

  // Save state to localStorage
  const isCollapsed = sidebar.classList.contains("collapsed");
  localStorage.setItem("sidebarCollapsed", isCollapsed);
}

function initSidebar() {
  const sidebar = document.getElementById("sidebar");
  const mainContent = document.querySelector(".main-content");
  const isCollapsed = localStorage.getItem("sidebarCollapsed") === "true";

  if (isCollapsed && sidebar) {
    sidebar.classList.add("collapsed");
    if (mainContent) {
      mainContent.classList.add("sidebar-collapsed");
    }
  }

  // Set active menu item based on current page
  const currentPage = window.location.pathname.split("/").pop() || "index.html";
  document.querySelectorAll(".sidebar-item").forEach((item) => {
    const href = item.getAttribute("href");
    if (href === currentPage) {
      item.classList.add("active");
    } else {
      item.classList.remove("active");
    }
  });
}

// ==================== MODALS ====================
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove("hidden");
    modal.classList.add("flex", "active");
    document.body.style.overflow = "hidden";

    // Re-initialize icons if using Lucide
    if (typeof lucide !== "undefined") {
      lucide.createIcons();
    }
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add("hidden");
    modal.classList.remove("flex", "active");
    document.body.style.overflow = "";
  }
}

function closeAllModals() {
  document
    .querySelectorAll('.modal-overlay, [id*="Modal"]')
    .forEach((modal) => {
      modal.classList.add("hidden");
      modal.classList.remove("flex", "active");
    });
  document.body.style.overflow = "";
}

// Close modal on backdrop click
document.addEventListener("click", function (e) {
  if (
    e.target.classList.contains("modal-overlay") ||
    e.target.classList.contains("backdrop-blur-sm")
  ) {
    closeAllModals();
  }
});

// Close modal on Escape key
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    closeAllModals();
  }
});

// ==================== TABS ====================
function switchTab(tabName, tabGroup = "default") {
  // Hide all tab contents
  document
    .querySelectorAll(
      `[data-tab-group="${tabGroup}"], [id^="content-"], [id^="tab-content-"]`,
    )
    .forEach((content) => {
      content.classList.add("hidden");
    });

  // Show selected tab content
  const tabContent =
    document.getElementById(`content-${tabName}`) ||
    document.getElementById(`tab-content-${tabName}`);
  if (tabContent) {
    tabContent.classList.remove("hidden");
  }

  // Update tab buttons
  document.querySelectorAll('.tab, .tab-btn, [id^="tab-"]').forEach((tab) => {
    tab.classList.remove(
      "active",
      "border-blue-500",
      "text-blue-400",
      "border-primary-500",
    );
    tab.classList.add("border-transparent", "text-gray-400", "text-gray-500");
  });

  const activeTab =
    document.querySelector(`[data-tab="${tabName}"]`) ||
    document.getElementById(`tab-${tabName}`);
  if (activeTab) {
    activeTab.classList.add("active", "border-blue-500", "text-blue-400");
    activeTab.classList.remove(
      "border-transparent",
      "text-gray-400",
      "text-gray-500",
    );
  }

  // Re-initialize icons
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }
}

// ==================== DROPDOWN ====================
function toggleDropdown(dropdownId) {
  const dropdown = document.getElementById(dropdownId);
  if (dropdown) {
    dropdown.classList.toggle("active");
  }
}

// Close dropdowns when clicking outside
document.addEventListener("click", function (e) {
  if (!e.target.closest(".dropdown")) {
    document.querySelectorAll(".dropdown.active").forEach((d) => {
      d.classList.remove("active");
    });
  }
});

// ==================== TOAST NOTIFICATIONS ====================
function showToast(message, type = "info", duration = 3000) {
  let container = document.getElementById("toast-container");
  if (!container) {
    container = document.createElement("div");
    container.id = "toast-container";
    container.className = "toast-container";
    document.body.appendChild(container);
  }

  const toast = document.createElement("div");
  toast.className = `toast ${type}`;

  const icons = {
    success: "check-circle",
    warning: "alert-triangle",
    danger: "x-circle",
    info: "info",
  };

  toast.innerHTML = `
        <i data-lucide="${icons[type] || "info"}" style="width:20px;height:20px;flex-shrink:0;"></i>
        <span style="flex:1;">${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;padding:4px;">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
        </button>
    `;

  container.appendChild(toast);

  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateX(100%)";
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ==================== CONFIRM DIALOG ====================
function confirmDialog(message, onConfirm, onCancel) {
  const modalId = "confirm-dialog-modal";

  let modal = document.getElementById(modalId);
  if (modal) modal.remove();

  modal = document.createElement("div");
  modal.id = modalId;
  modal.className = "modal-overlay active";
  modal.style.cssText =
    "position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:300;";

  modal.innerHTML = `
        <div class="modal modal-sm" style="background:var(--dark-900);border:1px solid var(--glass-border);border-radius:16px;max-width:400px;">
            <div class="modal-header" style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--glass-border);display:flex;align-items:center;gap:0.75rem;">
                <div style="width:40px;height:40px;background:rgba(245,158,11,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="alert-triangle" style="width:20px;height:20px;color:#f59e0b;"></i>
                </div>
                <h3 style="font-size:1.125rem;font-weight:600;color:white;">Confirm Action</h3>
            </div>
            <div class="modal-body" style="padding:1.5rem;">
                <p style="color:var(--dark-300);">${message}</p>
            </div>
            <div class="modal-footer" style="padding:1rem 1.5rem;border-top:1px solid var(--glass-border);display:flex;justify-content:flex-end;gap:0.75rem;">
                <button id="confirm-cancel" class="btn btn-secondary">Cancel</button>
                <button id="confirm-ok" class="btn btn-danger">Confirm</button>
            </div>
        </div>
    `;

  document.body.appendChild(modal);

  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  document.getElementById("confirm-cancel").onclick = function () {
    modal.remove();
    if (onCancel) onCancel();
  };

  document.getElementById("confirm-ok").onclick = function () {
    modal.remove();
    if (onConfirm) onConfirm();
  };

  modal.onclick = function (e) {
    if (e.target === modal) {
      modal.remove();
      if (onCancel) onCancel();
    }
  };
}

// ==================== FORM UTILITIES ====================
function getFormData(formId) {
  const form = document.getElementById(formId);
  if (!form) return {};

  const formData = new FormData(form);
  const data = {};

  formData.forEach((value, key) => {
    data[key] = value;
  });

  return data;
}

function setFormData(formId, data) {
  const form = document.getElementById(formId);
  if (!form) return;

  Object.keys(data).forEach((key) => {
    const input = form.querySelector(`[name="${key}"]`);
    if (input) {
      if (input.type === "checkbox") {
        input.checked = !!data[key];
      } else {
        input.value = data[key];
      }
    }
  });
}

function resetForm(formId) {
  const form = document.getElementById(formId);
  if (form) form.reset();
}

// ==================== LOADING STATES ====================
function showLoading(elementId) {
  const element = document.getElementById(elementId);
  if (element) {
    element.dataset.originalContent = element.innerHTML;
    element.innerHTML = '<span class="loading"></span>';
    element.disabled = true;
  }
}

function hideLoading(elementId) {
  const element = document.getElementById(elementId);
  if (element && element.dataset.originalContent) {
    element.innerHTML = element.dataset.originalContent;
    element.disabled = false;
  }
}

// ==================== COPY TO CLIPBOARD ====================
async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text);
    showToast("Copied to clipboard!", "success");
  } catch (err) {
    // Fallback for older browsers
    const textArea = document.createElement("textarea");
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand("copy");
    document.body.removeChild(textArea);
    showToast("Copied to clipboard!", "success");
  }
}

// ==================== DATE & NUMBER FORMATTING ====================
function formatDate(date, format = "short") {
  const d = new Date(date);
  const options = {
    short: { year: "numeric", month: "short", day: "numeric" },
    long: {
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    },
    time: { hour: "2-digit", minute: "2-digit", second: "2-digit" },
  };
  return d.toLocaleDateString("en-US", options[format] || options.short);
}

function formatNumber(num) {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + "M";
  } else if (num >= 1000) {
    return (num / 1000).toFixed(1) + "K";
  }
  return num.toString();
}

function formatBytes(bytes) {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB", "TB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

function formatDuration(ms) {
  if (ms < 1000) return ms + "ms";
  if (ms < 60000) return (ms / 1000).toFixed(2) + "s";
  return (ms / 60000).toFixed(2) + "m";
}

function timeAgo(date) {
  const seconds = Math.floor((new Date() - new Date(date)) / 1000);

  const intervals = {
    year: 31536000,
    month: 2592000,
    week: 604800,
    day: 86400,
    hour: 3600,
    minute: 60,
    second: 1,
  };

  for (const [unit, secondsInUnit] of Object.entries(intervals)) {
    const interval = Math.floor(seconds / secondsInUnit);
    if (interval >= 1) {
      return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
    }
  }

  return "just now";
}

// ==================== JSON UTILITIES ====================
function formatJSON(json) {
  try {
    const obj = typeof json === "string" ? JSON.parse(json) : json;
    return JSON.stringify(obj, null, 2);
  } catch (e) {
    return json;
  }
}

function validateJSON(str) {
  try {
    JSON.parse(str);
    return { valid: true, error: null };
  } catch (e) {
    return { valid: false, error: e.message };
  }
}

function syntaxHighlight(json) {
  if (typeof json !== "string") {
    json = JSON.stringify(json, null, 2);
  }

  json = json
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");

  return json.replace(
    /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
    function (match) {
      let cls = "number";
      if (/^"/.test(match)) {
        if (/:$/.test(match)) {
          cls = "key";
        } else {
          cls = "string";
        }
      } else if (/true|false/.test(match)) {
        cls = "boolean";
      } else if (/null/.test(match)) {
        cls = "null";
      }
      return '<span class="json-' + cls + '">' + match + "</span>";
    },
  );
}

// ==================== KEYBOARD SHORTCUTS ====================
const shortcuts = {};

function registerShortcut(keys, callback) {
  shortcuts[keys.toLowerCase()] = callback;
}

document.addEventListener("keydown", function (e) {
  const key = [];
  if (e.ctrlKey || e.metaKey) key.push("ctrl");
  if (e.shiftKey) key.push("shift");
  if (e.altKey) key.push("alt");
  key.push(e.key.toLowerCase());

  const combo = key.join("+");

  if (shortcuts[combo]) {
    e.preventDefault();
    shortcuts[combo]();
  }
});

// Common shortcuts
registerShortcut("ctrl+k", () => {
  const searchInput = document.querySelector(
    'input[type="search"], input[placeholder*="earch"]',
  );
  if (searchInput) searchInput.focus();
});

registerShortcut("escape", () => {
  closeAllModals();
});

// ==================== LOCAL STORAGE WRAPPER ====================
const storage = {
  get(key, defaultValue = null) {
    try {
      const item = localStorage.getItem(key);
      return item ? JSON.parse(item) : defaultValue;
    } catch (e) {
      return defaultValue;
    }
  },

  set(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
      return true;
    } catch (e) {
      return false;
    }
  },

  remove(key) {
    localStorage.removeItem(key);
  },

  clear() {
    localStorage.clear();
  },
};

// ==================== THEME TOGGLE ====================
function toggleTheme() {
  const html = document.documentElement;
  const isDark = html.classList.contains("dark");

  if (isDark) {
    html.classList.remove("dark");
    storage.set("theme", "light");
  } else {
    html.classList.add("dark");
    storage.set("theme", "dark");
  }
}

function initTheme() {
  const savedTheme = storage.get("theme", "dark");
  if (savedTheme === "dark") {
    document.documentElement.classList.add("dark");
  }
}

// ==================== INITIALIZE ====================
document.addEventListener("DOMContentLoaded", function () {
  // Initialize Lucide icons
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  // Initialize sidebar state
  initSidebar();

  // Initialize theme
  initTheme();

  // Initialize dashboard if on dashboard page
  if (
    window.location.pathname.includes("/dashboard") ||
    document.querySelector(".dashboard-container")
  ) {
    if (window.dashboardManager) {
      window.dashboardManager.init();
    }
  }

  // Initialize API service
  if (window.apiService) {
    window.apiService
      .healthCheck()
      .then(() => {
        console.log("API service connected");
      })
      .catch((error) => {
        console.warn("API service connection failed:", error);
      });
  }

  // Initialize dashboard utilities
  if (window.dashboardUtils) {
    window.dashboardUtils.init();
  }

  console.log("BangronDB Admin Panel initialized");
});

// Global error handler
window.addEventListener("error", function (event) {
  console.error("Global error:", event.error);
  if (window.dashboardUtils) {
    window.dashboardUtils.handleError(event.error, "Global");
  }
});

// Handle unhandled promise rejections
window.addEventListener("unhandledrejection", function (event) {
  console.error("Unhandled promise rejection:", event.reason);
  if (window.dashboardUtils) {
    window.dashboardUtils.handleError(event.reason, "Promise");
  }
});

// Performance monitoring
if (window.performance && window.performance.mark) {
  window.addEventListener("load", function () {
    performance.mark("dashboard-loaded");
    const navigation = performance.getEntriesByType("navigation")[0];
    if (navigation) {
      console.log(
        `Page load time: ${navigation.loadEventEnd - navigation.fetchStart}ms`,
      );
    }
  });
}

// Initialize performance optimization modules
document.addEventListener("DOMContentLoaded", function () {
  // Initialize Performance Optimizer
  if (typeof PerformanceOptimizer !== "undefined") {
    window.performanceOptimizer = new PerformanceOptimizer();
    console.log("Performance Optimizer initialized");
  }

  // Initialize Caching Strategy
  if (typeof CachingStrategy !== "undefined") {
    window.cachingStrategy = new CachingStrategy();
    console.log("Caching Strategy initialized");
  }

  // Initialize Database Optimization
  if (typeof DatabaseOptimizer !== "undefined") {
    window.databaseOptimizer = new DatabaseOptimizer();
    console.log("Database Optimizer initialized");
  }

  // Initialize User Experience Enhancements
  if (typeof ExperienceManager !== "undefined") {
    window.experienceManager = new ExperienceManager();
    console.log("User Experience Manager initialized");
  }

  // Initialize Performance Monitoring
  if (typeof PerformanceMonitor !== "undefined") {
    window.performanceMonitor = new PerformanceMonitor();
    console.log("Performance Monitor initialized");
  }

  // Initialize Service Worker if supported
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker
      .register("/assets/js/service-worker.js")
      .then((registration) => {
        console.log(
          "Service Worker registered with scope:",
          registration.scope,
        );
      })
      .catch((error) => {
        console.log("Service Worker registration failed:", error);
      });
  }
});

// Re-initialize icons after dynamic content changes (debounced)
let lucideRaf = null;
const observer = new MutationObserver(function () {
  if (typeof lucide === "undefined") return;
  if (lucideRaf !== null) return;
  lucideRaf = requestAnimationFrame(() => {
    lucideRaf = null;
    lucide.createIcons();
  });
});

observer.observe(document.body, {
  childList: true,
  subtree: true,
});
