/**
 * Schema Builder Configuration
 *
 * This file contains configuration settings for the enhanced schema builder
 */

const SchemaBuilderConfig = {
  // Field type configurations
  fieldTypes: {
    string: {
      name: "String",
      icon: "type",
      color: "#3b82f6",
      description: "Text data",
      default: "",
      constraints: {
        minLength: 0,
        maxLength: 255,
        pattern: "",
      },
    },
    int: {
      name: "Integer",
      icon: "hash",
      color: "#10b981",
      description: "Whole numbers",
      default: 0,
      constraints: {
        min: null,
        max: null,
      },
    },
    float: {
      name: "Float",
      icon: "percent",
      color: "#06b6d4",
      description: "Decimal numbers",
      default: 0.0,
      constraints: {
        min: null,
        max: null,
        precision: 2,
      },
    },
    boolean: {
      name: "Boolean",
      icon: "toggle-left",
      color: "#f59e0b",
      description: "True/False values",
      default: false,
      constraints: {},
    },
    date: {
      name: "Date",
      icon: "calendar",
      color: "#ef4444",
      description: "Date only",
      default: null,
      constraints: {
        format: "Y-m-d",
        autoValue: "",
      },
    },
    datetime: {
      name: "DateTime",
      icon: "clock",
      color: "#f97316",
      description: "Date and time",
      default: null,
      constraints: {
        format: "Y-m-d H:i:s",
        autoValue: "",
      },
    },
    enum: {
      name: "Enum",
      icon: "list",
      color: "#ec4899",
      description: "Predefined values",
      default: "",
      constraints: {
        values: [],
      },
    },
    array: {
      name: "Array",
      icon: "brackets",
      color: "#06b6d4",
      description: "List of items",
      default: [],
      constraints: {
        itemsType: "string",
        minItems: 0,
        maxItems: null,
      },
    },
    object: {
      name: "Object",
      icon: "braces",
      color: "#8b5cf6",
      description: "Nested document",
      default: {},
      constraints: {
        nestedFields: [],
      },
    },
    relation: {
      name: "Relation",
      icon: "link",
      color: "#10b981",
      description: "Reference to another collection",
      default: null,
      constraints: {
        reference: "",
        onDelete: "restrict",
        populateAlias: "",
      },
    },
    json: {
      name: "JSON",
      icon: "code",
      color: "#6366f1",
      description: "JSON data",
      default: {},
      constraints: {},
    },
    uuid: {
      name: "UUID",
      icon: "fingerprint",
      color: "#a855f7",
      description: "Unique identifier",
      default: "",
      constraints: {
        version: "4",
      },
    },
    binary: {
      name: "Binary",
      icon: "file",
      color: "#64748b",
      description: "Binary data",
      default: null,
      constraints: {
        maxSize: null,
      },
    },
    geojson: {
      name: "GeoJSON",
      icon: "map-pin",
      color: "#059669",
      description: "Geographic data",
      default: null,
      constraints: {
        type: "Point",
      },
    },
  },

  // Validation rule configurations
  validationRules: {
    required: {
      name: "Required",
      description: "Field must have a value",
      type: "boolean",
      default: false,
    },
    unique: {
      name: "Unique",
      description: "Field value must be unique",
      type: "boolean",
      default: false,
    },
    indexed: {
      name: "Indexed",
      description: "Create index for this field",
      type: "boolean",
      default: false,
    },
    searchable: {
      name: "Searchable",
      description: "Field can be searched",
      type: "boolean",
      default: false,
    },
    encrypted: {
      name: "Encrypted",
      description: "Field data is encrypted",
      type: "boolean",
      default: false,
    },
    min: {
      name: "Minimum",
      description: "Minimum value for numeric fields",
      type: "number",
      default: null,
    },
    max: {
      name: "Maximum",
      description: "Maximum value for numeric fields",
      type: "number",
      default: null,
    },
    minLength: {
      name: "Min Length",
      description: "Minimum length for string fields",
      type: "number",
      default: 0,
    },
    maxLength: {
      name: "Max Length",
      description: "Maximum length for string fields",
      type: "number",
      default: 255,
    },
    pattern: {
      name: "Pattern",
      description: "Regex pattern for validation",
      type: "string",
      default: "",
    },
    custom: {
      name: "Custom Validation",
      description: "Custom validation function",
      type: "string",
      default: "",
    },
    default: {
      name: "Default Value",
      description: "Default value when not provided",
      type: "any",
      default: null,
    },
  },

  // Index configurations
  indexTypes: {
    single: {
      name: "Single Field Index",
      description: "Index on a single field",
      fields: 1,
    },
    compound: {
      name: "Compound Index",
      description: "Index on multiple fields",
      fields: 2,
    },
    text: {
      name: "Text Index",
      description: "Full-text search index",
      fields: 1,
    },
    unique: {
      name: "Unique Index",
      description: "Unique constraint on field(s)",
      fields: 1,
    },
  },

  // Relationship configurations
  relationshipTypes: {
    "one-to-one": {
      name: "One to One",
      description: "One record relates to one record",
      cardinality: "1:1",
    },
    "one-to-many": {
      name: "One to Many",
      description: "One record relates to many records",
      cardinality: "1:N",
    },
    "many-to-many": {
      name: "Many to Many",
      description: "Many records relate to many records",
      cardinality: "M:N",
    },
  },

  // Delete actions for relationships
  deleteActions: {
    restrict: {
      name: "Restrict",
      description: "Prevent deletion if related records exist",
    },
    cascade: {
      name: "Cascade",
      description: "Delete related records when referenced record is deleted",
    },
    set_null: {
      name: "Set Null",
      description: "Set foreign key to null when referenced record is deleted",
    },
    no_action: {
      name: "No Action",
      description: "No action on deletion",
    },
  },

  // Date formats
  dateFormats: {
    "Y-m-d": "YYYY-MM-DD",
    "Y-m-d H:i:s": "YYYY-MM-DD HH:MM:SS",
    c: "ISO 8601",
    U: "Unix Timestamp",
  },

  // Schema templates
  templates: {
    user: {
      name: "User Collection",
      description: "Standard user collection template",
      fields: [
        { name: "_id", type: "uuid", required: true, description: "User ID" },
        {
          name: "username",
          type: "string",
          required: true,
          unique: true,
          description: "Username",
        },
        {
          name: "email",
          type: "string",
          required: true,
          unique: true,
          description: "Email address",
        },
        {
          name: "password",
          type: "string",
          required: true,
          description: "Password hash",
        },
        { name: "first_name", type: "string", description: "First name" },
        { name: "last_name", type: "string", description: "Last name" },
        {
          name: "role",
          type: "enum",
          required: true,
          description: "User role",
          constraints: { values: ["admin", "user", "moderator"] },
        },
        {
          name: "status",
          type: "enum",
          required: true,
          description: "Account status",
          constraints: { values: ["active", "inactive", "suspended"] },
        },
        {
          name: "profile_id",
          type: "relation",
          description: "User profile",
          constraints: { reference: "profiles", onDelete: "cascade" },
        },
        {
          name: "created_at",
          type: "datetime",
          required: true,
          description: "Creation timestamp",
          constraints: { autoValue: "now" },
        },
        {
          name: "updated_at",
          type: "datetime",
          required: true,
          description: "Update timestamp",
          constraints: { autoValue: "update" },
        },
      ],
      indexes: [
        { name: "idx_username", fields: ["username"], unique: true },
        { name: "idx_email", fields: ["email"], unique: true },
        { name: "idx_role", fields: ["role"] },
        { name: "idx_status", fields: ["status"] },
      ],
    },
    product: {
      name: "Product Collection",
      description: "E-commerce product template",
      fields: [
        {
          name: "_id",
          type: "uuid",
          required: true,
          description: "Product ID",
        },
        {
          name: "name",
          type: "string",
          required: true,
          description: "Product name",
        },
        {
          name: "description",
          type: "string",
          description: "Product description",
        },
        {
          name: "price",
          type: "float",
          required: true,
          description: "Product price",
        },
        {
          name: "currency",
          type: "string",
          required: true,
          default: "USD",
          description: "Currency code",
        },
        {
          name: "sku",
          type: "string",
          required: true,
          unique: true,
          description: "Stock keeping unit",
        },
        {
          name: "category_id",
          type: "relation",
          description: "Product category",
          constraints: { reference: "categories", onDelete: "set_null" },
        },
        {
          name: "brand_id",
          type: "relation",
          description: "Product brand",
          constraints: { reference: "brands", onDelete: "set_null" },
        },
        {
          name: "tags",
          type: "array",
          description: "Product tags",
          constraints: { itemsType: "string" },
        },
        {
          name: "images",
          type: "array",
          description: "Product images",
          constraints: { itemsType: "object" },
        },
        {
          name: "inventory",
          type: "object",
          description: "Inventory information",
          constraints: {
            nestedFields: [
              { name: "quantity", type: "int" },
              { name: "available", type: "boolean" },
            ],
          },
        },
        {
          name: "status",
          type: "enum",
          required: true,
          description: "Product status",
          constraints: { values: ["active", "inactive", "discontinued"] },
        },
        {
          name: "created_at",
          type: "datetime",
          required: true,
          description: "Creation timestamp",
          constraints: { autoValue: "now" },
        },
        {
          name: "updated_at",
          type: "datetime",
          required: true,
          description: "Update timestamp",
          constraints: { autoValue: "update" },
        },
      ],
      indexes: [
        { name: "idx_name", fields: ["name"] },
        { name: "idx_sku", fields: ["sku"], unique: true },
        { name: "idx_category", fields: ["category_id"] },
        { name: "idx_price", fields: ["price"] },
        { name: "idx_status", fields: ["status"] },
      ],
    },
    order: {
      name: "Order Collection",
      description: "E-commerce order template",
      fields: [
        { name: "_id", type: "uuid", required: true, description: "Order ID" },
        {
          name: "order_number",
          type: "string",
          required: true,
          unique: true,
          description: "Order number",
        },
        {
          name: "user_id",
          type: "relation",
          required: true,
          description: "Order user",
          constraints: { reference: "users", onDelete: "cascade" },
        },
        {
          name: "customer_id",
          type: "relation",
          description: "Customer information",
          constraints: { reference: "customers", onDelete: "set_null" },
        },
        {
          name: "items",
          type: "array",
          required: true,
          description: "Order items",
          constraints: { itemsType: "object" },
        },
        {
          name: "subtotal",
          type: "float",
          required: true,
          description: "Order subtotal",
        },
        {
          name: "tax",
          type: "float",
          required: true,
          description: "Order tax amount",
        },
        {
          name: "shipping",
          type: "float",
          required: true,
          description: "Shipping cost",
        },
        {
          name: "total",
          type: "float",
          required: true,
          description: "Order total",
        },
        {
          name: "currency",
          type: "string",
          required: true,
          default: "USD",
          description: "Currency code",
        },
        {
          name: "status",
          type: "enum",
          required: true,
          description: "Order status",
          constraints: {
            values: [
              "pending",
              "processing",
              "shipped",
              "delivered",
              "cancelled",
            ],
          },
        },
        {
          name: "payment_status",
          type: "enum",
          required: true,
          description: "Payment status",
          constraints: { values: ["pending", "paid", "failed", "refunded"] },
        },
        {
          name: "shipping_address",
          type: "object",
          description: "Shipping address",
          constraints: {
            nestedFields: [
              { name: "street", type: "string" },
              { name: "city", type: "string" },
              { name: "state", type: "string" },
              { name: "zip", type: "string" },
              { name: "country", type: "string" },
            ],
          },
        },
        {
          name: "billing_address",
          type: "object",
          description: "Billing address",
          constraints: {
            nestedFields: [
              { name: "street", type: "string" },
              { name: "city", type: "string" },
              { name: "state", type: "string" },
              { name: "zip", type: "string" },
              { name: "country", type: "string" },
            ],
          },
        },
        {
          name: "created_at",
          type: "datetime",
          required: true,
          description: "Creation timestamp",
          constraints: { autoValue: "now" },
        },
        {
          name: "updated_at",
          type: "datetime",
          required: true,
          description: "Update timestamp",
          constraints: { autoValue: "update" },
        },
      ],
      indexes: [
        { name: "idx_order_number", fields: ["order_number"], unique: true },
        { name: "idx_user", fields: ["user_id"] },
        { name: "idx_status", fields: ["status"] },
        { name: "idx_payment_status", fields: ["payment_status"] },
        { name: "idx_created_at", fields: ["created_at"] },
      ],
    },
  },

  // Default settings
  defaults: {
    canvas: {
      width: 1200,
      height: 600,
      backgroundColor: "rgba(15, 23, 42, 0.3)",
      grid: true,
      gridColor: "rgba(255, 255, 255, 0.05)",
      snapToGrid: true,
      gridSize: 20,
    },
    field: {
      width: 200,
      height: 100,
      padding: 12,
      borderRadius: 8,
      borderColor: "rgba(255, 255, 255, 0.1)",
      backgroundColor: "rgba(51, 65, 85, 0.8)",
    },
    connection: {
      color: "linear-gradient(90deg, #8b5cf6, #3b82f6)",
      width: 2,
      style: "solid",
      arrowSize: 8,
    },
    animation: {
      duration: 200,
      easing: "ease",
    },
  },

  // Performance settings
  performance: {
    maxFields: 1000,
    maxConnections: 500,
    renderThrottle: 16, // 60 FPS
    virtualScrolling: true,
    lazyLoading: true,
    cacheSize: 100,
  },

  // Accessibility settings
  accessibility: {
    keyboardNavigation: true,
    screenReader: true,
    highContrast: false,
    reducedMotion: false,
  },

  // Export formats
  exportFormats: {
    json: {
      name: "JSON",
      extension: ".json",
      mimeType: "application/json",
      description: "JavaScript Object Notation",
    },
    yaml: {
      name: "YAML",
      extension: ".yaml",
      mimeType: "application/x-yaml",
      description: "YAML Ain't Markup Language",
    },
    sql: {
      name: "SQL",
      extension: ".sql",
      mimeType: "application/sql",
      description: "Structured Query Language",
    },
    php: {
      name: "PHP",
      extension: ".php",
      mimeType: "application/x-php",
      description: "PHP Array Definition",
    },
  },

  // Import formats
  importFormats: {
    json: {
      name: "JSON",
      extension: ".json",
      mimeType: "application/json",
      description: "JavaScript Object Notation",
    },
    yaml: {
      name: "YAML",
      extension: ".yaml",
      mimeType: "application/x-yaml",
      description: "YAML Ain't Markup Language",
    },
    sql: {
      name: "SQL",
      extension: ".sql",
      mimeType: "application/sql",
      description: "Structured Query Language",
    },
  },

  // API endpoints
  api: {
    schemas: "/api/schemas",
    collections: "/api/collections",
    validation: "/api/validation",
    export: "/api/export",
    import: "/api/import",
    templates: "/api/templates",
  },

  // Error messages
  errors: {
    invalidField: "Invalid field configuration",
    duplicateField: "Field with this name already exists",
    invalidType: "Invalid field type",
    invalidReference: "Invalid reference field",
    invalidCollection: "Invalid collection reference",
    schemaTooLarge: "Schema exceeds maximum size limit",
    invalidFormat: "Invalid format for import",
    networkError: "Network error occurred",
    unknownError: "Unknown error occurred",
  },

  // Success messages
  messages: {
    fieldAdded: "Field added successfully",
    fieldUpdated: "Field updated successfully",
    fieldDeleted: "Field deleted successfully",
    schemaSaved: "Schema saved successfully",
    schemaExported: "Schema exported successfully",
    schemaImported: "Schema imported successfully",
    validationPassed: "Schema validation passed",
    validationFailed: "Schema validation failed",
  },
};

// Export configuration
if (typeof module !== "undefined" && module.exports) {
  module.exports = SchemaBuilderConfig;
}

// Make it available globally
if (typeof window !== "undefined") {
  window.SchemaBuilderConfig = SchemaBuilderConfig;
}
