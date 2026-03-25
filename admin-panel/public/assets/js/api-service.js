/**
 * BangronDB API Service
 * Handles all API communications for the dashboard
 */

class APIService {
  constructor() {
    this.config = window.dashboardConfig;
    this.baseURL = this.config.get("api.baseUrl");
    this.timeout = this.config.get("api.timeout");
    this.retryAttempts = this.config.get("api.retryAttempts");
    this.retryDelay = this.config.get("api.retryDelay");
    this.cache = new Map();
    this.pendingRequests = new Map();
    this.rateLimitQueue = [];
    this.rateLimitResetTime = null;
  }

  // Generic HTTP request method with retry logic
  async request(endpoint, options = {}, retryCount = 0) {
    const requestKey = `${endpoint}-${JSON.stringify(options)}`;

    // Check rate limiting
    if (this.isRateLimited()) {
      await this.waitForRateLimit();
    }

    // Check cache for GET requests
    if (options.method === "GET" && this.config.get("cache.enabled")) {
      const cachedData = this.getFromCache(endpoint);
      if (cachedData) {
        return cachedData;
      }
    }

    // Check for duplicate requests
    if (this.pendingRequests.has(requestKey)) {
      return this.pendingRequests.get(requestKey);
    }

    const requestPromise = this.makeRequest(endpoint, options, retryCount);
    this.pendingRequests.set(requestKey, requestPromise);

    try {
      const result = await requestPromise;

      // Cache successful GET requests
      if (options.method === "GET" && this.config.get("cache.enabled")) {
        this.setToCache(endpoint, result);
      }

      return result;
    } catch (error) {
      throw error;
    } finally {
      this.pendingRequests.delete(requestKey);
    }
  }

  async makeRequest(endpoint, options, retryCount) {
    const url = this.getFullUrl(endpoint);
    const apiOptions = this.config.getApiOptions(options.method, options.body);

    // Add authentication headers
    this.addAuthHeaders(apiOptions);

    try {
      const response = await fetch(url, {
        ...apiOptions,
        signal: AbortSignal.timeout(this.timeout),
      });

      if (!response.ok) {
        if (response.status === 429 && retryCount < this.retryAttempts) {
          // Rate limited, retry after delay
          await this.delay(this.retryDelay * (retryCount + 1));
          return this.makeRequest(endpoint, options, retryCount + 1);
        }

        throw new APIError(
          response.status,
          response.statusText,
          await this.parseErrorResponse(response),
        );
      }

      const data = await response.json();

      // Track analytics for successful requests
      this.config.trackEvent("api_request", {
        endpoint,
        method: options.method,
        status: response.status,
      });

      return data;
    } catch (error) {
      if (error.name === "AbortError") {
        throw new APIError("TIMEOUT", "Request timeout");
      }

      if (error instanceof APIError) {
        throw error;
      }

      throw new APIError("NETWORK", "Network error");
    }
  }

  // Dashboard-specific API methods
  async getDashboardData() {
    const endpoint = this.config.get("api.endpoints.dashboard");
    return this.request(endpoint, { method: "GET" });
  }

  async getDatabases() {
    const endpoint = this.config.get("api.endpoints.databases");
    return this.request(endpoint, { method: "GET" });
  }

  async getDatabase(id) {
    const endpoint = `${this.config.get("api.endpoints.databases")}/${id}`;
    return this.request(endpoint, { method: "GET" });
  }

  async getCollections(databaseId = null) {
    let endpoint = this.config.get("api.endpoints.collections");
    if (databaseId) {
      endpoint += `?database=${databaseId}`;
    }
    return this.request(endpoint, { method: "GET" });
  }

  async getCollection(databaseId, collectionId) {
    const endpoint = `${this.config.get("api.endpoints.collections")}/${databaseId}/${collectionId}`;
    return this.request(endpoint, { method: "GET" });
  }

  async getUsers() {
    const endpoint = this.config.get("api.endpoints.users");
    return this.request(endpoint, { method: "GET" });
  }

  async getUser(id) {
    const endpoint = `${this.config.get("api.endpoints.users")}/${id}`;
    return this.request(endpoint, { method: "GET" });
  }

  async getAuditLogs(params = {}) {
    let endpoint = this.config.get("api.endpoints.audit");
    const queryParams = new URLSearchParams(params);
    if (queryParams.toString()) {
      endpoint += `?${queryParams.toString()}`;
    }
    return this.request(endpoint, { method: "GET" });
  }

  async getSystemMetrics() {
    const endpoint = this.config.get("api.endpoints.system");
    return this.request(endpoint, { method: "GET" });
  }

  async getPerformanceMetrics(timeRange = "24h") {
    const endpoint = `${this.config.get("api.endpoints.metrics")}/performance`;
    const params = new URLSearchParams({ timeRange });
    return this.request(`${endpoint}?${params}`, { method: "GET" });
  }

  async getStorageMetrics() {
    const endpoint = `${this.config.get("api.endpoints.metrics")}/storage`;
    return this.request(endpoint, { method: "GET" });
  }

  async getUserActivity(timeRange = "24h") {
    const endpoint = `${this.config.get("api.endpoints.metrics")}/user-activity`;
    const params = new URLSearchParams({ timeRange });
    return this.request(`${endpoint}?${params}`, { method: "GET" });
  }

  async search(query, type = "all", limit = 10) {
    const endpoint = this.config.get("api.endpoints.search");
    const params = new URLSearchParams({ query, type, limit });
    return this.request(`${endpoint}?${params}`, { method: "GET" });
  }

  // Data modification methods
  async createDatabase(data) {
    const endpoint = this.config.get("api.endpoints.databases");
    return this.request(endpoint, {
      method: "POST",
      body: data,
    });
  }

  async updateDatabase(id, data) {
    const endpoint = `${this.config.get("api.endpoints.databases")}/${id}`;
    return this.request(endpoint, {
      method: "PUT",
      body: data,
    });
  }

  async deleteDatabase(id) {
    const endpoint = `${this.config.get("api.endpoints.databases")}/${id}`;
    return this.request(endpoint, { method: "DELETE" });
  }

  async createCollection(databaseId, data) {
    const endpoint = `${this.config.get("api.endpoints.collections")}/${databaseId}`;
    return this.request(endpoint, {
      method: "POST",
      body: data,
    });
  }

  async updateCollection(databaseId, collectionId, data) {
    const endpoint = `${this.config.get("api.endpoints.collections")}/${databaseId}/${collectionId}`;
    return this.request(endpoint, {
      method: "PUT",
      body: data,
    });
  }

  async deleteCollection(databaseId, collectionId) {
    const endpoint = `${this.config.get("api.endpoints.collections")}/${databaseId}/${collectionId}`;
    return this.request(endpoint, { method: "DELETE" });
  }

  async createUser(data) {
    const endpoint = this.config.get("api.endpoints.users");
    return this.request(endpoint, {
      method: "POST",
      body: data,
    });
  }

  async updateUser(id, data) {
    const endpoint = `${this.config.get("api.endpoints.users")}/${id}`;
    return this.request(endpoint, {
      method: "PUT",
      body: data,
    });
  }

  async deleteUser(id) {
    const endpoint = `${this.config.get("api.endpoints.users")}/${id}`;
    return this.request(endpoint, { method: "DELETE" });
  }

  // Export functionality
  async exportData(type, format = "json", params = {}) {
    const endpoint = `${this.config.get("api.endpoints.metrics")}/export`;
    const queryParams = new URLSearchParams({ type, format, ...params });
    return this.request(`${endpoint}?${queryParams}`, { method: "GET" });
  }

  // Real-time data methods
  async subscribeToRealTimeUpdates(callback) {
    if (!this.config.get("realTime.enabled")) {
      console.warn("Real-time updates are disabled");
      return;
    }

    const endpoint = `${this.config.get("api.endpoints.system")}/realtime`;

    try {
      const response = await fetch(this.getFullUrl(endpoint), {
        headers: {
          Accept: "text/event-stream",
          "Cache-Control": "no-cache",
        },
      });

      if (!response.ok) {
        throw new APIError(
          response.status,
          "Failed to establish real-time connection",
        );
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();

      while (true) {
        const { done, value } = await reader.read();

        if (done) break;

        const chunk = decoder.decode(value);
        const lines = chunk.split("\n");

        for (const line of lines) {
          if (line.startsWith("data: ")) {
            try {
              const data = JSON.parse(line.slice(6));
              callback(data);
            } catch (error) {
              console.error("Failed to parse real-time data:", error);
            }
          }
        }
      }
    } catch (error) {
      console.error("Real-time connection error:", error);
      // Attempt to reconnect
      setTimeout(
        () => this.subscribeToRealTimeUpdates(callback),
        this.config.get("realTime.reconnectDelay"),
      );
    }
  }

  // Utility methods
  getFullUrl(endpoint) {
    return `${this.baseURL}${endpoint}`;
  }

  addAuthHeaders(options) {
    const token = localStorage.getItem("auth_token");
    if (token) {
      options.headers["Authorization"] = `Bearer ${token}`;
    }
  }

  getFromCache(endpoint) {
    const cacheKey = this.config.getCacheKey(endpoint);
    const cached = this.cache.get(cacheKey);

    if (
      cached &&
      Date.now() - cached.timestamp < this.config.getCacheTTL(endpoint)
    ) {
      return cached.data;
    }

    return null;
  }

  setToCache(endpoint, data) {
    const cacheKey = this.config.getCacheKey(endpoint);
    this.cache.set(cacheKey, {
      data,
      timestamp: Date.now(),
    });

    // Limit cache size
    if (this.cache.size > 100) {
      const firstKey = this.cache.keys().next().value;
      this.cache.delete(firstKey);
    }
  }

  isRateLimited() {
    if (!this.config.get("security.rateLimit.enabled")) {
      return false;
    }

    const now = Date.now();
    if (this.rateLimitResetTime && now < this.rateLimitResetTime) {
      return true;
    }

    return false;
  }

  async waitForRateLimit() {
    if (!this.rateLimitResetTime) {
      return;
    }

    const delay = this.rateLimitResetTime - Date.now();
    if (delay > 0) {
      await this.delay(delay);
    }
  }

  async delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  async parseErrorResponse(response) {
    try {
      return await response.json();
    } catch {
      return { message: response.statusText };
    }
  }

  // Batch operations
  async batchRequests(requests) {
    const promises = requests.map((request) => {
      return this.request(request.endpoint, request.options).catch((error) => ({
        error: error.message,
        endpoint: request.endpoint,
      }));
    });

    return Promise.all(promises);
  }

  // Health check
  async healthCheck() {
    const endpoint = `${this.config.get("api.endpoints.system")}/health`;
    return this.request(endpoint, { method: "GET" });
  }

  // Clear cache
  clearCache() {
    this.cache.clear();
  }

  // Get API statistics
  getApiStats() {
    return {
      cacheSize: this.cache.size,
      pendingRequests: this.pendingRequests.size,
      rateLimited: this.isRateLimited(),
      lastRequestTime: this.lastRequestTime,
    };
  }
}

// API Error class
class APIError extends Error {
  constructor(status, message, details = null) {
    super(message);
    this.name = "APIError";
    this.status = status;
    this.details = details;
    this.timestamp = new Date().toISOString();
  }

  toJSON() {
    return {
      name: this.name,
      status: this.status,
      message: this.message,
      details: this.details,
      timestamp: this.timestamp,
    };
  }
}

// Global API service instance
window.apiService = new APIService();

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = { APIService, APIError };
}
