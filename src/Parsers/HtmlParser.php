<?php

namespace Tndhl\LinkPreview\Parsers;

use Tndhl\LinkPreview\Contracts\LinkInterface;
use Tndhl\LinkPreview\Contracts\PreviewInterface;
use Tndhl\LinkPreview\Contracts\ReaderInterface;
use Tndhl\LinkPreview\Contracts\ParserInterface;
use Tndhl\LinkPreview\Exceptions\ConnectionErrorException;
use Tndhl\LinkPreview\Models\Link;
use Tndhl\LinkPreview\Readers\HttpReader;
use Tndhl\LinkPreview\Models\HtmlPreview;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class HtmlParser
 */
class HtmlParser extends BaseParser implements ParserInterface
{
    /**
     * Supported HTML tags
     *
     * @var array
     */
    private $tags = [
        'cover' => [
            ['selector' => 'meta[itemprop="image contentUrl"]', 'attribute' => 'content'],
            ['selector' => 'meta[itemprop="image"]', 'attribute' => 'content'],
            ['selector' => 'meta[property="og:image"]', 'attribute' => 'content'],
            ['selector' => 'meta[property="twitter:image"]', 'attribute' => 'value']
        ],

        'title' => [
            ['selector' => 'meta[itemprop="name"]', 'attribute' => 'content'],
            ['selector' => 'meta[property="og:title"]', 'attribute' => 'content'],
            ['selector' => 'meta[property="twitter:title"]', 'attribute' => 'value'],
            ['selector' => 'title']
        ],

        'description' => [
            ['selector' => 'meta[itemprop="description"]', 'attribute' => 'content'],
            ['selector' => 'meta[property="og:description"]', 'attribute' => 'content'],
            ['selector' => 'meta[name="description"]', 'attribute' => 'content']
        ]
    ];

    /**
     * Smaller images will be ignored
     * @var int
     */
    private $imageMinimumWidth = 300;
    private $imageMinimumHeight = 300;

    /**
     * @param ReaderInterface $reader
     * @param PreviewInterface $preview
     */
    public function __construct(ReaderInterface $reader = null, PreviewInterface $preview = null)
    {
        $this->setReader($reader ?: new HttpReader());
        $this->setPreview($preview ?: new HtmlPreview());
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return 'general';
    }


    /**
     * @param int $width
     * @param int $height
     */
    public function setMinimumImageDimension($width, $height)
    {
        $this->imageMinimumWidth = $width;
        $this->imageMinimumHeight = $height;
    }

    /**
     * @inheritdoc
     */
    public function canParseLink(LinkInterface $link)
    {
        return !filter_var($link->getUrl(), FILTER_VALIDATE_URL) === false;
    }

    /**
     * @inheritdoc
     */
    public function parseLink(LinkInterface $link)
    {
        $link = $this->readLink($link);

        if (!$link->isUp()) {
            throw new ConnectionErrorException();
        }

        if ($link->isHtml()) {
            $this->getPreview()->update($this->parseHtml($link));
        } else {
            if ($link->isImage()) {
                $this->getPreview()->update($this->parseImage($link));
            }
        }

        return $this;
    }

    /**
     * @param LinkInterface $link
     * @return array
     */
    protected function parseImage(LinkInterface $link)
    {
        return [
            'cover' => $link->getEffectiveUrl(),
            'images' => [
                $link->getEffectiveUrl()
            ]
        ];
    }

    /**
     * Extract required data from html source
     * @param LinkInterface $link
     * @return array
     */
    protected function parseHtml(LinkInterface $link)
    {
        $images = [];

        try {
            $parser = new Crawler($link->getContent());

            // Parse all known tags
            foreach ($this->tags as $tag => $selectors) {
                foreach ($selectors as $selector) {
                    if ($parser->filter($selector['selector'])->count() > 0) {
                        if (isset($selector['attribute'])) {
                            ${$tag} = $parser->filter($selector['selector'])->first()->attr($selector['attribute']);
                        } else {
                            ${$tag} = $parser->filter($selector['selector'])->first()->text();
                        }

                        break;
                    }
                }

                // Default is empty string
                if (!isset(${$tag})) {
                    ${$tag} = '';
                }
            }

            // Parse all images on this page
            foreach ($parser->filter('img') as $image) {
                /** @var \DOMElement $image */
                if (!$image->hasAttribute('src')) {
                    continue;
                }

                if (filter_var($image->getAttribute('src'), FILTER_VALIDATE_URL) === false) {
                    continue;
                }

                // This is not bulletproof, actual image maybe bigger than tags
                if ($image->hasAttribute('width') && $image->getAttribute('width') < $this->imageMinimumWidth) {
                    continue;
                }
                if ($image->hasAttribute('height') && $image->getAttribute('height') < $this->imageMinimumHeight) {
                    continue;
                }

                $images[] = $image->getAttribute('src');
            }


            // Div Images
            foreach ($parser->filter('div[style]') as $slide) {
                /** @var \DOMElement $slide */
                $style = $slide->getAttribute('style');

                // Accept following format: background-image:url(...)
                $expression = '/background-image\:.*?url\(\\\'?(.*)?\\\'?\)/';
                preg_match($expression, $style, $matches);

                if (isset($matches[1])) {
                    $images[] = rtrim($matches[1], '\'');
                }
            }

            if (!isset($description)) {
                if (preg_match('/http\:\/\/bigginscott\.com\.au/', $link->getUrl())) {
                    $content = [];

                    /** @var \DOMElement $paragraph */
                    foreach ($parser->filter('section.tab-about > section > p') as $paragraph) {
                        $content[] = $paragraph->textContent;
                    }

                    $description = implode('<br/>', $content);
                }
            }
        } catch (\InvalidArgumentException $e) {
            // Ignore exceptions
        }

        $images = array_unique($images);

        $url = sprintf('%s://%s', parse_url($link->getUrl(), PHP_URL_SCHEME), parse_url($link->getUrl(), PHP_URL_HOST));

        /** @var string $image */
        foreach ($images as &$image) {
            if (substr($image, 0, 4) != 'http') {
                $image = $url . $image;
            }
        }

        if (!isset($cover) && count($images)) {
            $cover = $images[0];
        }

        return compact('cover', 'title', 'description', 'images');
    }
}
