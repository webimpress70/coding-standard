<?php
namespace ZendCodingStandard\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class BlankLineSniff implements Sniff
{
    /**
     * @return int[]
     */
    public function register()
    {
        return [
            T_COMMENT,
            T_OPEN_TAG,
            T_WHITESPACE,
        ];
    }

    /**
     * @param File $phpcsFile
     * @param int $stackPtr
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $next = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);
        if ($next && $tokens[$stackPtr]['line'] < $tokens[$next]['line'] - 2) {
            $fix = $phpcsFile->addFixableError('Unexpected blank line found.', $stackPtr + 1, '');

            if ($fix) {
                $phpcsFile->fixer->replaceToken($stackPtr + 1, '');
            }
        }
    }
}