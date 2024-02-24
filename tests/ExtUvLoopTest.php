<?php

namespace React\Tests\EventLoop;

use React\EventLoop\ExtUvLoop;

class ExtUvLoopTest extends AbstractLoopTest
{
    public function createLoop()
    {
        if (!function_exists('uv_loop_new')) {
            $this->markTestSkipped('uv tests skipped because ext-uv is not installed.');
        }

        return new ExtUvLoop();
    }

    /** @dataProvider intervalProvider */
    public function testTimerInterval($interval, $expectedExceptionMessage)
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->loop
            ->addTimer(
                $interval,
                function () {
                    return 0;
                }
            );
    }

    public function intervalProvider()
    {
        $oversizeInterval = PHP_INT_MAX / 1000;
        $maxValue = (int) (PHP_INT_MAX / 1000);
        $oneMaxValue = $maxValue + 1;
        $tenMaxValue = $maxValue + 10;
        $tenMillionsMaxValue = $maxValue + 10000000;
        $intMax = PHP_INT_MAX;
        $oneIntMax = PHP_INT_MAX + 1;
        $tenIntMax = PHP_INT_MAX + 10;
        $oneHundredIntMax = PHP_INT_MAX + 100;
        $oneThousandIntMax = PHP_INT_MAX + 1000;
        $tenMillionsIntMax = PHP_INT_MAX + 10000000;
        $tenThousandsTimesIntMax = PHP_INT_MAX * 1000;

        yield [
            $oversizeInterval,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$oversizeInterval}' passed."
        ];
        yield [
            $oneMaxValue,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$oneMaxValue}' passed.",
        ];
        yield [
            $tenMaxValue,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$tenMaxValue}' passed.",
        ];
        yield [
            $tenMillionsMaxValue,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$tenMillionsMaxValue}' passed.",
        ];
        yield [
            $intMax,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$intMax}' passed.",
        ];
        yield [
            $oneIntMax,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$oneIntMax}' passed.",
        ];
        yield [
            $tenIntMax,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$tenIntMax}' passed.",
        ];
        yield [
            $oneHundredIntMax,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$oneHundredIntMax}' passed.",
        ];
        yield [
            $oneThousandIntMax,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$oneThousandIntMax}' passed.",
        ];
        yield [
            $tenMillionsIntMax,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$tenMillionsIntMax}' passed.",
        ];
        yield [
            $tenThousandsTimesIntMax,
            "Interval overflow, value must be lower than '{$maxValue}', but '{$tenThousandsTimesIntMax}' passed.",
        ];
    }
}
