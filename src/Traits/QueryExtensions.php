<?php

namespace Snowsoft\LaravelModelCaching\Traits;

/**
 * Query Extensions Trait
 *
 * Gelişmiş sorgu builder metodları
 */
trait QueryExtensions
{
    /**
     * Cache-aware pagination
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function cachedPaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $perPage = $perPage ?: $this->model->getPerPage();
        $page = $page ?: request()->input($pageName, 1);

        // Cache key'e pagination bilgisi ekle
        $this->macroKey = $this->macroKey ?? '';
        $this->macroKey .= "-paginate_{$perPage}_page_{$page}";

        return $this->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Cache-aware simple pagination
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function cachedSimplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $perPage = $perPage ?: $this->model->getPerPage();
        $page = $page ?: request()->input($pageName, 1);

        $this->macroKey = $this->macroKey ?? '';
        $this->macroKey .= "-simple_paginate_{$perPage}_page_{$page}";

        return $this->simplePaginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Cache-aware chunk
     *
     * @param int $count
     * @param callable $callback
     * @return bool
     */
    public function cachedChunk($count, callable $callback)
    {
        $page = 1;

        do {
            $this->macroKey = $this->macroKey ?? '';
            $this->macroKey .= "-chunk_{$count}_page_{$page}";

            $results = $this->forPage($page, $count)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Cache-aware cursor pagination
     *
     * @param int $perPage
     * @param array $columns
     * @param string $cursorName
     * @param \Illuminate\Pagination\Cursor|null $cursor
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    public function cachedCursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        $perPage = $perPage ?: $this->model->getPerPage();

        $this->macroKey = $this->macroKey ?? '';
        $this->macroKey .= "-cursor_paginate_{$perPage}";

        if ($cursor) {
            $this->macroKey .= "_cursor_{$cursor->parameter()}";
        }

        return $this->cursorPaginate($perPage, $columns, $cursorName, $cursor);
    }

    /**
     * Cache with expiration time
     *
     * @param int $seconds
     * @return $this
     */
    public function cacheFor(int $seconds)
    {
        $this->cacheExpiration = $seconds;
        return $this;
    }

    /**
     * Cache with custom tags
     *
     * @param array|string $tags
     * @return $this
     */
    public function cacheTags($tags)
    {
        $this->customCacheTags = is_array($tags) ? $tags : [$tags];
        return $this;
    }

    /**
     * Get cached count with filters
     *
     * @param array $filters
     * @return int
     */
    public function cachedCountWithFilters(array $filters = []): int
    {
        $query = clone $this;

        foreach ($filters as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        $query->macroKey = $query->macroKey ?? '';
        $query->macroKey .= '-count_filters_' . md5(serialize($filters));

        return $query->count();
    }

    /**
     * Get cached results with custom ordering
     *
     * @param string $column
     * @param string $direction
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function cachedOrderBy($column, $direction = 'asc')
    {
        $this->macroKey = $this->macroKey ?? '';
        $this->macroKey .= "-order_by_{$column}_{$direction}";

        return $this->orderBy($column, $direction)->get();
    }

    /**
     * Get cached distinct values
     *
     * @param string $column
     * @return \Illuminate\Support\Collection
     */
    public function cachedDistinct(string $column)
    {
        $this->macroKey = $this->macroKey ?? '';
        $this->macroKey .= "-distinct_{$column}";

        return $this->distinct()->pluck($column);
    }
}
