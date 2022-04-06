<?php

declare(strict_types=1);

namespace WebimpressCodingStandard\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use WebimpressCodingStandard\CodingStandard;

use function array_column;
use function array_keys;
use function end;
use function sort;
use function str_replace;
use function strcasecmp;
use function usort;

use const T_ANON_CLASS;
use const T_CLASS;
use const T_CLOSE_CURLY_BRACKET;
use const T_COMMA;
use const T_OPEN_CURLY_BRACKET;
use const T_SEMICOLON;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

class TraitUsageSniff implements Sniff
{
    /**
     * @return int[]
     */
    public function register() : array
    {
        return [T_USE];
    }

    /**
     * @param int $stackPtr
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if (! CodingStandard::isTraitUse($phpcsFile, $stackPtr)) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        $keys = array_keys($tokens[$stackPtr]['conditions']);
        $classPtr = end($keys);
        $scopeOpener = $tokens[$classPtr]['scope_opener'];

        $start = $scopeOpener;
        while ($next = $phpcsFile->findNext(Tokens::$emptyTokens, $start + 1, $stackPtr, true)) {
            if ($tokens[$next]['code'] === T_USE) {
                $start = $phpcsFile->findEndOfStatement($next, T_COMMA);
                continue;
            }

            break;
        }

        if ($next) {
            $error = 'Trait must be at the beginning of the class';
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'FirstInClass');

            if ($fix) {
                $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);
                $end = $phpcsFile->findEndOfStatement($stackPtr, T_COMMA);
                $content = $phpcsFile->getTokensAsString($prev + 1, $end - $prev);

                $phpcsFile->fixer->beginChangeset();
                for ($i = $prev + 1; $i <= $end; ++$i) {
                    $phpcsFile->fixer->replaceToken($i, '');
                }
                $phpcsFile->fixer->addContent($start, $content);
                $phpcsFile->fixer->endChangeset();
            }

            return;
        }

        // No blank line before use keyword.
        $prev = $phpcsFile->findPrevious(T_WHITESPACE, $stackPtr - 1, null, true);
        if ($tokens[$prev]['line'] + 1 !== $tokens[$stackPtr]['line']) {
            $error = 'Blank line is not allowed before trait declaration';
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'BlankLineBeforeTraits');

            if ($fix) {
                $phpcsFile->fixer->beginChangeset();
                for ($i = $prev + 1; $i < $stackPtr; ++$i) {
                    if ($tokens[$i]['line'] === $tokens[$stackPtr]['line']) {
                        break;
                    }

                    $phpcsFile->fixer->replaceToken($i, '');
                }
                $phpcsFile->fixer->addNewline($prev);
                $phpcsFile->fixer->endChangeset();
            }
        }

        // One space after the use keyword.
        if ($tokens[$stackPtr + 1]['content'] !== ' ') {
            $error = 'There must be a single space after USE keyword';
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceAfterUse');

            if ($fix) {
                if ($tokens[$stackPtr + 1]['code'] === T_WHITESPACE) {
                    $phpcsFile->fixer->replaceToken($stackPtr + 1, ' ');
                } else {
                    $phpcsFile->fixer->addContent($stackPtr, ' ');
                }
            }
        }

        $scopeOpener = $phpcsFile->findNext([T_OPEN_CURLY_BRACKET, T_SEMICOLON], $stackPtr + 1);

        $comma = $phpcsFile->findNext(T_COMMA, $stackPtr + 1, $scopeOpener - 1);
        if ($comma) {
            $error = 'There must be one USE per declaration';
            $fix = $phpcsFile->addFixableError($error, $comma, 'OneUsePerDeclaration');

            if ($fix) {
                $phpcsFile->fixer->replaceToken($comma, ';' . $phpcsFile->eolChar . 'use ');
            }
        }

        // Check for T_WHITESPACE in trait name.
        $firstNotEmpty = $phpcsFile->findNext(
            T_WHITESPACE,
            $stackPtr + 1,
            $comma ?: $scopeOpener,
            true
        );
        $lastNotEmpty = $phpcsFile->findPrevious(
            T_WHITESPACE,
            ($comma ?: $scopeOpener) - 1,
            $stackPtr + 1,
            true
        );

        if ($firstNotEmpty !== $lastNotEmpty) {
            $emptyInName = $phpcsFile->findNext(
                Tokens::$emptyTokens,
                $firstNotEmpty + 1,
                $lastNotEmpty
            );
            if ($emptyInName) {
                $error = 'Empty token %s is not allowed in trait name';
                $data = [$tokens[$emptyInName]['type']];
                $fix = $phpcsFile->addFixableError($error, $emptyInName, 'EmptyToken', $data);

                if ($fix) {
                    $phpcsFile->fixer->replaceToken($emptyInName, '');
                }
            }
        }

        if ($tokens[$scopeOpener]['code'] === T_OPEN_CURLY_BRACKET) {
            $scopeCloser = $tokens[$scopeOpener]['scope_closer'];

            $prevNonEmpty = $phpcsFile->findPrevious(Tokens::$emptyTokens, $scopeOpener - 1, null, true);
            $nextNonEmpty = $phpcsFile->findNext(Tokens::$emptyTokens, $scopeOpener + 1, null, true);

            if ($scopeCloser === $nextNonEmpty) {
                $error = 'Empty brackets with trait are redundant';
                $fix = $phpcsFile->addFixableError($error, $scopeOpener, 'EmptyBrackets');

                if ($fix) {
                    $phpcsFile->fixer->beginChangeset();
                    for ($i = $prev + 1; $i < $nextNonEmpty; ++$i) {
                        $phpcsFile->fixer->replaceToken($scopeOpener, '');
                        $phpcsFile->fixer->replaceToken($scopeCloser, ';');
                    }
                    $phpcsFile->fixer->endChangeset();
                }
            } elseif ($tokens[$prevNonEmpty]['line'] !== $tokens[$scopeOpener]['line']) {
                $error = 'There must be a single space before curly bracket';
                $fix = $phpcsFile->addFixableError($error, $scopeOpener, 'SpaceBeforeCurly');

                if ($fix) {
                    $phpcsFile->fixer->beginChangeset();
                    for ($i = $prevNonEmpty + 1; $i < $scopeOpener; ++$i) {
                        $phpcsFile->fixer->replaceToken($i, '');
                    }
                    $phpcsFile->fixer->addContentBefore($scopeOpener, ' ');
                    $phpcsFile->fixer->endChangeset();
                }
            } elseif ($tokens[$scopeOpener - 1]['content'] !== ' ') {
                $error = 'There must be a single space before curly bracket';
                $fix = $phpcsFile->addFixableError($error, $scopeOpener, 'SpaceBeforeCurly');

                if ($fix) {
                    if ($tokens[$scopeOpener - 1]['code'] === T_WHITESPACE) {
                        $phpcsFile->fixer->replaceToken($scopeOpener - 1, ' ');
                    } else {
                        $phpcsFile->fixer->addContent($scopeOpener - 1, ' ');
                    }
                }
            }

            if ($nextNonEmpty < $scopeCloser) {
                if ($tokens[$nextNonEmpty]['line'] !== $tokens[$scopeOpener]['line'] + 1) {
                    $error = 'Content must be in next line after opening curly bracket';
                    $fix = $phpcsFile->addFixableError($error, $scopeOpener, 'OpeningCurlyBracket');

                    if ($fix) {
                        $phpcsFile->fixer->beginChangeset();
                        for ($i = $scopeOpener + 1; $i < $nextNonEmpty; ++$i) {
                            $phpcsFile->fixer->replaceToken($i, '');
                        }
                        $phpcsFile->fixer->addContentBefore($nextNonEmpty, $phpcsFile->eolChar);
                        $phpcsFile->fixer->endChangeset();
                    }
                }

                $prevNonEmpty = $phpcsFile->findPrevious(
                    Tokens::$emptyTokens,
                    $scopeCloser - 1,
                    null,
                    true
                );
                if ($tokens[$prevNonEmpty]['line'] + 1 !== $tokens[$scopeCloser]['line']) {
                    $error = 'Close curly bracket must be in next line after content';
                    $fix = $phpcsFile->addFixableError($error, $scopeCloser, 'ClosingCurlyBracket');

                    if ($fix) {
                        $phpcsFile->fixer->beginChangeset();
                        for ($i = $prevNonEmpty + 1; $i < $scopeCloser; ++$i) {
                            $phpcsFile->fixer->replaceToken($i, '');
                        }
                        $phpcsFile->fixer->addContentBefore($scopeCloser, $phpcsFile->eolChar);
                        $phpcsFile->fixer->endChangeset();
                    }
                }
            }

            // Detect all statements inside curly brackets.
            $statements = [];
            $begin = $phpcsFile->findNext(Tokens::$emptyTokens, $scopeOpener + 1, null, true);
            while ($end = $phpcsFile->findNext([T_SEMICOLON], $begin + 1, $scopeCloser)) {
                $statements[] = [
                    'begin' => $begin,
                    'end' => $end,
                    'content' => $phpcsFile->getTokensAsString($begin, $end - $begin + 1),
                ];
                $begin = $phpcsFile->findNext(Tokens::$emptyTokens, $end + 1, null, true);
            }

            $lastStatement = null;
            foreach ($statements as $statement) {
                if (! $lastStatement) {
                    $lastStatement = $statement;
                    continue;
                }

                $order = $this->compareStatements($statement, $lastStatement);

                if ($order < 0) {
                    $error = 'Statements in trait are incorrectly ordered. The first wrong is %s';
                    $data = [$statement['content']];
                    $fix = $phpcsFile->addFixableError($error, $statement['begin'], 'TraitStatementsOrder', $data);

                    if ($fix) {
                        $this->fixAlphabeticalOrder($phpcsFile, $statements);
                    }

                    break;
                }

                $lastStatement = $statement;
            }
        } else {
            $scopeCloser = $scopeOpener;
        }

        $class = $phpcsFile->findPrevious([T_CLASS, T_TRAIT, T_ANON_CLASS], $stackPtr - 1);
        // Only interested in the last USE statement from here onwards.
        $nextUse = $stackPtr;
        do {
            $nextUse = $phpcsFile->findNext(T_USE, $nextUse + 1, $tokens[$class]['scope_closer']);
        } while ($nextUse !== false
            && (! CodingStandard::isTraitUse($phpcsFile, $nextUse)
                || ! isset($tokens[$nextUse]['conditions'][$class])
                || $tokens[$nextUse]['level'] !== $tokens[$class]['level'] + 1)
        );

        if ($nextUse !== false) {
            return;
        }

        // Find next (after traits) non-whitespace token.
        $next = $phpcsFile->findNext(T_WHITESPACE, $scopeCloser + 1, null, true);

        $diff = $tokens[$next]['line'] - $tokens[$scopeCloser]['line'] - 1;
        if ($diff !== 1
            && $tokens[$next]['code'] !== T_CLOSE_CURLY_BRACKET
        ) {
            $error = 'There must be one blank line after the last USE statement; %s found;';
            $data = [$diff];
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceAfterLastUse', $data);

            if ($fix) {
                if ($diff === 0) {
                    $phpcsFile->fixer->addNewline($scopeCloser);
                } else {
                    $phpcsFile->fixer->beginChangeset();
                    for ($i = $scopeCloser + 1; $i < $next; ++$i) {
                        if ($tokens[$i]['line'] === $tokens[$next]['line']) {
                            break;
                        }

                        $phpcsFile->fixer->replaceToken($i, '');
                    }
                    $phpcsFile->fixer->addNewline($scopeCloser);
                    $phpcsFile->fixer->endChangeset();
                }
            }
        }
    }

    /**
     * Fix order of statements inside trait's curly brackets.
     *
     * @param string[] $statements
     *
     * @return void
     */
    private function fixAlphabeticalOrder(File $phpcsFile, array $statements)
    {
        $phpcsFile->fixer->beginChangeset();
        foreach ($statements as $statement) {
            for ($i = $statement['begin']; $i <= $statement['end']; ++$i) {
                $phpcsFile->fixer->replaceToken($i, '');
            }
        }

        usort($statements, function (array $a, array $b) {
            return $this->compareStatements($a, $b);
        });

        $begins = array_column($statements, 'begin');
        sort($begins);

        foreach ($begins as $k => $begin) {
            $phpcsFile->fixer->addContent($begin, $statements[$k]['content']);
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * @param string[] $a
     * @param string[] $b
     */
    private function compareStatements(array $a, array $b) : int
    {
        return strcasecmp(
            $this->clearName($a['content']),
            $this->clearName($b['content'])
        );
    }

    private function clearName(string $name) : string
    {
        return str_replace('\\', ':', $name);
    }
}
