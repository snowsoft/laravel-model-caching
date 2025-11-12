<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Performance;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;

/**
 * Memory Usage Performance Tests
 *
 * Tests to measure memory consumption and ensure
 * cache doesn't cause memory leaks.
 */
class MemoryUsageTest extends IntegrationTestCase
{
    public function testMemoryUsageWithCaching()
    {
        $initialMemory = memory_get_usage(true);

        // Create and cache multiple queries
        for ($i = 0; $i < 100; $i++) {
            Author::where('name', "Test{$i}")->get();
        }

        $afterMemory = memory_get_usage(true);
        $memoryUsed = $afterMemory - $initialMemory;

        // Memory should be reasonable (less than 50MB for 100 queries)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, "Memory usage should be reasonable");
    }

    public function testMemoryDoesNotGrowUnbounded()
    {
        $memoryReadings = [];

        // Perform many cache operations
        for ($i = 0; $i < 1000; $i++) {
            Author::where('name', "Test{$i}")->get();

            // Sample memory every 100 iterations
            if ($i % 100 === 0) {
                $memoryReadings[] = memory_get_usage(true);
            }
        }

        // Memory growth should be linear or sub-linear, not exponential
        $first = $memoryReadings[0];
        $last = end($memoryReadings);
        $growth = $last - $first;

        // Growth should be reasonable (less than 100MB for 1000 queries)
        $this->assertLessThan(100 * 1024 * 1024, $growth, "Memory should not grow unbounded");
    }

    public function testCacheFlushReleasesMemory()
    {
        // Fill cache
        for ($i = 0; $i < 100; $i++) {
            Author::where('name', "Test{$i}")->get();
        }

        $memoryBeforeFlush = memory_get_usage(true);

        // Flush cache
        Author::flushCache();

        // Force garbage collection
        gc_collect_cycles();

        $memoryAfterFlush = memory_get_usage(true);

        // Memory should be released (or at least not increase)
        $this->assertLessThanOrEqual($memoryBeforeFlush, $memoryAfterFlush + (10 * 1024 * 1024),
            "Memory should be released after cache flush");
    }

    public function testMemoryWithLargeResults()
    {
        // Create many records
        factory(Author::class, 1000)->create();

        $initialMemory = memory_get_usage(true);

        // Cache large result set
        $authors = Author::all();

        $afterQueryMemory = memory_get_usage(true);

        // Query again (should use cache)
        $authors2 = Author::all();

        $afterCacheMemory = memory_get_usage(true);

        // Cache should not significantly increase memory
        $cacheMemoryIncrease = $afterCacheMemory - $afterQueryMemory;

        // Cache overhead should be minimal
        $this->assertLessThan(10 * 1024 * 1024, $cacheMemoryIncrease,
            "Cache should not significantly increase memory for large results");
    }

    public function testMemoryWithRelations()
    {
        // Create authors with books
        $authors = factory(Author::class, 50)->create();
        foreach ($authors as $author) {
            factory(\GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book::class, 10)->create([
                'author_id' => $author->id,
            ]);
        }

        $initialMemory = memory_get_usage(true);

        // Cache with relations
        Author::with('books')->get();

        $afterMemory = memory_get_usage(true);
        $memoryUsed = $afterMemory - $initialMemory;

        // Should be reasonable
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed,
            "Memory usage with relations should be reasonable");
    }
}
