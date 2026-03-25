/**
 * Schema Builder Utilities
 * 
 * This file contains utility functions for the enhanced schema builder
 */

class SchemaBuilderUtils {
    constructor() {
        this.version = '1.0.0';
    }
    
    // Field management utilities
    static generateFieldName(type, existingFields = []) {
        const baseNames = {
            string: 'field',
            int: 'number',
            float: 'decimal',
            boolean: 'flag',
            date: 'date_field',
            datetime: 'timestamp',
            enum: 'option',
            array: 'list',
            object: 'data',
            relation: 'reference',
            json: 'json_data',
            uuid: 'identifier',
            binary: 'file',
            geojson: 'location'
        };
        
        const baseName = baseNames[type] || 'field';
        let counter = 1;
        let fieldName = baseName;
        
        // Ensure field name is valid
        fieldName = this.sanitizeFieldName(fieldName);
        
        // Check for conflicts and generate unique name
        while (existingFields.includes(fieldName)) {
            fieldName = `${baseName}_${counter}`;
            fieldName = this.sanitizeFieldName(fieldName);
            counter++;
        }
        
        return fieldName;
    }
    
    static sanitizeFieldName(name) {
        // Remove invalid characters and ensure it starts with letter or underscore
        return name
            .replace(/[^a-zA-Z0-9_]/g, '_')
            .replace(/^[0-9]/, '_$&')
            .replace(/_{2,}/g, '_')
            .replace(/_$/, '');
    }
    
    static validateFieldName(name) {
        const pattern = /^[a-zA-Z_][a-zA-Z0-9_]*$/;
        return pattern.test(name);
    }
    
    static generateFieldId() {
        return `field_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }
    
    // Schema validation utilities
    static validateSchema(schema) {
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
            
            // Check for invalid field names
            const invalidNames = schema.fields.filter(f => !this.validateFieldName(f.name));
            if (invalidNames.length > 0) {
                warnings.push(`Invalid field names: ${invalidNames.map(f => f.name).join(', ')}`);
            }
            
            // Check for reserved field names
            const reservedNames = ['_id', '_rev', '_key', '_created', '_updated'];
            const reservedFields = schema.fields.filter(f => reservedNames.includes(f.name));
            if (reservedFields.length > 0) {
                warnings.push(`Reserved field names used: ${reservedFields.map(f => f.name).join(', ')}`);
            }
            
            // Validate field types
            schema.fields.forEach(field => {
                if (!this.validateFieldType(field)) {
                    errors.push(`Invalid field configuration for "${field.name}"`);
                }
                
                // Validate field constraints
                if (field.constraints) {
                    const constraintErrors = this.validateFieldConstraints(field);
                    errors.push(...constraintErrors);
                }
            });
        }
        
        // Validate indexes
        if (schema.indexes && schema.indexes.length > 0) {
            schema.indexes.forEach((index, indexIndex) => {
                if (!index.name) {
                    errors.push(`Index ${indexIndex + 1}: Index name is required`);
                }
                
                if (!index.fields || !Array.isArray(index.fields) || index.fields.length === 0) {
                    errors.push(`Index "${index.name}": At least one field is required`);
                }
                
                // Check if referenced fields exist
                if (schema.fields) {
                    const fieldNames = schema.fields.map(f => f.name);
                    const missingFields = index.fields.filter(f => !fieldNames.includes(f));
                    if (missingFields.length > 0) {
                        errors.push(`Index "${index.name}": Referenced fields do not exist: ${missingFields.join(', ')}`);
                    }
                }
            });
        }
        
        // Validate relationships
        if (schema.relationships && schema.relationships.length > 0) {
            schema.relationships.forEach((relationship, relIndex) => {
                if (!relationship.from || !relationship.to) {
                    errors.push(`Relationship ${relIndex + 1}: Both "from" and "to" are required`);
                }
                
                if (!relationship.type || !['one-to-one', 'one-to-many', 'many-to-many'].includes(relationship.type)) {
                    errors.push(`Relationship ${relIndex + 1}: Invalid relationship type`);
                }
            });
        }
        
        return {
            isValid: errors.length === 0,
            errors,
            warnings
        };
    }
    
    static validateFieldType(field) {
        if (!field.name || !field.type) {
            return false;
        }
        
        const validTypes = [
            'string', 'int', 'float', 'boolean', 'date', 'datetime',
            'enum', 'array', 'object', 'relation', 'json', 'uuid', 'binary', 'geojson'
        ];
        
        return validTypes.includes(field.type);
    }
    
    static validateFieldConstraints(field) {
        const errors = [];
        const constraints = field.constraints || {};
        
        switch (field.type) {
            case 'string':
                if (constraints.minLength !== undefined && constraints.minLength < 0) {
                    errors.push(`Field "${field.name}": minLength cannot be negative`);
                }
                if (constraints.maxLength !== undefined && constraints.maxLength < 0) {
                    errors.push(`Field "${field.name}": maxLength cannot be negative`);
                }
                if (constraints.minLength !== undefined && constraints.maxLength !== undefined && 
                    constraints.minLength > constraints.maxLength) {
                    errors.push(`Field "${field.name}": minLength cannot be greater than maxLength`);
                }
                break;
                
            case 'int':
            case 'float':
                if (constraints.min !== undefined && constraints.max !== undefined && 
                    constraints.min > constraints.max) {
                    errors.push(`Field "${field.name}": min cannot be greater than max`);
                }
                break;
                
            case 'enum':
                if (!constraints.values || !Array.isArray(constraints.values) || constraints.values.length === 0) {
                    errors.push(`Field "${field.name}": At least one value is required for enum`);
                }
                break;
                
            case 'array':
                if (constraints.minItems !== undefined && constraints.minItems < 0) {
                    errors.push(`Field "${field.name}": minItems cannot be negative`);
                }
                if (constraints.maxItems !== undefined && constraints.maxItems < 0) {
                    errors.push(`Field "${field.name}": maxItems cannot be negative`);
                }
                if (constraints.minItems !== undefined && constraints.maxItems !== undefined && 
                    constraints.minItems > constraints.maxItems) {
                    errors.push(`Field "${field.name}": minItems cannot be greater than maxItems`);
                }
                break;
                
            case 'relation':
                if (!constraints.reference) {
                    errors.push(`Field "${field.name}": Reference collection is required for relation`);
                }
                if (constraints.onDelete && !['restrict', 'cascade', 'set_null', 'no_action'].includes(constraints.onDelete)) {
                    errors.push(`Field "${field.name}": Invalid onDelete action`);
                }
                break;
        }
        
        return errors;
    }
    
    // Schema transformation utilities
    static transformSchema(schema, transformation) {
        const transformed = JSON.parse(JSON.stringify(schema));
        
        switch (transformation) {
            case 'toMongo':
                return this.transformToMongoSchema(transformed);
            case 'toSQL':
                return this.transformToSQLSchema(transformed);
            case 'toGraphQL':
                return this.transformToGraphQLSchema(transformed);
            case 'toREST':
                return this.transformToRESTSchema(transformed);
            default:
                return transformed;
        }
    }
    
    static transformToMongoSchema(schema) {
        const mongoSchema = {
            collection: schema.collection,
            fields: {}
        };
        
        schema.fields.forEach(field => {
            const fieldDef = { type: this.mapToMongoType(field.type) };
            
            if (field.required) fieldDef.required = true;
            if (field.unique) fieldDef.unique = true;
            if (field.indexed) fieldDef.index = true;
            
            // Add constraints
            if (field.constraints) {
                Object.assign(fieldDef, field.constraints);
            }
            
            mongoSchema.fields[field.name] = fieldDef;
        });
        
        return mongoSchema;
    }
    
    static transformToSQLSchema(schema) {
        const sqlSchema = {
            table: schema.collection,
            fields: [],
            indexes: [],
            relationships: []
        };
        
        schema.fields.forEach(field => {
            const fieldDef = {
                name: field.name,
                type: this.mapToSQLType(field.type),
                nullable: !field.required,
                unique: field.unique
            };
            
            if (field.constraints) {
                if (field.constraints.minLength !== undefined) fieldDef.length = field.constraints.maxLength;
                if (field.constraints.min !== undefined) fieldDef.min = field.constraints.min;
                if (field.constraints.max !== undefined) fieldDef.max = field.constraints.max;
            }
            
            sqlSchema.fields.push(fieldDef);
        });
        
        // Add indexes
        schema.indexes.forEach(index => {
            sqlSchema.indexes.push({
                name: index.name,
                fields: index.fields,
                unique: index.unique || false
            });
        });
        
        return sqlSchema;
    }
    
    static transformToGraphQLSchema(schema) {
        const graphqlSchema = {
            type: schema.collection,
            fields: []
        };
        
        schema.fields.forEach(field => {
            const fieldDef = {
                name: field.name,
                type: this.mapToGraphQLType(field.type),
                required: field.required
            };
            
            if (field.description) {
                fieldDef.description = field.description;
            }
            
            graphqlSchema.fields.push(fieldDef);
        });
        
        return graphqlSchema;
    }
    
    static transformToRESTSchema(schema) {
        const restSchema = {
            endpoint: `/${schema.collection}`,
            methods: ['GET', 'POST', 'PUT', 'DELETE'],
            fields: []
        };
        
        schema.fields.forEach(field => {
            const fieldDef = {
                name: field.name,
                type: field.type,
                required: field.required,
                readOnly: field.name === '_id'
            };
            
            if (field.constraints) {
                fieldDef.constraints = field.constraints;
            }
            
            restSchema.fields.push(fieldDef);
        });
        
        return restSchema;
    }
    
    // Type mapping utilities
    static mapToMongoType(type) {
        const typeMap = {
            string: 'String',
            int: 'Number',
            float: 'Number',
            boolean: 'Boolean',
            date: 'Date',
            datetime: 'Date',
            enum: 'String',
            array: 'Array',
            object: 'Object',
            relation: 'ObjectId',
            json: 'Mixed',
            uuid: 'String',
            binary: 'Buffer',
            geojson: 'Object'
        };
        
        return typeMap[type] || 'Mixed';
    }
    
    static mapToSQLType(type) {
        const typeMap = {
            string: 'VARCHAR(255)',
            int: 'INTEGER',
            float: 'DECIMAL(10,2)',
            boolean: 'BOOLEAN',
            date: 'DATE',
            datetime: 'DATETIME',
            enum: 'VARCHAR(50)',
            array: 'JSON',
            object: 'JSON',
            relation: 'VARCHAR(36)',
            json: 'JSON',
            uuid: 'VARCHAR(36)',
            binary: 'BLOB',
            geojson: 'GEOMETRY'
        };
        
        return typeMap[type] || 'TEXT';
    }
    
    static mapToGraphQLType(type) {
        const typeMap = {
            string: 'String',
            int: 'Int',
            float: 'Float',
            boolean: 'Boolean',
            date: 'String',
            datetime: 'String',
            enum: 'String',
            array: '[String]',
            object: 'JSON',
            relation: 'ID',
            json: 'JSON',
            uuid: 'ID',
            binary: 'String',
            geojson: 'JSON'
        };
        
        return typeMap[type] || 'String';
    }
    
    // Schema comparison utilities
    static compareSchemas(schema1, schema2) {
        const differences = {
            added: [],
            removed: [],
            modified: [],
            unchanged: []
        };
        
        const fields1 = schema1.fields || [];
        const fields2 = schema2.fields || [];
        
        const fields1Map = new Map(fields1.map(f => [f.name, f]));
        const fields2Map = new Map(fields2.map(f => [f.name, f]));
        
        // Find added fields
        fields2.forEach(field2 => {
            if (!fields1Map.has(field2.name)) {
                differences.added.push(field2);
            }
        });
        
        // Find removed fields
        fields1.forEach(field1 => {
            if (!fields2Map.has(field1.name)) {
                differences.removed.push(field1);
            }
        });
        
        // Find modified fields
        fields1.forEach(field1 => {
            const field2 = fields2Map.get(field1.name);
            if (field2 && this.isFieldModified(field1, field2)) {
                differences.modified.push({
                    field: field1.name,
                    changes: this.getFieldChanges(field1, field2)
                });
            } else if (field2) {
                differences.unchanged.push(field1.name);
            }
        });
        
        return differences;
    }
    
    static isFieldModified(field1, field2) {
        return JSON.stringify(field1) !== JSON.stringify(field2);
    }
    
    static getFieldChanges(field1, field2) {
        const changes = {};
        
        if (field1.type !== field2.type) {
            changes.type = { from: field1.type, to: field2.type };
        }
        
        if (field1.required !== field2.required) {
            changes.required = { from: field1.required, to: field2.required };
        }
        
        if (field1.unique !== field2.unique) {
            changes.unique = { from: field1.unique, to: field2.unique };
        }
        
        if (field1.indexed !== field2.indexed) {
            changes.indexed = { from: field1.indexed, to: field2.indexed };
        }
        
        if (field1.description !== field2.description) {
            changes.description = { from: field1.description, to: field2.description };
        }
        
        // Compare constraints
        if (JSON.stringify(field1.constraints) !== JSON.stringify(field2.constraints)) {
            changes.constraints = { from: field1.constraints, to: field2.constraints };
        }
        
        return changes;
    }
    
    // Schema generation utilities
    static generateSampleData(schema, count = 10) {
        const sampleData = [];
        
        for (let i = 0; i < count; i++) {
            const record = {};
            
            schema.fields.forEach(field => {
                record[field.name] = this.generateFieldValue(field, i);
            });
            
            sampleData.push(record);
        }
        
        return sampleData;
    }
    
    static generateFieldValue(field, index) {
        const value = field.default || null;
        
        if (value !== null) {
            return value;
        }
        
        switch (field.type) {
            case 'string':
                return `sample_${field.name}_${index}`;
            case 'int':
                return index + 1;
            case 'float':
                return (index + 1) * 1.5;
            case 'boolean':
                return index % 2 === 0;
            case 'date':
            case 'datetime':
                return new Date(Date.now() + index * 24 * 60 * 60 * 1000).toISOString();
            case 'enum':
                if (field.constraints && field.constraints.values) {
                    return field.constraints.values[index % field.constraints.values.length];
                }
                return 'option';
            case 'array':
                return [1, 2, 3];
            case 'object':
                return { key: `value_${index}` };
            case 'relation':
                return `relation_${index}`;
            case 'uuid':
                return `uuid-${index}-0000-0000-0000-000000000000`;
            case 'json':
                return { json: `data_${index}` };
            case 'binary':
                return Buffer.from('binary data');
            case 'geojson':
                return {
                    type: 'Point',
                    coordinates: [index * 0.1, index * 0.1]
                };
            default:
                return null;
        }
    }
    
    // Schema optimization utilities
    static optimizeSchema(schema) {
        const optimized = JSON.parse(JSON.stringify(schema));
        
        // Remove empty constraints
        optimized.fields.forEach(field => {
            if (field.constraints) {
                Object.keys(field.constraints).forEach(key => {
                    if (field.constraints[key] === null || field.constraints[key] === undefined || field.constraints[key] === '') {
                        delete field.constraints[key];
                    }
                });
                
                // Remove empty constraints object
                if (Object.keys(field.constraints).length === 0) {
                    delete field.constraints;
                }
            }
        });
        
        // Generate indexes based on field properties
        if (!optimized.indexes) {
            optimized.indexes = [];
        }
        
        optimized.fields.forEach(field => {
            if (field.unique && !optimized.indexes.find(idx => idx.fields.includes(field.name))) {
                optimized.indexes.push({
                    name: `idx_${field.name}`,
                    fields: [field.name],
                    unique: true
                });
            }
        });
        
        return optimized;
    }
    
    // Schema documentation utilities
    static generateDocumentation(schema) {
        const documentation = {
            title: `${schema.collection} Schema Documentation`,
            description: `Schema for ${schema.collection} collection`,
            version: schema.version || '1.0.0',
            created: schema.createdAt || new Date().toISOString(),
            updated: schema.updatedAt || new Date().toISOString(),
            fields: [],
            indexes: [],
            relationships: []
        };
        
        // Generate field documentation
        schema.fields.forEach(field => {
            const fieldDoc = {
                name: field.name,
                type: field.type,
                required: field.required || false,
                unique: field.unique || false,
                indexed: field.indexed || false,
                description: field.description || '',
                constraints: field.constraints || {}
            };
            
            documentation.fields.push(fieldDoc);
        });
        
        // Generate index documentation
        if (schema.indexes) {
            schema.indexes.forEach(index => {
                const indexDoc = {
                    name: index.name,
                    fields: index.fields,
                    unique: index.unique || false,
                    description: index.description || ''
                };
                
                documentation.indexes.push(indexDoc);
            });
        }
        
        // Generate relationship documentation
        if (schema.relationships) {
            schema.relationships.forEach(relationship => {
                const relationshipDoc = {
                    from: relationship.from,
                    to: relationship.to,
                    type: relationship.type,
                    description: relationship.description || ''
                };
                
                documentation.relationships.push(relationshipDoc);
            });
        }
        
        return documentation;
    }
    
    // Schema migration utilities
    static generateMigration(fromSchema, toSchema) {
        const migration = {
            from: fromSchema,
            to: toSchema,
            changes: this.compareSchemas(fromSchema, toSchema),
            up: [],
            down: [],
            timestamp: new Date().toISOString()
        };
        
        // Generate up migration steps
        migration.changes.removed.forEach(field => {
            migration.up.push({
                type: 'remove_field',
                field: field.name
            });
        });
        
        migration.changes.added.forEach(field => {
            migration.up.push({
                type: 'add_field',
                field: field
            });
        });
        
        migration.changes.modified.forEach(change => {
            migration.up.push({
                type: 'modify_field',
                field: change.field,
                changes: change.changes
            });
        });
        
        // Generate down migration steps (reverse order)
        migration.changes.modified.forEach(change => {
            migration.down.unshift({
                type: 'modify_field',
                field: change.field,
                changes: this.reverseChanges(change.changes)
            });
        });
        
        migration.changes.added.forEach(field => {
            migration.down.unshift({
                type: 'remove_field',
                field: field.name
            });
        });
        
        migration.changes.removed.forEach(field => {
            migration.down.unshift({
                type: 'add_field',
                field: field
            });
        });
        
        return migration;
    }
    
    static reverseChanges(changes) {
        const reversed = {};
        
        Object.keys(changes).forEach(key => {
            reversed[key] = { to: changes[key].from, from: changes[key].to };
        });
        
        return reversed;
    }
    
    // Utility functions
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    static deepClone(obj) {
        if (obj === null || typeof obj !== 'object') return obj;
        if (obj instanceof Date) return new Date(obj.getTime());
        if (obj instanceof Array) return obj.map(item => this.deepClone(item));
        if (typeof obj === 'object') {
            const clonedObj = {};
            Object.keys(obj).forEach(key => {
                clonedObj[key] = this.deepClone(obj[key]);
            });
            return clonedObj;
        }
    }
    
    static formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    static formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    static generateId() {
        return Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
    }
    
    static isValidJSON(str) {
        try {
            JSON.parse(str);
            return true;
        } catch (e) {
            return false;
        }
    }
    
    static sanitizeInput(input) {
        if (typeof input !== 'string') return input;
        
        return input
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, ''')
            .replace(/&/g, '&');
    }
}

// Export utilities
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SchemaBuilderUtils;
}

// Make it available globally
if (typeof window !== 'undefined') {
    window.SchemaBuilderUtils = SchemaBuilderUtils;
}