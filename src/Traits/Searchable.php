<?php

namespace Snowsoft\LaravelModelCaching\Traits;

use Illuminate\Container\Container;
use Snowsoft\LaravelModelCaching\Services\SearchIndexService;

/**
 * Searchable Trait
 *
 * Full-text search ve arama sorguları için cache desteği
 * Development/test ortamlarında MongoDB veya PostgreSQL index desteği
 */
trait Searchable
{
    /**
     * Full-text search with caching and optional index support
     *
     * @param string $term
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function search(string $term, array $columns = [])
    {
        if (empty($columns)) {
            // Model'de searchable property'si varsa kullan
            $columns = $this->getSearchableColumns();
        }

        $query = $this->newQuery();

        if (empty($columns)) {
            return $query->whereRaw('1 = 0'); // No results if no searchable columns
        }

        // Index servisi aktifse, index'ten arama yap
        $indexService = Container::getInstance()->make(SearchIndexService::class);
        if ($indexService->isEnabled()) {
            $model = $this->getModel() ?? $this->model ?? null;
            if ($model) {
                $indexedIds = $indexService->searchIndex($model, $term, $columns);
                if (!empty($indexedIds)) {
                    // Index'ten bulunan ID'lerle sorgu oluştur
                    $query->whereIn($model->getKeyName(), $indexedIds);
                    $query->macroKey = $query->macroKey ?? '';
                    $query->macroKey .= '-search_index_' . md5($term . implode('_', $columns));
                    return $query;
                }
            }
        }

        // Fallback: Normal LIKE sorgusu
        $query->where(function ($q) use ($term, $columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });

        // Cache key için search term ekle
        $query->macroKey = $query->macroKey ?? '';
        $query->macroKey .= '-search_' . md5($term . implode('_', $columns));

        return $query;
    }

    /**
     * Advanced search with multiple terms
     *
     * @param array $terms
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function searchMultiple(array $terms, array $columns = [])
    {
        if (empty($columns)) {
            $columns = $this->getSearchableColumns();
        }

        $query = $this->newQuery();

        if (empty($columns)) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function ($q) use ($terms, $columns) {
            foreach ($terms as $term) {
                $q->where(function ($subQ) use ($term, $columns) {
                    foreach ($columns as $column) {
                        $subQ->orWhere($column, 'like', "%{$term}%");
                    }
                });
            }
        });

        $query->macroKey = $query->macroKey ?? '';
        $query->macroKey .= '-search_multiple_' . md5(serialize($terms) . implode('_', $columns));

        return $query;
    }

    /**
     * Search with filters
     *
     * @param string $term
     * @param array $filters
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function searchWithFilters(string $term, array $filters = [], array $columns = [])
    {
        $query = $this->search($term, $columns);

        foreach ($filters as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        $query->macroKey = $query->macroKey ?? '';
        $query->macroKey .= '-filters_' . md5(serialize($filters));

        return $query;
    }

    /**
     * Get searchable columns
     *
     * @return array
     */
    protected function getSearchableColumns(): array
    {
        $model = $this->getModel() ?? $this->model ?? null;

        if (!$model) {
            return [];
        }

        // Model'de searchable property'si varsa kullan
        if (property_exists($model, 'searchable') && is_array($model->searchable)) {
            return $model->searchable;
        }

        // Varsayılan: name, title, description gibi yaygın kolonlar
        $defaultColumns = ['name', 'title', 'description', 'content'];

        try {
            $tableColumns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
            return array_intersect($defaultColumns, $tableColumns);
        } catch (\Exception $e) {
            // Fallback: sadece default columns
            return $defaultColumns;
        }
    }

    /**
     * Search with relevance scoring (for full-text search)
     *
     * @param string $term
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function searchRelevant(string $term, array $columns = [])
    {
        if (empty($columns)) {
            $columns = $this->getSearchableColumns();
        }

        $query = $this->newQuery();

        // Add relevance scoring
        $relevanceSelect = [];
        foreach ($columns as $column) {
            $relevanceSelect[] = "CASE WHEN {$column} LIKE '%{$term}%' THEN 1 ELSE 0 END as relevance_{$column}";
        }

        $query->selectRaw('*, (' . implode(' + ', $relevanceSelect) . ') as relevance')
            ->where(function ($q) use ($term, $columns) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            })
            ->orderBy('relevance', 'desc');

        $query->macroKey = $query->macroKey ?? '';
        $query->macroKey .= '-search_relevant_' . md5($term . implode('_', $columns));

        return $query;
    }
}
