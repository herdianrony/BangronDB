# 👨‍💻 Proyek Pertama: TODO App dengan BangronDB

Tutorial lengkap membuat aplikasi TODO sederhana **dari nol**.

## 🎯 Tujuan

Buat aplikasi TODO dengan fitur:

- ✅ Tambah task
- ✅ Lihat semua task
- ✅ Mark task sebagai done
- ✅ Hapus task

## 📋 Prerequisites

- PHP 8.0+
- Composer
- Text editor (VSCode, Sublime, dll)
- Terminal/Command Prompt

---

## 🚀 Step 1: Setup Project

### 1.1 Buat folder project

```bash
mkdir my-todo-app
cd my-todo-app
```

### 1.2 Init Composer

```bash
composer init
```

Jawab pertanyaan:

- Package name: `my-todo-app`
- Description: `Simple TODO App with BangronDB`
- Author: `Your Name <your@email.com>`
- License: `MIT`
- (Press enter untuk yang lain)

### 1.3 Install BangronDB

```bash
composer require herdianrony/bangrondb
```

### 1.4 Buat struktur folder

```bash
mkdir data
mkdir src
```

---

## 🗂️ Step 2: Buat Database Config

File: `config.php`

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use BangronDB\Client;

// Inisialisasi client
$client = new Client(__DIR__ . '/data');

// Ambil database (otomatis dibuat jika tidak ada)
$db = $client->todo_app;

// Ambil collection
$tasks = $db->tasks;

// Konfigurasi collection
$tasks->setSchema([
    'title' => [
        'required' => true,
        'type' => 'string',
        'min' => 3,
        'max' => 255
    ],
    'description' => [
        'type' => 'string'
    ],
    'is_done' => [
        'type' => 'boolean'
    ],
    'created_at' => [
        'type' => 'string'
    ]
]);

// Hook: tambah timestamp otomatis
$tasks->on('beforeInsert', function($doc) {
    if (!isset($doc['created_at'])) {
        $doc['created_at'] = date('Y-m-d H:i:s');
    }
    if (!isset($doc['is_done'])) {
        $doc['is_done'] = false;
    }
    return $doc;
});

?>
```

---

## 📝 Step 3: Buat Task Model

File: `src/Task.php`

```php
<?php

class Task
{
    private $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
    }

    /**
     * Tambah task baru
     */
    public function create($title, $description = '')
    {
        return $this->collection->insert([
            'title' => $title,
            'description' => $description
        ]);
    }

    /**
     * Ambil semua task
     */
    public function getAll()
    {
        return $this->collection->find()
            ->sort(['created_at' => -1])
            ->toArray();
    }

    /**
     * Ambil task by ID
     */
    public function getById($id)
    {
        return $this->collection->findOne(['_id' => $id]);
    }

    /**
     * Ambil task yang belum done
     */
    public function getPending()
    {
        return $this->collection->find(['is_done' => false])
            ->sort(['created_at' => -1])
            ->toArray();
    }

    /**
     * Tandai task sebagai done
     */
    public function markDone($id)
    {
        return $this->collection->update(
            ['_id' => $id],
            ['is_done' => true]
        );
    }

    /**
     * Tandai task sebagai pending
     */
    public function markPending($id)
    {
        return $this->collection->update(
            ['_id' => $id],
            ['is_done' => false]
        );
    }

    /**
     * Hapus task
     */
    public function delete($id)
    {
        return $this->collection->remove(['_id' => $id]);
    }

    /**
     * Update task
     */
    public function update($id, $title, $description)
    {
        return $this->collection->update(
            ['_id' => $id],
            [
                'title' => $title,
                'description' => $description
            ]
        );
    }
}

?>
```

---

## 🎯 Step 4: Buat Script CLI

File: `index.php` (command line interface)

```php
<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Task.php';

$taskModel = new Task($tasks);

// Baca command dari command line
$argc = isset($argc) ? $argc : 0;
$argv = isset($argv) ? $argv : [];

if ($argc < 2) {
    showHelp();
    exit;
}

$command = $argv[1] ?? '';

switch ($command) {
    case 'add':
        if ($argc < 3) {
            echo "❌ Usage: php index.php add \"Task Title\" [\"Description\"]\n";
            exit;
        }
        $title = $argv[2];
        $description = $argv[3] ?? '';
        addTask($taskModel, $title, $description);
        break;

    case 'list':
        listTasks($taskModel);
        break;

    case 'done':
        if ($argc < 3) {
            echo "❌ Usage: php index.php done <ID>\n";
            exit;
        }
        doneTask($taskModel, $argv[2]);
        break;

    case 'pending':
        if ($argc < 3) {
            echo "❌ Usage: php index.php pending <ID>\n";
            exit;
        }
        pendingTask($taskModel, $argv[2]);
        break;

    case 'delete':
        if ($argc < 3) {
            echo "❌ Usage: php index.php delete <ID>\n";
            exit;
        }
        deleteTask($taskModel, $argv[2]);
        break;

    case 'view':
        if ($argc < 3) {
            echo "❌ Usage: php index.php view <ID>\n";
            exit;
        }
        viewTask($taskModel, $argv[2]);
        break;

    case 'pending-only':
        listPendingTasks($taskModel);
        break;

    default:
        echo "❌ Unknown command: $command\n";
        showHelp();
}

// ======== Functions ========

function showHelp()
{
    echo "\n📋 TODO App - Help\n";
    echo "===================\n\n";
    echo "Commands:\n";
    echo "  add <title> [description]  - Tambah task baru\n";
    echo "  list                       - Lihat semua task\n";
    echo "  pending-only               - Lihat task yang belum selesai\n";
    echo "  view <id>                  - Lihat detail task\n";
    echo "  done <id>                  - Tandai task selesai\n";
    echo "  pending <id>               - Tandai task pending\n";
    echo "  delete <id>                - Hapus task\n";
    echo "\n";
}

function addTask($taskModel, $title, $description)
{
    try {
        $id = $taskModel->create($title, $description);
        echo "✅ Task ditambah dengan ID: $id\n";
        echo "   Title: $title\n";
        if ($description) {
            echo "   Desc: $description\n";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

function listTasks($taskModel)
{
    $tasks = $taskModel->getAll();

    if (empty($tasks)) {
        echo "📭 Tidak ada task\n";
        return;
    }

    echo "\n📋 Daftar Task (" . count($tasks) . ")\n";
    echo "================\n\n";

    foreach ($tasks as $task) {
        $id = substr($task['_id'], 0, 8); // Show short ID
        $status = $task['is_done'] ? '✅' : '⏳';
        $title = $task['title'];

        echo "$status [$id] $title\n";

        if (isset($task['description']) && $task['description']) {
            echo "   " . $task['description'] . "\n";
        }
        echo "   " . $task['created_at'] . "\n";
        echo "\n";
    }
}

function listPendingTasks($taskModel)
{
    $tasks = $taskModel->getPending();

    if (empty($tasks)) {
        echo "🎉 Tidak ada task yang pending (semuanya selesai!)\n";
        return;
    }

    echo "\n⏳ Task Pending (" . count($tasks) . ")\n";
    echo "===============\n\n";

    foreach ($tasks as $task) {
        $id = substr($task['_id'], 0, 8);
        $title = $task['title'];

        echo "⏳ [$id] $title\n";
        if (isset($task['description']) && $task['description']) {
            echo "   " . $task['description'] . "\n";
        }
        echo "\n";
    }
}

function viewTask($taskModel, $id)
{
    $task = $taskModel->getById($id);

    if (!$task) {
        echo "❌ Task tidak ditemukan\n";
        return;
    }

    $status = $task['is_done'] ? '✅ Done' : '⏳ Pending';

    echo "\n📌 Task Detail\n";
    echo "==============\n\n";
    echo "ID: " . $task['_id'] . "\n";
    echo "Title: " . $task['title'] . "\n";
    echo "Status: " . $status . "\n";
    echo "Created: " . $task['created_at'] . "\n";

    if (isset($task['description']) && $task['description']) {
        echo "Description: " . $task['description'] . "\n";
    }

    echo "\n";
}

function doneTask($taskModel, $id)
{
    try {
        $taskModel->markDone($id);
        echo "✅ Task ditandai selesai\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

function pendingTask($taskModel, $id)
{
    try {
        $taskModel->markPending($id);
        echo "⏳ Task ditandai pending\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

function deleteTask($taskModel, $id)
{
    try {
        $taskModel->delete($id);
        echo "🗑️ Task dihapus\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

?>
```

---

## 🧪 Step 5: Test Aplikasi

### 5.1 Tambah beberapa task

```bash
php index.php add "Belajar BangronDB" "Tutorial lengkap database NoSQL"
php index.php add "Buat project TODO"
php index.php add "Publish ke Packagist"
```

**Output:**

```
✅ Task ditambah dengan ID: 550e8400-e29b-41d4-a716-446655440000
   Title: Belajar BangronDB
   Desc: Tutorial lengkap database NoSQL
```

### 5.2 Lihat semua task

```bash
php index.php list
```

**Output:**

```
📋 Daftar Task (3)
================

⏳ [550e8400] Belajar BangronDB
   Tutorial lengkap database NoSQL
   2024-01-15 10:30:45

⏳ [a1b2c3d4] Buat project TODO
   2024-01-15 10:31:20

⏳ [e5f6g7h8] Publish ke Packagist
   2024-01-15 10:32:00
```

### 5.3 Tandai task selesai

```bash
php index.php done 550e8400-e29b-41d4-a716-446655440000
```

### 5.4 Lihat task pending

```bash
php index.php pending-only
```

### 5.5 Lihat detail task

```bash
php index.php view 550e8400-e29b-41d4-a716-446655440000
```

### 5.6 Hapus task

```bash
php index.php delete 550e8400-e29b-41d4-a716-446655440000
```

---

## 🎨 Step 6 (Optional): Buat Web Interface

Untuk interface yang lebih user-friendly, Anda bisa buat dengan HTML/PHP:

File: `web.php`

```php
<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Task.php';

$taskModel = new Task($tasks);
$tasks_list = $taskModel->getAll();

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $taskModel->create($_POST['title'], $_POST['description'] ?? '');
            header('Location: web.php');
            exit;
        } elseif ($_POST['action'] === 'done') {
            $taskModel->markDone($_POST['id']);
            header('Location: web.php');
            exit;
        } elseif ($_POST['action'] === 'delete') {
            $taskModel->delete($_POST['id']);
            header('Location: web.php');
            exit;
        }
    }
    $tasks_list = $taskModel->getAll();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TODO App</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { margin-bottom: 20px; }
        .form { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        input, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; }
        button { background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; }
        button:hover { background: #45a049; }
        .task { background: white; padding: 15px; margin-bottom: 10px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .task.done { opacity: 0.6; text-decoration: line-through; }
        .task-info { flex: 1; }
        .task-actions { margin-left: 10px; }
        .task-actions button { padding: 5px 10px; margin: 0 5px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 TODO App</h1>

        <div class="form">
            <h3>Tambah Task Baru</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <input type="text" name="title" placeholder="Judul task..." required>
                <textarea name="description" placeholder="Deskripsi (optional)..." rows="3"></textarea>
                <button type="submit">➕ Tambah</button>
            </form>
        </div>

        <h3>Daftar Task</h3>
        <?php if (empty($tasks_list)): ?>
            <p>📭 Tidak ada task</p>
        <?php else: ?>
            <?php foreach ($tasks_list as $task): ?>
                <div class="task <?php echo $task['is_done'] ? 'done' : ''; ?>">
                    <div class="task-info">
                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                        <?php if (isset($task['description']) && $task['description']): ?>
                            <p><?php echo htmlspecialchars($task['description']); ?></p>
                        <?php endif; ?>
                        <small><?php echo $task['created_at']; ?></small>
                    </div>
                    <div class="task-actions">
                        <?php if (!$task['is_done']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="done">
                                <input type="hidden" name="id" value="<?php echo $task['_id']; ?>">
                                <button type="submit">✅ Done</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $task['_id']; ?>">
                            <button type="submit" onclick="return confirm('Yakin?')">🗑️ Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
```

Jalankan dengan:

```bash
php -S localhost:8000
```

Buka di browser: `http://localhost:8000/web.php`

---

## 🎓 Pelajaran

Dari project ini Anda sudah belajar:

1. ✅ Setup BangronDB
2. ✅ CRUD operations
3. ✅ Schema validation
4. ✅ Hooks (before insert)
5. ✅ Querying dan sorting
6. ✅ Model pattern
7. ✅ CLI dan Web interface

---

## 🚀 Next Steps

Untuk menambah fitur:

1. **Filter & Search**: Gunakan `$regex` operator
2. **Categories**: Tambah field category dan query by category
3. **Priority**: Tambah field priority dan sort by priority
4. **Email Notification**: Tambah hook untuk send email
5. **Encryption**: Untuk data sensitif, enable encryption
6. **Soft Delete**: Ubah delete jadi soft delete

Referensi: Lihat dokumentasi di `docs/` folder!

---

**Selamat! Anda sudah belajar BangronDB dengan project nyata! 🎉**
