<?php

declare(strict_types=1);

namespace WebimpressCodingStandard\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\AbstractVariableSniff;
use WebimpressCodingStandard\Helper\AnnotationsTrait;

class PropertyAnnotationSniff extends AbstractVariableSniff
{
    use AnnotationsTrait;

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    protected function processMemberVar(File $phpcsFile, $stackPtr)
    {
        $this->processAnnotations($phpcsFile, $stackPtr);
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    protected function processVariable(File $phpcsFile, $stackPtr)
    {
        // Sniff process only class member vars.
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    protected function processVariableInString(File $phpcsFile, $stackPtr)
    {
        // Sniff process only class member vars.
    }
}
