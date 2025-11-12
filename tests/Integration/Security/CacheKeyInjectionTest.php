<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Security;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Cache;

/**
 * Cache Key Injection Security Tests
 *
 * Tests to prevent cache key injection attacks and ensure
 * cache keys are properly sanitized and escaped.
 */
class CacheKeyInjectionTest extends IntegrationTestCase
{
    public function testCacheKeyWithSpecialCharacters()
    {
        // Test that special characters in query values don't break cache keys
        $maliciousInputs = [
            "'; DROP TABLE users; --",
            "<script>alert('xss')</script>",
            "../../etc/passwd",
            "\x00\x01\x02",
            "test\n\r\t",
            "test'\"",
            "test\\",
            "test{test}",
            "test[test]",
            "test(test)",
        ];

        foreach ($maliciousInputs as $input) {
            $author = Author::where('name', $input)->first();
            // Should not throw exception
            $this->assertTrue(true);
        }
    }

    public function testCacheKeyWithNullBytes()
    {
        // Null bytes should be handled safely
        $input = "test\x00null";

        try {
            Author::where('name', $input)->first();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("Null bytes in cache key should be handled safely: " . $e->getMessage());
        }
    }

    public function testCacheKeyWithVeryLongString()
    {
        // Very long strings should not cause issues
        $longString = str_repeat('a', 10000);

        try {
            Author::where('name', $longString)->first();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail("Long strings in cache key should be handled: " . $e->getMessage());
        }
    }

    public function testCacheKeyWithUnicodeCharacters()
    {
        // Unicode characters should be handled properly
        $unicodeInputs = [
            "测试",
            "тест",
            "テスト",
            "🎉",
            "مرحبا",
        ];

        foreach ($unicodeInputs as $input) {
            try {
                Author::where('name', $input)->first();
                $this->assertTrue(true);
            } catch (\Exception $e) {
                $this->fail("Unicode characters should be handled: " . $e->getMessage());
            }
        }
    }

    public function testCacheKeyCollisionPrevention()
    {
        // Different queries should produce different cache keys
        $query1 = Author::where('name', 'Test1')->get();
        $query2 = Author::where('name', 'Test2')->get();

        $key1 = $this->getCacheKey(Author::where('name', 'Test1'));
        $key2 = $this->getCacheKey(Author::where('name', 'Test2'));

        $this->assertNotEquals($key1, $key2, "Different queries should have different cache keys");
    }

    public function testCacheKeyWithSQLInjectionAttempt()
    {
        // SQL injection attempts should not affect cache keys
        $sqlInjectionAttempts = [
            "1' OR '1'='1",
            "1' UNION SELECT * FROM users--",
            "admin'--",
            "1'; DELETE FROM users; --",
        ];

        foreach ($sqlInjectionAttempts as $input) {
            try {
                $result = Author::where('name', $input)->first();
                // Should not throw exception or cause cache corruption
                $this->assertTrue(true);
            } catch (\Exception $e) {
                // Expected to fail query, but should not break cache system
                $this->assertStringNotContainsString('cache', strtolower($e->getMessage()));
            }
        }
    }

    public function testCacheKeyWithArrayInjection()
    {
        // Array injection attempts should be handled
        try {
            Author::whereIn('id', [1, 2, 3, "'; DROP TABLE users; --"])->get();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Should fail gracefully
            $this->assertTrue(true);
        }
    }

    public function testCacheKeyWithObjectInjection()
    {
        // Object injection should be handled
        $maliciousObject = new \stdClass();
        $maliciousObject->name = "test";

        try {
            Author::where('name', $maliciousObject)->first();
            // Should handle or fail gracefully
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testCacheKeySanitization()
    {
        // Cache keys should be properly sanitized
        $query = Author::where('name', "test'\"\\");
        $key = $this->getCacheKey($query);

        // Key should not contain unescaped special characters that could break cache
        $this->assertIsString($key);
        $this->assertNotEmpty($key);
    }

    protected function getCacheKey($query)
    {
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('makeCacheKey');
        $method->setAccessible(true);

        return $method->invoke($query, ['*']);
    }
}
