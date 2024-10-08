<?php

declare(strict_types=1);

namespace Paysera\PhpCsFixerConfig\Fixer\PhpBasic\Basic;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class SingleClassPerFileFixer extends AbstractFixer
{
    public const CONVENTION = 'PhpBasic convention 1.3: Only one class/interface can be declared per file';

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Checks if there is one class per file.',
            [
                new CodeSample(
                    <<<'PHP'
<?php
    class ClassOne
    {
    }
    class ClassTwo
    {
    }

PHP,
                ),
            ],
            null,
            'Paysera recommendation.'
        );
    }

    public function getName(): string
    {
        return 'Paysera/php_basic_basic_single_class_per_file';
    }

    public function isRisky(): bool
    {
        // Paysera Recommendation
        return true;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound(Token::getClassyTokenKinds());
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $tokenCount = $tokens->count();
        $classCount = 0;

        for ($key = 0; $key < $tokenCount; $key++) {
            if ($tokens[$key]->isGivenKind(Token::getClassyTokenKinds())) {
                $classCount++;
            }

            if ($classCount > 1 && !$tokens[$tokenCount - 1]->isGivenKind(T_COMMENT)) {
                $tokens->insertSlices([
                    $tokenCount => [
                        new Token([T_WHITESPACE, "\n"]),
                        new Token([T_COMMENT, '// TODO: "' . $tokens[$key]->getContent() . '" - ' . self::CONVENTION]),
                    ],
                ]);
                break;
            }
        }
    }
}
