<?php

namespace App\Services;

use App\Application;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Yaml\Yaml;

class IndexingService
{
    public function __construct (
        private readonly GitService $gitService
    ) {}

    /**
     * Get SQLite3 database.
     *
     * @return PDO
     */
    public function getSqlite (): PDO
    {
        $sqlitePath = __DIR__ . '/../../storage/indexing.sqlite';
        if (!file_exists($sqlitePath)) {
            mkdir(dirname($sqlitePath), 0777, true);
            touch($sqlitePath);
        }

        return new PDO('sqlite:' . $sqlitePath);
    }

    /**
     * Save the index of all markdown files.
     *
     * @return void
     */
    public function saveIndexing (): void
    {
        $sqlite = $this->getSqlite();
        $sqlite->exec('DROP TABLE IF EXISTS posts');
        $sqlite->exec('DROP TABLE IF EXISTS posts_categories');
        $sqlite->exec('DROP TABLE IF EXISTS posts_tags');
        $sqlite->exec('DROP TABLE IF EXISTS categories');
        $sqlite->exec('DROP TABLE IF EXISTS tags');
        $sqlite->exec('CREATE TABLE IF NOT EXISTS posts (
            slug TEXT PRIMARY KEY,
            path TEXT NOT NULL,
            title TEXT NOT NULL,
            categories TEXT NOT NULL,
            tags TEXT NOT NULL,
            createdAt INTEGER NOT NULL,
            updatedAt INTEGER NOT NULL,
            extras TEXT NOT NULL,
            UNIQUE (path)
        )');
        $sqlite->exec('CREATE TABLE IF NOT EXISTS posts_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post TEXT NOT NULL,
            category TEXT NOT NULL,
            UNIQUE (post, category)
        )');
        $sqlite->exec('CREATE TABLE IF NOT EXISTS posts_tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post TEXT NOT NULL,
            tag TEXT NOT NULL,
            UNIQUE (post, tag)
        )');
        $sqlite->exec('CREATE TABLE IF NOT EXISTS categories (
            name TEXT PRIMARY KEY,
            count INTEGER NOT NULL,
            UNIQUE (name)
        )');
        $sqlite->exec('CREATE TABLE IF NOT EXISTS tags (
            name TEXT PRIMARY KEY,
            count INTEGER NOT NULL,
            UNIQUE (name)
        )');

        $postStmt = $sqlite->prepare('INSERT OR REPLACE INTO posts (slug, path, title, categories, tags, createdAt, updatedAt, extras) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $postCategoryStmt = $sqlite->prepare('INSERT OR REPLACE INTO posts_categories (post, category) VALUES (?, ?)');
        $postTagStmt = $sqlite->prepare('INSERT OR REPLACE INTO posts_tags (post, tag) VALUES (?, ?)');

        $index = $this->getIndexing();
        $categories = $tags = [];

        foreach ($index as $post) {
            $postStmt->execute([ $post->slug, $post->path, $post->title, json_encode($post->categories), json_encode($post->tags), $post->createdAt, $post->updatedAt, json_encode($post->extras) ]);
            foreach ($post->categories as $category) {
                $postCategoryStmt->execute([ $post->slug, $category ]);

                if (!isset($categories[$category])) {
                    $categories[$category] = 0;
                }
                $categories[$category]++;
            }
            foreach ($post->tags as $tag) {
                $postTagStmt->execute([ $post->slug, $tag ]);

                if (!isset($tags[$tag])) {
                    $tags[$tag] = 0;
                }
                $tags[$tag]++;
            }
        }

        $categoryStmt = $sqlite->prepare('INSERT OR REPLACE INTO categories (name, count) VALUES (?, ?)');
        foreach ($categories as $category => $count) {
            $categoryStmt->execute([ $category, $count ]);
        }

        $tagStmt = $sqlite->prepare('INSERT OR REPLACE INTO tags (name, count) VALUES (?, ?)');
        foreach ($tags as $tag => $count) {
            $tagStmt->execute([ $tag, $count ]);
        }
    }

    /**
     * Get the index of all markdown files.
     *
     * @return array
     */
    public function getIndexing (): array
    {
        $posts = [];
        foreach ($this->getAllFiles() as $file) {
            $data = $this->parseFilename($file);
            $firstMatters = $this->parseFirstMatters($file);

            $extras = [];
            foreach ($firstMatters as $key => $value) {
                if ($key === 'date') {
                    $key = 'createdAt';
                }
                if (($key === 'createdAt' || $key === 'updatedAt') && !is_numeric($value)) {
                    $value = strtotime($value);
                }

                if ($key === 'category' || $key === 'categories') {
                    if (!is_array($value)) {
                        $value = [ $value ];
                    }

                    $data->categories = array_unique(array_merge($data->categories, $value));
                    continue;
                }

                if ($key === 'tag' || $key === 'tags') {
                    if (!is_array($value)) {
                        $value = [ $value ];
                    }

                    $data->tags = array_unique($value);
                    continue;
                }

                if ($key === 'slug' || $key === 'title' || $key === 'createdAt' || $key === 'updatedAt') {
                    $data->{$key} = $value;
                } else {
                    $extras[$key] = $value;
                }
            }

            if (!isset($data->createdAt)) {
                $data->createdAt = $this->gitService->getCreatedAt($file);
            }
            if (!isset($data->updatedAt)) {
                $data->updatedAt = $this->gitService->getUpdatedAt($file);
            }
            if (!isset($data->tags)) {
                $data->tags = [];
            }

            $data->extras = $extras;
            $posts[$data->slug] = $data;
        }

        return $posts;
    }

    /**
     * Get the root directory of the markdown files.
     *
     * @return string|false
     */
    public function getRootDir (): string|false
    {
        $rootDir = Application::get('env:MARKDOWN_ROOT') ?? './markdowns';
        if (!str_starts_with($rootDir, '/')) {
            $rootDir = __DIR__ . '/../../' . $rootDir;
        }

        return realpath($rootDir);
    }

    /**
     * Get all files in a directory.
     *
     * @return array
     */
    public function getAllFiles (): array
    {
        $rootDir = $this->getRootDir();

        $dir = new RecursiveDirectoryIterator($rootDir);
        $iterator = new RecursiveIteratorIterator($dir);
        $regex = new RegexIterator($iterator, '/^.+\.md$/i', RegexIterator::GET_MATCH);

        $files = [];
        foreach ($regex as $file) {
            $files[] = realpath($file[0]);
        }

        return $files;
    }

    /**
     * Parse a filename.
     *
     * @param string $path
     * @return object
     */
    public function parseFilename (string $path): object
    {
        $rootDir = $this->getRootDir();
        $trimmedPath = substr($path, strlen($rootDir) + 1);

        $segments = explode('/', $trimmedPath);
        $title = substr(array_pop($segments), 0, -3);
        $category = implode('/', $segments);

        $createdAt = null;
        if (str_contains($title, '-')) {
            $segments = explode('-', $title, 4);
            if (count($segments) >= 4) {
                $createdAt = strtotime($segments[0] . '-' . $segments[1] . '-' . $segments[2]);
                $title = $segments[3];
            }
        }

        return (object) [
            'path' => $path,
            'slug' => sha1($trimmedPath),
            'title' => $title,
            'categories' => [ $category ],
            'tags' => [],
            'createdAt' => $createdAt
        ];
    }

    /**
     * Parse the first matter of a file.
     *
     * @param string $path
     * @return object
     */
    public function parseFirstMatters (string $path): object
    {
        $file = fopen($path, 'r');
        $line = fgets($file);
        if (!str_starts_with($line, '---')) {
            return (object) [];
        }

        $matter = $line = '';
        while (!str_starts_with($line, '---')) {
            $matter .= $line;
            $line = fgets($file);
        }
        fclose($file);

        return (object) Yaml::parse($matter);
    }

    /**
     * Get a post.
     *
     * @param string $slug
     * @return object|null
     */
    public function getPost (string $slug): ?object
    {
        $sqlite = $this->getSqlite();
        $stmt = $sqlite->prepare('SELECT * FROM posts WHERE slug = ?');
        if (!$stmt->execute([ $slug ]) || !($post = $stmt->fetchObject())) {
            return null;
        }

        return $post;
    }

    /**
     * Get all posts.
     *
     * @param int $offset
     * @param int $limit
     * @param string|null $where
     * @return object
     */
    public function getPosts (int $offset, int $limit, string $where = null): object
    {
        if ($where) {
            $query = 'SELECT * FROM posts WHERE title LIKE ? ORDER BY createdAt DESC LIMIT ? OFFSET ?';
            $params = [ '%' . $where . '%', $limit, $offset ];
        }
        else {
            $query = 'SELECT * FROM posts ORDER BY createdAt DESC LIMIT ? OFFSET ?';
            $params = [ $limit, $offset ];
        }

        $sqlite = $this->getSqlite();
        $stmt = $sqlite->prepare($query);
        if (!$stmt->execute($params)) {
            return (object) [
                'totalCount' => 0,
                'posts' => []
            ];
        }

        $posts = $stmt->fetchAll(PDO::FETCH_OBJ);

        if ($where) {
            $query = 'SELECT count(*) FROM posts WHERE title LIKE ?';
            $params = [ '%' . $where . '%' ];
        }
        else {
            $query = 'SELECT count(*) FROM posts';
            $params = [ ];
        }

        $stmt = $sqlite->prepare($query);
        if (!$stmt->execute($params)) {
            return (object) [
                'totalCount' => count($posts),
                'posts' => $posts
            ];
        }

        $count = $stmt->fetchColumn();
        return (object) [
            'totalCount' => $count,
            'posts' => $posts
        ];
    }

    /**
     * Get all posts from a category.
     *
     * @param string $category
     * @param int $offset
     * @param int $limit
     * @param string|null $where
     * @return object
     */
    public function getCategoryPosts (string $category, int $offset, int $limit, string $where = null): object
    {
        if ($where) {
            $query = '
                SELECT posts_categories.category, posts.* FROM posts_categories
                INNER JOIN posts ON posts_categories.post = posts.slug
                WHERE posts_categories.category = ? AND posts.title LIKE ? ORDER BY posts.createdAt DESC LIMIT ? OFFSET ?
            ';
            $params = [ $category, '%' . $where . '%', $limit, $offset ];
        }
        else {
            $query = '
                SELECT posts_categories.category, posts.* FROM posts_categories
                INNER JOIN posts ON posts_categories.post = posts.slug
                WHERE posts_categories.category = ? ORDER BY posts.createdAt DESC LIMIT ? OFFSET ?
            ';
            $params = [ $category, $limit, $offset ];
        }

        $sqlite = $this->getSqlite();
        $stmt = $sqlite->prepare($query);
        if (!$stmt->execute($params)) {
            return (object) [
                'totalCount' => 0,
                'posts' => []
            ];
        }

        $posts = $stmt->fetchAll(PDO::FETCH_OBJ);

        if ($where) {
            $query = '
                SELECT count(*), posts_categories.category, posts.* FROM posts_categories
                INNER JOIN posts ON posts_categories.post = posts.slug
                WHERE posts_categories.category = ? AND posts.title LIKE ?
            ';
            $params = [ $category, '%' . $where . '%' ];
        }
        else {
            $query = 'SELECT count FROM categories WHERE name = ?';
            $params = [ $category ];
        }

        $stmt = $sqlite->prepare($query);
        if (!$stmt->execute($params)) {
            return (object) [
                'totalCount' => count($posts),
                'posts' => $posts
            ];
        }

        $count = $stmt->fetchColumn();
        return (object) [
            'totalCount' => $count,
            'posts' => $posts
        ];
    }

    /**
     * Get all posts from a tag.
     *
     * @param string $tag
     * @param int $offset
     * @param int $limit
     * @param string|null $where
     * @return object
     */
    public function getTagPosts (string $tag, int $offset, int $limit, string $where = null): object
    {
        if ($where) {
            $query = '
                SELECT posts_tags.tag, posts.* FROM posts_tags
                INNER JOIN posts ON posts_tags.post = posts.slug
                WHERE posts_tags.tag = ? AND posts.title LIKE ? ORDER BY posts.createdAt DESC LIMIT ? OFFSET ?
            ';
            $params = [ $tag, '%' . $where . '%', $limit, $offset ];
        }
        else {
            $query = '
                SELECT posts_tags.tag, posts.* FROM posts_tags
                INNER JOIN posts ON posts_tags.post = posts.slug
                WHERE posts_tags.tag = ? ORDER BY posts.createdAt DESC LIMIT ? OFFSET ?
            ';
            $params = [ $tag, $limit, $offset ];
        }

        $sqlite = $this->getSqlite();
        $stmt = $sqlite->prepare($query);
        if (!$stmt->execute($params)) {
            return (object) [
                'totalCount' => 0,
                'posts' => []
            ];
        }

        $posts = $stmt->fetchAll(PDO::FETCH_OBJ);

        if ($where) {
            $query = '
                SELECT count(*), posts_tags.tag, posts.* FROM posts_tags
                INNER JOIN posts ON posts_tags.post = posts.slug
                WHERE posts_tags.tag = ? AND posts.title LIKE ?
            ';
            $params = [ $tag, '%' . $where . '%' ];
        }
        else {
            $query = 'SELECT count FROM tags WHERE name = ?';
            $params = [ $tag ];
        }

        $stmt = $sqlite->prepare($query);
        if (!$stmt->execute($params)) {
            return (object) [
                'totalCount' => count($posts),
                'posts' => $posts
            ];
        }

        $count = $stmt->fetchColumn();
        return (object) [
            'totalCount' => $count,
            'posts' => $posts
        ];
    }

    /**
     * Get all categories.
     *
     * @return array
     */
    public function getCategories (): array
    {
        $sqlite = $this->getSqlite();
        $stmt = $sqlite->prepare('SELECT * FROM categories');
        if (!$stmt->execute()) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get a category.
     *
     * @param string $name
     * @return object|null
     */
    public function getCategory (string $name): ?object
    {
        $sqlite = $this->getSqlite();
        $stmt = $sqlite->prepare('SELECT * FROM categories WHERE name = ?');
        if (!$stmt->execute([ $name ]) || !($category = $stmt->fetchObject())) {
            return null;
        }

        return $category;
    }

    /**
     * Get all tags.
     *
     * @return array
     */
    public function getTags (): array
    {
        $sqlite = $this->getSqlite();
        $stmt = $sqlite->prepare('SELECT * FROM tags');
        if (!$stmt->execute()) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get a tag.
     *
     * @param string $name
     * @return object|null
     */
    public function getTag (string $name): ?object
    {
        $sqlite = $this->getSqlite();
        $stmt = $sqlite->prepare('SELECT * FROM tags WHERE name = ?');
        if (!$stmt->execute([ $name ]) || !($tag = $stmt->fetchObject())) {
            return null;
        }

        return $tag;
    }
}