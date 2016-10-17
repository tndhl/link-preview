<?php

namespace Tndhl\LinkPreview\Tests;

use Tndhl\LinkPreview\Client;

class LinkPreviewTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function default_parsers_are_added_automatically()
    {
        $linkPreview = new Client('http://www.google.com');
        $linkPreview->getParsers();

        self::assertArrayHasKey('general', $linkPreview->getParsers());
    }

    /**
     * @test
     */
    public function can_add_extra_parsers()
    {
        $generalParserMock = $this->getMock('Tndhl\LinkPreview\Parsers\HtmlParser', null);
        $youtubeParserMock = $this->getMock('Tndhl\LinkPreview\Parsers\YouTubeParser', null);

        $linkPreview = new Client('http://www.google.com');

        // check if parser is added to the list
        $linkPreview->addParser($generalParserMock);
        $parsers = $linkPreview->getParsers();
        self::assertContains('general', $parsers);

        // check if parser added to the beginning of the list
        $linkPreview->addParser($youtubeParserMock);
        $parsers = $linkPreview->getParsers();
        self::assertEquals('youtube', key($parsers));

        return $linkPreview;
    }

    /**
     * @test
     */
    public function can_parse_an_html_page()
    {
        $linkMock = $this->getMock('Tndhl\LinkPreview\Models\Link', null, ['http://www.google.com']);

        $generalParserMock = $this->getMock('Tndhl\LinkPreview\Parsers\HtmlParser');
        $previewMock = $this->getMock('Tndhl\LinkPreview\Models\HtmlPreview');

        $generalParserMock->expects(self::once())
            ->method('canParseLink')
            ->will(self::returnValue(true));

        /*$generalParserMock->expects(self::once())
            ->method('getPreview')
            ->will(self::returnValue($previewMock));*/

        $generalParserMock->expects(self::once())
            ->method('__toString')
            ->will(self::returnValue('general'));

        $generalParserMock->expects(self::once())
            ->method('parseLink')
            ->will(self::returnValue($previewMock));

        $linkPreview = new Client('http://www.google.com');
        $linkPreview->addParser($generalParserMock);
        $parsed = $linkPreview->getPreviews();

        self::assertArrayHasKey('general', $parsed);
    }

    /**
     * @depends can_add_extra_parsers
     * @test
     */
    public function can_remove_a_parser(Client $linkPreview)
    {
        $linkPreview->removeParser('general');
        $parsers = $linkPreview->getParsers();
        self::assertNotContains('general', $parsers);
    }

    /**
     * @test
     */
    public function can_set_an_url()
    {
        $linkPreview = new Client('http://github.com');
        self::assertEquals('http://github.com', $linkPreview->getUrl());
    }

    /**
     * @test
     */
    public function can_parse_a_youtube_link()
    {
        $linkPreview = new Client('https://www.youtube.com/watch?v=C0DPdy98e4c');
        $parsedLink = $linkPreview->getPreview('youtube');
        self::assertInstanceOf('Tndhl\LinkPreview\Models\VideoPreview', $parsedLink);
    }
}
