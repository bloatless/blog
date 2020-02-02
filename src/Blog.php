<?php

declare(strict_types=1);

namespace Bloatless\Blog;

class Blog
{
    const HTTP_VERSION = '1.1';

    const IMAGE_CACHE_LIFETIME = 60*60*24*90;

    const THUMB_QUALITY = 100;

    /**
     * @var string $pathTemplates Path to the views/layouts.
     */
    protected $pathTemplates = '';

    /**
     * @var string $pathArticles Path to the article markdown files.
     */
    protected $pathArticles = '';

    /**
     * @var string $pathPublic Path to the "public" folder.
     */
    protected $pathPublic = '';

    /**
     * @var string $pathThumbs Path to store thumbnails.
     */
    protected $pathThumbs = '';

    /**
     * @var array $categoryIcons
     */
    protected $categoryIcons = [];

    /**
     * @var array $pageConfigs
     */
    protected $pageConfigs = [];

    /**
     * HTTP status code messages.
     *
     * @var array
     */
    protected $statusMessages = [
        200 => 'OK',
        404 => 'Not Found',
        500 => 'Internal Server Error',
    ];

    public function __construct(array $config = [])
    {
        // Set default paths
        $this->pathTemplates = $config['paths']['templates'] ?? __DIR__ . '/../resources/views/';
        $this->pathArticles = $config['paths']['articles'] ?? __DIR__ . '/../resources/articles/';
        $this->pathPublic = $config['paths']['public'] ?? __DIR__ . '/../public/';
        $this->pathThumbs = $config['paths']['thumbs'] ?? __DIR__ . '/../storage/.thumbs/';

        // set page configs
        $this->pageConfigs = $config['pages'];

        // Set category icons
        $this->categoryIcons = $config['category_icons'] ?? [];
    }

    /**
     * This methods routes the request and calls the corresponding method(s).
     *
     * @throws \Exception
     * @return void
     */
    public function __invoke(): void
    {
        try {
            $routeInfo = $this->route();
            switch ($routeInfo['page']) {
                case 'blog':
                    $response = $this->getBlogResponse();
                    break;
                case 'feed':
                    $response = $this->getFeedResponse();
                    break;
                case 'article':
                    $response = $this->getArticleResponse($routeInfo['slug']);
                    break;
                case 'image':
                    $response = $this->getImageResponse($routeInfo['path'], $routeInfo['width'], $routeInfo['height']);
                    break;
                default:
                    $response = $this->getCustomPageResponse($routeInfo['page']);
                    break;
            }
            $this->sendResponse($response);
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $response = $this->getNotFoundResponse();
            } else {
                $response = $this->getErrorResponse($e);
            }
            $this->sendResponse($response);
        }
    }

    /**
     * Routes a request and returns an array containing information necessary to execute the request.
     *
     * @return array
     * @throws \Exception
     */
    protected function route(): array
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $page = null;
        foreach ($this->pageConfigs as $pageId => $pageConfig) {
            $pattern = $pageConfig['parse_pattern'];
            if (preg_match($pattern, $requestUri, $match) === 1) {
                $page = $pageId;
                break;
            }
        }

        if (empty($page)) {
            throw new \Exception('Page not found.', 404);
        }

        $routeInfo = ['page' => $page];
        if (count($match) === 1) {
            return $routeInfo;
        }

        switch ($page) {
            case 'article':
                $routeInfo['slug'] = $match[1];
                break;
            case 'image':
                $routeInfo['width'] = (int) $match[1];
                $routeInfo['height'] = (int) $match[2];
                $routeInfo['path'] = $match[3];
                break;
        }

        return $routeInfo;
    }

    /**
     * Generates the response for the blog/article-list page.
     *
     * @return array
     * @throws \Exception
     */
    protected function getBlogResponse(): array
    {
        $articles = $this->getBlogArticles();
        $articles = $this->attachCategoryIcons($articles);
        $articles = $this->groupArticlesByYear($articles);

        return ['body' => $this->renderView('blog', [
            'meta_title' => $this->pageConfigs['blog']['meta_title'],
            'meta_index' => $this->pageConfigs['blog']['meta_robots'],
            'meta_description' => $this->pageConfigs['blog']['meta_description'],
            'articles' => $articles,
        ])];
    }

    /**
     * Generates the response for an "article" page.
     *
     * @param string $slug
     * @return array
     * @throws \Exception
     */
    protected function getArticleResponse(string $slug): array
    {
        $article = null;
        $filenames = $this->getArticleFilenames();
        foreach ($filenames as $filename) {
            $articleMeta = $this->getMetaFromArticleFile($filename);
            if ($articleMeta->slug === $slug) {
                $article = $this->getArticleFromFile($filename);
                break;
            }
        }

        if (empty($article)) {
            throw new \Exception('Article not found.', 404);
        }

        return ['body' => $this->renderView('article', [
            'meta_title' => $article->metaTitle,
            'meta_index' => 'index, follow',
            'meta_description' => $article->description,
            'article' => $article,
        ])];
    }

    /**
     * Generates the response for the blog rss feed.
     *
     * @return array
     * @throws \Exception
     */
    protected function getFeedResponse(): array
    {
        $articles = $this->getBlogArticles($this->pageConfigs['feed']['max_articles'], false);
        $articles = $this->attachFeedPropertiesToArticles($articles);

        return [
            'body' => $this->renderFeed($articles),
            'headers' => [
                'Content-Type' => 'text/xml'
            ]
        ];
    }

    /**
     * Resizes and image and generates the response for the resized image.

     * @param string $imagePath
     * @param int $width
     * @param int $height
     * @return array
     * @throws \Exception
     */
    protected function getImageResponse(string $imagePath, int $width, int $height): array
    {
        $fullImagePath = $this->pathPublic . $imagePath;
        if (!file_exists($fullImagePath)) {
            throw new \Exception('Image not found', 404);
        }

        $pathInfo = pathinfo($fullImagePath);
        $thumbFilename = sprintf('%s_%dx%d.%s', $pathInfo['filename'], $width, $height, $pathInfo['extension']);
        $pathThumbnail = $this->pathThumbs . $thumbFilename;

        if (!file_exists($pathThumbnail)) {
            $this->resizeImage($fullImagePath, $pathThumbnail, $width, $height);
        }

        $body = file_get_contents($pathThumbnail);
        $imageInfo = getimagesize($pathThumbnail);

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => $imageInfo['mime'],
                'Cache-Control' => 'protected , max-age='. self::IMAGE_CACHE_LIFETIME,
                'Expires' => gmdate('D, d M Y H:i:s', time()+self::IMAGE_CACHE_LIFETIME).' GMT',
                'Content-Length' => strlen($body),
                'Last-Modified' => gmdate('D, d M Y H:i:s', filemtime($pathThumbnail)) . ' GMT',
                'ETag' => '"' . md5($body) . '"',
            ]
        ];
    }

    /**
     * Generate the response for a custom page.
     *
     * @param string $page
     * @return array
     * @throws \Exception
     */
    protected function getCustomPageResponse(string $page): array
    {
        if (!isset($this->pageConfigs[$page])) {
            throw new \Exception('Page not found.', 404);
        }

        return ['body' => $this->renderView($this->pageConfigs[$page]['view'], [
            'meta_title' => $this->pageConfigs[$page]['meta_title'],
            'meta_index' => $this->pageConfigs[$page]['meta_robots'],
            'meta_description' => $this->pageConfigs[$page]['meta_description'],
        ])];
    }


    /**
     * Generate the response for the "not found 404" page.
     *
     * @return array
     * @throws \Exception
     */
    protected function getNotFoundResponse(): array
    {
        return [
            'status_code' => 404,
            'body' => $this->renderView('not_found', [
                'meta_title' => 'Not found',
                'meta_description' => 'Page not found',
                'meta_index' => 'noindex, follow',
            ])
        ];
    }


    /**
     * Generates the response for the "error 500" page.
     *
     * @param \Exception $e
     * @return array
     * @throws \Exception
     */
    protected function getErrorResponse(\Exception $e)
    {
        return [
            'status_code' => 500,
            'body' => $this->renderView('error', [
                'file' => $e->getFile(),
                'line' => (string) $e->getLine(),
                'message' => $e->getMessage(),
                'meta_title' => 'Error 500',
                'meta_description' => 'Internal Server Error',
                'meta_index' => 'noindex, follow',
            ])
        ];
    }

    /**
     * Sends an http response to the client.
     *
     * @param array $response
     */
    protected function sendResponse(array $response): void
    {
        $response['status_code'] = $response['status_code'] ?? 200;
        $response['status_message'] = $this->statusMessages[$response['status_code']] ?? '';
        $response['headers'] = $response['headers'] ?? [];

        // send http header:
        $httpHeader = sprintf(
            'HTTP/%s %d %s',
            self::HTTP_VERSION,
            $response['status_code'],
            $response['status_message']
        );
        header($httpHeader, true);

        // send additional headers:
        foreach ($response['headers'] as $name => $value) {
            header(sprintf('%s: %s', $name, $value), true);
        }

        // send body:
        echo $response['body'];
    }

    /**
     * Fetches a list of blog articles (Full articles or metadata only).
     *
     * @param int $limit
     * @param bool $metaOnly
     * @return array
     * @throws \Exception
     */
    protected function getBlogArticles(int $limit = 0, bool $metaOnly = true): array
    {
        $filenames = $this->getArticleFilenames();
        if ($limit > 0) {
            $filenames = array_slice($filenames, 0, $limit);
        }
        $articles = [];
        foreach ($filenames as $filename) {
            if ($metaOnly === true) {
                $articles[] = $this->getMetaFromArticleFile($filename);
                continue;
            }
            $articles[] = $this->getArticleFromFile($filename);
        }

        return $articles;
    }

    /**
     * Fetches a list of article filenames.
     *
     * @return array
     */
    protected function getArticleFilenames(): array
    {
        $filenames = [];
        foreach (glob($this->pathArticles . '*.md') as $file) {
            $filenames[] = basename($file);
        }
        rsort($filenames);

        return $filenames;
    }

    /**
     * Fetches the meta-information for the article with given filename.
     *
     * @param string $filename
     * @return \stdClass
     * @throws \Exception
     */
    protected function getMetaFromArticleFile(string $filename): \stdClass
    {
        $fp = fopen($this->pathArticles . $filename, 'r');
        if ($fp === false) {
            throw new  \Exception('Could not read article file.');
        }
        $content = '';
        while (($line = fgets($fp, 256)) !== false) {
            $line = trim($line);
            if ($line === '::METAEND::') {
                break;
            }
            $content .= $line;
        }
        fclose($fp);
        $metadata = json_decode($content);
        if (empty($metadata)) {
            throw new \Exception('Could not decode article metadata.');
        }

        return $metadata;
    }

    /**
     * Fetches the complete article for given filename. Will render markdown to html, replace shortcodes, ...
     *
     * @param string $filename
     * @return \stdClass
     * @throws \Exception
     */
    protected function getArticleFromFile(string $filename): \stdClass
    {
        if (empty($filename)) {
            throw new \Exception('Article file not found.');
        }

        $content = file_get_contents($this->pathArticles . $filename);
        if (strpos($content, '::METAEND::') === false) {
            throw new \Exception('Invalid article. (Metadata missing)');
        }
        $markedownParser = new \Parsedown;
        $sections = explode('::METAEND::', $content);
        $data = json_decode($sections[0]);
        $data->content = $this->replaceShortcodes($sections[1]);
        $data->content = $markedownParser->text($data->content);

        return $data;
    }

    /**
     * Groups a list of articles by year.
     *
     * @param array $articles
     * @return array
     */
    protected function groupArticlesByYear(array $articles): array
    {
        $articlesGrouped = [];
        foreach ($articles as $item) {
            $year = substr($item->date, 0, 4);
            $articlesGrouped[$year][] = $item;
        }

        return $articlesGrouped;
    }

    /**
     * Replaces shortcodes within given string (blog article).
     *
     * @param string $content
     * @return string
     */
    protected function replaceShortcodes(string $content): string
    {
        $content = preg_replace_callback('/\[gallery\sfolder="(.+)"\]/Us', function ($match) {
            return $this->getGalleryCode($match[1]);
        }, $content);

        return $content;
    }

    /**
     * Generates the html code to replace a gallery shortcode.
     *
     * @param string $galleryPath
     * @return string
     * @throws \Exception
     */
    protected function getGalleryCode(string $galleryPath): string
    {
        $galleryPath = trim($galleryPath, '/');
        $galleryPathFull = $this->pathPublic . $galleryPath;
        $imageFiles = glob($galleryPathFull . '/*.{jpg,png,gif}', GLOB_BRACE);
        $imageFiles = array_map('basename', $imageFiles);

        return $this->renderView('partials/gallery', [
            'path_gallery' => '/'.$galleryPath.'/',
            'images' => $imageFiles
        ]);
    }

    /**
     * Attaches unicode icons representing article categories to a list of articles.
     *
     * @param array $articles
     * @return array
     */
    protected function attachCategoryIcons(array $articles): array
    {
        foreach ($articles as $article) {
            $article->icons = '';
            if (empty($article->categories)) {
                continue;
            }
            $categories = strtolower($article->categories);
            $categories = explode(',', $categories);
            $article->icons = array_intersect_key($this->categoryIcons, array_flip($categories));
            $article->icons = implode('', $article->icons);
        }

        return $articles;
    }

    /**
     * Attaches some properties to articles which are required when displaying articles in an rss feed.
     *
     * @param array $articles
     * @return array
     */
    protected function attachFeedPropertiesToArticles(array $articles): array
    {
        foreach ($articles as $article) {
            $article->link = $this->buildUrl('article', ['slug' => $article->slug], true);
            $article->guid = $article->link;
            $article->pubDate = date(DATE_RSS, strtotime($article->date));
        }

        return $articles;
    }

    /**
     * Renders the view identified by given name.
     *
     * @param string $name
     * @param array $tmplVars
     * @return string
     * @throws \Exception
     */
    protected function renderView(string $name, array $tmplVars = []): string
    {
        $viewFile = $this->pathTemplates . $name . '.phtml';
        $view = $this->renderTemplateFile($viewFile, $tmplVars);
        if (preg_match('/<!-- extends "(.+)" -->/Us', $view, $matches) !== 1) {
            return $view;
        }
        $layoutName = trim($matches[1]);
        $view = str_replace('<!-- extends "' . $layoutName . '" -->', '', $view);
        $tmplVars['content'] = trim($view);
        $layoutFile = $this->pathTemplates . 'layouts/' . $layoutName . '.phtml';
        return $this->renderTemplateFile($layoutFile, $tmplVars);
    }

    /**
     * Renders a generic template file (view, layout, partial, ...)
     *
     * @param string $templateFile
     * @param array $tmplVars
     * @return string
     * @throws \Exception
     */
    protected function renderTemplateFile(string $templateFile, array $tmplVars): string
    {
        if (!file_exists($templateFile)) {
            throw new \Exception(sprintf('Template file not found. (%s)', $templateFile));
        }

        extract($tmplVars);
        ob_start();
        include $templateFile;
        $content = ob_get_clean();

        return $content;
    }

    /**
     * Echos a variable from within a template and applies htmlentities by default.
     *
     * @param $variable
     * @param bool $escape
     */
    protected function out($variable, bool $escape = true): void
    {
        if ($escape === true) {
            $variable = (string) $variable;
            $variable = htmlentities($variable);
        }
        echo $variable;
    }

    /**
     * Echos the URL to the requested page.
     *
     * @param string $page
     * @param array $params
     * @param bool $absolute
     * @return void
     */
    protected function url(string $page, array $params = [], bool $absolute = false): void
    {
        echo $this->buildUrl($page, $params, $absolute);
    }

    /**
     * Generates an rss feed output from given articles.
     *
     * @param array $articles
     * @return string
     */
    protected function renderFeed(array $articles) : string
    {
        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0" />',
            LIBXML_NOERROR|LIBXML_ERR_NONE|LIBXML_ERR_FATAL
        );

        // add channel:
        $xml->addChild('channel');
        $xml->channel->addChild('title', $this->pageConfigs['feed']['channel_title']);
        $xml->channel->addChild('link', $this->buildUrl('feed', [], true));
        $xml->channel->addChild('description', $this->pageConfigs['feed']['channel_description']);
        if (!empty($articles)) {
            $xml->channel->addChild('pubDate', date(DATE_RSS, strtotime($articles[0]->date)));
        }

        // add items:
        foreach ($articles as $article) {
            $item = $xml->channel->addChild('item');
            $item->addChild('title', $article->title);
            $item->addChild('link', $article->link);
            $item->addChild('guid', $article->guid);
            $item->addChild('pubDate', $article->pubDate);
            $this->addChildWithCData('description', $article->content, $item);
        }

        // return xml:
        return $xml->asXML();
    }

    /**
     * Some hack-method to allow cdata-enclosed elements in simple-xml (which is not supported by default).
     *
     * @param string $name
     * @param string $value
     * @param \SimpleXMLElement $parent
     * @return \SimpleXMLElement
     */
    protected function addChildWithCData(string $name, string $value, \SimpleXMLElement &$parent): \SimpleXMLElement
    {
        $child = $parent->addChild($name);
        $childNode = dom_import_simplexml($child);
        $childOwner = $childNode->ownerDocument;
        $childNode->appendChild($childOwner->createCDATASection($value));

        return $child;
    }

    /**
     * Generates the absolute or relative URL to the requested page according to route configuration.
     *
     * @param string $page
     * @param array $params
     * @param bool $absolute
     * @return string
     */
    protected function buildUrl(string $page, array $params = [], bool $absolute = false): string
    {
        if (!isset($this->pageConfigs[$page])) {
            return '';
        }
        $path = $this->pageConfigs[$page]['build_pattern'] ?? '';
        foreach ($params as $name => $value) {
            $path = str_replace(':'.$name, $value, $path);
        }
        if ($absolute === true) {
            $path = $this->getUrlFromPath($path);
        }
        return $path;
    }

    /**
     * Generates a complete url from given path (by adding domain and schema).
     *
     * @param string $path
     * @return string
     */
    protected function getUrlFromPath(string $path) : string
    {
        $host = $_SERVER['HTTP_HOST'];
        $schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
        $path = ltrim($path, '/');
        return $schema . $host . '/' . $path;
    }

    /**
     * Resizes and crops and image to given size. (Used for thumbnail generation)
     *
     * @param string $pathToSource
     * @param string $pathToTarget
     * @param int $targetWidth
     * @param int $targetHeight
     * @return void
     * @throws \Exception
     */
    protected function resizeImage(
        string $pathToSource,
        string $pathToTarget,
        int $targetWidth,
        int $targetHeight
    ): void {
        if (!file_exists($pathToSource)) {
            throw new \Exception('Could not find source image.');
        }

        $info = getimagesize($pathToSource);
        if ($info === false) {
            throw new \Exception('Invalid image file.');
        }

        $imgSource = null;
        switch ($info['mime']) {
            case 'image/jpeg':
                $imgSource = imagecreatefromjpeg($pathToSource);
                break;
            case 'image/png':
                $imgSource = imagecreatefrompng($pathToSource);
                break;
            case 'image/webp':
                $imgSource = imagecreatefromwebp($pathToSource);
                break;
        }

        if (empty($imgSource)) {
            throw new \Exception('Unsupported image type');
        }

        // calculate width and height of thumbnail (before cropping to target size)
        $sourceWidth = $info[0];
        $sourceHeight = $info[1];
        $aspectRatio = $sourceWidth / $sourceHeight;
        if (($targetHeight / $targetWidth) > ($sourceHeight / $sourceWidth)) {
            $thumbHeight = (int) $targetHeight;
            $thumbWidth = (int) ($targetHeight * $aspectRatio);
        } else {
            $thumbWidth = (int) $targetWidth;
            $thumbHeight = (int) ($targetWidth / $aspectRatio);
        }

        // create thumbnail and copy/resize source image to thumbnail
        imagepalettetotruecolor($imgSource);
        $imgThumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        $transparentColor = imagecolorallocatealpha($imgThumb, 0, 0, 0, 127);
        imagecolortransparent($imgThumb, $transparentColor);
        imagefill($imgThumb, 0, 0, $transparentColor);
        imagecopyresampled($imgThumb, $imgSource, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);
        unset($imgSource);

        // Crop thumbnail to rectangular area with target dimensions (uses center of thumbnail)
        $x1 = floor(($thumbWidth / 2) - ($targetWidth / 2));
        $x2 = $targetWidth + $x1;
        $y1 = floor(($thumbHeight / 2) - ($targetHeight / 2));
        $y2 = $targetHeight + $y1;
        $imgThumb = imagecrop($imgThumb, [
            'x' => min($x1, $x2),
            'y' => min($y1, $y2),
            'width' => abs($x2 - $x1),
            'height' => abs($y2 - $y1)
        ]);

        // Store thumbnail to file:
        switch ($info['mime']) {
            case 'image/jpeg':
                imageinterlace($imgThumb, 1);
                imagejpeg($imgThumb, $pathToTarget, self::THUMB_QUALITY);
                break;
            case 'image/png':
                imagesavealpha($imgThumb, true);
                imagepng($imgThumb, $pathToTarget, (int) round(9 * self::THUMB_QUALITY / 100));
                break;
            case 'image/webp':
                imagesavealpha($imgThumb, true);
                imagewebp($imgThumb, $pathToTarget, self::THUMB_QUALITY);
                break;
        }
    }
}
