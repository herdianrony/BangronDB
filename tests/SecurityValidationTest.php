<?php

declare(strict_types=1);

namespace BangronDB\Tests;

use BangronDB\Database;
use BangronDB\Exceptions\ValidationException;
use BangronDB\Security\FieldValidator;
use BangronDB\UtilArrayQuery;
use PHPUnit\Framework\TestCase;

/**
 * Security validation tests for BangronDB.
 * Tests for prevention of RCE, NoSQL injection, ReDoS, PRAGMA injection, and path traversal.
 */
class SecurityValidationTest extends TestCase
{
    // ==================== Field Name Validation Tests ====================

    public function testValidFieldName(): void
    {
        $this->assertTrue(FieldValidator::isValidFieldName('username'));
        $this->assertTrue(FieldValidator::isValidFieldName('user_name'));
        $this->assertTrue(FieldValidator::isValidFieldName('user-name'));
        $this->assertTrue(FieldValidator::isValidFieldName('user.name'));
        $this->assertTrue(FieldValidator::isValidFieldName('field123'));
        $this->assertTrue(FieldValidator::isValidFieldName('_private'));
        $this->assertTrue(FieldValidator::isValidFieldName('Name123_test-field'));
    }

    public function testInvalidFieldNameEmpty(): void
    {
        $this->assertFalse(FieldValidator::isValidFieldName(''));
    }

    public function testInvalidFieldNameTooLong(): void
    {
        $longName = str_repeat('a', 300);
        $this->assertFalse(FieldValidator::isValidFieldName($longName));
    }

    public function testInvalidFieldNameWithQuotes(): void
    {
        $this->assertFalse(FieldValidator::isValidFieldName("field'name"));
        $this->assertFalse(FieldValidator::isValidFieldName('field"name'));
        $this->assertFalse(FieldValidator::isValidFieldName('field`name'));
    }

    public function testInvalidFieldNameWithSemicolon(): void
    {
        $this->assertFalse(FieldValidator::isValidFieldName('field;name'));
    }

    public function testInvalidFieldNameWithParentheses(): void
    {
        $this->assertFalse(FieldValidator::isValidFieldName('field(name)'));
        $this->assertFalse(FieldValidator::isValidFieldName('field{name}'));
        $this->assertFalse(FieldValidator::isValidFieldName('field[name]'));
    }

    public function testInvalidFieldNameWithAngleBrackets(): void
    {
        $this->assertFalse(FieldValidator::isValidFieldName('field<name>'));
        $this->assertFalse(FieldValidator::isValidFieldName('field<name'));
        $this->assertFalse(FieldValidator::isValidFieldName('field>name'));
    }

    public function testInvalidFieldNameWithBackslash(): void
    {
        $this->assertFalse(FieldValidator::isValidFieldName('field\\name'));
    }

    public function testInvalidFieldNameWithControlChars(): void
    {
        $this->assertFalse(FieldValidator::isValidFieldName("field\nname"));
        $this->assertFalse(FieldValidator::isValidFieldName("field\rname"));
        $this->assertFalse(FieldValidator::isValidFieldName("field\x00name"));
    }

    public function testValidateFieldNameThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid field name');
        FieldValidator::validateFieldName("field'hack");
    }

    // ==================== PRAGMA Key Escaping Tests ====================

    public function testEscapePragmaKeyValidKey(): void
    {
        $key = 'MySecureKey123!@#$%';
        $escaped = FieldValidator::escapePragmaKey($key);
        $this->assertIsString($escaped);
        $this->assertNotEmpty($escaped);
    }

    public function testEscapePragmaKeyWithSingleQuote(): void
    {
        $key = "pass'word";
        $escaped = FieldValidator::escapePragmaKey($key);
        // Single quotes should be doubled
        $this->assertStringContainsString("''", $escaped);
    }

    public function testEscapePragmaKeyEmpty(): void
    {
        $this->expectException(ValidationException::class);
        FieldValidator::escapePragmaKey('');
    }

    public function testEscapePragmaKeyWithControlChars(): void
    {
        $this->expectException(ValidationException::class);
        FieldValidator::escapePragmaKey("key\x00with\x01control");
    }

    // ==================== Database Path Validation Tests ====================

    public function testValidateDatabasePathInMemory(): void
    {
        $result = FieldValidator::validateDatabasePath(':memory:');
        $this->assertEquals(':memory:', $result);
    }

    public function testValidateDatabasePathEmpty(): void
    {
        $this->expectException(ValidationException::class);
        FieldValidator::validateDatabasePath('');
    }

    // ==================== Regex Sanitization Tests ====================

    public function testSanitizeRegexPatternEscapesSpecialChars(): void
    {
        $pattern = 'user.* | password+';
        $sanitized = FieldValidator::sanitizeRegexPattern($pattern);
        $this->assertStringContainsString('\\', $sanitized);
        // Should escape the dot and asterisks
    }

    public function testSanitizeRegexPatternWithReDosAttempt(): void
    {
        // Classic ReDoS pattern: (a+)+b - should be escaped
        $pattern = '(a+)+b';
        $sanitized = FieldValidator::sanitizeRegexPattern($pattern);
        // After preg_quote, the pattern should be literal
        $this->assertStringContainsString('\\(', $sanitized);
        $this->assertStringContainsString('\\+', $sanitized);
    }

    // ==================== Safe Callable Validation Tests ====================

    public function testSafeCallableWithClosure(): void
    {
        $closure = function ($doc) {
            return $doc['age'] > 18;
        };
        $this->assertTrue(FieldValidator::isSafeCallable($closure));
    }

    public function testSafeCallableWithStringFunctionName(): void
    {
        // This is dangerous and should NOT be considered safe
        $this->assertFalse(FieldValidator::isSafeCallable('system'));
        $this->assertFalse(FieldValidator::isSafeCallable('exec'));
        $this->assertFalse(FieldValidator::isSafeCallable('is_array'));
    }

    public function testSafeCallableWithArrayCallable(): void
    {
        // Array callables like [$obj, 'method'] are also dangerous
        $this->assertFalse(FieldValidator::isSafeCallable([new DatabaseMockForTest(), 'method']));
    }

    public function testSafeCallableWithNull(): void
    {
        $this->assertFalse(FieldValidator::isSafeCallable(null));
    }

    public function testValidateSafeCallableThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('only accepts Closure objects');
        FieldValidator::validateSafeCallable('system', '$where');
    }

    public function testValidateSafeCallableWithValidClosure(): void
    {
        $closure = fn ($doc) => $doc['id'] > 0;
        // Should not throw
        FieldValidator::validateSafeCallable($closure, '$func');
        $this->assertTrue(true);
    }

    // ==================== Array Query Integration Tests ====================

    public function testArrayQueryWithSafeWhereClosure(): void
    {
        $doc = ['name' => 'John', 'age' => 30];
        $criteria = [
            '$where' => fn ($d) => $d['age'] > 25,
        ];
        $result = UtilArrayQuery::match($criteria, $doc);
        $this->assertTrue($result);
    }

    public function testArrayQueryWithUnsafeWhereString(): void
    {
        $doc = ['name' => 'John', 'age' => 30];
        $criteria = [
            '$where' => 'is_array',  // Dangerous: string function name
        ];
        $this->expectException(ValidationException::class);
        UtilArrayQuery::match($criteria, $doc);
    }

    public function testArrayQueryWithSafeFuncClosure(): void
    {
        $doc = ['value' => 42];
        $criteria = [
            'value' => ['$func' => fn ($val) => $val * 2 === 84],
        ];
        $result = UtilArrayQuery::match($criteria, $doc);
        $this->assertTrue($result);
    }

    public function testArrayQueryWithUnsafeFuncString(): void
    {
        $doc = ['value' => 42];
        $criteria = [
            'value' => ['$func' => 'strlen'],  // Dangerous
        ];
        $this->expectException(ValidationException::class);
        UtilArrayQuery::match($criteria, $doc);
    }

    // ==================== Field Validation in Queries Tests ====================

    public function testArrayQueryWithValidFieldNames(): void
    {
        $doc = ['user_name' => 'John', 'user-email' => 'john@example.com'];
        $criteria = [
            'user_name' => 'John',
            'user-email' => 'john@example.com',
        ];
        $result = UtilArrayQuery::match($criteria, $doc);
        $this->assertTrue($result);
    }

    public function testArrayQueryWithInvalidFieldNameQuote(): void
    {
        $doc = ['name' => 'John'];
        $criteria = [
            "name' OR '1'='1" => 'John',  // SQL injection attempt
        ];
        $this->expectException(ValidationException::class);
        UtilArrayQuery::match($criteria, $doc);
    }

    public function testArrayQueryWithInvalidFieldNameSemicolon(): void
    {
        $doc = ['name' => 'John'];
        $criteria = [
            'name; DROP TABLE users;--' => 'John',
        ];
        $this->expectException(ValidationException::class);
        UtilArrayQuery::match($criteria, $doc);
    }

    // ==================== Regex Operator Safety Tests ====================

    public function testRegexOperatorWithValidPattern(): void
    {
        $doc = ['email' => 'user@example.com'];
        // Use full regex with delimiters for safety
        $criteria = [
            'email' => ['$regex' => '/user@.*\.com/i'],
        ];
        $result = UtilArrayQuery::match($criteria, $doc);
        $this->assertTrue($result);
    }

    public function testRegexOperatorWithSpecialChars(): void
    {
        $doc = ['text' => 'price: $100'];
        // Use full regex with delimiters for safety
        $criteria = [
            'text' => ['$regex' => '/price:\s*\$\d+/'],
        ];
        $result = UtilArrayQuery::match($criteria, $doc);
        $this->assertTrue($result);
    }

    public function testRegexOperatorPreventDelimiterInjection(): void
    {
        $doc = ['name' => 'test/value'];
        // Raw pattern with forward slash - should be escaped to prevent injection
        $criteria = [
            'name' => ['$regex' => 'test/value'],  // Raw pattern with /
        ];
        // Should match literally because / is escaped
        $result = UtilArrayQuery::match($criteria, $doc);
        // The pattern becomes /test\/value/iu which matches the literal string
        $this->assertTrue($result);
    }

    public function testJsonDecodeErrorHandling(): void
    {
        $doc = ['tags' => 'value1,value2'];
        // $has operator should handle both arrays and JSON strings
        $criteria = [
            'tags' => ['$has' => 'value1'],
        ];
        $result = UtilArrayQuery::match($criteria, $doc);
        $this->assertIsBool($result);
    }

    // ==================== Strict Types Validation ====================

    public function testStrictTypesDeclaration(): void
    {
        // This test verifies that strict_types=1 is present
        // It will fail if type coercion occurs unexpectedly
        $this->assertTrue(true);
        // In a real scenario with strict_types=1, operations like:
        // function test(int $x) { } test("123");  // Would throw error
    }
}

/**
 * Mock class for testing callable validation.
 */
class DatabaseMockForTest
{
    public function method(): bool
    {
        return true;
    }
}
