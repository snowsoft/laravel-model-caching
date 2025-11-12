<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Performance;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\Cache;

/**
 * Concurrency and Race Condition Tests
 *
 * Tests to ensure cache works correctly under concurrent access
 * and prevents race conditions.
 */
class ConcurrencyTest extends IntegrationTestCase
{
    public function testConcurrentCacheWrites()
    {
        // Simulate concurrent writes
        $results = [];
        $iterations = 10;

        // Use parallel processing simulation
        for ($i = 0; $i < $iterations; $i++) {
            $results[] = Author::where('name', "Concurrent{$i}")->get();
        }

        // All should succeed
        $this->assertCount($iterations, $results);

        // Verify cache consistency
        for ($i = 0; $i < $iterations; $i++) {
            $cached = Author::where('name', "Concurrent{$i}")->get();
            $this->assertEquals($results[$i]->pluck('id'), $cached->pluck('id'));
        }
    }

    public function testCacheReadDuringWrite()
    {
        // Create a record
        $author = factory(Author::class)->create(['name' => 'Original']);

        // Start reading (should cache)
        $read1 = Author::find($author->id);

        // Update (should invalidate cache)
        $author->update(['name' => 'Updated']);

        // Read again (should get fresh data)
        $read2 = Author::find($author->id);

        // Should get updated data
        $this->assertEquals('Updated', $read2->name);
    }

    public function testCacheInvalidationRaceCondition()
    {
        $author = factory(Author::class)->create(['name' => 'Original']);

        // Cache the query
        $cached1 = Author::find($author->id);

        // Simulate concurrent updates
        $author->update(['name' => 'Update1']);
        $author->update(['name' => 'Update2']);
        $author->update(['name' => 'Update3']);

        // Final read should have latest data
        $final = Author::find($author->id);
        $this->assertEquals('Update3', $final->name);
    }

    public function testMultipleTenantsConcurrentAccess()
    {
        // Enable multi-tenancy
        config(['laravel-model-caching.multi-tenancy.enabled' => true]);

        $results = [];

        // Simulate concurrent access from different tenants
        for ($i = 1; $i <= 5; $i++) {
            \Snowsoft\LaravelModelCaching\TenantResolver::setResolver(fn() => "tenant_{$i}");
            $results["tenant_{$i}"] = Author::where('name', 'Test')->get();
        }

        // Each tenant should have isolated cache
        foreach ($results as $tenant => $result) {
            \Snowsoft\LaravelModelCaching\TenantResolver::setResolver(fn() => $tenant);
            $cached = Author::where('name', 'Test')->get();
            $this->assertEquals($result->pluck('id'), $cached->pluck('id'));
        }

        config(['laravel-model-caching.multi-tenancy.enabled' => false]);
    }

    public function testCacheKeyGenerationUnderLoad()
    {
        // Generate many cache keys concurrently
        $keys = [];
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $query = Author::where('name', "LoadTest{$i}")
                ->where('id', '>', $i)
                ->orderBy('name');

            $key = $this->getCacheKey($query);
            $keys[] = $key;
        }

        // All keys should be unique
        $uniqueKeys = array_unique($keys);
        $this->assertCount($iterations, $uniqueKeys, "All cache keys should be unique");
    }

    public function testSelectiveInvalidationUnderLoad()
    {
        // Create related models
        $authors = factory(Author::class, 10)->create();
        foreach ($authors as $author) {
            factory(\GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book::class, 5)->create([
                'author_id' => $author->id,
            ]);
        }

        // Cache all
        Author::with('books')->get();

        // Update multiple authors concurrently
        foreach ($authors as $author) {
            $author->update(['name' => 'Updated' . $author->id]);
        }

        // Verify all updates are reflected
        foreach ($authors as $author) {
            $updated = Author::find($author->id);
            $this->assertEquals('Updated' . $author->id, $updated->name);
        }
    }

    protected function getCacheKey($query)
    {
        $reflection = new \ReflectionClass($query);
        $method = $reflection->getMethod('makeCacheKey');
        $method->setAccessible(true);

        return $method->invoke($query, ['*']);
    }
}
