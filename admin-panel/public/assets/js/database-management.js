/**
 * BangronDB Database Management JavaScript
 * Enhanced functionality for database management interface
 */

class DatabaseManager {
  constructor() {
    this.databases = [];
    this.selectedDatabase = null;
    this.healthData = {};
    this.backupData = {};
    this.permissions = {};
    this.searchQuery = "";
    this.filters = {
      status: "",
      encryption: "",
    };

    this.init();
  }

  init() {
    this.loadDatabases();
    this.setupEventListeners();
    this.startRealTimeUpdates();
    this.initializeCharts();
  }

  async loadDatabases() {
    try {
      const response = await fetch("/api/databases");
      this.databases = await response.json();
      this.renderDatabases();
      this.updateStats();
    } catch (error) {
      console.error("Error loading databases:", error);
      this.showNotification("Error loading databases", "error");
    }
  }

  renderDatabases() {
    const container = document.querySelector(".database-grid");
    if (!container) return;

    const filteredDatabases = this.filterDatabases();

    container.innerHTML = filteredDatabases
      .map(
        (db) => `
            <div class="database-card database-card-enhanced" data-db-id="${db._id}">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-3 bg-blue-50 rounded-lg group-hover:bg-blue-100 transition-colors">
                        <i data-lucide="database" class="w-6 h-6 text-blue-600"></i>
                    </div>
                    <span class="health-indicator health-healthy">
                        <i data-lucide="check-circle-2" class="w-3 h-3 mr-1"></i>
                        ${db.status || "Healthy"}
                    </span>
                </div>
                <h3 class="font-semibold text-slate-900 text-lg">${db._id}</h3>
                <p class="text-sm text-slate-500 mt-1">${db.collections_count || 0} collections • ${this.formatBytes(db.size || 0)}</p>
                <div class="flex items-center gap-2 mt-3">
                    ${db.encrypted ? '<span class="database-tag tag-encrypted"><i data-lucide="lock" class="w-3 h-3 mr-1"></i> Encrypted</span>' : '<span class="database-tag tag-unencrypted">No Encryption</span>'}
                    ${db.backup_enabled ? '<span class="database-tag tag-backup"><i data-lucide="shield-check" class="w-3 h-3 mr-1"></i> Auto Backup</span>' : ""}
                </div>
            </div>
        `,
      )
      .join("");

    // Re-initialize Lucide icons
    lucide.createIcons();

    // Add click handlers
    container.querySelectorAll(".database-card").forEach((card) => {
      card.addEventListener("click", (e) => {
        if (!e.target.closest("button")) {
          const dbId = card.dataset.dbId;
          this.navigateToDatabase(dbId);
        }
      });
    });
  }

  filterDatabases() {
    return this.databases.filter((db) => {
      const matchesSearch =
        db._id.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
        (db.label &&
          db.label.toLowerCase().includes(this.searchQuery.toLowerCase()));

      const matchesStatus =
        !this.filters.status ||
        (this.filters.status === "healthy" &&
          (db.status === "healthy" || !db.status)) ||
        (this.filters.status === "warning" && db.status === "warning") ||
        (this.filters.status === "error" && db.status === "error");

      const matchesEncryption =
        !this.filters.encryption ||
        (this.filters.encryption === "encrypted" && db.encrypted) ||
        (this.filters.encryption === "unencrypted" && !db.encrypted);

      return matchesSearch && matchesStatus && matchesEncryption;
    });
  }

  updateStats() {
    const totalDatabases = this.databases.length;
    const healthyDatabases = this.databases.filter(
      (db) => !db.status || db.status === "healthy",
    ).length;
    const totalSize = this.databases.reduce(
      (sum, db) => sum + (db.size || 0),
      0,
    );
    const activeBackups = this.databases.filter(
      (db) => db.backup_enabled,
    ).length;

    // Update stat cards
    document.querySelector(".stat-card:nth-child(1) .text-2xl").textContent =
      totalDatabases;
    document.querySelector(".stat-card:nth-child(2) .text-2xl").textContent =
      healthyDatabases;
    document.querySelector(".stat-card:nth-child(3) .text-2xl").textContent =
      this.formatBytes(totalSize);
    document.querySelector(".stat-card:nth-child(4) .text-2xl").textContent =
      activeBackups;
  }

  setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById("searchDatabases");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        this.searchQuery = e.target.value;
        this.renderDatabases();
      });
    }

    // Filter functionality
    const statusFilter = document.getElementById("statusFilter");
    if (statusFilter) {
      statusFilter.addEventListener("change", (e) => {
        this.filters.status = e.target.value;
        this.renderDatabases();
      });
    }

    const encryptionFilter = document.getElementById("encryptionFilter");
    if (encryptionFilter) {
      encryptionFilter.addEventListener("change", (e) => {
        this.filters.encryption = e.target.value;
        this.renderDatabases();
      });
    }

    // Modal event listeners
    document.querySelectorAll('[onclick^="openModal"]').forEach((button) => {
      button.addEventListener("click", (e) => {
        const modalId = e.target.getAttribute("onclick").match(/'([^']+)'/)[1];
        this.openModal(modalId);
      });
    });

    document.querySelectorAll('[onclick^="closeModal"]').forEach((button) => {
      button.addEventListener("click", (e) => {
        const modalId = e.target.getAttribute("onclick").match(/'([^']+)'/)[1];
        this.closeModal(modalId);
      });
    });
  }

  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove("hidden");
      modal.classList.add("flex");
      lucide.createIcons();
    }
  }

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add("hidden");
      modal.classList.remove("flex");
    }
  }

  async loadDatabaseHealth(dbId) {
    try {
      const response = await fetch(`/api/databases/${dbId}/health`);
      this.healthData[dbId] = await response.json();
      this.updateHealthDisplay(dbId);
    } catch (error) {
      console.error("Error loading health data:", error);
      this.showNotification("Error loading health data", "error");
    }
  }

  updateHealthDisplay(dbId) {
    const healthData = this.healthData[dbId];
    if (!healthData) return;

    // Update health metrics cards
    const connectionCard = document.querySelector(".health-metric-connection");
    if (connectionCard) {
      connectionCard.querySelector(".text-sm").textContent =
        healthData.connection.status;
      connectionCard.querySelector(".font-medium").textContent =
        healthData.responseTime + "ms";
    }

    const storageCard = document.querySelector(".health-metric-storage");
    if (storageCard) {
      storageCard.querySelector(".text-sm").textContent = "Storage Usage";
      storageCard.querySelector(".font-medium").textContent =
        healthData.storage.usage + "%";

      // Update progress bar
      const progressBar = storageCard.querySelector(".progress-bar-fill");
      if (progressBar) {
        progressBar.style.width = healthData.storage.usage + "%";
      }
    }

    const performanceCard = document.querySelector(
      ".health-metric-performance",
    );
    if (performanceCard) {
      performanceCard.querySelector(".text-sm").textContent = "Performance";
      performanceCard.querySelector(".font-medium").textContent =
        healthData.performance.score;
      performanceCard.querySelector(".text-xs").textContent =
        "Avg: " + healthData.performance.avgQueryTime + "ms";
    }

    const backupCard = document.querySelector(".health-metric-backup");
    if (backupCard) {
      backupCard.querySelector(".text-sm").textContent = "Last Backup";
      backupCard.querySelector(".font-medium").textContent =
        healthData.backup.lastBackup;
      backupCard.querySelector(".text-xs").textContent =
        "Next: " + healthData.backup.nextBackup;
    }
  }

  async loadDatabaseBackups(dbId) {
    try {
      const response = await fetch(`/api/databases/${dbId}/backups`);
      this.backupData[dbId] = await response.json();
      this.updateBackupsDisplay(dbId);
    } catch (error) {
      console.error("Error loading backup data:", error);
      this.showNotification("Error loading backup data", "error");
    }
  }

  updateBackupsDisplay(dbId) {
    const backups = this.backupData[dbId];
    if (!backups) return;

    const backupList = document.querySelector(".backup-list");
    if (backupList) {
      backupList.innerHTML = backups
        .map(
          (backup) => `
                <div class="backup-item">
                    <div>
                        <p class="font-medium text-slate-900">${backup.filename}</p>
                        <p class="text-xs text-slate-500">${backup.created_at} • ${this.formatBytes(backup.size)}</p>
                    </div>
                    <div class="backup-actions">
                        <button onclick="databaseManager.downloadBackup('${backup.filename}')" class="text-blue-400 hover:text-blue-300">
                            <i data-lucide="download" class="w-4 h-4"></i>
                        </button>
                        <button onclick="databaseManager.restoreBackup('${backup.filename}')" class="text-green-400 hover:text-green-300">
                            <i data-lucide="upload" class="w-4 h-4"></i>
                        </button>
                        <button onclick="databaseManager.deleteBackup('${backup.filename}')" class="text-red-400 hover:text-red-300">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `,
        )
        .join("");

      lucide.createIcons();
    }
  }

  async downloadBackup(filename) {
    try {
      const response = await fetch(
        `/api/databases/${this.selectedDatabase}/backups/${filename}/download`,
      );
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      a.click();
      window.URL.revokeObjectURL(url);
      this.showNotification("Backup downloaded successfully", "success");
    } catch (error) {
      console.error("Error downloading backup:", error);
      this.showNotification("Error downloading backup", "error");
    }
  }

  async restoreBackup(filename) {
    if (
      !confirm(
        `Restore database from backup "${filename}"? This will replace all current data.`,
      )
    ) {
      return;
    }

    try {
      const response = await fetch(
        `/api/databases/${this.selectedDatabase}/backups/${filename}/restore`,
        {
          method: "POST",
        },
      );
      const result = await response.json();

      if (result.success) {
        this.showNotification("Database restored successfully", "success");
        this.loadDatabaseHealth(this.selectedDatabase);
      } else {
        this.showNotification(
          "Error restoring database: " + result.message,
          "error",
        );
      }
    } catch (error) {
      console.error("Error restoring backup:", error);
      this.showNotification("Error restoring backup", "error");
    }
  }

  async deleteBackup(filename) {
    if (!confirm(`Delete backup "${filename}"? This cannot be undone.`)) {
      return;
    }

    try {
      const response = await fetch(
        `/api/databases/${this.selectedDatabase}/backups/${filename}`,
        {
          method: "DELETE",
        },
      );
      const result = await response.json();

      if (result.success) {
        this.showNotification("Backup deleted successfully", "success");
        this.loadDatabaseBackups(this.selectedDatabase);
      } else {
        this.showNotification(
          "Error deleting backup: " + result.message,
          "error",
        );
      }
    } catch (error) {
      console.error("Error deleting backup:", error);
      this.showNotification("Error deleting backup", "error");
    }
  }

  async optimizeDatabase() {
    if (
      !confirm("Optimize database performance? This may take a few minutes.")
    ) {
      return;
    }

    const button = event.target;
    button.disabled = true;
    button.innerHTML =
      '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Optimizing...';

    try {
      const response = await fetch(
        `/api/databases/${this.selectedDatabase}/optimize`,
        {
          method: "POST",
        },
      );
      const result = await response.json();

      if (result.success) {
        this.showNotification("Database optimized successfully", "success");
        this.loadDatabaseHealth(this.selectedDatabase);
      } else {
        this.showNotification(
          "Error optimizing database: " + result.message,
          "error",
        );
      }
    } catch (error) {
      console.error("Error optimizing database:", error);
      this.showNotification("Error optimizing database", "error");
    } finally {
      button.disabled = false;
      button.innerHTML =
        '<i data-lucide="zap" class="w-4 h-4 mr-2"></i> Optimize';
      lucide.createIcons();
    }
  }

  async cleanupDatabase() {
    if (
      !confirm(
        "Clean up database? This will remove orphaned documents and optimize storage.",
      )
    ) {
      return;
    }

    const button = event.target;
    button.disabled = true;
    button.innerHTML =
      '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Cleaning...';

    try {
      const response = await fetch(
        `/api/databases/${this.selectedDatabase}/cleanup`,
        {
          method: "POST",
        },
      );
      const result = await response.json();

      if (result.success) {
        this.showNotification("Database cleaned up successfully", "success");
        this.loadDatabaseHealth(this.selectedDatabase);
      } else {
        this.showNotification(
          "Error cleaning database: " + result.message,
          "error",
        );
      }
    } catch (error) {
      console.error("Error cleaning database:", error);
      this.showNotification("Error cleaning database", "error");
    } finally {
      button.disabled = false;
      button.innerHTML =
        '<i data-lucide="broom" class="w-4 h-4 mr-2"></i> Cleanup';
      lucide.createIcons();
    }
  }

  async reindexDatabase() {
    if (
      !confirm(
        "Rebuild database indexes? This may take some time depending on data size.",
      )
    ) {
      return;
    }

    const button = event.target;
    button.disabled = true;
    button.innerHTML =
      '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Reindexing...';

    try {
      const response = await fetch(
        `/api/databases/${this.selectedDatabase}/reindex`,
        {
          method: "POST",
        },
      );
      const result = await response.json();

      if (result.success) {
        this.showNotification("Database reindexed successfully", "success");
        this.loadDatabaseHealth(this.selectedDatabase);
      } else {
        this.showNotification(
          "Error reindexing database: " + result.message,
          "error",
        );
      }
    } catch (error) {
      console.error("Error reindexing database:", error);
      this.showNotification("Error reindexing database", "error");
    } finally {
      button.disabled = false;
      button.innerHTML =
        '<i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Reindex';
      lucide.createIcons();
    }
  }

  startRealTimeUpdates() {
    // Update health data every 30 seconds
    setInterval(() => {
      if (this.selectedDatabase) {
        this.loadDatabaseHealth(this.selectedDatabase);
      }
    }, 30000);

    // Update backup schedules every minute
    setInterval(() => {
      if (this.selectedDatabase) {
        this.loadDatabaseBackups(this.selectedDatabase);
      }
    }, 60000);
  }

  initializeCharts() {
    // Initialize performance charts if they exist
    const performanceChart = document.getElementById("performanceChart");
    if (performanceChart) {
      this.createPerformanceChart(performanceChart);
    }

    const storageChart = document.getElementById("storageChart");
    if (storageChart) {
      this.createStorageChart(storageChart);
    }
  }

  createPerformanceChart(canvas) {
    const ctx = canvas.getContext("2d");
    new Chart(ctx, {
      type: "line",
      data: {
        labels: ["1m", "2m", "3m", "4m", "5m"],
        datasets: [
          {
            label: "Query Response Time",
            data: [12, 19, 15, 25, 22],
            borderColor: "rgb(59, 130, 246)",
            backgroundColor: "rgba(59, 130, 246, 0.1)",
            tension: 0.4,
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
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
            },
            ticks: {
              color: "rgba(255, 255, 255, 0.7)",
            },
          },
          x: {
            grid: {
              color: "rgba(255, 255, 255, 0.1)",
            },
            ticks: {
              color: "rgba(255, 255, 255, 0.7)",
            },
          },
        },
      },
    });
  }

  createStorageChart(canvas) {
    const ctx = canvas.getContext("2d");
    new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: ["Used", "Free"],
        datasets: [
          {
            data: [45, 55],
            backgroundColor: [
              "rgba(59, 130, 246, 0.8)",
              "rgba(255, 255, 255, 0.1)",
            ],
            borderWidth: 0,
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
              color: "rgba(255, 255, 255, 0.7)",
            },
          },
        },
      },
    });
  }

  formatBytes(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB", "TB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  showNotification(message, type = "info") {
    // Create notification element
    const notification = document.createElement("div");
    notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white ${
      type === "success"
        ? "bg-green-500"
        : type === "error"
          ? "bg-red-500"
          : type === "warning"
            ? "bg-yellow-500"
            : "bg-blue-500"
    }`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Remove notification after 3 seconds
    setTimeout(() => {
      notification.remove();
    }, 3000);
  }

  navigateToDatabase(dbId) {
    window.location.href = `/databases/${encodeURIComponent(dbId)}`;
  }
}

// Initialize database manager when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.databaseManager = new DatabaseManager();
});

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = DatabaseManager;
}
