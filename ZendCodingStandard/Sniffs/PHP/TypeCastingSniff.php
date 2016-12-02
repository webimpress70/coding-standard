<?php
/**
 * Copied from:
 * @see https://github.com/dereuromark/codesniffer-standards/blob/master/CakePHP/Sniffs/PHP/TypeCastingSniff.php
 *
 * Changes:
 * - disallow (unset) cast
 * - omit white chars in casting
 */

namespace ZendCodingStandard\Sniffs\PHP;

use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Sniff;
use PHP_CodeSniffer_Tokens;

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://pear.php.net/package/PHP_CodeSniffer_CakePHP
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 *
 * Asserts that type casts are in the short form:
 * - bool instead of boolean
 * - int instead of integer
 */
class TypeCastingSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * Note, that this sniff only checks the value and casing of a cast.
     * It does not check for whitespace issues regarding casts, as
     * - Squiz.WhiteSpace.CastSpacing.ContainsWhiteSpace checks for whitespace in the cast
     * - Generic.Formatting.NoSpaceAfterCast.SpaceFound checks for whitespace after the cast
     *
     * @return array
     */
    public function register()
    {
        return array_merge(PHP_CodeSniffer_Tokens::$castTokens, [T_BOOLEAN_NOT]);
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int $stackPtr The position of the current token in the
     *                      stack passed in $tokens.
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Process !! casts
        if ($tokens[$stackPtr]['code'] == T_BOOLEAN_NOT) {
            $nextToken = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);
            if ($tokens[$nextToken]['code'] != T_BOOLEAN_NOT) {
                return;
            }
            $error = 'Usage of !! cast is not allowed. Please use (bool) to cast.';
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'NotAllowed');

            if ($fix) {
                $phpcsFile->fixer->beginChangeset();
                $phpcsFile->fixer->replaceToken($stackPtr, '(bool)');
                $phpcsFile->fixer->replaceToken($nextToken, '');
                $phpcsFile->fixer->endChangeset();
            }

            return;
        }

        if ($tokens[$stackPtr]['code'] == T_UNSET_CAST) {
            $phpcsFile->addError('(unset) casting is not allowed.', $stackPtr, 'UnsetCast');
            return;
        }

        // Only allow short forms if both short and long forms are possible
        $matching = [
            '(boolean)' => '(bool)',
            '(integer)' => '(int)',
        ];
        $content = $tokens[$stackPtr]['content'];
        $key = preg_replace('/\s/', '', strtolower($content));
        if (isset($matching[$key]) || $content !== $key) {
            $error = 'Please use %s instead of %s.';
            $expected = isset($matching[$key]) ? $matching[$key] : $key;
            $data = [
                $expected,
                $content,
            ];
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'NotAllowed', $data);

            if ($fix) {
                $phpcsFile->fixer->replaceToken($stackPtr, $expected);
            }

            return;
        }
    }
}