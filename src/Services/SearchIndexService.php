<?php

namespace Snowsoft\LaravelModelCaching\Services;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Search Index Service
 *
 * Arama ve indexleme için ara veritabanı yönetimi
 * Development/test ortamlarında MongoDB veya PostgreSQL kullanılabilir
 */
class SearchIndexService
{
    protected $config;
    protected $connection;
    protected $driver;
    protected $enabled;

    public function __construct()
    {
        $this->config = Container::getInstance()->make('config');
        $this->enabled = $this->config->get('laravel-model-caching.search-index.enabled', false);
        $this->driver = $this->config->get('laravel-model-caching.search-index.driver', null);
        $this->connection = $this->config->get('laravel-model-caching.search-index.connection', null);
    }

    /**
     * Search index servisi aktif mi?
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        // Sadece production dışında aktif
        if (app()->environment('production')) {
            return false;
        }

        return $this->enabled && $this->driver && $this->connection;
    }

    /**
     * Model için index oluştur
     *
     * @param Model $model
     * @param array $searchableColumns
     * @return bool
     */
    public function createIndex(Model $model, array $searchableColumns = []): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            if ($this->driver === 'mongodb') {
                return $this->createMongoIndex($model, $searchableColumns);
            } elseif ($this->driver === 'pgsql') {
                return $this->createPostgresIndex($model, $searchableColumns);
            }
        } catch (\Exception $e) {
            Log::warning('Search index creation failed', [
                'model' => get_class($model),
                'driver' => $this->driver,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Model için index'i güncelle
     *
     * @param Model $model
     * @param array $data
     * @return bool
     */
    public function updateIndex(Model $model, array $data): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            if ($this->driver === 'mongodb') {
                return $this->updateMongoIndex($model, $data);
            } elseif ($this->driver === 'pgsql') {
                return $this->updatePostgresIndex($model, $data);
            }
        } catch (\Exception $e) {
            Log::warning('Search index update failed', [
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Model için index'i sil
     *
     * @param Model $model
     * @return bool
     */
    public function deleteIndex(Model $model): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            if ($this->driver === 'mongodb') {
                return $this->deleteMongoIndex($model);
            } elseif ($this->driver === 'pgsql') {
                return $this->deletePostgresIndex($model);
            }
        } catch (\Exception $e) {
            Log::warning('Search index deletion failed', [
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Index'ten arama yap
     *
     * @param Model $model
     * @param string $term
     * @param array $searchableColumns
     * @return array Model ID'leri
     */
    public function searchIndex(Model $model, string $term, array $searchableColumns = []): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            if ($this->driver === 'mongodb') {
                return $this->searchMongoIndex($model, $term, $searchableColumns);
            } elseif ($this->driver === 'pgsql') {
                return $this->searchPostgresIndex($model, $term, $searchableColumns);
            }
        } catch (\Exception $e) {
            Log::warning('Search index query failed', [
                'model' => get_class($model),
                'term' => $term,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Tüm model'ler için index'i yeniden oluştur
     *
     * @param string $modelClass
     * @return int Number of indexed records
     */
    public function rebuildIndex(string $modelClass): int
    {
        if (!$this->isEnabled() || !class_exists($modelClass)) {
            return 0;
        }

        $model = new $modelClass;
        $count = 0;

        try {
            // Tüm kayıtları al ve index'le
            $records = $model->all();
            foreach ($records as $record) {
                if ($this->updateIndex($record, $record->toArray())) {
                    $count++;
                }
            }
        } catch (\Exception $e) {
            Log::error('Index rebuild failed', [
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);
        }

        return $count;
    }

    /**
     * MongoDB index oluştur
     *
     * @param Model $model
     * @param array $searchableColumns
     * @return bool
     */
    protected function createMongoIndex(Model $model, array $searchableColumns): bool
    {
        $collection = $this->getMongoCollection($model);

        // Text index oluştur
        $indexFields = [];
        foreach ($searchableColumns as $column) {
            $indexFields[$column] = 'text';
        }

        if (empty($indexFields)) {
            return false;
        }

        try {
            $collection->createIndex($indexFields, ['name' => 'search_text_index']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * MongoDB index güncelle
     *
     * @param Model $model
     * @param array $data
     * @return bool
     */
    protected function updateMongoIndex(Model $model, array $data): bool
    {
        $collection = $this->getMongoCollection($model);
        $document = [
            'model_id' => $model->getKey(),
            'model_type' => get_class($model),
            'data' => $data,
            'search_text' => $this->extractSearchText($data),
            'updated_at' => now(),
        ];

        try {
            $collection->updateOne(
                ['model_id' => $model->getKey(), 'model_type' => get_class($model)],
                ['$set' => $document],
                ['upsert' => true]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * MongoDB index sil
     *
     * @param Model $model
     * @return bool
     */
    protected function deleteMongoIndex(Model $model): bool
    {
        $collection = $this->getMongoCollection($model);

        try {
            $collection->deleteOne([
                'model_id' => $model->getKey(),
                'model_type' => get_class($model),
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * MongoDB'de arama yap
     *
     * @param Model $model
     * @param string $term
     * @param array $searchableColumns
     * @return array
     */
    protected function searchMongoIndex(Model $model, string $term, array $searchableColumns): array
    {
        $collection = $this->getMongoCollection($model);

        try {
            $results = $collection->find([
                '$text' => ['$search' => $term],
                'model_type' => get_class($model),
            ]);

            $ids = [];
            foreach ($results as $result) {
                $ids[] = $result['model_id'];
            }

            return $ids;
        } catch (\Exception $e) {
            // Fallback: regex search
            $results = $collection->find([
                'search_text' => ['$regex' => $term, '$options' => 'i'],
                'model_type' => get_class($model),
            ]);

            $ids = [];
            foreach ($results as $result) {
                $ids[] = $result['model_id'];
            }

            return $ids;
        }
    }

    /**
     * MongoDB collection al
     *
     * @param Model $model
     * @return mixed
     */
    protected function getMongoCollection(Model $model)
    {
        $db = DB::connection($this->connection);
        $collectionName = 'search_index_' . str_replace('\\', '_', get_class($model));
        return $db->getCollection($collectionName);
    }

    /**
     * PostgreSQL index oluştur
     *
     * @param Model $model
     * @param array $searchableColumns
     * @return bool
     */
    protected function createPostgresIndex(Model $model, array $searchableColumns): bool
    {
        $tableName = $this->getPostgresTableName($model);

        try {
            // Tablo oluştur
            DB::connection($this->connection)->statement("
                CREATE TABLE IF NOT EXISTS {$tableName} (
                    id SERIAL PRIMARY KEY,
                    model_id INTEGER NOT NULL,
                    model_type VARCHAR(255) NOT NULL,
                    search_text TSVECTOR,
                    data JSONB,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(model_id, model_type)
                )
            ");

            // GIN index oluştur (full-text search için)
            DB::connection($this->connection)->statement("
                CREATE INDEX IF NOT EXISTS {$tableName}_search_text_idx
                ON {$tableName} USING GIN(search_text)
            ");

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * PostgreSQL index güncelle
     *
     * @param Model $model
     * @param array $data
     * @return bool
     */
    protected function updatePostgresIndex(Model $model, array $data): bool
    {
        $tableName = $this->getPostgresTableName($model);
        $searchText = $this->extractSearchText($data);
        $modelId = $model->getKey();
        $modelType = get_class($model);

        try {
            // tsvector oluştur
            $searchTextVector = DB::connection($this->connection)->selectOne("
                SELECT to_tsvector('english', ?) as vector
            ", [$searchText])->vector;

            DB::connection($this->connection)->statement("
                INSERT INTO {$tableName} (model_id, model_type, search_text, data, updated_at)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (model_id, model_type)
                DO UPDATE SET
                    search_text = EXCLUDED.search_text,
                    data = EXCLUDED.data,
                    updated_at = EXCLUDED.updated_at
            ", [$modelId, $modelType, $searchTextVector, json_encode($data), now()]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * PostgreSQL index sil
     *
     * @param Model $model
     * @return bool
     */
    protected function deletePostgresIndex(Model $model): bool
    {
        $tableName = $this->getPostgresTableName($model);

        try {
            DB::connection($this->connection)->statement("
                DELETE FROM {$tableName}
                WHERE model_id = ? AND model_type = ?
            ", [$model->getKey(), get_class($model)]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * PostgreSQL'de arama yap
     *
     * @param Model $model
     * @param string $term
     * @param array $searchableColumns
     * @return array
     */
    protected function searchPostgresIndex(Model $model, string $term, array $searchableColumns): array
    {
        $tableName = $this->getPostgresTableName($model);
        $modelType = get_class($model);

        try {
            $results = DB::connection($this->connection)->select("
                SELECT model_id
                FROM {$tableName}
                WHERE model_type = ?
                AND search_text @@ to_tsquery('english', ?)
                ORDER BY ts_rank(search_text, to_tsquery('english', ?)) DESC
            ", [$modelType, $term, $term]);

            return array_map(function ($result) {
                return $result->model_id;
            }, $results);
        } catch (\Exception $e) {
            // Fallback: ILIKE search
            $results = DB::connection($this->connection)->select("
                SELECT model_id
                FROM {$tableName}
                WHERE model_type = ?
                AND search_text::text ILIKE ?
            ", [$modelType, "%{$term}%"]);

            return array_map(function ($result) {
                return $result->model_id;
            }, $results);
        }
    }

    /**
     * PostgreSQL tablo adı al
     *
     * @param Model $model
     * @return string
     */
    protected function getPostgresTableName(Model $model): string
    {
        $modelClass = str_replace('\\', '_', get_class($model));
        return 'search_index_' . strtolower($modelClass);
    }

    /**
     * Arama metnini çıkar
     *
     * @param array $data
     * @return string
     */
    protected function extractSearchText(array $data): string
    {
        // Sadece string değerleri birleştir
        $texts = [];
        foreach ($data as $value) {
            if (is_string($value)) {
                $texts[] = $value;
            } elseif (is_array($value)) {
                $texts[] = $this->extractSearchText($value);
            }
        }

        return implode(' ', $texts);
    }
}
