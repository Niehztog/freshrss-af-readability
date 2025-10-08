<?php

use PHPUnit\Framework\TestCase;

class HTMLawedTest extends TestCase
{
    public function dataForImgSrcsetAttribute()
    {
        return [
            'srcset with width descriptor' => [
                '<div><img src="a.jpg" alt="image a" srcset="a.jpg 100w, b.jpg 450w" /></div>',
            ],
            'srcset with pixel ratio density' => [
                '<div><img src="a.jpg" alt="image a" srcset="a.jpg, b.jpg 1.5x,c.jpg 2x" /></div>',
                '<div><img src="a.jpg" alt="image a" srcset="a.jpg, b.jpg 1.5x, c.jpg 2x" /></div>',
            ],
            'srcset with invalid descriptor' => [
                '<div><img src="a.jpg" alt="image a" srcset=" a.jpg ,   b.jpg x2" /></div>',
                '<div><img src="a.jpg" alt="image a" srcset="a.jpg" /></div>',
            ],
            'srcset with commas in resource path' => [
                '<div><img src="a.jpg" alt="image a" srcset="a.jpg,c_120 100w,b.jpg 450w" /></div>',
                '<div><img src="a.jpg" alt="image a" srcset="a.jpg,c_120 100w, b.jpg 450w" /></div>',
            ],
        ];
    }

    /**
     * @dataProvider dataForImgSrcsetAttribute
     */
    public function testImgSrcsetAttribute($input, $expectedOutput = null)
    {
        $output = htmLawed($input);

        $this->assertSame($expectedOutput ?: $input, $output);
    }

    public function testPictureBlocks()
    {
        $input = '<div><picture><source srcset="a.jpg, c.jpg 405w" /><img src="z.jpg" alt="image" /></picture></div>';

        $output = htmLawed($input);
        $this->assertSame($input, $output);
    }
}
