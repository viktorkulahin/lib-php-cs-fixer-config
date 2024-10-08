<?php

declare(strict_types=1);

namespace Paysera\PhpCsFixerConfig\Fixer\PhpBasic\Feature;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class StaticMethodsFixer extends AbstractFixer
{
    public const CONVENTION = 'PhpBasic convention 3.11: Static function must return only "self" or "static" constants';

    private array $validTokens;

    public function __construct()
    {
        parent::__construct();

        $this->validTokens = [
            T_COMMENT,
            T_STATIC,
            T_STRING,
            T_DOUBLE_COLON,
            T_RETURN,
            T_DOUBLE_ARROW,
            T_WHITESPACE,
            T_VARIABLE,
        ];
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            <<<'TEXT'
We do use static methods only in these cases:
* to create an entity for fluent interface, if PHP version in the project is lower than 5.4.
    We use (new Entity())->set(\'a\') in 5.4 or above
* to give available values for some field of an entity, used in validation.
TEXT,
            [
                new CodeSample(
                    <<<'PHP'
<?php
class Sample {
    public static function someFunction()
    {
        return SomeClass::get("something");
    }
}

PHP,
                ),
            ],
            null,
            'Paysera recommendation.',
        );
    }

    public function getName(): string
    {
        return 'Paysera/php_basic_feature_static_methods';
    }

    public function isRisky(): bool
    {
        // Paysera Recommendation
        return true;
    }

    public function getPriority(): int
    {
        // Should run after `VisibilityRequiredFixer` and after `ArraySyntaxFixer` as short syntax
        return -10;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([T_STATIC, T_FUNCTION]);
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $key => $token) {
            $functionIndex = $tokens->getNextMeaningfulToken($key);
            $functionNameIndex = $functionIndex ? $tokens->getNextMeaningfulToken($functionIndex) : null;

            if (
                $token->isGivenKind(T_STATIC)
                && $tokens[$functionIndex]->isGivenKind(T_FUNCTION)
                && $tokens[$functionNameIndex]->isGivenKind(T_STRING)
            ) {
                $curlyBraceStartIndex = $tokens->getNextTokenOfKind($key, ['{']);
                $curlyBraceEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $curlyBraceStartIndex);
                if (
                    !$this->isStaticMethodValid($tokens, $curlyBraceStartIndex, $curlyBraceEndIndex)
                    && !$tokens[$curlyBraceStartIndex - 2]->isGivenKind(T_COMMENT)
                ) {
                    $tokens->insertSlices([
                        ($curlyBraceStartIndex - 1) => [
                            new Token([T_WHITESPACE, ' ']),
                            new Token([
                                T_COMMENT,
                                '// TODO: "' . $tokens[$functionNameIndex]->getContent() . '" - ' . self::CONVENTION,
                            ]),
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * @param Tokens $tokens
     * @param int $startIndex
     * @param int $endIndex
     * @return bool
     */
    private function isStaticMethodValid(Tokens $tokens, $startIndex, $endIndex): bool
    {
        for ($i = $startIndex + 1; $i < $endIndex; $i++) {
            if (
                $tokens[$i]->isGivenKind($this->validTokens)
                || $tokens[$i]->equalsAny([',', ';'])
                || $tokens[$i]->getContent() === '['
                || $tokens[$i]->getContent() === ']'
            ) {
                continue;
            }

            return false;
        }

        return true;
    }
}
