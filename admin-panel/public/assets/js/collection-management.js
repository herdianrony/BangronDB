/**
 * Collection Management JavaScript
 * Enhanced BangronDB Admin Panel Features
 */

class CollectionManager {
  constructor() {
    this.currentCollection = "";
    this.currentDatabase = "";
    this.selectedDocuments = new Set();
    this.schemaFields = [];
    this.searchableFields = [];
    this.indexes = [];

    this.initializeEventListeners();
    this.initializeTooltips();
    this.initializeModals();
  }

  /**
   * Initialize event listeners
   */
  initializeEventListeners() {
    document.addEventListener("DOMContentLoaded", () => {
      this.initializeLucideIcons();
      this.initializeTabSwitching();
      this.initializeFormHandlers();
      this.initializeSearch();
      this.initializeBulkOperations();
    });

    // Handle escape key for modals
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeAllModals();
      }
    });
  }

  /**
   * Initialize Lucide icons
   */
  initializeLucideIcons() {
    if (typeof lucide !== "undefined") {
      lucide.createIcons();
    }
  }

  /**
   * Initialize tab switching functionality
   */
  initializeTabSwitching() {
    const tabButtons = document.querySelectorAll('[id^="tab-"]');
    tabButtons.forEach((button) => {
      button.addEventListener("click", (e) => {
        const tabName = e.target.id.replace("tab-", "");
        this.switchTab(tabName);
      });
    });
  }

  /**
   * Switch between tabs
   */
  switchTab(tabName) {
    // Hide all content panels
    document.querySelectorAll('[id^="content-"]').forEach((panel) => {
      panel.classList.add("hidden");
    });

    // Remove active state from all tabs
    document.querySelectorAll('[id^="tab-"]').forEach((tab) => {
      tab.classList.remove("border-blue-500", "text-blue-500");
      tab.classList.add("border-transparent", "text-gray-400");
    });

    // Show selected content and activate tab
    const contentPanel = document.getElementById(`content-${tabName}`);
    const activeTab = document.getElementById(`tab-${tabName}`);

    if (contentPanel && activeTab) {
      contentPanel.classList.remove("hidden");
      activeTab.classList.remove("border-transparent", "text-gray-400");
      activeTab.classList.add("border-blue-500", "text-blue-500");

      // Initialize tab-specific features
      this.initializeTabFeatures(tabName);
    }

    this.initializeLucideIcons();
  }

  /**
   * Initialize features specific to each tab
   */
  initializeTabFeatures(tabName) {
    switch (tabName) {
      case "identity":
        this.initializeIdentityTab();
        break;
      case "security":
        this.initializeSecurityTab();
        break;
      case "schema":
        this.initializeSchemaTab();
        break;
      case "indexes":
        this.initializeIndexesTab();
        break;
      case "advanced":
        this.initializeAdvancedTab();
        break;
    }
  }

  /**
   * Initialize Identity tab features
   */
  initializeIdentityTab() {
    const idModeSelect = document.getElementById("id_mode");
    if (idModeSelect) {
      idModeSelect.addEventListener("change", (e) => {
        const prefixField = document.getElementById("prefix_field");
        if (prefixField) {
          prefixField.classList.toggle("hidden", e.target.value !== "prefix");
        }
      });
    }
  }

  /**
   * Initialize Security tab features
   */
  initializeSecurityTab() {
    const encryptionToggle = document.getElementById("encryption_enabled");
    if (encryptionToggle) {
      encryptionToggle.addEventListener("change", (e) => {
        const encryptionDetails = document.getElementById("encryption_details");
        if (encryptionDetails) {
          encryptionDetails.classList.toggle("hidden", !e.target.checked);
        }
      });
    }
  }

  /**
   * Initialize Schema tab features
   */
  initializeSchemaTab() {
    this.loadSchemaFields();
    this.initializeSchemaBuilder();
  }

  /**
   * Initialize Indexes tab features
   */
  initializeIndexesTab() {
    this.loadSearchableFields();
    this.loadPerformanceIndexes();
  }

  /**
   * Initialize Advanced tab features
   */
  initializeAdvancedTab() {
    const softDeleteToggle = document.getElementById("soft_deletes_enabled");
    if (softDeleteToggle) {
      softDeleteToggle.addEventListener("change", (e) => {
        const softDeleteField = document.getElementById("soft_delete_field");
        if (softDeleteField) {
          softDeleteField.classList.toggle("hidden", !e.target.checked);
        }
      });
    }
  }

  /**
   * Initialize form handlers
   */
  initializeFormHandlers() {
    // Auto-save functionality
    const form = document.querySelector("form");
    if (form) {
      let saveTimeout;
      form.addEventListener("input", (e) => {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
          this.autoSaveForm();
        }, 2000);
      });
    }

    // Form validation
    this.initializeFormValidation();
  }

  /**
   * Initialize form validation
   */
  initializeFormValidation() {
    const requiredFields = document.querySelectorAll(
      "input[required], select[required], textarea[required]",
    );
    requiredFields.forEach((field) => {
      field.addEventListener("blur", () => {
        this.validateField(field);
      });
    });
  }

  /**
   * Validate individual field
   */
  validateField(field) {
    const value = field.value.trim();
    const fieldName = field.name || field.id;

    // Basic validation rules
    if (field.hasAttribute("required") && !value) {
      this.showFieldError(field, "This field is required");
      return false;
    }

    if (field.type === "email" && value && !this.isValidEmail(value)) {
      this.showFieldError(field, "Please enter a valid email address");
      return false;
    }

    if (field.type === "number" && value && isNaN(value)) {
      this.showFieldError(field, "Please enter a valid number");
      return false;
    }

    this.clearFieldError(field);
    return true;
  }

  /**
   * Show field error
   */
  showFieldError(field, message) {
    let errorElement = field.nextElementSibling;
    if (!errorElement || !errorElement.classList.contains("field-error")) {
      errorElement = document.createElement("div");
      errorElement.className = "field-error text-red-400 text-xs mt-1";
      field.parentNode.insertBefore(errorElement, field.nextSibling);
    }
    errorElement.textContent = message;
    field.classList.add("border-red-500");
  }

  /**
   * Clear field error
   */
  clearFieldError(field) {
    const errorElement = field.nextElementSibling;
    if (errorElement && errorElement.classList.contains("field-error")) {
      errorElement.remove();
    }
    field.classList.remove("border-red-500");
  }

  /**
   * Auto-save form
   */
  autoSaveForm() {
    const form = document.querySelector("form");
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Simulate API call
    console.log("Auto-saving form data:", data);

    // Show save indicator
    this.showSaveIndicator();
  }

  /**
   * Show save indicator
   */
  showSaveIndicator() {
    const existingIndicator = document.querySelector(".save-indicator");
    if (existingIndicator) {
      existingIndicator.remove();
    }

    const indicator = document.createElement("div");
    indicator.className =
      "save-indicator fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50";
    indicator.innerHTML =
      '<i data-lucide="check" class="w-4 h-4 inline mr-2"></i>Saved';
    document.body.appendChild(indicator);

    this.initializeLucideIcons();

    setTimeout(() => {
      indicator.remove();
    }, 3000);
  }

  /**
   * Initialize search functionality
   */
  initializeSearch() {
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
      let searchTimeout;
      searchInput.addEventListener("input", (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          this.performSearch(e.target.value);
        }, 500);
      });
    }
  }

  /**
   * Perform search
   */
  performSearch(query) {
    if (!query) {
      this.clearSearchResults();
      return;
    }

    try {
      const searchQuery = JSON.parse(query);
      console.log("Searching with query:", searchQuery);
      this.executeSearch(searchQuery);
    } catch (e) {
      // Show search error
      this.showSearchError("Invalid JSON format in search query");
    }
  }

  /**
   * Execute search query
   */
  executeSearch(query) {
    // Simulate search execution
    console.log("Executing search:", query);
    this.highlightSearchResults(query);
  }

  /**
   * Highlight search results
   */
  highlightSearchResults(query) {
    // Clear previous highlights
    document.querySelectorAll(".search-highlight").forEach((el) => {
      el.classList.remove("search-highlight");
    });

    // Implement highlighting logic
    // This would be implemented based on the actual search results
  }

  /**
   * Clear search results
   */
  clearSearchResults() {
    // Clear search highlights
    document.querySelectorAll(".search-highlight").forEach((el) => {
      el.classList.remove("search-highlight");
    });
  }

  /**
   * Show search error
   */
  showSearchError(message) {
    const existingError = document.querySelector(".search-error");
    if (existingError) {
      existingError.remove();
    }

    const errorElement = document.createElement("div");
    errorElement.className =
      "search-error fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50";
    errorElement.textContent = message;
    document.body.appendChild(errorElement);

    setTimeout(() => {
      errorElement.remove();
    }, 5000);
  }

  /**
   * Initialize bulk operations
   */
  initializeBulkOperations() {
    const selectAllCheckbox = document.getElementById("selectAll");
    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener("change", (e) => {
        this.toggleSelectAll(e.target.checked);
      });
    }

    // Initialize individual document checkboxes
    document.querySelectorAll(".document-checkbox").forEach((checkbox) => {
      checkbox.addEventListener("change", (e) => {
        this.updateSelectedDocuments(e.target);
      });
    });
  }

  /**
   * Toggle select all documents
   */
  toggleSelectAll(checked) {
    const checkboxes = document.querySelectorAll(".document-checkbox");
    checkboxes.forEach((checkbox) => {
      checkbox.checked = checked;
      this.updateSelectedDocuments(checkbox);
    });
  }

  /**
   * Update selected documents set
   */
  updateSelectedDocuments(checkbox) {
    const docId = checkbox.value;
    if (checkbox.checked) {
      this.selectedDocuments.add(docId);
    } else {
      this.selectedDocuments.delete(docId);
    }

    this.updateSelectedCount();
    this.updateSelectAllCheckbox();
  }

  /**
   * Update selected count display
   */
  updateSelectedCount() {
    const countElement = document.getElementById("selectedCount");
    const listElement = document.getElementById("selectedList");

    if (countElement) {
      countElement.textContent = this.selectedDocuments.size;
    }

    if (listElement) {
      if (this.selectedDocuments.size > 0) {
        const docList = Array.from(this.selectedDocuments)
          .map(
            (id) => `<div class="text-xs p-1 bg-white/10 rounded">${id}</div>`,
          )
          .join("");
        listElement.innerHTML = docList;
      } else {
        listElement.innerHTML =
          '<div class="text-gray-400">No documents selected</div>';
      }
    }
  }

  /**
   * Update select all checkbox state
   */
  updateSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById("selectAll");
    const totalCheckboxes =
      document.querySelectorAll(".document-checkbox").length;
    const checkedCheckboxes = this.selectedDocuments.size;

    if (selectAllCheckbox) {
      selectAllCheckbox.checked = checkedCheckboxes === totalCheckboxes;
      selectAllCheckbox.indeterminate =
        checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
    }
  }

  /**
   * Initialize modals
   */
  initializeModals() {
    // Close modal when clicking outside
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal-overlay")) {
        e.target.classList.remove("active");
      }
    });
  }

  /**
   * Open modal
   */
  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add("active");
      this.initializeLucideIcons();
      this.initializeModalContent(modalId);
    }
  }

  /**
   * Close modal
   */
  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove("active");
    }
  }

  /**
   * Close all modals
   */
  closeAllModals() {
    document.querySelectorAll(".modal-overlay").forEach((modal) => {
      modal.classList.remove("active");
    });
  }

  /**
   * Initialize modal content
   */
  initializeModalContent(modalId) {
    switch (modalId) {
      case "collectionStatsModal":
        this.loadCollectionStatistics();
        break;
      case "schemaBuilderModal":
        this.initializeSchemaBuilderModal();
        break;
    }
  }

  /**
   * Load collection statistics
   */
  loadCollectionStatistics() {
    // Simulate loading statistics
    console.log("Loading collection statistics...");
    // This would make an API call to fetch actual statistics
  }

  /**
   * Initialize schema builder modal
   */
  initializeSchemaBuilderModal() {
    // Initialize drag and drop functionality
    this.initializeDragAndDrop();
  }

  /**
   * Initialize drag and drop for schema builder
   */
  initializeDragAndDrop() {
    // This would implement drag and drop functionality for schema fields
    console.log("Initializing drag and drop...");
  }

  /**
   * Initialize tooltips
   */
  initializeTooltips() {
    // Initialize tooltips for interactive elements
    const tooltipElements = document.querySelectorAll("[title]");
    tooltipElements.forEach((element) => {
      element.addEventListener("mouseenter", (e) => {
        this.showTooltip(e.target, e.target.title);
      });

      element.addEventListener("mouseleave", () => {
        this.hideTooltip();
      });
    });
  }

  /**
   * Show tooltip
   */
  showTooltip(element, text) {
    const tooltip = document.createElement("div");
    tooltip.className =
      "tooltip fixed bg-gray-800 text-white text-xs rounded px-2 py-1 z-50 pointer-events-none";
    tooltip.textContent = text;
    document.body.appendChild(tooltip);

    const rect = element.getBoundingClientRect();
    tooltip.style.left =
      rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px";
    tooltip.style.top = rect.bottom + 5 + "px";
  }

  /**
   * Hide tooltip
   */
  hideTooltip() {
    const tooltip = document.querySelector(".tooltip");
    if (tooltip) {
      tooltip.remove();
    }
  }

  /**
   * Load schema fields
   */
  loadSchemaFields() {
    // Simulate loading schema fields
    this.schemaFields = [];
    console.log("Loading schema fields...");
  }

  /**
   * Load searchable fields
   */
  loadSearchableFields() {
    // Simulate loading searchable fields
    this.searchableFields = [];
    console.log("Loading searchable fields...");
  }

  /**
   * Load performance indexes
   */
  loadPerformanceIndexes() {
    // Simulate loading performance indexes
    this.indexes = [];
    console.log("Loading performance indexes...");
  }

  /**
   * Initialize schema builder
   */
  initializeSchemaBuilder() {
    const addFieldButton = document.querySelector(
      '[onclick="addFieldToSchema()"]',
    );
    if (addFieldButton) {
      addFieldButton.addEventListener("click", () => {
        this.addSchemaField();
      });
    }
  }

  /**
   * Add schema field
   */
  addSchemaField() {
    const fieldName = document.getElementById("new_field_name");
    const fieldType = document.getElementById("new_field_type");
    const fieldRequired = document.getElementById("new_field_required");

    if (!fieldName || !fieldName.value) {
      this.showNotification("Please enter a field name", "error");
      return;
    }

    const field = {
      name: fieldName.value,
      type: fieldType.value,
      required: fieldRequired.value === "true",
    };

    this.schemaFields.push(field);
    this.renderSchemaFields();
    this.clearSchemaFieldForm();
    this.showNotification("Field added successfully", "success");
  }

  /**
   * Render schema fields
   */
  renderSchemaFields() {
    const tbody = document.getElementById("schema_fields");
    if (!tbody) return;

    tbody.innerHTML = this.schemaFields
      .map(
        (field) => `
            <tr class="border-b border-white/5">
                <td class="px-4 py-3 text-sm font-mono">${field.name}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800">
                        ${field.type}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium ${field.required ? "bg-red-100 text-red-800" : "bg-gray-100 text-gray-600"}">
                        ${field.required ? "Required" : "Optional"}
                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-400">-</td>
                <td class="px-4 py-3 text-right">
                    <button onclick="collectionManager.removeSchemaField('${field.name}')" class="text-gray-400 hover:text-red-400 p-1">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </td>
            </tr>
        `,
      )
      .join("");

    this.initializeLucideIcons();
  }

  /**
   * Remove schema field
   */
  removeSchemaField(fieldName) {
    this.schemaFields = this.schemaFields.filter(
      (field) => field.name !== fieldName,
    );
    this.renderSchemaFields();
    this.showNotification("Field removed", "info");
  }

  /**
   * Clear schema field form
   */
  clearSchemaFieldForm() {
    const fieldName = document.getElementById("new_field_name");
    const fieldType = document.getElementById("new_field_type");
    const fieldRequired = document.getElementById("new_field_required");

    if (fieldName) fieldName.value = "";
    if (fieldType) fieldType.value = "string";
    if (fieldRequired) fieldRequired.value = "false";
  }

  /**
   * Add searchable field
   */
  addSearchableField() {
    const field = prompt("Enter field name:");
    if (field) {
      this.searchableFields.push(field);
      this.renderSearchableFields();
      this.showNotification("Searchable field added", "success");
    }
  }

  /**
   * Render searchable fields
   */
  renderSearchableFields() {
    const container = document.getElementById("searchable_fields");
    if (!container) return;

    container.innerHTML = this.searchableFields
      .map(
        (field) => `
            <div class="flex items-center justify-between p-3 bg-white/5 rounded-lg border border-white/10">
                <div class="flex items-center gap-3">
                    <i data-lucide="key" class="w-4 h-4 text-gray-400"></i>
                    <span class="font-mono text-sm">${field}</span>
                </div>
                <button onclick="collectionManager.removeSearchableField('${field}')" class="text-gray-400 hover:text-red-400">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        `,
      )
      .join("");

    this.initializeLucideIcons();
  }

  /**
   * Remove searchable field
   */
  removeSearchableField(field) {
    this.searchableFields = this.searchableFields.filter((f) => f !== field);
    this.renderSearchableFields();
    this.showNotification("Searchable field removed", "info");
  }

  /**
   * Add performance index
   */
  addIndex() {
    const index = prompt("Enter index field:");
    if (index) {
      this.indexes.push(index);
      this.renderIndexes();
      this.showNotification("Index added", "success");
    }
  }

  /**
   * Render performance indexes
   */
  renderIndexes() {
    const container = document.getElementById("performance_indexes");
    if (!container) return;

    container.innerHTML = this.indexes
      .map(
        (index) => `
            <div class="flex items-center justify-between p-3 bg-white/5 rounded-lg border border-white/10">
                <div class="flex items-center gap-3">
                    <i data-lucide="zap" class="w-4 h-4 text-gray-400"></i>
                    <span class="font-mono text-sm">${index}</span>
                </div>
                <span class="text-xs text-green-400">Active</span>
            </div>
        `,
      )
      .join("");

    this.initializeLucideIcons();
  }

  /**
   * Export collection
   */
  exportCollection() {
    const exportData = {
      collection: this.currentCollection,
      database: this.currentDatabase,
      schema: this.schemaFields,
      searchableFields: this.searchableFields,
      indexes: this.indexes,
      timestamp: new Date().toISOString(),
    };

    const dataStr = JSON.stringify(exportData, null, 2);
    const dataBlob = new Blob([dataStr], { type: "application/json" });
    const url = URL.createObjectURL(dataBlob);

    const link = document.createElement("a");
    link.href = url;
    link.download = `${this.currentCollection}_export_${new Date().toISOString().split("T")[0]}.json`;
    link.click();

    URL.revokeObjectURL(url);
    this.showNotification("Collection exported successfully", "success");
  }

  /**
   * Import collection
   */
  importCollection() {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = ".json";

    input.onchange = (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
          try {
            const data = JSON.parse(e.target.result);
            this.importCollectionData(data);
          } catch (error) {
            this.showNotification("Invalid import file", "error");
          }
        };
        reader.readAsText(file);
      }
    };

    input.click();
  }

  /**
   * Import collection data
   */
  importCollectionData(data) {
    if (data.schema) {
      this.schemaFields = data.schema;
      this.renderSchemaFields();
    }

    if (data.searchableFields) {
      this.searchableFields = data.searchableFields;
      this.renderSearchableFields();
    }

    if (data.indexes) {
      this.indexes = data.indexes;
      this.renderIndexes();
    }

    this.showNotification("Collection imported successfully", "success");
  }

  /**
   * Show notification
   */
  showNotification(message, type = "info") {
    const colors = {
      success: "bg-green-500",
      error: "bg-red-500",
      info: "bg-blue-500",
      warning: "bg-yellow-500",
    };

    const notification = document.createElement("div");
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg z-50`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
      notification.remove();
    }, 3000);
  }

  /**
   * Utility functions
   */
  isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + " " + date.toLocaleTimeString();
  }
}

// Initialize collection manager when DOM is ready
let collectionManager;
document.addEventListener("DOMContentLoaded", () => {
  collectionManager = new CollectionManager();
});

// Make collection manager globally available
window.collectionManager = collectionManager;

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = CollectionManager;
}
