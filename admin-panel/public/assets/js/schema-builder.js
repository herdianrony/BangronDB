/**
 * Enhanced Schema Builder for BangronDB Admin Panel
 *
 * Features:
 * - Visual schema design with drag-and-drop
 * - Comprehensive field management
 * - Relationship mapping
 * - Validation rules
 * - Index management
 * - Schema versioning
 * - Advanced features support
 */

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

    this.fieldTypes = {
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

    this.validationRules = {
      required: false,
      unique: false,
      indexed: false,
      searchable: false,
      encrypted: false,
      min: null,
      max: null,
      pattern: null,
      custom: null,
      default: null,
    };

    this.init();
  }

  init() {
    this.setupEventListeners();
    this.initializeCanvas();
    this.loadSampleSchema();
    this.renderFieldPalette();
    this.renderCanvas();
    this.updatePreview();
  }

  setupEventListeners() {
    // Global drag and drop
    document.addEventListener("dragover", (e) => this.handleDragOver(e));
    document.addEventListener("drop", (e) => this.handleDrop(e));

    // Window resize
    window.addEventListener("resize", () => this.handleResize());

    // Keyboard shortcuts
    document.addEventListener("keydown", (e) => this.handleKeyboard(e));
  }

  initializeCanvas() {
    const canvas = document.getElementById("schemaCanvas");
    if (!canvas) return;

    // Set canvas dimensions
    const container = canvas.parentElement;
    canvas.style.width = container.offsetWidth + "px";
    canvas.style.height = "600px";

    // Canvas click handler
    canvas.addEventListener("click", (e) => this.handleCanvasClick(e));

    // Canvas context menu
    canvas.addEventListener("contextmenu", (e) => this.handleContextMenu(e));
  }

  renderFieldPalette() {
    const palette = document.getElementById("fieldPalette");
    if (!palette) return;

    palette.innerHTML = "";

    Object.entries(this.fieldTypes).forEach(([type, config]) => {
      const fieldItem = document.createElement("div");
      fieldItem.className =
        "field-type-item flex items-center gap-3 p-3 glass-light rounded-lg cursor-grab hover:bg-white/10 transition-colors";
      fieldItem.draggable = true;
      fieldItem.dataset.type = type;

      fieldItem.innerHTML = `
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: ${config.color}20;">
                    <i data-lucide="${config.icon}" class="w-4 h-4" style="color: ${config.color};"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium">${this.capitalizeFirst(type)}</p>
                    <p class="text-xs text-slate-400">${this.getFieldDescription(type)}</p>
                </div>
            `;

      fieldItem.addEventListener("dragstart", (e) =>
        this.handleFieldDragStart(e, type),
      );
      fieldItem.addEventListener("dragend", (e) => this.handleFieldDragEnd(e));

      palette.appendChild(fieldItem);
    });

    // Re-initialize Lucide icons
    if (window.lucide) {
      window.lucide.createIcons();
    }
  }

  renderCanvas() {
    const canvas = document.getElementById("schemaCanvas");
    if (!canvas) return;

    // Clear existing canvas elements
    canvas
      .querySelectorAll(".canvas-field, .connection-line")
      .forEach((el) => el.remove());

    // Render fields
    this.schema.fields.forEach((field, index) => {
      this.renderFieldOnCanvas(field, index);
    });

    // Render connection lines
    this.renderConnectionLines();
  }

  renderFieldOnCanvas(field, index) {
    const canvas = document.getElementById("schemaCanvas");
    if (!canvas) return;

    const fieldElement = document.createElement("div");
    fieldElement.className = "canvas-field slide-in";
    fieldElement.dataset.fieldName = field.name;
    fieldElement.dataset.fieldIndex = index;
    fieldElement.style.left = 50 + (index % 3) * 220 + "px";
    fieldElement.style.top = 50 + Math.floor(index / 3) * 100 + "px";

    const typeConfig = this.fieldTypes[field.type];
    const badges = this.getFieldBadges(field);

    fieldElement.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <i data-lucide="${typeConfig.icon}" class="w-4 h-4" style="color: ${typeConfig.color};"></i>
                    <span class="font-medium text-sm">${field.name}</span>
                </div>
                <div class="flex items-center gap-1">
                    <button class="p-1 hover:bg-white/10 rounded transition-colors" onclick="schemaBuilder.editField('${field.name}')" title="Edit">
                        <i data-lucide="edit-2" class="w-3 h-3"></i>
                    </button>
                    <button class="p-1 hover:bg-red-500/20 rounded transition-colors" onclick="schemaBuilder.deleteField('${field.name}')" title="Delete">
                        <i data-lucide="trash-2" class="w-3 h-3 text-red-400"></i>
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-1 mb-2">
                <span class="type-badge" style="background-color: ${typeConfig.color}20; color: ${typeConfig.color};">
                    ${field.type}
                </span>
                ${badges}
            </div>
            ${field.description ? `<p class="text-xs text-slate-400">${field.description}</p>` : ""}
            ${
              field.relation
                ? `<div class="mt-2 text-xs text-emerald-400">
                <i data-lucide="link" class="w-3 h-3 inline mr-1"></i>
                ${field.reference}
            </div>`
                : ""
            }
        `;

    // Add drag functionality
    fieldElement.draggable = true;
    fieldElement.addEventListener("dragstart", (e) =>
      this.handleCanvasFieldDragStart(e, field),
    );
    fieldElement.addEventListener("dragend", (e) =>
      this.handleCanvasFieldDragEnd(e),
    );
    fieldElement.addEventListener("click", (e) =>
      this.handleFieldClick(e, field),
    );

    // Add connection points for relationships
    if (field.type === "relation") {
      this.addConnectionPoints(fieldElement, field);
    }

    canvas.appendChild(fieldElement);

    // Re-initialize Lucide icons
    if (window.lucide) {
      window.lucide.createIcons();
    }
  }

  getFieldBadges(field) {
    const badges = [];

    if (field.required)
      badges.push(
        '<span class="text-xs px-2 py-0.5 bg-red-500/20 text-red-300 rounded">Required</span>',
      );
    if (field.unique)
      badges.push(
        '<span class="text-xs px-2 py-0.5 bg-blue-500/20 text-blue-300 rounded">Unique</span>',
      );
    if (field.indexed)
      badges.push(
        '<span class="text-xs px-2 py-0.5 bg-green-500/20 text-green-300 rounded">Indexed</span>',
      );
    if (field.searchable)
      badges.push(
        '<span class="text-xs px-2 py-0.5 bg-cyan-500/20 text-cyan-300 rounded">Searchable</span>',
      );
    if (field.encrypted)
      badges.push(
        '<span class="text-xs px-2 py-0.5 bg-amber-500/20 text-amber-300 rounded">Encrypted</span>',
      );

    return badges.join(" ");
  }

  addConnectionPoints(fieldElement, field) {
    const sourcePoint = document.createElement("div");
    sourcePoint.className = "connection-point source";
    sourcePoint.dataset.field = field.name;
    sourcePoint.dataset.type = "source";
    sourcePoint.addEventListener("click", (e) =>
      this.handleConnectionPointClick(e, field),
    );

    fieldElement.appendChild(sourcePoint);
  }

  renderConnectionLines() {
    const canvas = document.getElementById("schemaCanvas");
    if (!canvas) return;

    this.connectionLines.forEach((line) => {
      const lineElement = document.createElement("div");
      lineElement.className = "connection-line";
      lineElement.style.left = line.start.x + "px";
      lineElement.style.top = line.start.y + "px";
      lineElement.style.width = line.length + "px";
      lineElement.style.transform = `rotate(${line.angle}rad)`;

      canvas.appendChild(lineElement);
    });
  }

  handleFieldDragStart(e, type) {
    this.isDragging = true;
    this.draggedElement = e.target;
    e.target.classList.add("dragging");
    e.dataTransfer.effectAllowed = "copy";
    e.dataTransfer.setData("fieldType", type);
  }

  handleFieldDragEnd(e) {
    this.isDragging = false;
    e.target.classList.remove("dragging");
  }

  handleCanvasFieldDragStart(e, field) {
    this.isDragging = true;
    this.draggedElement = e.target;
    e.target.classList.add("dragging");
    e.dataTransfer.effectAllowed = "move";
    e.dataTransfer.setData("fieldName", field.name);
  }

  handleCanvasFieldDragEnd(e) {
    this.isDragging = false;
    e.target.classList.remove("dragging");
  }

  handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = "copy";
  }

  handleDrop(e) {
    e.preventDefault();

    const canvas = document.getElementById("schemaCanvas");
    if (!canvas) return;

    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    const fieldType = e.dataTransfer.getData("fieldType");
    const fieldName = e.dataTransfer.getData("fieldName");

    if (fieldType && !fieldName) {
      // Adding new field
      this.addFieldFromPalette(fieldType, x, y);
    } else if (fieldName && !fieldType) {
      // Moving existing field
      this.moveField(fieldName, x, y);
    }
  }

  addFieldFromPalette(type, x, y) {
    const field = this.createField(type);
    field.x = x;
    field.y = y;

    this.schema.fields.push(field);
    this.renderCanvas();
    this.updatePreview();
    this.showToast("Field Added", `${field.name} has been added to the schema`);
  }

  createField(type) {
    const field = {
      name: "",
      type: type,
      required: false,
      unique: false,
      indexed: false,
      searchable: false,
      encrypted: false,
      description: "",
      default: null,
      constraints: {},
    };

    // Set default values based on type
    const typeConfig = this.fieldTypes[type];
    if (typeConfig.default !== null) {
      field.default = typeConfig.default;
    }

    // Add type-specific constraints
    this.addTypeSpecificConstraints(field, type);

    return field;
  }

  addTypeSpecificConstraints(field, type) {
    switch (type) {
      case "string":
        field.constraints.minLength = 0;
        field.constraints.maxLength = 255;
        field.constraints.pattern = "";
        break;
      case "int":
        field.constraints.min = null;
        field.constraints.max = null;
        break;
      case "float":
        field.constraints.min = null;
        field.constraints.max = null;
        field.constraints.precision = 2;
        break;
      case "enum":
        field.constraints.values = [];
        break;
      case "array":
        field.constraints.itemsType = "string";
        field.constraints.minItems = 0;
        field.constraints.maxItems = null;
        break;
      case "object":
        field.constraints.nestedFields = [];
        break;
      case "relation":
        field.constraints.reference = "";
        field.constraints.onDelete = "restrict";
        field.constraints.populateAlias = "";
        break;
      case "date":
      case "datetime":
        field.constraints.format = "Y-m-d H:i:s";
        field.constraints.autoValue = "";
        break;
    }
  }

  moveField(fieldName, x, y) {
    const field = this.schema.fields.find((f) => f.name === fieldName);
    if (field) {
      field.x = x;
      field.y = y;
      this.renderCanvas();
    }
  }

  handleCanvasClick(e) {
    if (e.target.id === "schemaCanvas") {
      this.deselectAllFields();
    }
  }

  handleFieldClick(e, field) {
    e.stopPropagation();
    this.selectField(field);
  }

  selectField(field) {
    this.deselectAllFields();
    this.selectedField = field;

    const fieldElement = document.querySelector(
      `[data-field-name="${field.name}"]`,
    );
    if (fieldElement) {
      fieldElement.classList.add("selected");
    }

    this.showFieldProperties(field);
  }

  deselectAllFields() {
    document.querySelectorAll(".canvas-field.selected").forEach((el) => {
      el.classList.remove("selected");
    });
    this.selectedField = null;
  }

  showFieldProperties(field) {
    const propertiesPanel = document.getElementById("fieldProperties");
    if (!propertiesPanel) return;

    propertiesPanel.innerHTML = `
            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold mb-3 flex items-center gap-2">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        Field Properties
                    </h3>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Field Name</label>
                    <input type="text" value="${field.name}" class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-violet-500" onchange="schemaBuilder.updateFieldProperty('name', this.value)">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                    <textarea class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-violet-500" rows="2" placeholder="Field description..." onchange="schemaBuilder.updateFieldProperty('description', this.value)">${field.description || ""}</textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 p-3 glass-light rounded-lg cursor-pointer hover:bg-white/10">
                        <input type="checkbox" ${field.required ? "checked" : ""} class="w-4 h-4 rounded border-white/20 bg-slate-800 text-violet-500 focus:ring-violet-500" onchange="schemaBuilder.updateFieldProperty('required', this.checked)">
                        <span class="text-sm">Required</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 glass-light rounded-lg cursor-pointer hover:bg-white/10">
                        <input type="checkbox" ${field.unique ? "checked" : ""} class="w-4 h-4 rounded border-white/20 bg-slate-800 text-violet-500 focus:ring-violet-500" onchange="schemaBuilder.updateFieldProperty('unique', this.checked)">
                        <span class="text-sm">Unique</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 glass-light rounded-lg cursor-pointer hover:bg-white/10">
                        <input type="checkbox" ${field.indexed ? "checked" : ""} class="w-4 h-4 rounded border-white/20 bg-slate-800 text-violet-500 focus:ring-violet-500" onchange="schemaBuilder.updateFieldProperty('indexed', this.checked)">
                        <span class="text-sm">Indexed</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 glass-light rounded-lg cursor-pointer hover:bg-white/10">
                        <input type="checkbox" ${field.searchable ? "checked" : ""} class="w-4 h-4 rounded border-white/20 bg-slate-800 text-violet-500 focus:ring-violet-500" onchange="schemaBuilder.updateFieldProperty('searchable', this.checked)">
                        <span class="text-sm">Searchable</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 glass-light rounded-lg cursor-pointer hover:bg-white/10">
                        <input type="checkbox" ${field.encrypted ? "checked" : ""} class="w-4 h-4 rounded border-white/20 bg-slate-800 text-violet-500 focus:ring-violet-500" onchange="schemaBuilder.updateFieldProperty('encrypted', this.checked)">
                        <span class="text-sm">Encrypted</span>
                    </label>
                </div>
                
                ${this.renderTypeSpecificOptions(field)}
                
                <div class="flex gap-2 pt-4 border-t border-white/10">
                    <button class="flex-1 px-4 py-2 bg-violet-500/20 text-violet-300 rounded-lg hover:bg-violet-500/30 transition-colors text-sm" onclick="schemaBuilder.showAdvancedSettings()">
                        Advanced Settings
                    </button>
                    <button class="flex-1 px-4 py-2 bg-red-500/20 text-red-300 rounded-lg hover:bg-red-500/30 transition-colors text-sm" onclick="schemaBuilder.deleteField('${field.name}')">
                        Delete Field
                    </button>
                </div>
            </div>
        `;

    if (window.lucide) {
      window.lucide.createIcons();
    }
  }

  renderTypeSpecificOptions(field) {
    const type = field.type;
    let options = "";

    switch (type) {
      case "string":
        options = `
                    <div class="space-y-3 p-4 bg-blue-500/10 rounded-xl border border-blue-500/20">
                        <h4 class="font-medium text-blue-300">String Options</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Min Length</label>
                                <input type="number" value="${field.constraints.minLength || 0}" class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" onchange="schemaBuilder.updateFieldConstraint('minLength', this.value)">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Max Length</label>
                                <input type="number" value="${field.constraints.maxLength || 255}" class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" onchange="schemaBuilder.updateFieldConstraint('maxLength', this.value)">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Regex Pattern</label>
                            <input type="text" value="${field.constraints.pattern || ""}" class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g., ^[a-zA-Z0-9]+$" onchange="schemaBuilder.updateFieldConstraint('pattern', this.value)">
                        </div>
                    </div>
                `;
        break;
      case "int":
        options = `
                    <div class="space-y-3 p-4 bg-green-500/10 rounded-xl border border-green-500/20">
                        <h4 class="font-medium text-green-300">Integer Options</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Min Value</label>
                                <input type="number" value="${field.constraints.min || ""}" class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-green-500" onchange="schemaBuilder.updateFieldConstraint('min', this.value)">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Max Value</label>
                                <input type="number" value="${field.constraints.max || ""}" class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-green-500" onchange="schemaBuilder.updateFieldConstraint('max', this.value)">
                            </div>
                        </div>
                    </div>
                `;
        break;
      case "relation":
        options = `
                    <div class="space-y-3 p-4 bg-violet-500/10 rounded-xl border border-violet-500/20">
                        <h4 class="font-medium text-violet-300">Relation Options</h4>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Reference</label>
                            <select class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-violet-500" onchange="schemaBuilder.updateFieldConstraint('reference', this.value)">
                                <option value="">Select collection...</option>
                                <option value="users" ${field.constraints.reference === "users" ? "selected" : ""}>users</option>
                                <option value="products" ${field.constraints.reference === "products" ? "selected" : ""}>products</option>
                                <option value="orders" ${field.constraints.reference === "orders" ? "selected" : ""}>orders</option>
                                <option value="categories" ${field.constraints.reference === "categories" ? "selected" : ""}>categories</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">On Delete</label>
                                <select class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-violet-500" onchange="schemaBuilder.updateFieldConstraint('onDelete', this.value)">
                                    <option value="restrict" ${field.constraints.onDelete === "restrict" ? "selected" : ""}>Restrict</option>
                                    <option value="cascade" ${field.constraints.onDelete === "cascade" ? "selected" : ""}>Cascade</option>
                                    <option value="set_null" ${field.constraints.onDelete === "set_null" ? "selected" : ""}>Set Null</option>
                                    <option value="no_action" ${field.constraints.onDelete === "no_action" ? "selected" : ""}>No Action</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Populate Alias</label>
                                <input type="text" value="${field.constraints.populateAlias || ""}" class="w-full px-3 py-2 bg-slate-800/50 border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-violet-500" placeholder="e.g., author" onchange="schemaBuilder.updateFieldConstraint('populateAlias', this.value)">
                            </div>
                        </div>
                    </div>
                `;
        break;
    }

    return options;
  }

  updateFieldProperty(property, value) {
    if (!this.selectedField) return;

    this.selectedField[property] = value;
    this.renderCanvas();
    this.updatePreview();
    this.schema.updatedAt = new Date().toISOString();
  }

  updateFieldConstraint(constraint, value) {
    if (!this.selectedField) return;

    if (!this.selectedField.constraints) {
      this.selectedField.constraints = {};
    }

    this.selectedField.constraints[constraint] = value === "" ? null : value;
    this.renderCanvas();
    this.updatePreview();
    this.schema.updatedAt = new Date().toISOString();
  }

  deleteField(fieldName) {
    if (!confirm(`Are you sure you want to delete "${fieldName}"?`)) return;

    this.schema.fields = this.schema.fields.filter((f) => f.name !== fieldName);
    this.selectedField = null;
    this.renderCanvas();
    this.updatePreview();
    this.schema.updatedAt = new Date().toISOString();
    this.showToast(
      "Field Deleted",
      `"${fieldName}" has been removed from the schema`,
    );
  }

  editField(fieldName) {
    const field = this.schema.fields.find((f) => f.name === fieldName);
    if (field) {
      this.selectField(field);
    }
  }

  handleConnectionPointClick(e, field) {
    e.stopPropagation();
    // Handle relationship creation logic
    this.showToast(
      "Relationship Mode",
      "Click on another field to create a relationship",
    );
  }

  handleContextMenu(e) {
    e.preventDefault();
    // Handle context menu for canvas
  }

  handleKeyboard(e) {
    // Handle keyboard shortcuts
    if (e.key === "Delete" && this.selectedField) {
      this.deleteField(this.selectedField.name);
    }
    if (e.ctrlKey && e.key === "s") {
      e.preventDefault();
      this.saveSchema();
    }
  }

  handleResize() {
    // Handle window resize
    this.initializeCanvas();
    this.renderCanvas();
  }

  updatePreview() {
    this.updateJSONPreview();
    this.updatePHPPreview();
    this.updateValidationPreview();
    this.updateRelationshipDiagram();
  }

  updateJSONPreview() {
    const preview = document.getElementById("jsonPreview");
    if (!preview) return;

    const schemaJSON = {
      collection: this.schema.collection,
      version: this.schema.version,
      fields: this.schema.fields,
      indexes: this.schema.indexes,
      relationships: this.schema.relationships,
      metadata: {
        createdAt: this.schema.createdAt,
        updatedAt: this.schema.updatedAt,
      },
    };

    preview.querySelector("code").textContent = JSON.stringify(
      schemaJSON,
      null,
      2,
    );
  }

  updatePHPPreview() {
    const preview = document.getElementById("phpPreview");
    if (!preview) return;

    const phpCode = this.generatePHPCode();
    preview.querySelector("code").textContent = phpCode;
  }

  generatePHPCode() {
    let phpCode = `<?php
// Set Schema Validation
\$${this.schema.collection} = \$db->${this.schema.collection}->setSchema([`;

    this.schema.fields.forEach((field) => {
      phpCode += `\n    '${field.name}' => [`;

      if (field.required) phpCode += "\n        'required' => true,";
      if (field.unique) phpCode += "\n        'unique' => true,";
      if (field.indexed) phpCode += "\n        'indexed' => true,";
      if (field.searchable) phpCode += "\n        'searchable' => true,";
      if (field.encrypted) phpCode += "\n        'encrypted' => true,";

      phpCode += `\n        'type' => '${field.type}',`;

      if (field.default !== null) {
        phpCode += `\n        'default' => ${this.formatPHPValue(field.default)},`;
      }

      if (field.constraints && Object.keys(field.constraints).length > 0) {
        Object.entries(field.constraints).forEach(([key, value]) => {
          if (value !== null && value !== "") {
            phpCode += `\n        '${key}' => ${this.formatPHPValue(value)},`;
          }
        });
      }

      phpCode += "\n    ],";
    });

    phpCode += `\n]);`;

    // Add searchable fields
    const searchableFields = this.schema.fields.filter((f) => f.searchable);
    if (searchableFields.length > 0) {
      phpCode += `

// Set Searchable Fields`;
      searchableFields.forEach((field) => {
        phpCode += `\n\$${this.schema.collection}->setSearchableFields(['${field.name}']);`;
      });
    }

    // Add relationships
    const relationFields = this.schema.fields.filter(
      (f) => f.type === "relation",
    );
    if (relationFields.length > 0) {
      phpCode += `

// Populate Relations`;
      relationFields.forEach((field) => {
        if (field.constraints.reference) {
          phpCode += `\n\$results = \$${this.schema.collection}->find()->populate('${field.name}', '${field.constraints.reference}')->toArray();`;
        }
      });
    }

    return phpCode;
  }

  formatPHPValue(value) {
    if (typeof value === "string") {
      return `"${value}"`;
    } else if (typeof value === "boolean") {
      return value ? "true" : "false";
    } else if (Array.isArray(value)) {
      return "[" + value.map((v) => this.formatPHPValue(v)).join(", ") + "]";
    } else if (typeof value === "object" && value !== null) {
      return (
        "{" +
        Object.entries(value)
          .map(([k, v]) => `"${k}": ${this.formatPHPValue(v)}`)
          .join(", ") +
        "}"
      );
    } else {
      return value;
    }
  }

  updateValidationPreview() {
    const preview = document.getElementById("validationPreview");
    if (!preview) return;

    const validationRules = this.generateValidationRules();
    preview.querySelector("code").textContent = validationRules;
  }

  generateValidationRules() {
    let rules = `{\n  "_id": {\n    "type": "string",\n    "required": true,\n    "auto": true\n  }`;

    this.schema.fields.forEach((field) => {
      rules += `\n  "${field.name}": {\n    "type": "${field.type}"`;

      if (field.required) rules += ',\n    "required": true';
      if (field.unique) rules += ',\n    "unique": true';
      if (field.constraints && field.constraints.pattern)
        rules += ',\n    "pattern": "' + field.constraints.pattern + '"';
      if (field.constraints && field.constraints.minLength !== undefined)
        rules += ',\n    "minLength": ' + field.constraints.minLength;
      if (field.constraints && field.constraints.maxLength !== undefined)
        rules += ',\n    "maxLength": ' + field.constraints.maxLength;
      if (field.constraints && field.constraints.min !== undefined)
        rules += ',\n    "min": ' + field.constraints.min;
      if (field.constraints && field.constraints.max !== undefined)
        rules += ',\n    "max": ' + field.constraints.max;

      if (
        field.type === "enum" &&
        field.constraints &&
        field.constraints.values
      ) {
        rules +=
          ',\n    "enum": [' +
          field.constraints.values.map((v) => `"${v}"`).join(", ") +
          "]";
      }

      if (field.default !== null) {
        rules += ',\n    "default": ' + JSON.stringify(field.default);
      }

      rules += "\n  }";
    });

    rules += "\n}";
    return rules;
  }

  updateRelationshipDiagram() {
    const diagram = document.getElementById("relationsDiagram");
    if (!diagram) return;

    // Render relationship diagram
    diagram.innerHTML = `
            <div class="relation-diagram">
                <div class="collection-node current" style="left: 50px; top: 50px;">
                    <div class="flex items-center gap-2 mb-3">
                        <i data-lucide="database" class="w-5 h-5 text-violet-400"></i>
                        <h4 class="font-semibold">${this.schema.collection || "Collection"}</h4>
                    </div>
                    ${this.renderCollectionFields()}
                </div>
                
                ${this.renderRelatedCollections()}
            </div>
        `;

    if (window.lucide) {
      window.lucide.createIcons();
    }
  }

  renderCollectionFields() {
    return this.schema.fields
      .map((field) => {
        if (field.type === "relation") {
          return `<div class="field-node relation">
                    <i data-lucide="link" class="w-3 h-3 inline mr-1"></i>
                    ${field.name} → ${field.constraints.reference || "Unknown"}
                </div>`;
        } else {
          return `<div class="field-node">
                    <span class="${field.required ? "text-red-400" : ""}">${field.name}</span>
                    <span class="text-slate-500">: ${field.type}</span>
                </div>`;
        }
      })
      .join("");
  }

  renderRelatedCollections() {
    const relations = this.schema.fields.filter((f) => f.type === "relation");
    return relations
      .map(
        (relation, index) => `
            <div class="collection-node related" style="left: ${300 + index * 250}px; top: 50px;">
                <div class="flex items-center gap-2 mb-3">
                    <i data-lucide="folder" class="w-5 h-5 text-emerald-400"></i>
                    <h4 class="font-medium">${relation.constraints.reference || "Unknown"}</h4>
                </div>
                <div class="field-node">
                    <i data-lucide="link" class="w-3 h-3 inline mr-1"></i>
                    ${relation.name}
                </div>
            </div>
        `,
      )
      .join("");
  }

  loadSampleSchema() {
    this.schema = {
      collection: "users",
      version: "1.0.0",
      fields: [
        {
          name: "_id",
          type: "string",
          required: true,
          unique: true,
          indexed: true,
          description: "Primary key",
          constraints: {
            auto: true,
          },
        },
        {
          name: "username",
          type: "string",
          required: true,
          unique: true,
          indexed: true,
          searchable: true,
          description: "User username",
          constraints: {
            minLength: 3,
            maxLength: 50,
            pattern: "^[a-zA-Z0-9_]+$",
          },
        },
        {
          name: "email",
          type: "string",
          required: true,
          unique: true,
          searchable: true,
          description: "User email address",
          constraints: {
            pattern: "^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$",
          },
        },
        {
          name: "role",
          type: "enum",
          required: true,
          description: "User role",
          constraints: {
            values: ["admin", "editor", "user", "viewer"],
          },
        },
        {
          name: "profile_id",
          type: "relation",
          description: "User profile relation",
          constraints: {
            reference: "profiles",
            onDelete: "cascade",
            populateAlias: "profile",
          },
        },
      ],
      indexes: [
        { name: "idx_username", fields: ["username"], unique: true },
        { name: "idx_email", fields: ["email"], unique: true },
        { name: "idx_role", fields: ["role"] },
      ],
      relationships: [
        { from: "users.profile_id", to: "profiles._id", type: "one-to-one" },
      ],
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
  }

  saveSchema() {
    // Save schema to backend
    const schemaData = JSON.stringify(this.schema, null, 2);

    // Simulate API call
    console.log("Saving schema:", schemaData);

    this.showToast("Schema Saved", "Schema has been saved successfully");
  }

  exportSchema(format = "json") {
    const schemaData = JSON.stringify(this.schema, null, 2);

    if (format === "json") {
      const blob = new Blob([schemaData], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${this.schema.collection}_schema.json`;
      a.click();
      URL.revokeObjectURL(url);
    }

    this.showToast(
      "Schema Exported",
      `Schema exported as ${format.toUpperCase()}`,
    );
  }

  importSchema(jsonData) {
    try {
      const importedSchema = JSON.parse(jsonData);
      this.schema = { ...this.schema, ...importedSchema };
      this.renderCanvas();
      this.updatePreview();
      this.showToast("Schema Imported", "Schema imported successfully");
    } catch (error) {
      this.showToast("Import Error", "Invalid schema format", "error");
    }
  }

  showToast(title, message, type = "success") {
    const toast = document.createElement("div");
    toast.className = `toast ${type} show`;
    toast.innerHTML = `
            <div class="w-8 h-8 rounded-lg flex items-center justify-center ${type === "success" ? "bg-emerald-500/20" : type === "error" ? "bg-red-500/20" : "bg-amber-500/20"}">
                <i data-lucide="${type === "success" ? "check-circle" : type === "error" ? "x-circle" : "alert-circle"}" class="w-4 h-4 ${type === "success" ? "text-emerald-400" : type === "error" ? "text-red-400" : "text-amber-400"}"></i>
            </div>
            <div>
                <p class="font-medium">${title}</p>
                <p class="text-sm text-slate-400">${message}</p>
            </div>
        `;

    document.body.appendChild(toast);

    if (window.lucide) {
      window.lucide.createIcons();
    }

    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  getFieldDescription(type) {
    const descriptions = {
      string: "Text data",
      int: "Whole numbers",
      float: "Decimal numbers",
      boolean: "True/False values",
      date: "Date only",
      datetime: "Date and time",
      enum: "Predefined values",
      array: "List of items",
      object: "Nested document",
      relation: "Reference to another collection",
      json: "JSON data",
      uuid: "Unique identifier",
      binary: "Binary data",
      geojson: "Geographic data",
    };

    return descriptions[type] || "Custom type";
  }

  showAdvancedSettings() {
    // Show advanced settings modal
    this.showToast(
      "Advanced Settings",
      "Advanced settings feature coming soon",
    );
  }

  generateIndexes() {
    const indexes = [];

    // Auto-generate indexes for unique and required fields
    this.schema.fields.forEach((field) => {
      if (field.unique) {
        indexes.push({
          name: `idx_${field.name}`,
          fields: [field.name],
          unique: true,
        });
      } else if (field.indexed) {
        indexes.push({
          name: `idx_${field.name}`,
          fields: [field.name],
          unique: false,
        });
      }
    });

    return indexes;
  }

  validateSchema() {
    const errors = [];
    const warnings = [];

    // Check for required fields
    if (!this.schema.collection) {
      errors.push("Collection name is required");
    }

    // Check for duplicate field names
    const fieldNames = this.schema.fields.map((f) => f.name);
    const duplicates = fieldNames.filter(
      (name, index) => fieldNames.indexOf(name) !== index,
    );
    if (duplicates.length > 0) {
      errors.push(`Duplicate field names: ${duplicates.join(", ")}`);
    }

    // Check for valid field names
    const invalidNames = this.schema.fields.filter(
      (f) => !/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(f.name),
    );
    if (invalidNames.length > 0) {
      warnings.push(
        `Invalid field names: ${invalidNames.map((f) => f.name).join(", ")}`,
      );
    }

    // Check for relation references
    const relationFields = this.schema.fields.filter(
      (f) => f.type === "relation",
    );
    relationFields.forEach((field) => {
      if (!field.constraints || !field.constraints.reference) {
        errors.push(`Relation field "${field.name}" missing reference`);
      }
    });

    return { errors, warnings };
  }
}

// Initialize schema builder when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.schemaBuilder = new SchemaBuilder();
});

// Export for use in other files
if (typeof module !== "undefined" && module.exports) {
  module.exports = SchemaBuilder;
}
