<?php

declare(strict_types=1);

namespace WebimpressCodingStandardTest\Sniffs\Commenting;

use WebimpressCodingStandardTest\Sniffs\AbstractTestCase;

class ClassAnnotationUnitTest extends AbstractTestCase
{
    public function getErrorList(string $testFile = '') : array
    {
        switch ($testFile) {
            case 'ClassAnnotationUnitTest.1.inc':
                return [];
            case 'ClassAnnotationUnitTest.2.inc':
                return [
                    8 => 1,
                    10 => 1,
                ];
        }

        return [
            4 => 1,
            5 => 1,
            6 => 1,
            8 => 1,
            10 => 1,
            13 => 1,
            20 => 1,
            23 => 1,
            29 => 1,
            35 => 1,
            38 => 1,
            41 => 1,
        ];
    }

    public function getWarningList(string $testFile = '') : array
    {
        return [];
    }
}
