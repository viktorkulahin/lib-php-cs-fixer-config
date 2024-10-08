<?php

declare(strict_types=1);

namespace Paysera\PhpCsFixerConfig\SyntaxParser;

use Paysera\PhpCsFixerConfig\Parser\Entity\ContextualToken;
use Paysera\PhpCsFixerConfig\SyntaxParser\Entity\ImportedClasses;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

class ImportedClassesParser
{
    public function parseImportedClasses(ContextualToken $firstToken): ImportedClasses
    {
        $importedClasses = new ImportedClasses();
        $token = $firstToken;
        while ($token !== null) {
            if ($token->isGivenKind(T_NAMESPACE)) {
                $token = $this->parseNamespaceStatement($token, $importedClasses);
            } elseif ($token->isGivenKind(T_USE)) {
                $token = $this->parseUseStatement($token, $importedClasses);
            }

            $token = $token->getNextToken();
        }

        return $importedClasses;
    }

    public function parseImportedClassesFromTokens(Tokens $tokens): ImportedClasses
    {
        $namespace = $this->getNamespace($tokens);
        $importedClasses = (new ImportedClasses())->setCurrentNamespace($namespace);

        foreach ((new TokensAnalyzer($tokens))->getImportUseIndexes() as $useIndex) {
            $this->parseUseStatementFromTokens($useIndex, $tokens, $importedClasses);
        }

        return $importedClasses;
    }

    public function getNamespace(Tokens $tokens): string
    {
        $namespaceIndex = $tokens->getNextTokenOfKind(0, [[T_NAMESPACE]]);
        $namespaceEndIndex = $namespaceIndex ? $tokens->getNextTokenOfKind($namespaceIndex, [';']) : null;

        if($namespaceIndex && $namespaceEndIndex) {
            return $tokens->generatePartialCode(
                $tokens->getNextMeaningfulToken($namespaceIndex),
                $tokens->getPrevMeaningfulToken($namespaceEndIndex)
            );
        }

        return '';
    }

    public function parseUseStatementFromTokens(int $start, Tokens $tokens, ImportedClasses $importedClasses)
    {
        $className = null;
        $currentContent = '';
        for ($index = $start + 1; $index < $tokens->count(); $index++) {
            $token = $tokens[$index];
            if ($token->getContent() === ';') {
                break;
            }
            if ($token->isGivenKind(T_AS)) {
                $className = $currentContent;
                $currentContent = '';
            } elseif (!$token->isWhitespace()) {
                $currentContent .= $token->getContent();
            }
        }

        if ($className === null) {
            $className = $currentContent;
            preg_match('/[^\\\\]+$/', $className, $matches);
            $importName = $matches[0];
        } else {
            $importName = $currentContent;
        }

        $importedClasses->registerImport($importName, $className);
    }

    private function parseUseStatement(ContextualToken $useToken, ImportedClasses $importedClasses): ContextualToken
    {
        $token = $useToken->nextToken();

        $className = null;
        $importName = null;
        $currentContent = '';
        while ($token->getContent() !== ';') {
            if ($token->isGivenKind(T_AS)) {
                $className = $currentContent;
                $currentContent = '';
            } elseif (!$token->isWhitespace()) {
                $currentContent .= $token->getContent();
            }
            $token = $token->nextToken();
        }

        if ($className === null) {
            $className = $currentContent;
            preg_match('/[^\\\\]+$/', $className, $matches);
            $importName = $matches[0];
        } else {
            $importName = $currentContent;
        }

        $importedClasses->registerImport($importName, $className);

        return $token;
    }

    private function parseNamespaceStatement(
        ContextualToken $namespaceToken,
        ImportedClasses $importedClasses
    ): ContextualToken {
        $token = $namespaceToken->nextNonWhitespaceToken();
        $namespace = '';
        while ($token->getContent() !== ';' && !$token->isWhitespace()) {
            $namespace .= $token->getContent();
            $token = $token->getNextToken();
        }

        $importedClasses->setCurrentNamespace($namespace);

        return $token;
    }
}
