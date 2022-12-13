<?php

namespace fivefilters\Readability\Test;

class TestPage
{
    private $slug;
    private $configuration;
    private $sourceHTML;
    private $expectedHTML;
    private $expectedImages;
    private $expectedMetadata;

    public function __construct($slug, $configuration, $sourceHTML, $expectedHTML, $expectedImages, $expectedMetadata)
    {
        $this->slug = $slug;
        $this->configuration = $configuration;
        $this->sourceHTML = $sourceHTML;
        $this->expectedHTML = $expectedHTML;
        $this->expectedImages = $expectedImages;
        $this->expectedMetadata = $expectedMetadata;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @return string
     */
    public function getSourceHTML()
    {
        return $this->sourceHTML;
    }

    /**
     * @return string
     */
    public function getExpectedHTML()
    {
        return $this->expectedHTML;
    }

    /**
     * @return mixed
     */
    public function getExpectedImages()
    {
        return $this->expectedImages;
    }

    /**
     * @return \stdClass
     */
    public function getExpectedMetadata()
    {
        return $this->expectedMetadata;
    }
}
