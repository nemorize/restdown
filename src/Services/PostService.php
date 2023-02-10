<?php

namespace App\Services;

class PostService
{
    public function __construct (
        private readonly IndexingService $indexingService,
        private readonly MarkdownService $markdownService
    ) {}

    /**
     * Get multiple posts.
     *
     * @param int $offset
     * @param int $limit
     * @param string|null $where
     * @return array
     */
    public function getPosts (int $offset = 0, int $limit = 10, string $where = null): array
    {
        $posts = $this->indexingService->getPosts($offset, $limit, $where);
        return array_map(function ($post) {
            $post->content = $this->parseMarkdown($post->path);
            $post->categories = json_decode($post->categories);
            $post->tags = json_decode($post->tags);

            unset($post->path);
            return $post;
        }, $posts);
    }

    /**
     * Get a post by its slug.
     *
     * @param string $slug
     * @return object|null
     */
    public function getPost (string $slug): ?object
    {
        $post = $this->indexingService->getPost($slug);
        if ($post === null) {
            return null;
        }

        $post->content = $this->parseMarkdown($post->path);
        $post->categories = json_decode($post->categories);
        $post->tags = json_decode($post->tags);

        unset($post->path);
        return $post;
    }

    /**
     * Parse a markdown file.
     *
     * @param string $path
     * @return string
     */
    public function parseMarkdown (string $path): string
    {
        $cacheDir = __DIR__ . '/../../storage/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $cachePath = $cacheDir . '/' . sha1($path) . '.html';
        if (file_exists($cachePath) && filemtime($cachePath) >= filemtime($path)) {
            return file_get_contents($cachePath);
        }

        $output = $this->markdownService->file($path);
        file_put_contents($cachePath, $output);

        return $output;
    }
}