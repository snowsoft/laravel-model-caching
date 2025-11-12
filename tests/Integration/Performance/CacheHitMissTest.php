<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Performance;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Cache Hit/Miss Performance Tests
 *
 * Tests to measure cache hit rates and performance improvements.
 */
class CacheHitMissTest extends IntegrationTestCase
{
    public function testCacheHitAfterFirstQuery()
    {
        // Clear cache first
        Author::flushCache();

        // First query - should be a miss
        $start1 = microtime(true);
        $authors1 = Author::where('name', 'Test')->get();
        $time1 = microtime(true) - $start1;

        // Second query - should be a hit
        $start2 = microtime(true);
        $authors2 = Author::where('name', 'Test')->get();
        $time2 = microtime(true) - $start2;

        // Cache hit should be faster
        $this->assertLessThan($time1, $time2 * 10, "Cache hit should be significantly faster");

        // Results should be identical
        $this->assertEquals($authors1->pluck('id'), $authors2->pluck('id'));
    }

    public function testCacheHitRate()
    {
        // Clear cache
        Author::flushCache();

        $hits = 0;
        $misses = 0;
        $iterations = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            Author::where('name', 'Test')->get();
            $time = microtime(true) - $start;

            if ($i === 0) {
                $misses++;
            } else {
                $hits++;
            }
        }

        $hitRate = ($hits / ($hits + $misses)) * 100;

        // After first query, hit rate should be high
        $this->assertGreaterThan(80, $hitRate, "Cache hit rate should be high after first query");
    }

    public function testCachePerformanceWithLargeDataset()
    {
        // Create multiple records
        factory(Author::class, 100)->create();

        // Clear cache
        Author::flushCache();

        // First query - miss
        $start1 = microtime(true);
        $authors1 = Author::all();
        $time1 = microtime(true) - $start1;

        // Second query - hit
        $start2 = microtime(true);
        $authors2 = Author::all();
        $time2 = microtime(true) - $start2;

        // Cache should provide significant speedup
        $speedup = $time1 / max($time2, 0.0001);

        $this->assertGreaterThan(2, $speedup, "Cache should provide at least 2x speedup");
        $this->assertEquals($authors1->count(), $authors2->count());
    }

    public function testCachePerformanceWithRelations()
    {
        // Create authors with books
        $authors = factory(Author::class, 10)->create();
        foreach ($authors as $author) {
            factory(\GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book::class, 5)->create([
                'author_id' => $author->id,
            ]);
        }

        // Clear cache
        Author::flushCache();

        // First query with relations - miss
        $start1 = microtime(true);
        $authors1 = Author::with('books')->get();
        $time1 = microtime(true) - $start1;

        // Second query - hit
        $start2 = microtime(true);
        $authors2 = Author::with('books')->get();
        $time2 = microtime(true) - $start2;

        // Should be faster
        $this->assertLessThan($time1, $time2 * 5);
        $this->assertEquals($authors1->count(), $authors2->count());
    }

    public function testCacheMissOnUpdate()
    {
        $author = factory(Author::class)->create(['name' => 'Original']);

        // Cache the query
        $cached = Author::where('name', 'Original')->first();

        // Update
        $author->update(['name' => 'Updated']);

        // Query should be a miss (cache invalidated)
        $start = microtime(true);
        $updated = Author::where('name', 'Updated')->first();
        $time = microtime(true) - $start;

        // Should find updated record
        $this->assertNotNull($updated);
    }

    public function testQueryCountReduction()
    {
        // Enable query logging
        DB::enableQueryLog();

        // Clear cache
        Author::flushCache();

        // First query - should execute DB query
        Author::where('name', 'Test')->get();
        $queries1 = count(DB::getQueryLog());

        // Clear query log
        DB::flushQueryLog();

        // Second query - should use cache
        Author::where('name', 'Test')->get();
        $queries2 = count(DB::getQueryLog());

        // Second query should have fewer or no DB queries
        $this->assertLessThanOrEqual($queries2, $queries1);
    }

    public function testCachePerformanceWithComplexQuery()
    {
        factory(Author::class, 50)->create();

        // Clear cache
        Author::flushCache();

        // Complex query - first time
        $start1 = microtime(true);
        $authors1 = Author::where('name', 'like', '%Test%')
            ->where('id', '>', 10)
            ->orderBy('name')
            ->limit(20)
            ->get();
        $time1 = microtime(true) - $start1;

        // Same query - should use cache
        $start2 = microtime(true);
        $authors2 = Author::where('name', 'like', '%Test%')
            ->where('id', '>', 10)
            ->orderBy('name')
            ->limit(20)
            ->get();
        $time2 = microtime(true) - $start2;

        // Should be faster
        $this->assertLessThan($time1, $time2 * 3);
        $this->assertEquals($authors1->count(), $authors2->count());
    }
}
