<?php

namespace App\Services;

use App\Application;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Symfony\Component\Yaml\Yaml;

class IndexingService
{
    private static ?array $indexing = null;

    public function __construct (
        private readonly GitService $gitService
    ) {}

    /**
     * Save the index of all markdown files.
     *
     * @return void
     */
    public function saveIndexing (): void
    {
        $index = $this->getIndexing();
        $raw = var_export($index, true);

        $dirPath = __DIR__ . '/../../storage';
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0777, true);
        }

        file_put_contents($dirPath . '/indexing.php', '<?php return ' . $raw . ';');
    }

    /**
     * Get the index of all markdown files.
     *
     * @return array
     */
    public function getIndexing (): array
    {
        $posts = [];
        $categories = [];
        $tags = [];

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

                if ($key === 'slug' || $key === 'title' || $key === 'tags' || $key === 'createdAt' || $key === 'updatedAt') {
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

            $data->extras = $extras;
            $posts[$data->slug] = $data;

            foreach ($data->categories as $category) {
                if (!isset($categories[$category])) {
                    $categories[$category] = [];
                }
                $categories[$category][] = $data->slug;
            }

            foreach ($data->tags as $tag) {
                if (!isset($tags[$tag])) {
                    $tags[$tag] = [];
                }
                $tags[$tag][] = $data->slug;
            }
        }

        return [
            'posts' => $posts,
            'categories' => $categories,
            'tags' => $tags,
        ];
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
            'originalPath' => $path,
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
     * Load the index of all markdown files.
     *
     * @return array
     */
    public function loadIndex (): array
    {
        if (self::$indexing !== null) {
            return self::$indexing;
        }

        $indexPath = __DIR__ . '/../../storage/indexing.php';
        if (!file_exists($indexPath)) {
            return self::$indexing = $this->getIndexing();
        }

        return self::$indexing = require $indexPath;
    }

    /**
     * Get a post.
     *
     * @param string $slug
     * @return object|null
     */
    public function getPost (string $slug): ?object
    {
        $index = $this->loadIndex();
        if (!isset($index['posts'][$slug])) {
            return null;
        }

        return $index['posts'][$slug];
    }

    /**
     * Get all posts.
     *
     * @return array
     */
    public function getPosts (): array
    {
        $index = $this->loadIndex();
        return $index['posts'];
    }

    /**
     * Get all posts from a category.
     *
     * @param string $category
     * @return array
     */
    public function getCategoryPosts (string $category): array
    {
        $index = $this->loadIndex();
        if (!isset($index['categories'][$category])) {
            return [];
        }

        return array_map(function ($slug) use ($index) {
            return $index['posts'][$slug];
        }, $index['categories'][$category]);
    }

    /**
     * Get all posts from a tag.
     *
     * @param string $tag
     * @return array
     */
    public function getTagPosts (string $tag): array
    {
        $index = $this->loadIndex();
        if (!isset($index['tags'][$tag])) {
            return [];
        }

        return array_map(function ($slug) use ($index) {
            return $index['posts'][$slug];
        }, $index['tags'][$tag]);
    }
}