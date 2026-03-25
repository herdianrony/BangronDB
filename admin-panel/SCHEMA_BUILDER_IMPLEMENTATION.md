# Schema Builder Implementation

## Overview

The Schema Builder is a powerful visual tool for designing and managing database schemas in the BangronDB Admin Panel. It provides an intuitive drag-and-drop interface for creating, modifying, and validating database collections with comprehensive field types, relationships, and constraints.

## Features

### Core Features

- **Visual Schema Design**: Canvas-based schema designer with drag-and-drop functionality
- **Comprehensive Field Management**: Support for various field types with extensive customization
- **Relationship Mapping**: Visual representation of relationships between collections
- **Validation Rules**: Powerful validation system with custom rules and constraints
- **Index Management**: Automatic and manual index creation for performance optimization
- **Schema Versioning**: Track and manage schema changes with version history
- **Advanced Features**: Templates, inheritance, conditional fields, and more

### Field Types Support

- **Basic Types**: String, Number, Boolean, Date, DateTime
- **Advanced Types**: Object, Array, Reference, Embed, File, Image
- **Special Types**: GeoJSON, JSON, Binary, UUID

### Field Properties

- **Validation**: required, min/max length, pattern, custom validation
- **Indexing**: unique, indexed, full-text search
- **Security**: encryption, access control
- **Performance**: caching, compression

### Relationship Management

- **One-to-One, One-to-Many, Many-to-Many relationships**
- **Foreign key constraints**
- **Cascade operations**
- **Reference integrity**

### Schema Operations

- **Create, Read, Update, Delete schemas**
- **Schema migration tools**
- **Schema comparison and sync**
- \*\*Schema backup and restore

### Advanced Features

- **Schema templates and presets**
- **Schema inheritance**
- **Conditional fields**
- **Dynamic schema generation**
- **Schema analytics**
- **Performance optimization suggestions**

## Technical Implementation

### File Structure

```
admin-panel/
├── public/assets/css/
│   └── schema-builder.css          # Schema builder styles
├── public/assets/js/
│   ├── schema-builder.js          # Main schema builder class
│   ├── schema-builder-config.js   # Configuration settings
│   ├── schema-builder-api.js      # API service
│   └── schema-builder-utils.js    # Utility functions
└── views/schema-builder/
    └── index.latte                 # Main template
```

### Core Classes

#### SchemaBuilder Class

The main class that handles all schema builder functionality.

```javascript
class SchemaBuilder {
  constructor() {
    this.schema = {
      collection: "",
      fields: [],
      indexes: [],
      relationships: [],
      version: "1.0.0",
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };

    this.selectedField = null;
    this.selectedFieldType = "string";
    this.isDragging = false;
    this.draggedElement = null;
    this.canvasOffset = { x: 0, y: 0 };
    this.connectionLines = [];
  }
}
```

#### Key Methods

- `renderCanvas()`: Renders the schema canvas with fields and connections
- `addFieldFromPalette()`: Adds new fields from the palette
- `updateFieldProperty()`: Updates field properties
- `validateSchema()`: Validates the current schema
- `exportSchema()`: Exports schema in various formats
- `importSchema()`: Imports schema from external sources

### Field Management

#### Field Types

```javascript
const fieldTypes = {
  string: { icon: "type", color: "#3b82f6", default: "" },
  int: { icon: "hash", color: "#10b981", default: 0 },
  float: { icon: "percent", color: "#06b6d4", default: 0.0 },
  boolean: { icon: "toggle-left", color: "#f59e0b", default: false },
  date: { icon: "calendar", color: "#ef4444", default: null },
  datetime: { icon: "clock", color: "#f97316", default: null },
  enum: { icon: "list", color: "#ec4899", default: "" },
  array: { icon: "brackets", color: "#06b6d4", default: [] },
  object: { icon: "braces", color: "#8b5cf6", default: {} },
  relation: { icon: "link", color: "#10b981", default: null },
  json: { icon: "code", color: "#6366f1", default: {} },
  uuid: { icon: "fingerprint", color: "#a855f7", default: "" },
  binary: { icon: "file", color: "#64748b", default: null },
  geojson: { icon: "map-pin", color: "#059669", default: null },
};
```

#### Field Constraints

```javascript
const constraints = {
  // String constraints
  minLength: 0,
  maxLength: 255,
  pattern: "",

  // Number constraints
  min: null,
  max: null,

  // Array constraints
  minItems: 0,
  maxItems: null,

  // Enum constraints
  values: [],

  // Relation constraints
  reference: "",
  onDelete: "restrict",
  populateAlias: "",
};
```

### Validation System

#### Schema Validation

```javascript
validateSchema(schema) {
    const errors = [];
    const warnings = [];

    // Check required fields
    if (!schema.collection) {
        errors.push('Collection name is required');
    }

    // Check field names
    if (schema.fields && schema.fields.length > 0) {
        const fieldNames = schema.fields.map(f => f.name);

        // Check for duplicate field names
        const duplicates = fieldNames.filter((name, index) => fieldNames.indexOf(name) !== index);
        if (duplicates.length > 0) {
            errors.push(`Duplicate field names: ${duplicates.join(', ')}`);
        }

        // Validate field types and constraints
        schema.fields.forEach(field => {
            if (!this.validateFieldType(field)) {
                errors.push(`Invalid field configuration for "${field.name}"`);
            }
        });
    }

    return { isValid: errors.length === 0, errors, warnings };
}
```

#### Field Validation

```javascript
validateFieldConstraints(field) {
    const errors = [];
    const constraints = field.constraints || {};

    switch (field.type) {
        case 'string':
            if (constraints.minLength !== undefined && constraints.minLength < 0) {
                errors.push(`Field "${field.name}": minLength cannot be negative`);
            }
            break;

        case 'int':
        case 'float':
            if (constraints.min !== undefined && constraints.max !== undefined &&
                constraints.min > constraints.max) {
                errors.push(`Field "${field.name}": min cannot be greater than max`);
            }
            break;
    }

    return errors;
}
```

### Relationship Management

#### Relationship Types

```javascript
const relationshipTypes = {
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
};
```

#### Delete Actions

```javascript
const deleteActions = {
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
};
```

### API Integration

#### Schema API

```javascript
class SchemaBuilderAPI {
  constructor() {
    this.baseURL = "/api";
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
}
```

### Schema Templates

#### Pre-defined Templates

```javascript
const templates = {
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
      // ... more fields
    ],
  },
  product: {
    name: "Product Collection",
    description: "E-commerce product template",
    fields: [
      { name: "_id", type: "uuid", required: true, description: "Product ID" },
      {
        name: "name",
        type: "string",
        required: true,
        description: "Product name",
      },
      // ... more fields
    ],
  },
};
```

## Usage Examples

### Creating a Simple Schema

```javascript
const schema = {
  collection: "users",
  fields: [
    {
      name: "username",
      type: "string",
      required: true,
      unique: true,
      constraints: {
        minLength: 3,
        maxLength: 50,
      },
    },
    {
      name: "email",
      type: "string",
      required: true,
      unique: true,
      constraints: {
        pattern: "^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$",
      },
    },
    {
      name: "profile_id",
      type: "relation",
      constraints: {
        reference: "profiles",
        onDelete: "cascade",
      },
    },
  ],
};
```

### Adding Validation Rules

```javascript
const validationRules = {
  required: true,
  unique: true,
  indexed: true,
  searchable: true,
  encrypted: false,
  constraints: {
    minLength: 3,
    maxLength: 50,
    pattern: "^[a-zA-Z0-9_]+$",
  },
};
```

### Creating Relationships

```javascript
const relationships = [
  {
    from: "users.profile_id",
    to: "profiles._id",
    type: "one-to-one",
    description: "User profile relationship",
  },
  {
    from: "posts.user_id",
    to: "users._id",
    type: "many-to-one",
    description: "Post author relationship",
  },
];
```

## Performance Considerations

### Optimization Techniques

- **Virtual Scrolling**: Efficient rendering of large schemas
- **Lazy Loading**: Load field types and templates on demand
- **Debouncing**: Debounce rapid input events
- **Caching**: Cache frequently accessed data
- **Throttling**: Throttle expensive operations

### Memory Management

- **Clean Up**: Remove event listeners when not needed
- **Object Pooling**: Reuse DOM elements where possible
- **Garbage Collection**: Proper cleanup of temporary objects

## Security Considerations

### Input Validation

- **Sanitization**: Sanitize all user inputs
- **Validation**: Validate all schema data before processing
- **Type Checking**: Ensure proper data types for all fields

### Access Control

- **Permissions**: Implement proper access controls
- **Authentication**: Require authentication for sensitive operations
- **Authorization**: Check user permissions before allowing operations

## Browser Compatibility

### Supported Browsers

- **Chrome**: 90+
- **Firefox**: 88+
- **Safari**: 14+
- **Edge**: 90+

### Polyfills Required

- **Intersection Observer**: For virtual scrolling
- **Resize Observer**: For responsive design
- **Custom Elements**: For custom components

## Testing

### Unit Tests

- **Field Validation**: Test field validation logic
- **Schema Validation**: Test schema validation
- **API Integration**: Test API calls

### Integration Tests

- **Drag and Drop**: Test drag and drop functionality
- **Canvas Rendering**: Test canvas rendering
- **Template Application**: Test template application

### E2E Tests

- **User Workflow**: Test complete user workflows
- **Error Handling**: Test error scenarios
- **Performance**: Test performance under load

## Deployment

### Build Process

```bash
# Install dependencies
npm install

# Build assets
npm run build

# Start development server
npm run dev
```

### Production Deployment

```bash
# Build for production
npm run build:prod

# Deploy to server
npm run deploy
```

## Troubleshooting

### Common Issues

- **Canvas Not Rendering**: Check CSS and JavaScript loading
- **Drag and Drop Not Working**: Check event listeners
- **API Calls Failing**: Check network connectivity and API endpoints

### Debug Mode

```javascript
// Enable debug mode
window.schemaBuilder.debug = true;

// Check schema state
console.log(window.schemaBuilder.schema);

// Validate schema
const result = window.schemaBuilder.validateSchema();
console.log(result);
```

## Future Enhancements

### Planned Features

- **Real-time Collaboration**: Multiple users editing simultaneously
- **AI-powered Suggestions**: Intelligent field and constraint suggestions
- **Visual Query Builder**: Build queries visually
- **Performance Analytics**: Schema performance metrics
- **Automated Testing**: Automated schema testing

### Performance Improvements

- **Web Workers**: Offload heavy computations
- **WebAssembly**: For performance-critical operations
- **Service Workers**: For offline functionality

## Contributing

### Development Setup

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

### Code Style

- Follow existing code style
- Use meaningful variable names
- Add comments for complex logic
- Write tests for new features

### Reporting Issues

- Use GitHub issues for bug reports
- Provide detailed reproduction steps
- Include browser and version information
- Add screenshots if applicable

## License

This project is licensed under the MIT License. See the LICENSE file for details.
