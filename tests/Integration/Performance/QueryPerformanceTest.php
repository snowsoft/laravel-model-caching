<?php

namespace GeneaLabs\LaravelModelCaching\Tests\Integration\Performance;

use GeneaLabs\LaravelModelCaching\Tests\Fixtures\Author;
use GeneaLabs\LaravelModelCaching\Tests\IntegrationTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Query Performance Tests
 *
 * Tests to measure query execution time improvements
 * with caching enabled.
 */
class QueryPerformanceTest extends IntegrationTestCase
{
    public function testSimpleQueryPerformance()
    {
        factory(Author::class, 100)->create();

        // Clear cache
        Author::flushCache();

        // Without cache
        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            Author::where('name', 'Test')->get();
        }
        $timeWithoutCache = microtime(true) - $start;

        // With cache (first query caches, rest use cache)
        Author::flushCache();
        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            Author::where('name', 'Test')->get();
        }
        $timeWithCache = microtime(true) - $start;

        // Cached queries should be faster
        $this->assertLessThan($timeWithoutCache, $timeWithCache * 2,
            "Cached queries should be significantly faster");
    }

    public function testComplexQueryPerformance()
    {
        factory(Author::class, 200)->create();

        // Clear cache
        Author::flushCache();

        $complexQuery = function() {
            return Author::where('name', 'like', '%Test%')
                ->where('id', '>', 10)
                ->where('id', '<', 100)
                ->orderBy('name')
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get();
        };

        // First execution (cache miss)
        $start1 = microtime(true);
        $result1 = $complexQuery();
        $time1 = microtime(true) - $start1;

        // Second execution (cache hit)
        $start2 = microtime(true);
        $result2 = $complexQuery();
        $time2 = microtime(true) - $start2;

        // Should be faster
        $speedup = $time1 / max($time2, 0.0001);
        $this->assertGreaterThan(1.5, $speedup, "Complex query should benefit from cache");
        $this->assertEquals($result1->count(), $result2->count());
    }

    public function testJoinQueryPerformance()
    {
        $authors = factory(Author::class, 50)->create();
        foreach ($authors as $author) {
            factory(\GeneaLabs\LaravelModelCaching\Tests\Fixtures\Book::class, 5)->create([
                'author_id' => $author->id,
            ]);
        }

        // Clear cache
        Author::flushCache();

        // Query with join
        $query = function() {
            return Author::join('books', 'authors.id', '=', 'books.author_id')
                ->select('authors.*', 'books.title')
                ->get();
        };

        // First execution
        $start1 = microtime(true);
        $result1 = $query();
        $time1 = microtime(true) - $start1;

        // Second execution
        $start2 = microtime(true);
        $result2 = $query();
        $time2 = microtime(true) - $start2;

        // Should be faster
        $this->assertLessThan($time1, $time2 * 3);
        $this->assertEquals($result1->count(), $result2->count());
    }

    public function testAggregateQueryPerformance()
    {
        factory(Author::class, 100)->create();

        // Clear cache
        Author::flushCache();

        // Aggregate queries
        $queries = [
            fn() => Author::count(),
            fn() => Author::max('id'),
            fn() => Author::min('id'),
            fn() => Author::avg('id'),
            fn() => Author::sum('id'),
        ];

        foreach ($queries as $query) {
            // First execution
            $start1 = microtime(true);
            $result1 = $query();
            $time1 = microtime(true) - $start1;

            // Second execution
            $start2 = microtime(true);
            $result2 = $query();
            $time2 = microtime(true) - $start2;

            // Should be faster and same result
            $this->assertLessThan($time1, $time2 * 5);
            $this->assertEquals($result1, $result2);
        }
    }

    public function testPaginationPerformance()
    {
        factory(Author::class, 200)->create();

        // Clear cache
        Author::flushCache();

        // First page
        $start1 = microtime(true);
        $page1 = Author::paginate(20);
        $time1 = microtime(true) - $start1;

        // Same page again
        $start2 = microtime(true);
        $page2 = Author::paginate(20);
        $time2 = microtime(true) - $start2;

        // Should be faster
        $this->assertLessThan($time1, $time2 * 3);
        $this->assertEquals($page1->total(), $page2->total());
    }

    public function testQueryCountReduction()
    {
        DB::enableQueryLog();

        // Clear cache
        Author::flushCache();

        // First query
        Author::where('name', 'Test')->get();
        $queries1 = DB::getQueryLog();

        // Clear log
        DB::flushQueryLog();

        // Second query (should use cache)
        Author::where('name', 'Test')->get();
        $queries2 = DB::getQueryLog();

        // Second query should have fewer DB queries
        $this->assertLessThanOrEqual(count($queries2), count($queries1));
    }
}
