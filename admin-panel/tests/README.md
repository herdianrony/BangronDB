# Testing Framework - BangronDB Admin Panel

Testing framework lengkap untuk BangronDB Admin Panel. Framework ini menyediakan test cases untuk semua komponen sistem, termasuk unit testing, integration testing, dan end-to-end testing.

## 📋 Testing Overview

### Testing Philosophy

- **Test-Driven Development**: Write tests before implementing features
- **Continuous Integration**: Automated testing on every commit
- **Code Coverage**: Maintain high code coverage standards
- **Quality Assurance**: Ensure reliability and performance

### Testing Pyramid

| Level                 | Type                          | Percentage | Tools             |
| --------------------- | ----------------------------- | ---------- | ----------------- |
| **Unit Tests**        | Fast, isolated tests          | 70%        | PHPUnit, Jest     |
| **Integration Tests** | Medium speed, component tests | 20%        | PHPUnit, Cypress  |
| **End-to-End Tests**  | Slow, full workflow tests     | 10%        | Selenium, Cypress |

### Test Environment

| Environment    | Purpose                | Configuration         |
| -------------- | ---------------------- | --------------------- |
| **Local**      | Development testing    | Local PHP, SQLite     |
| **CI**         | Continuous integration | Docker containers     |
| **Staging**    | Pre-production testing | Production-like setup |
| **Production** | No tests               | Monitoring only       |

## 🧪 Unit Testing

### PHP Unit Testing

#### Test Structure

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── AuthServiceTest.php
│   │   ├── DatabaseServiceTest.php
│   │   └── UserServiceTest.php
│   ├── Controllers/
│   │   ├── DashboardControllerTest.php
│   │   └── DatabaseControllerTest.php
│   └── Models/
│       ├── UserTest.php
│       └── DatabaseTest.php
├── Feature/
│   ├── AuthenticationTest.php
│   ├── DatabaseOperationsTest.php
│   └── UserManagementTest.php
└── TestCase.php
```

#### Example Test Cases

```php
<?php
// tests/Unit/Services/AuthServiceTest.php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AuthService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService();
    }

    /** @test */
    public function it_can_login_user_with_valid_credentials()
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Act
        $result = $this->authService->login([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('token', $result['data']);
        $this->assertEquals($user->id, $result['data']['user']['id']);
    }

    /** @test */
    public function it_fails_login_with_invalid_credentials()
    {
        // Arrange
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Act
        $result = $this->authService->login([
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid credentials', $result['message']);
    }

    /** @test */
    public function it_validates_login_input()
    {
        // Act
        $result = $this->authService->login([
            'email' => 'invalid-email',
            'password' => ''
        ]);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }
}
```

#### Database Service Test

```php
<?php
// tests/Unit/Services/DatabaseServiceTest.php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\DatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $databaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseService = new DatabaseService();
    }

    /** @test */
    public function it_creates_database_successfully()
    {
        // Arrange
        $databaseData = [
            'name' => 'test_db',
            'path' => '/tmp/test_db',
            'encryption' => true
        ];

        // Act
        $result = $this->databaseService->create($databaseData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('databases', [
            'name' => 'test_db',
            'path' => '/tmp/test_db'
        ]);
    }

    /** @test */
    public function it_validates_database_creation_input()
    {
        // Act
        $result = $this->databaseService->create([
            'name' => '', // Invalid: empty name
            'path' => '/invalid/path' // Invalid: path issues
        ]);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }
}
```

#### Controller Test

```php
<?php
// tests/Unit/Controllers/DashboardControllerTest.php
namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Controllers\DashboardController;
use Illuminate\Http\Request;

class DashboardControllerTest extends TestCase
{
    protected $dashboardController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboardController = new DashboardController();
    }

    /** @test */
    public function it_returns_dashboard_metrics()
    {
        // Arrange
        $request = new Request();

        // Act
        $response = $this->dashboardController->getMetrics($request);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }
}
```

### JavaScript Testing

#### Jest Test Structure

```javascript
// tests/unit/services/authService.test.js
const authService = require("../../src/services/authService");
const mockUserRepository = require("../../src/repositories/mockUserRepository");

describe("AuthService", () => {
  let authServiceInstance;
  let mockUserRepo;

  beforeEach(() => {
    mockUserRepo = new mockUserRepository();
    authServiceInstance = new authService(mockUserRepo);
  });

  describe("login", () => {
    it("should login user with valid credentials", async () => {
      // Arrange
      const loginData = {
        email: "test@example.com",
        password: "password123",
      };

      mockUserRepo.findByEmail.mockResolvedValue({
        id: 1,
        email: "test@example.com",
        password: "$2y$10$hashedpassword",
      });

      // Act
      const result = await authServiceInstance.login(loginData);

      // Assert
      expect(result.success).toBe(true);
      expect(result.data).toHaveProperty("token");
      expect(result.data.user).toEqual({
        id: 1,
        email: "test@example.com",
      });
    });

    it("should fail login with invalid credentials", async () => {
      // Arrange
      const loginData = {
        email: "test@example.com",
        password: "wrongpassword",
      };

      mockUserRepo.findByEmail.mockResolvedValue({
        id: 1,
        email: "test@example.com",
        password: "$2y$10$hashedpassword",
      });

      // Act
      const result = await authServiceInstance.login(loginData);

      // Assert
      expect(result.success).toBe(false);
      expect(result.message).toBe("Invalid credentials");
    });

    it("should validate login input", async () => {
      // Arrange
      const loginData = {
        email: "invalid-email",
        password: "",
      };

      // Act
      const result = await authServiceInstance.login(loginData);

      // Assert
      expect(result.success).toBe(false);
      expect(result.errors).toBeDefined();
      expect(result.errors.email).toContain(
        "The email field must be a valid email address.",
      );
    });
  });

  describe("logout", () => {
    it("should logout user successfully", async () => {
      // Arrange
      const token = "valid-jwt-token";

      // Act
      const result = await authServiceInstance.logout(token);

      // Assert
      expect(result.success).toBe(true);
      expect(result.message).toBe("Logged out successfully");
    });
  });
});
```

#### Component Testing

```javascript
// tests/unit/components/Dashboard.test.js
import { render, screen, fireEvent } from "@testing-library/react";
import Dashboard from "../../src/components/Dashboard";
import { DashboardProvider } from "../../src/contexts/DashboardContext";

describe("Dashboard Component", () => {
  it("renders dashboard metrics", () => {
    // Arrange
    const mockMetrics = {
      databases: 5,
      collections: 25,
      documents: 1500,
      storage: "2.5GB",
    };

    // Act
    render(
      <DashboardProvider value={{ metrics: mockMetrics }}>
        <Dashboard />
      </DashboardProvider>,
    );

    // Assert
    expect(screen.getByText("5 Databases")).toBeInTheDocument();
    expect(screen.getByText("25 Collections")).toBeInTheDocument();
    expect(screen.getByText("1,500 Documents")).toBeInTheDocument();
    expect(screen.getByText("2.5GB Storage")).toBeInTheDocument();
  });

  it("refreshes data when refresh button is clicked", async () => {
    // Arrange
    const mockRefresh = jest.fn();
    const mockMetrics = {
      databases: 5,
      collections: 25,
      documents: 1500,
      storage: "2.5GB",
    };

    // Act
    render(
      <DashboardProvider
        value={{
          metrics: mockMetrics,
          refreshData: mockRefresh,
        }}
      >
        <Dashboard />
      </DashboardProvider>,
    );

    const refreshButton = screen.getByRole("button", { name: /refresh/i });

    // Act
    fireEvent.click(refreshButton);

    // Assert
    expect(mockRefresh).toHaveBeenCalledTimes(1);
  });
});
```

## 🔗 Integration Testing

### API Integration Tests

```php
<?php
// tests/Feature/Api/DatabaseApiTest.php
namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Database;

class DatabaseApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_lists_all_databases()
    {
        // Arrange
        Database::factory()->count(3)->create();

        // Act
        $response = $this->getJson('/api/v1/databases');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'databases' => [
                    '*' => [
                        'id',
                        'name',
                        'path',
                        'document_count',
                        'created_at'
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function it_creates_database_successfully()
    {
        // Arrange
        $databaseData = [
            'name' => 'test_db',
            'path' => '/tmp/test_db',
            'encryption' => true
        ];

        // Act
        $response = $this->postJson('/api/v1/databases', $databaseData);

        // Assert
        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('databases', [
            'name' => 'test_db',
            'path' => '/tmp/test_db'
        ]);
    }

    /** @test */
    public function it_validates_database_creation()
    {
        // Act
        $response = $this->postJson('/api/v1/databases', [
            'name' => '', // Invalid: empty name
            'path' => ''  // Invalid: empty path
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'path']);
    }
}
```

### Database Integration Tests

```php
<?php
// tests/Integration/DatabaseIntegrationTest.php
namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\DatabaseService;
use App\Services\CollectionService;

class DatabaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $databaseService;
    protected $collectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseService = new DatabaseService();
        $this->collectionService = new CollectionService();
    }

    /** @test */
    public function it_manages_database_and_collection_lifecycle()
    {
        // Arrange
        $databaseData = [
            'name' => 'integration_test_db',
            'path': '/tmp/integration_test_db'
        ];

        // Act - Create database
        $database = $this->databaseService->create($databaseData);

        // Assert - Database created
        $this->assertTrue($database['success']);
        $this->assertDatabaseHas('databases', [
            'name' => 'integration_test_db'
        ]);

        // Act - Create collection
        $collectionData = [
            'name' => 'users',
            'database_id' => $database['data']['id'],
            'schema' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'email']
            ]
        ];

        $collection = $this->collectionService->create($collectionData);

        // Assert - Collection created
        $this->assertTrue($collection['success']);
        $this->assertDatabaseHas('collections', [
            'name' => 'users',
            'database_id' => $database['data']['id']
        ]);

        // Act - List collections
        $collections = $this->collectionService->listByDatabase($database['data']['id']);

        // Assert - Collections listed
        $this->assertCount(1, $collections['data']);
        $this->assertEquals('users', $collections['data'][0]['name']);

        // Act - Delete database
        $deleteResult = $this->databaseService->delete($database['data']['id']);

        // Assert - Database deleted
        $this->assertTrue($deleteResult['success']);
        $this->assertDatabaseMissing('databases', [
            'name' => 'integration_test_db'
        ]);
    }
}
```

### Authentication Integration Tests

```php
<?php
// tests/Feature/AuthenticationTest.php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_login_and_receive_token()
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'token',
                'user' => [
                    'id',
                    'email',
                    'name'
                ]
            ]
        ]);
    }

    /** @test */
    public function authenticated_user_can_access_protected_routes()
    {
        // Arrange
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->getJson('/api/v1/databases', [
            'Authorization' => 'Bearer ' . $token
        ]);

        // Assert
        $response->assertStatus(200);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        // Act
        $response = $this->getJson('/api/v1/databases');

        // Assert
        $response->assertStatus(401);
    }
}
```

## 🚀 End-to-End Testing

### Cypress E2E Tests

```javascript
// tests/e2e/auth.cy.js
describe("Authentication Flow", () => {
  beforeEach(() => {
    cy.visit("/login");
  });

  it("allows user to login with valid credentials", () => {
    // Arrange
    cy.intercept("POST", "/api/v1/auth/login").as("loginRequest");

    // Act
    cy.get('input[name="email"]').type("test@example.com");
    cy.get('input[name="password"]').type("password123");
    cy.get('button[type="submit"]').click();

    // Assert
    cy.wait("@loginRequest").its("response.statusCode").should("eq", 200);
    cy.url().should("include", "/dashboard");
    cy.contains("Welcome back").should("be.visible");
  });

  it("shows error for invalid login credentials", () => {
    // Arrange
    cy.intercept("POST", "/api/v1/auth/login", {
      statusCode: 401,
      body: {
        success: false,
        message: "Invalid credentials",
      },
    }).as("loginRequest");

    // Act
    cy.get('input[name="email"]').type("invalid@example.com");
    cy.get('input[name="password"]').type("wrongpassword");
    cy.get('button[type="submit"]').click();

    // Assert
    cy.wait("@loginRequest");
    cy.contains("Invalid credentials").should("be.visible");
  });

  it("allows user to logout", () => {
    // Arrange - Login first
    cy.login("test@example.com", "password123");

    // Act
    cy.get('[data-testid="user-menu"]').click();
    cy.contains("Logout").click();

    // Assert
    cy.url().should("include", "/login");
    cy.contains("Login").should("be.visible");
  });
});
```

### Database Management E2E Tests

```javascript
// tests/e2e/database.cy.js
describe("Database Management", () => {
  beforeEach(() => {
    cy.login("admin@example.com", "password123");
    cy.visit("/databases");
  });

  it("allows user to create new database", () => {
    // Arrange
    cy.intercept("POST", "/api/v1/databases").as("createDatabase");

    // Act
    cy.contains("Create Database").click();
    cy.get('input[name="name"]').type("test_database");
    cy.get('input[name="path"]').type("/tmp/test_db");
    cy.get('button[type="submit"]').click();

    // Assert
    cy.wait("@createDatabase").its("response.statusCode").should("eq", 201);
    cy.contains("test_database").should("be.visible");
  });

  it("allows user to view database details", () => {
    // Arrange - Create test database first
    cy.createDatabase("test_db", "/tmp/test_db");

    // Act
    cy.contains("test_db").click();

    // Assert
    cy.url().should("include", "/databases/test_db");
    cy.contains("Database Details").should("be.visible");
  });

  it("allows user to delete database", () => {
    // Arrange - Create test database first
    cy.createDatabase("test_db", "/tmp/test_db");

    // Arrange - Mock delete request
    cy.intercept("DELETE", "/api/v1/databases/*").as("deleteDatabase");

    // Act
    cy.contains("test_db").click();
    cy.contains("Delete Database").click();
    cy.contains("Confirm").click();

    // Assert
    cy.wait("@deleteDatabase").its("response.statusCode").should("eq", 200);
    cy.contains("test_db").should("not.exist");
  });
});
```

### Collection Management E2E Tests

```javascript
// tests/e2e/collection.cy.js
describe("Collection Management", () => {
  beforeEach(() => {
    cy.login("admin@example.com", "password123");
    cy.createDatabase("test_db", "/tmp/test_db");
    cy.visit("/databases/test_db/collections");
  });

  it("allows user to create collection with schema", () => {
    // Arrange
    cy.intercept("POST", "/api/v1/databases/test_db/collections").as(
      "createCollection",
    );

    // Act
    cy.contains("Create Collection").click();
    cy.get('input[name="name"]').type("users");

    // Add schema fields
    cy.contains("Add Field").click();
    cy.get('input[name="fields[0][name]"]').type("name");
    cy.get('select[name="fields[0][type]"]').select("string");

    cy.contains("Add Field").click();
    cy.get('input[name="fields[1][name]"]').type("email");
    cy.get('select[name="fields[1][type]"]').select("email");

    cy.get('button[type="submit"]').click();

    // Assert
    cy.wait("@createCollection").its("response.statusCode").should("eq", 201);
    cy.contains("users").should("be.visible");
  });

  it("allows user to insert documents", () => {
    // Arrange - Create collection first
    cy.createCollection("users", "test_db");

    // Arrange - Mock insert request
    cy.intercept(
      "POST",
      "/api/v1/databases/test_db/collections/users/documents",
    ).as("insertDocument");

    // Act
    cy.contains("users").click();
    cy.contains("Insert Document").click();

    // Fill document form
    cy.get('textarea[name="document"]').type(
      JSON.stringify(
        {
          name: "John Doe",
          email: "john@example.com",
          age: 30,
        },
        null,
        2,
      ),
    );

    cy.get('button[type="submit"]').click();

    // Assert
    cy.wait("@insertDocument").its("response.statusCode").should("eq", 201);
    cy.contains("John Doe").should("be.visible");
  });
});
```

## 📊 Performance Testing

### Load Testing with Artisan

```php
<?php
// tests/Performance/LoadTest.php
namespace Tests\Performance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_handles_concurrent_database_operations()
    {
        // Arrange
        $this->createTestDatabase();

        // Act - Simulate concurrent requests
        $startTime = microtime(true);
        $results = [];

        for ($i = 0; $i < 100; $i++) {
            $results[] = $this->performDatabaseOperation();
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Assert
        $this->assertLessThan(10, $totalTime); // Should complete in under 10 seconds

        foreach ($results as $result) {
            $this->assertTrue($result['success']);
        }
    }

    protected function createTestDatabase()
    {
        // Create test database and collection
        $databaseData = [
            'name' => 'load_test_db',
            'path' => ':memory:'
        ];

        $database = $this->postJson('/api/v1/databases', $databaseData);
        $databaseId = $database->json('data.id');

        $collectionData = [
            'name' => 'test_collection',
            'database_id' => $databaseId
        ];

        $this->postJson("/api/v1/databases/{$databaseId}/collections", $collectionData);
    }

    protected function performDatabaseOperation()
    {
        return $this->postJson('/api/v1/databases/load_test_db/collections/test_collection/documents', [
            'name' => 'Test User ' . rand(1, 1000),
            'email' => 'test' . rand(1, 1000) . '@example.com'
        ])->json();
    }
}
```

### Performance Monitoring Tests

```php
<?php
// tests/Performance/PerformanceTest.php
namespace Tests\Performance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_measures_query_performance()
    {
        // Arrange
        $this->createTestData(1000); // Create 1000 test records

        // Act - Measure query performance
        $startTime = microtime(true);
        $response = $this->getJson('/api/v1/databases/performance_test_db/collections/users/documents?limit=100');
        $endTime = microtime(true);

        $queryTime = $endTime - $startTime;

        // Assert
        $response->assertStatus(200);
        $this->assertLessThan(0.5, $queryTime); // Query should complete in under 500ms

        $data = $response->json();
        $this->assertCount(100, $data['data']['documents']);
    }

    protected function createTestData($count)
    {
        // Create test database and collection
        $database = $this->postJson('/api/v1/databases', [
            'name' => 'performance_test_db',
            'path' => ':memory:'
        ])->json();

        $this->postJson("/api/v1/databases/{$database['data']['id']}/collections", [
            'name' => 'users'
        ]);

        // Insert test data
        for ($i = 0; $i < $count; $i++) {
            $this->postJson("/api/v1/databases/{$database['data']['id']}/collections/users/documents", [
                'name' => 'User ' . $i,
                'email' => 'user' . $i . '@example.com',
                'age' => rand(18, 65)
            ]);
        }
    }
}
```

## 🔧 Test Configuration

### PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.3/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         verbose="true">

    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">tests/Integration</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/</directory>
            <file>src/Kernel.php</file>
        </exclude>
        <report>
            <html outputDirectory="coverage"/>
            <text outputFile="coverage.txt"/>
        </report>
    </coverage>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
    </php>
</phpunit>
```

### Cypress Configuration

```javascript
// cypress.config.js
const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    baseUrl: "http://localhost:8080",
    viewportWidth: 1280,
    viewportHeight: 720,
    video: false,
    screenshotOnRunFailure: true,
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    responseTimeout: 10000,
    chromeWebSecurity: false,
    env: {
      apiUrl: "http://localhost:8080/api/v1",
    },
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
});
```

### Test Environment Setup

```bash
# Create test database
php artisan db:create --database=testing

# Run migrations
php artisan migrate --database=testing

# Generate test data
php artisan db:seed --class=UserSeeder --database=testing
```

## 📋 Test Data Management

### Factories

```php
// database/factories/UserFactory.php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }
}
```

### Seeders

```php
// database/seeders/DatabaseSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Database;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create test users
        User::factory(10)->create();

        // Create admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin'
        ]);

        // Create test databases
        Database::factory(5)->create();
    }
}
```

### Test Datasets

```php
// tests/Datasets/TestData.php
namespace Tests\Datasets;

class TestData
{
    public static function getUserData()
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'user'
        ];
    }

    public static function getDatabaseData()
    {
        return [
            'name' => 'test_database',
            'path' => '/tmp/test_db',
            'encryption' => true,
            'backup_enabled' => true
        ];
    }

    public static function getCollectionData()
    {
        return [
            'name' => 'users',
            'schema' => [
                'name' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'email', 'required' => true],
                'age' => ['type' => 'integer', 'min' => 0]
            ]
        ];
    }
}
```

## 🚀 Continuous Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/test.yml
name: Test Suite

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    strategy:
      matrix:
        php-version: [8.0, 8.1, 8.2]
        test-suite: [unit, feature, integration]

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: pdo_sqlite, pdo_mysql, zip, gd, curl
          tools: composer

      - name: Install dependencies
        run: |
          composer install --no-progress --no-interaction
          npm install

      - name: Run tests
        if: matrix.test-suite == 'unit'
        run: |
          ./vendor/bin/phpunit --testsuite=Unit --coverage-clover=coverage-unit.xml

      - name: Run feature tests
        if: matrix.test-suite == 'feature'
        run: |
          ./vendor/bin/phpunit --testsuite=Feature --coverage-clover=coverage-feature.xml

      - name: Run integration tests
        if: matrix.test-suite == 'integration'
        run: |
          ./vendor/bin/phpunit --testsuite=Integration --coverage-clover=coverage-integration.xml

      - name: Upload coverage to Codecov
        if: matrix.test-suite == 'unit'
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage-unit.xml
          flags: unit
          name: codecov-unit

      - name: Upload coverage to Codecov
        if: matrix.test-suite == 'feature'
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage-feature.xml
          flags: feature
          name: codecov-feature

      - name: Upload coverage to Codecov
        if: matrix.test-suite == 'integration'
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage-integration.xml
          flags: integration
          name: codecov-integration

  e2e-test:
    runs-on: ubuntu-latest
    needs: test

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: "18"

      - name: Install dependencies
        run: |
          npm install
          npm install cypress --save-dev

      - name: Start application
        run: |
          php -S localhost:8080 -t public &
          sleep 10

      - name: Run E2E tests
        run: npx cypress run

      - name: Upload test results
        uses: actions/upload-artifact@v3
        if: always()
        with:
          name: e2e-test-results
          path: cypress/videos/
```

### Jenkins Pipeline

```groovy
// Jenkinsfile
pipeline {
    agent any

    environment {
        APP_ENV = 'testing'
        DB_CONNECTION = 'sqlite'
        DB_DATABASE = ':memory:'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Setup') {
            steps {
                sh 'composer install --no-progress --no-interaction'
                sh 'npm install'
            }
        }

        stage('Unit Tests') {
            steps {
                sh './vendor/bin/phpunit --testsuite=Unit --coverage-clover=coverage-unit.xml'
            }
        }

        stage('Feature Tests') {
            steps {
                sh './vendor/bin/phpunit --testsuite=Feature --coverage-clover=coverage-feature.xml'
            }
        }

        stage('Integration Tests') {
            steps {
                sh './vendor/bin/phpunit --testsuite=Integration --coverage-clover=coverage-integration.xml'
            }
        }

        stage('E2E Tests') {
            steps {
                sh 'npm run cypress:run'
            }
        }

        stage('Performance Tests') {
            steps {
                sh './vendor/bin/phpunit --testsuite=Performance'
            }
        }

        stage('Security Tests') {
            steps {
                sh './vendor/bin/phpunit --testsuite=Security'
            }
        }
    }

    post {
        always {
            echo 'Pipeline completed'
            archiveArtifacts artifacts: 'coverage-*.xml', fingerprint: true
            archiveArtifacts artifacts: 'cypress/screenshots/**/*', fingerprint: true
            archiveArtifacts artifacts: 'cypress/videos/**/*', fingerprint: true
        }

        success {
            echo 'Pipeline succeeded'
            emailext (
                subject: "Pipeline Success: ${env.JOB_NAME} - ${env.BUILD_NUMBER}",
                body: "Pipeline completed successfully. Coverage: ${env.COVERAGE}%",
                to: "${env.CHANGE_AUTHOR_EMAIL}, dev-team@company.com"
            )
        }

        failure {
            echo 'Pipeline failed'
            emailext (
                subject: "Pipeline Failed: ${env.JOB_NAME} - ${env.BUILD_NUMBER}",
                body: "Pipeline failed. Please check the logs.",
                to: "${env.CHANGE_AUTHOR_EMAIL}, dev-team@company.com"
            )
        }
    }
}
```

## 📊 Test Reporting

### Coverage Reports

```bash
# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage

# Generate coverage report in text format
./vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml

# Upload to Codecov
bash <(curl -s https://codecov.io/bash)
```

### Test Results

```bash
# Run tests with output
./vendor/bin/phpunit --verbose --testdox

# Run tests with JUnit output
./vendor/bin/phpunit --log-junit junit.xml

# Run tests with coverage
./vendor/bin/phpunit --coverage-clover coverage.xml
```

### Performance Metrics

```php
// tests/Metrics/PerformanceMetrics.php
namespace Tests\Metrics;

use Tests\TestCase;

class PerformanceMetrics extends TestCase
{
    /** @test */
    public function it_measures_test_execution_time()
    {
        $startTime = microtime(true);

        // Run test operations
        $this->assertTrue(true);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Log performance metrics
        $this->performanceLog('test_execution_time', $executionTime);

        // Assert performance threshold
        $this->assertLessThan(1, $executionTime);
    }

    protected function performanceLog($metric, $value)
    {
        // Log to performance tracking system
        // Could be integrated with monitoring tools
    }
}
```

## 🎯 Test Best Practices

### Writing Good Tests

1. **Follow AAA Pattern**: Arrange, Act, Assert
2. **Use Descriptive Names**: Test names should describe the behavior
3. **Test One Thing**: Each test should test one specific behavior
4. **Use Mocks Wisely**: Mock external dependencies
5. **Keep Tests Fast**: Unit tests should run in milliseconds
6. **Maintain Test Independence**: Tests should not depend on each other

### Test Organization

```
tests/
├── Unit/                    # Fast, isolated tests
│   ├── Services/
│   ├── Controllers/
│   └── Models/
├── Feature/                 # Integration tests
│   ├── Api/
│   ├── Authentication/
│   └── Authorization/
├── Integration/            # Full stack tests
│   ├── Database/
│   └── ExternalServices/
├── Performance/            # Performance tests
├── Security/               # Security tests
└── E2E/                   # End-to-end tests
    ├── auth/
    ├── database/
    └── collection/
```

### Test Data Management

1. **Use Factories**: Create test data with factories
2. **Clean Up**: Clean up after each test
3. **Use Test Datasets**: Reusable test data
4. **Seed Test Data**: Seed database with test data
5. **Isolate Test Data**: Each test should have its own data

### Continuous Testing

1. **Run Tests on Every Commit**: Automated CI/CD
2. **Measure Coverage**: Maintain high coverage standards
3. **Monitor Performance**: Track test execution time
4. **Review Test Results**: Regular review of test results
5. **Refactor Tests**: Keep tests maintainable

---

**Testing Checklist**:

- [ ] All unit tests pass
- [ ] Integration tests working
- [ ] E2E tests complete
- [ ] Performance tests within thresholds
- [ ] Security tests passing
- [ ] Code coverage > 80%
- [ ] Tests run in CI/CD pipeline
- [ ] Test documentation up to date

For testing support, contact: testing-support@bangrondb.io
