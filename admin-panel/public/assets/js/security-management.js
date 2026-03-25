class SecurityManagement {
  constructor() {
    this.apiBase = "/api/security";
    this.init();
  }

  init() {
    this.bindEvents();
    this.loadSecurityDashboard();
    this.startRealTimeMonitoring();
  }

  bindEvents() {
    // Encryption key management
    document.querySelectorAll('[data-action="generate-key"]').forEach((btn) => {
      btn.addEventListener("click", (e) => this.handleGenerateKey(e));
    });

    document.querySelectorAll('[data-action="rotate-key"]').forEach((btn) => {
      btn.addEventListener("click", (e) => this.handleRotateKey(e));
    });

    document.querySelectorAll('[data-action="revoke-key"]').forEach((btn) => {
      btn.addEventListener("click", (e) => this.handleRevokeKey(e));
    });

    // Security event handling
    document.querySelectorAll('[data-action="handle-event"]').forEach((btn) => {
      btn.addEventListener("click", (e) => this.handleSecurityEvent(e));
    });

    // Access control
    document
      .querySelectorAll('[data-action="grant-permission"]')
      .forEach((btn) => {
        btn.addEventListener("click", (e) => this.handleGrantPermission(e));
      });

    document
      .querySelectorAll('[data-action="revoke-permission"]')
      .forEach((btn) => {
        btn.addEventListener("click", (e) => this.handleRevokePermission(e));
      });

    // ABAC policy management
    document
      .querySelectorAll('[data-action="create-policy"]')
      .forEach((btn) => {
        btn.addEventListener("click", (e) => this.handleCreatePolicy(e));
      });

    document
      .querySelectorAll('[data-action="update-policy"]')
      .forEach((btn) => {
        btn.addEventListener("click", (e) => this.handleUpdatePolicy(e));
      });

    document
      .querySelectorAll('[data-action="delete-policy"]')
      .forEach((btn) => {
        btn.addEventListener("click", (e) => this.handleDeletePolicy(e));
      });

    // JIT access requests
    document.querySelectorAll('[data-action="request-jit"]').forEach((btn) => {
      btn.addEventListener("click", (e) => this.handleRequestJIT(e));
    });

    document.querySelectorAll('[data-action="approve-jit"]').forEach((btn) => {
      btn.addEventListener("click", (e) => this.handleApproveJIT(e));
    });

    document.querySelectorAll('[data-action="deny-jit"]').forEach((btn) => {
      btn.addEventListener("click", (e) => this.handleDenyJIT(e));
    });

    // Filter and search
    document
      .querySelectorAll('[data-filter="security-events"]')
      .forEach((filter) => {
        filter.addEventListener("change", (e) => this.filterSecurityEvents(e));
      });

    document
      .querySelectorAll('[data-search="security-events"]')
      .forEach((search) => {
        search.addEventListener("input", (e) => this.searchSecurityEvents(e));
      });
  }

  // Security Dashboard
  async loadSecurityDashboard() {
    try {
      const response = await fetch(`${this.apiBase}/dashboard`);
      const data = await response.json();

      if (data.success) {
        this.updateSecurityDashboard(data.dashboard);
      } else {
        this.showNotification("Failed to load security dashboard", "error");
      }
    } catch (error) {
      console.error("Error loading security dashboard:", error);
      this.showNotification("Error loading security dashboard", "error");
    }
  }

  updateSecurityDashboard(dashboard) {
    // Update security metrics
    this.updateMetricCard(
      "security-score",
      dashboard.metrics?.security_score || 0,
    );
    this.updateMetricCard(
      "encryption-coverage",
      dashboard.metrics?.encryption_coverage || 0,
    );
    this.updateMetricCard(
      "active-threats",
      dashboard.metrics?.active_threats || 0,
    );
    this.updateMetricCard(
      "compliance-score",
      dashboard.metrics?.compliance_score || 0,
    );

    // Update encryption keys
    this.updateEncryptionKeys(dashboard.encryption_keys || []);

    // Update security events
    this.updateSecurityEvents(dashboard.recent_events || []);

    // Update threat intelligence
    this.updateThreatIntelligence(dashboard.threat_intelligence || []);

    // Update access control status
    this.updateAccessControlStatus(dashboard.access_control || []);
  }

  updateMetricCard(cardId, value) {
    const card = document.getElementById(cardId);
    if (card) {
      const valueElement = card.querySelector(".metric-value");
      if (valueElement) {
        valueElement.textContent = value;
      }
    }
  }

  updateEncryptionKeys(keys) {
    const container = document.querySelector(".encryption-keys-container");
    if (!container) return;

    container.innerHTML = keys
      .map(
        (key) => `
            <div class="encryption-key-item" data-key-id="${key.key_id}">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg ${this.getKeyColor(key.type)} flex items-center justify-center">
                            <i class="w-5 h-5 ${this.getKeyIcon(key.type)}" data-lucide="${this.getKeyIcon(key.type)}"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900">${key.name}</p>
                            <p class="text-xs text-slate-500 font-mono">${key.key_id.substring(0, 16)}...</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium ${this.getStatusColor(key.status)}">
                            ${key.status}
                        </span>
                        <span class="text-xs text-slate-500">${this.formatDate(key.created_at)}</span>
                        <div class="flex gap-1">
                            ${this.getKeyActions(key)}
                        </div>
                    </div>
                </div>
            </div>
        `,
      )
      .join("");

    // Re-initialize Lucide icons
    lucide.createIcons();
  }

  getKeyColor(type) {
    const colors = {
      master: "bg-purple-100",
      collection: "bg-blue-100",
      field: "bg-green-100",
      client_side: "bg-amber-100",
    };
    return colors[type] || "bg-slate-100";
  }

  getKeyIcon(type) {
    const icons = {
      master: "shield",
      collection: "database",
      field: "lock",
      client_side: "user",
    };
    return icons[type] || "key";
  }

  getStatusColor(status) {
    const colors = {
      active: "bg-emerald-100 text-emerald-800",
      rotated: "bg-slate-100 text-slate-600",
      expired: "bg-red-100 text-red-800",
      pending: "bg-amber-100 text-amber-800",
    };
    return colors[status] || "bg-slate-100 text-slate-600";
  }

  getKeyActions(key) {
    const actions = [];

    if (key.status === "active") {
      actions.push(`
                <button class="text-slate-400 hover:text-slate-600 p-1" data-action="rotate-key" data-key-id="${key.key_id}" title="Rotate Key">
                    <i class="w-4 h-4" data-lucide="refresh-cw"></i>
                </button>
            `);
    }

    actions.push(`
            <button class="text-slate-400 hover:text-slate-600 p-1" data-action="view-key" data-key-id="${key.key_id}" title="View Key Details">
                <i class="w-4 h-4" data-lucide="eye"></i>
            </button>
        `);

    actions.push(`
            <button class="text-slate-400 hover:text-slate-600 p-1" data-action="revoke-key" data-key-id="${key.key_id}" title="Revoke Key">
                <i class="w-4 h-4" data-lucide="trash-2"></i>
            </button>
        `);

    return actions.join("");
  }

  updateSecurityEvents(events) {
    const container = document.querySelector(".security-events-list");
    if (!container) return;

    container.innerHTML = events
      .map(
        (event) => `
            <div class="security-event-item ${this.getEventSeverityClass(event.severity)}" data-event-id="${event.id}">
                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 ${this.getEventColor(event.severity)} rounded-full mt-2"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-900">${event.event_type}</p>
                        <p class="text-xs text-slate-500">${event.source_ip} • ${this.formatDate(event.timestamp)}</p>
                        <p class="text-xs ${this.getEventTextColor(event.severity)} mt-1">${event.description}</p>
                        ${
                          event.status !== "pending"
                            ? `
                            <div class="mt-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium ${this.getStatusColor(event.status)}">
                                    ${event.status}
                                </span>
                                ${
                                  event.resolution_notes
                                    ? `
                                    <span class="text-xs text-slate-500 ml-2">Note: ${event.resolution_notes}</span>
                                `
                                    : ""
                                }
                            </div>
                        `
                            : ""
                        }
                    </div>
                    ${
                      event.status === "pending"
                        ? `
                        <div class="flex gap-1">
                            <button class="text-slate-400 hover:text-emerald-600 p-1" data-action="handle-event" data-event-id="${event.id}" data-action-status="resolved" title="Resolve">
                                <i class="w-4 h-4" data-lucide="check"></i>
                            </button>
                            <button class="text-slate-400 hover:text-red-600 p-1" data-action="handle-event" data-event-id="${event.id}" data-action-status="investigating" title="Investigate">
                                <i class="w-4 h-4" data-lucide="search"></i>
                            </button>
                        </div>
                    `
                        : ""
                    }
                </div>
            </div>
        `,
      )
      .join("");

    // Re-initialize Lucide icons
    lucide.createIcons();
  }

  getEventSeverityClass(severity) {
    const classes = {
      critical: "border-l-4 border-red-500",
      high: "border-l-4 border-red-400",
      medium: "border-l-4 border-amber-400",
      low: "border-l-4 border-blue-400",
      info: "border-l-4 border-slate-400",
    };
    return classes[severity] || "border-l-4 border-slate-400";
  }

  getEventColor(severity) {
    const colors = {
      critical: "bg-red-500",
      high: "bg-red-400",
      medium: "bg-amber-400",
      low: "bg-blue-400",
      info: "bg-slate-400",
    };
    return colors[severity] || "bg-slate-400";
  }

  getEventTextColor(severity) {
    const colors = {
      critical: "text-red-600",
      high: "text-red-500",
      medium: "text-amber-600",
      low: "text-blue-600",
      info: "text-slate-600",
    };
    return colors[severity] || "text-slate-600";
  }

  updateThreatIntelligence(threats) {
    const container = document.querySelector(".threat-intelligence-list");
    if (!container) return;

    container.innerHTML = threats
      .map(
        (threat) => `
            <div class="threat-item">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-900">${threat.threat_type}</p>
                        <p class="text-xs text-slate-500">${threat.source} • First seen: ${this.formatDate(threat.first_seen)}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium ${this.getThreatSeverityColor(threat.severity)}">
                            ${threat.severity}
                        </span>
                    </div>
                </div>
                <div class="mt-2 text-xs text-slate-600">
                    <p>${threat.description}</p>
                    ${
                      threat.mitigation && threat.mitigation.length > 0
                        ? `
                        <div class="mt-2">
                            <p class="font-medium text-slate-700">Mitigation:</p>
                            <ul class="list-disc list-inside mt-1">
                                ${threat.mitigation.map((action) => `<li>${action}</li>`).join("")}
                            </ul>
                        </div>
                    `
                        : ""
                    }
                </div>
            </div>
        `,
      )
      .join("");
  }

  getThreatSeverityColor(severity) {
    const colors = {
      critical: "bg-red-100 text-red-800",
      high: "bg-red-100 text-red-800",
      medium: "bg-amber-100 text-amber-800",
      low: "bg-blue-100 text-blue-800",
      informational: "bg-slate-100 text-slate-800",
    };
    return colors[severity] || "bg-slate-100 text-slate-800";
  }

  updateAccessControlStatus(status) {
    const mfaCoverage = document.getElementById("mfa-coverage");
    const activeRoles = document.getElementById("active-roles");
    const abacPolicies = document.getElementById("abac-policies");
    const jitRequests = document.getElementById("jit-requests");

    if (mfaCoverage) {
      mfaCoverage.textContent = `${status.mfa_coverage || 0}%`;
    }
    if (activeRoles) {
      activeRoles.textContent = status.active_roles || 0;
    }
    if (abacPolicies) {
      abacPolicies.textContent = status.abac_policies || 0;
    }
    if (jitRequests) {
      jitRequests.textContent = status.pending_requests || 0;
    }
  }

  // Encryption Management
  async handleGenerateKey(event) {
    const button = event.currentTarget;
    const keyType = button.dataset.keyType;
    const collection = button.dataset.collection;
    const field = button.dataset.field;

    try {
      const response = await fetch(`${this.apiBase}/encryption/generate-key`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          type: keyType,
          collection: collection,
          field: field,
          fields: button.dataset.fields
            ? JSON.parse(button.dataset.fields)
            : [],
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(
          "Encryption key generated successfully",
          "success",
        );
        this.loadEncryptionKeys();
      } else {
        this.showNotification(
          data.error || "Failed to generate encryption key",
          "error",
        );
      }
    } catch (error) {
      console.error("Error generating encryption key:", error);
      this.showNotification("Failed to generate encryption key", "error");
    }
  }

  async handleRotateKey(event) {
    const button = event.currentTarget;
    const keyId = button.dataset.keyId;

    try {
      const response = await fetch(`${this.apiBase}/encryption/rotate-keys`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          keyIds: [keyId],
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("Key rotation completed successfully", "success");
        this.loadEncryptionKeys();
      } else {
        this.showNotification(data.error || "Failed to rotate key", "error");
      }
    } catch (error) {
      console.error("Error rotating key:", error);
      this.showNotification("Failed to rotate key", "error");
    }
  }

  async handleRevokeKey(event) {
    const button = event.currentTarget;
    const keyId = button.dataset.keyId;

    if (
      !confirm(
        "Are you sure you want to revoke this encryption key? This action cannot be undone.",
      )
    ) {
      return;
    }

    try {
      const response = await fetch(`${this.apiBase}/encryption/revoke-key`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          keyId: keyId,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("Encryption key revoked successfully", "success");
        this.loadEncryptionKeys();
      } else {
        this.showNotification(data.error || "Failed to revoke key", "error");
      }
    } catch (error) {
      console.error("Error revoking key:", error);
      this.showNotification("Failed to revoke key", "error");
    }
  }

  // Security Event Handling
  async handleSecurityEvent(event) {
    const button = event.currentTarget;
    const eventId = button.dataset.eventId;
    const actionStatus = button.dataset.actionStatus;

    try {
      const response = await fetch(`${this.apiBase}/handle-event`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          eventId: eventId,
          status: actionStatus,
          resolutionNotes:
            actionStatus === "resolved"
              ? "Event resolved by administrator"
              : null,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("Security event handled successfully", "success");
        this.loadSecurityEvents();
      } else {
        this.showNotification(
          data.error || "Failed to handle security event",
          "error",
        );
      }
    } catch (error) {
      console.error("Error handling security event:", error);
      this.showNotification("Failed to handle security event", "error");
    }
  }

  // Access Control Management
  async handleGrantPermission(event) {
    const button = event.currentTarget;
    const userId = button.dataset.userId;
    const action = button.dataset.action;
    const resource = button.dataset.resource;

    try {
      const response = await fetch(`${this.apiBase}/grant-permission`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          userId: userId,
          action: action,
          resource: resource,
          conditions: [],
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("Permission granted successfully", "success");
        this.loadAccessControl();
      } else {
        this.showNotification(
          data.error || "Failed to grant permission",
          "error",
        );
      }
    } catch (error) {
      console.error("Error granting permission:", error);
      this.showNotification("Failed to grant permission", "error");
    }
  }

  async handleRevokePermission(event) {
    const button = event.currentTarget;
    const userId = button.dataset.userId;
    const action = button.dataset.action;
    const resource = button.dataset.resource;

    try {
      const response = await fetch(`${this.apiBase}/revoke-permission`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          userId: userId,
          action: action,
          resource: resource,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("Permission revoked successfully", "success");
        this.loadAccessControl();
      } else {
        this.showNotification(
          data.error || "Failed to revoke permission",
          "error",
        );
      }
    } catch (error) {
      console.error("Error revoking permission:", error);
      this.showNotification("Failed to revoke permission", "error");
    }
  }

  // ABAC Policy Management
  async handleCreatePolicy(event) {
    const button = event.currentTarget;
    const policyData = JSON.parse(button.dataset.policyData);

    try {
      const response = await fetch(`${this.apiBase}/create-abac-policy`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(policyData),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("ABAC policy created successfully", "success");
        this.loadABACPolicies();
      } else {
        this.showNotification(
          data.error || "Failed to create ABAC policy",
          "error",
        );
      }
    } catch (error) {
      console.error("Error creating ABAC policy:", error);
      this.showNotification("Failed to create ABAC policy", "error");
    }
  }

  async handleUpdatePolicy(event) {
    const button = event.currentTarget;
    const policyId = button.dataset.policyId;
    const policyData = JSON.parse(button.dataset.policyData);

    try {
      const response = await fetch(`${this.apiBase}/update-abac-policy`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          policyId: policyId,
          ...policyData,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("ABAC policy updated successfully", "success");
        this.loadABACPolicies();
      } else {
        this.showNotification(
          data.error || "Failed to update ABAC policy",
          "error",
        );
      }
    } catch (error) {
      console.error("Error updating ABAC policy:", error);
      this.showNotification("Failed to update ABAC policy", "error");
    }
  }

  async handleDeletePolicy(event) {
    const button = event.currentTarget;
    const policyId = button.dataset.policyId;

    if (!confirm("Are you sure you want to delete this ABAC policy?")) {
      return;
    }

    try {
      const response = await fetch(`${this.apiBase}/delete-abac-policy`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          policyId: policyId,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("ABAC policy deleted successfully", "success");
        this.loadABACPolicies();
      } else {
        this.showNotification(
          data.error || "Failed to delete ABAC policy",
          "error",
        );
      }
    } catch (error) {
      console.error("Error deleting ABAC policy:", error);
      this.showNotification("Failed to delete ABAC policy", "error");
    }
  }

  // JIT Access Management
  async handleRequestJIT(event) {
    const button = event.currentTarget;
    const action = button.dataset.action;
    const resource = button.dataset.resource;
    const reason = prompt("Please provide a reason for this access request:");

    try {
      const response = await fetch(`${this.apiBase}/request-jit-access`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: action,
          resource: resource,
          reason: reason,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(
          "Access request submitted successfully",
          "success",
        );
        this.loadJITRequests();
      } else {
        this.showNotification(
          data.error || "Failed to submit access request",
          "error",
        );
      }
    } catch (error) {
      console.error("Error requesting JIT access:", error);
      this.showNotification("Failed to submit access request", "error");
    }
  }

  async handleApproveJIT(event) {
    const button = event.currentTarget;
    const requestId = button.dataset.requestId;
    const durationHours = parseInt(
      prompt("Enter duration in hours (default: 24):") || "24",
    );

    try {
      const response = await fetch(`${this.apiBase}/approve-jit-access`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          requestId: requestId,
          durationHours: durationHours,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(
          "Access request approved successfully",
          "success",
        );
        this.loadJITRequests();
      } else {
        this.showNotification(
          data.error || "Failed to approve access request",
          "error",
        );
      }
    } catch (error) {
      console.error("Error approving JIT access:", error);
      this.showNotification("Failed to approve access request", "error");
    }
  }

  async handleDenyJIT(event) {
    const button = event.currentTarget;
    const requestId = button.dataset.requestId;
    const reason = prompt("Please provide a reason for denying this request:");

    try {
      const response = await fetch(`${this.apiBase}/deny-jit-access`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          requestId: requestId,
          reason: reason,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("Access request denied successfully", "success");
        this.loadJITRequests();
      } else {
        this.showNotification(
          data.error || "Failed to deny access request",
          "error",
        );
      }
    } catch (error) {
      console.error("Error denying JIT access:", error);
      this.showNotification("Failed to deny access request", "error");
    }
  }

  // Filter and Search
  filterSecurityEvents(event) {
    const filterValue = event.target.value;
    const events = document.querySelectorAll(".security-event-item");

    events.forEach((event) => {
      const eventType = event.dataset.eventType || "";
      if (filterValue === "all" || eventType.includes(filterValue)) {
        event.style.display = "block";
      } else {
        event.style.display = "none";
      }
    });
  }

  searchSecurityEvents(event) {
    const searchTerm = event.target.value.toLowerCase();
    const events = document.querySelectorAll(".security-event-item");

    events.forEach((event) => {
      const text = event.textContent.toLowerCase();
      if (text.includes(searchTerm)) {
        event.style.display = "block";
      } else {
        event.style.display = "none";
      }
    });
  }

  // Real-time Monitoring
  startRealTimeMonitoring() {
    // Refresh security events every 30 seconds
    setInterval(() => {
      this.loadSecurityEvents();
    }, 30000);

    // Refresh threat intelligence every hour
    setInterval(() => {
      this.loadThreatIntelligence();
    }, 3600000);

    // Monitor for new security events
    this.setupEventSource();
  }

  setupEventSource() {
    if (EventSource) {
      const eventSource = new EventSource(`${this.apiBase}/events-stream`);

      eventSource.addEventListener("security-event", (event) => {
        const eventData = JSON.parse(event.data);
        this.handleRealTimeSecurityEvent(eventData);
      });

      eventSource.addEventListener("threat-update", (event) => {
        const threatData = JSON.parse(event.data);
        this.handleRealTimeThreatUpdate(threatData);
      });

      eventSource.addEventListener("error", (event) => {
        console.error("EventSource error:", event);
        // Attempt to reconnect after 5 seconds
        setTimeout(() => this.setupEventSource(), 5000);
      });
    }
  }

  handleRealTimeSecurityEvent(event) {
    // Add event to the top of the events list
    const container = document.querySelector(".security-events-list");
    if (container) {
      const eventElement = this.createSecurityEventElement(event);
      container.insertBefore(eventElement, container.firstChild);

      // Keep only the latest 50 events
      while (container.children.length > 50) {
        container.removeChild(container.lastChild);
      }
    }

    // Show notification for critical events
    if (event.severity === "critical" || event.severity === "high") {
      this.showNotification(`Security Alert: ${event.event_type}`, "error");
    }
  }

  handleRealTimeThreatUpdate(threat) {
    // Update threat intelligence display
    this.loadThreatIntelligence();

    // Show notification for new critical threats
    if (threat.severity === "critical") {
      this.showNotification(
        `Critical Threat Detected: ${threat.threat_type}`,
        "error",
      );
    }
  }

  createSecurityEventElement(event) {
    const div = document.createElement("div");
    div.className = `security-event-item ${this.getEventSeverityClass(event.severity)} animate-fade-in`;
    div.dataset.eventId = event.id;
    div.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="w-2 h-2 ${this.getEventColor(event.severity)} rounded-full mt-2"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-900">${event.event_type}</p>
                    <p class="text-xs text-slate-500">${event.source_ip} • ${this.formatDate(event.timestamp)}</p>
                    <p class="text-xs ${this.getEventTextColor(event.severity)} mt-1">${event.description}</p>
                </div>
            </div>
        `;
    return div;
  }

  // Utility Methods
  formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    if (diff < 60000) {
      // Less than 1 minute
      return "Just now";
    } else if (diff < 3600000) {
      // Less than 1 hour
      return `${Math.floor(diff / 60000)} minutes ago`;
    } else if (diff < 86400000) {
      // Less than 1 day
      return `${Math.floor(diff / 3600000)} hours ago`;
    } else {
      return date.toLocaleDateString();
    }
  }

  showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 shadow-lg ${
      type === "success"
        ? "bg-emerald-500"
        : type === "error"
          ? "bg-red-500"
          : "bg-blue-500"
    }`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.style.opacity = "0";
      notification.style.transition = "opacity 0.3s ease";
      setTimeout(() => {
        notification.remove();
      }, 300);
    }, 3000);
  }

  // Data Loading Methods
  async loadEncryptionKeys() {
    try {
      const response = await fetch(`${this.apiBase}/encryption/keys-status`);
      const data = await response.json();

      if (data.success) {
        this.updateEncryptionKeys(data.keys || []);
      }
    } catch (error) {
      console.error("Error loading encryption keys:", error);
    }
  }

  async loadSecurityEvents() {
    try {
      const response = await fetch(`${this.apiBase}/events?limit=20`);
      const data = await response.json();

      if (data.success) {
        this.updateSecurityEvents(data.events || []);
      }
    } catch (error) {
      console.error("Error loading security events:", error);
    }
  }

  async loadThreatIntelligence() {
    try {
      const response = await fetch(`${this.apiBase}/threat-intelligence`);
      const data = await response.json();

      if (data.success) {
        this.updateThreatIntelligence(data.threats || []);
      }
    } catch (error) {
      console.error("Error loading threat intelligence:", error);
    }
  }

  async loadAccessControl() {
    try {
      const response = await fetch(`${this.apiBase}/access-control/dashboard`);
      const data = await response.json();

      if (data.success) {
        this.updateAccessControlStatus(data.dashboard || {});
      }
    } catch (error) {
      console.error("Error loading access control:", error);
    }
  }

  async loadABACPolicies() {
    try {
      const response = await fetch(`${this.apiBase}/abac-policies`);
      const data = await response.json();

      if (data.success) {
        this.updateABACPolicies(data.policies || []);
      }
    } catch (error) {
      console.error("Error loading ABAC policies:", error);
    }
  }

  async loadJITRequests() {
    try {
      const response = await fetch(`${this.apiBase}/jit-requests`);
      const data = await response.json();

      if (data.success) {
        this.updateJITRequests(data.requests || []);
      }
    } catch (error) {
      console.error("Error loading JIT requests:", error);
    }
  }
}

// Initialize Security Management when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  const securityManagement = new SecurityManagement();
});
