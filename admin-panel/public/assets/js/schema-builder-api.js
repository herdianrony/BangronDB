/**
 * Schema Builder API Service
 *
 * This file handles API communication for the schema builder
 */

class SchemaBuilderAPI {
  constructor() {
    this.baseURL = "/api";
    this.defaultHeaders = {
      "Content-Type": "application/json",
      Accept: "application/json",
    };
  }

  // Generic API request method
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const config = {
      headers: { ...this.defaultHeaders, ...(options.headers || {}) },
      ...options,
    };

    try {
      const response = await fetch(url, config);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("API request failed:", error);
      throw error;
    }
  }

  // Schema operations
  async getSchemas(database = null) {
    const endpoint = database ? `/schemas?database=${database}` : "/schemas";
    return this.request(endpoint);
  }

  async getSchema(collection, database = null) {
    const endpoint = database
      ? `/schemas/${collection}?database=${database}`
      : `/schemas/${collection}`;
    return this.request(endpoint);
  }

  async createSchema(schemaData) {
    return this.request("/schemas", {
      method: "POST",
      body: JSON.stringify(schemaData),
    });
  }

  async updateSchema(collection, schemaData, database = null) {
    const endpoint = database
      ? `/schemas/${collection}?database=${database}`
      : `/schemas/${collection}`;
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(schemaData),
    });
  }

  async deleteSchema(collection, database = null) {
    const endpoint = database
      ? `/schemas/${collection}?database=${database}`
      : `/schemas/${collection}`;
    return this.request(endpoint, {
      method: "DELETE",
    });
  }

  async validateSchema(schemaData) {
    return this.request("/schemas/validate", {
      method: "POST",
      body: JSON.stringify(schemaData),
    });
  }

  // Collection operations
  async getCollections(database = null) {
    const endpoint = database
      ? `/collections?database=${database}`
      : "/collections";
    return this.request(endpoint);
  }

  async getCollection(collection, database = null) {
    const endpoint = database
      ? `/collections/${collection}?database=${database}`
      : `/collections/${collection}`;
    return this.request(endpoint);
  }

  async createCollection(collectionData) {
    return this.request("/collections", {
      method: "POST",
      body: JSON.stringify(collectionData),
    });
  }

  async updateCollection(collection, collectionData, database = null) {
    const endpoint = database
      ? `/collections/${collection}?database=${database}`
      : `/collections/${collection}`;
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(collectionData),
    });
  }

  async deleteCollection(collection, database = null) {
    const endpoint = database
      ? `/collections/${collection}?database=${database}`
      : `/collections/${collection}`;
    return this.request(endpoint, {
      method: "DELETE",
    });
  }

  // Field operations
  async addField(collection, fieldData, database = null) {
    const endpoint = database
      ? `/collections/${collection}/fields?database=${database}`
      : `/collections/${collection}/fields`;
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(fieldData),
    });
  }

  async updateField(collection, fieldName, fieldData, database = null) {
    const endpoint = database
      ? `/collections/${collection}/fields/${fieldName}?database=${database}`
      : `/collections/${collection}/fields/${fieldName}`;
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(fieldData),
    });
  }

  async deleteField(collection, fieldName, database = null) {
    const endpoint = database
      ? `/collections/${collection}/fields/${fieldName}?database=${database}`
      : `/collections/${collection}/fields/${fieldName}`;
    return this.request(endpoint, {
      method: "DELETE",
    });
  }

  // Index operations
  async createIndex(collection, indexData, database = null) {
    const endpoint = database
      ? `/collections/${collection}/indexes?database=${database}`
      : `/collections/${collection}/indexes`;
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(indexData),
    });
  }

  async getIndex(collection, indexName, database = null) {
    const endpoint = database
      ? `/collections/${collection}/indexes/${indexName}?database=${database}`
      : `/collections/${collection}/indexes/${indexName}`;
    return this.request(endpoint);
  }

  async updateIndex(collection, indexName, indexData, database = null) {
    const endpoint = database
      ? `/collections/${collection}/indexes/${indexName}?database=${database}`
      : `/collections/${collection}/indexes/${indexName}`;
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(indexData),
    });
  }

  async deleteIndex(collection, indexName, database = null) {
    const endpoint = database
      ? `/collections/${collection}/indexes/${indexName}?database=${database}`
      : `/collections/${collection}/indexes/${indexName}`;
    return this.request(endpoint, {
      method: "DELETE",
    });
  }

  async getIndexes(collection, database = null) {
    const endpoint = database
      ? `/collections/${collection}/indexes?database=${database}`
      : `/collections/${collection}/indexes`;
    return this.request(endpoint);
  }

  // Relationship operations
  async createRelationship(collection, relationshipData, database = null) {
    const endpoint = database
      ? `/collections/${collection}/relationships?database=${database}`
      : `/collections/${collection}/relationships`;
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(relationshipData),
    });
  }

  async getRelationships(collection, database = null) {
    const endpoint = database
      ? `/collections/${collection}/relationships?database=${database}`
      : `/collections/${collection}/relationships`;
    return this.request(endpoint);
  }

  async deleteRelationship(collection, relationshipId, database = null) {
    const endpoint = database
      ? `/collections/${collection}/relationships/${relationshipId}?database=${database}`
      : `/collections/${collection}/relationships/${relationshipId}`;
    return this.request(endpoint, {
      method: "DELETE",
    });
  }

  // Export/Import operations
  async exportSchema(collection, format = "json", database = null) {
    const endpoint = database
      ? `/schemas/${collection}/export?format=${format}&database=${database}`
      : `/schemas/${collection}/export?format=${format}`;
    return this.request(endpoint);
  }

  async importSchema(schemaData, format = "json") {
    return this.request("/schemas/import", {
      method: "POST",
      headers: {
        "Content-Type": `application/${format}`,
      },
      body: JSON.stringify(schemaData),
    });
  }

  // Template operations
  async getTemplates() {
    return this.request("/templates");
  }

  async getTemplate(templateName) {
    return this.request(`/templates/${templateName}`);
  }

  async applyTemplate(collection, templateName, database = null) {
    const endpoint = database
      ? `/collections/${collection}/templates/${templateName}?database=${database}`
      : `/collections/${collection}/templates/${templateName}`;
    return this.request(endpoint, {
      method: "POST",
    });
  }

  // Schema versioning
  async getSchemaVersions(collection, database = null) {
    const endpoint = database
      ? `/schemas/${collection}/versions?database=${database}`
      : `/schemas/${collection}/versions`;
    return this.request(endpoint);
  }

  async createSchemaVersion(collection, versionData, database = null) {
    const endpoint = database
      ? `/schemas/${collection}/versions?database=${database}`
      : `/schemas/${collection}/versions`;
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(versionData),
    });
  }

  async getSchemaVersion(collection, version, database = null) {
    const endpoint = database
      ? `/schemas/${collection}/versions/${version}?database=${database}`
      : `/schemas/${collection}/versions/${version}`;
    return this.request(endpoint);
  }

  async restoreSchemaVersion(collection, version, database = null) {
    const endpoint = database
      ? `/schemas/${collection}/versions/${version}/restore?database=${database}`
      : `/schemas/${collection}/versions/${version}/restore`;
    return this.request(endpoint, {
      method: "POST",
    });
  }

  // Schema comparison
  async compareSchemas(schema1, schema2) {
    return this.request("/schemas/compare", {
      method: "POST",
      body: JSON.stringify({ schema1, schema2 }),
    });
  }

  async generateMigration(fromSchema, toSchema) {
    return this.request("/schemas/migration", {
      method: "POST",
      body: JSON.stringify({ from: fromSchema, to: toSchema }),
    });
  }

  // Schema analytics
  async getSchemaStats(collection, database = null) {
    const endpoint = database
      ? `/schemas/${collection}/stats?database=${database}`
      : `/schemas/${collection}/stats`;
    return this.request(endpoint);
  }

  async getSchemaPerformance(collection, database = null) {
    const endpoint = database
      ? `/schemas/${collection}/performance?database=${database}`
      : `/schemas/${collection}/performance`;
    return this.request(endpoint);
  }

  // Search operations
  async searchSchemas(query) {
    return this.request(`/schemas/search?q=${encodeURIComponent(query)}`);
  }

  async searchCollections(query, database = null) {
    const endpoint = database
      ? `/collections/search?q=${encodeURIComponent(query)}&database=${database}`
      : `/collections/search?q=${encodeURIComponent(query)}`;
    return this.request(endpoint);
  }

  // Batch operations
  async batchCreateSchemas(schemas) {
    return this.request("/schemas/batch", {
      method: "POST",
      body: JSON.stringify({ schemas }),
    });
  }

  async batchUpdateSchemas(updates) {
    return this.request("/schemas/batch", {
      method: "PUT",
      body: JSON.stringify({ updates }),
    });
  }

  async batchDeleteSchemas(collections) {
    return this.request("/schemas/batch", {
      method: "DELETE",
      body: JSON.stringify({ collections }),
    });
  }

  // Health check
  async healthCheck() {
    return this.request("/health");
  }

  // Ping
  async ping() {
    return this.request("/ping");
  }
}

// Export API service
if (typeof module !== "undefined" && module.exports) {
  module.exports = SchemaBuilderAPI;
}

// Make it available globally
if (typeof window !== "undefined") {
  window.SchemaBuilderAPI = SchemaBuilderAPI;

  // Create a singleton instance
  window.schemaBuilderAPI = new SchemaBuilderAPI();
}
