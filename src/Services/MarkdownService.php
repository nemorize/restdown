<?php

namespace App\Services;

use ParsedownExtra;

class MarkdownService extends ParsedownExtra
{
    private string $rootDir = '';

    /**
     * Parse markdown file.
     *
     * @param string $path
     * @return string
     */
    public function file (string $path): string
    {
        $raw = file_get_contents($path);
        if (str_starts_with($raw, '---')) {
            $raw = explode('---', $raw, 3)[2] ?? '';
        }

        $this->rootDir = dirname($path);
        $parsed = $this->text($raw);
        $this->rootDir = '';

        return $parsed;
    }

    /**
     * Inline image.
     *
     * @param $Excerpt
     * @return array|null
     */
    protected function inlineImage ($Excerpt): ?array
    {
        $image = parent::inlineImage($Excerpt);
        if ($image === null) {
            return null;
        }

        $src = $image['element']['attributes']['src'];
        if (str_starts_with($src, 'http:') || str_starts_with($src, 'https:') || str_starts_with($src, 'data:') || str_starts_with($src, 'blob:') || str_starts_with($src, '//')) {
            return $image;
        }

        $imagePath = realpath($this->rootDir . '/' . $src);
        if ($imagePath === false) {
            return null;
        }

        $raw = file_get_contents($imagePath);
        $imageType = pathinfo($imagePath, PATHINFO_EXTENSION);
        $image['element']['attributes']['src'] = 'data:image/' . $imageType . ';base64,' . base64_encode($raw);

        return $image;
    }
}