<?php

declare(strict_types=1);

namespace Paysera\PhpCsFixerConfig\Fixer\PSR1;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class FileSideEffectsFixer extends AbstractFixer
{
    use ConfigurableFixerTrait {
        configure as public configureConfigurableFixerTrait;
    }

    public const CONVENTION = '/* TODO: A file should declare new symbols (classes, functions, constants, etc.)
    and cause no other side effects, or it should execute logic with side effects, but should not do both. */';

    private array $forbiddenFunctions;
    private array $forbiddenTokens;

    public function __construct()
    {
        parent::__construct();

        $this->forbiddenFunctions = ['print_r', 'var_dump', 'ini_set'];
        $this->forbiddenTokens = [T_INCLUDE, T_ECHO];
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            <<<'TEXT'
Ensures a file declare new symbols and causes no other side effects,
or executes logic with side effects, but not both.
TEXT,
            [
                new CodeSample(
                    <<<'PHP'
<?php
// side effect: change ini settings
ini_set("error_reporting", E_ALL);
 
// side effect: loads a file
include "file.php";
                  
// declaration
function foo()
{
    // function body
    $a = 1;
    var_dump($a);
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
        return 'Paysera/psr_1_file_side_effects';
    }

    public function isRisky(): bool
    {
        // Paysera Recommendation
        return true;
    }

    public function getPriority(): int
    {
        return -49;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_STRING);
    }

    public function configure(array $configuration = null): void
    {
        $this->configureConfigurableFixerTrait($configuration);

        if ($this->configuration['side_effects'] === true) {
            return;
        }
        if (isset($this->configuration['side_effects']['functions'])) {
            $this->forbiddenFunctions = $this->configuration['side_effects']['functions'];
        }
        if (isset($this->configuration['side_effects']['tokens'])) {
            $this->forbiddenTokens = $this->configuration['side_effects']['tokens'];
        }
    }

    protected function createConfigurationDefinition(): FixerConfigurationResolver
    {
        return
            new FixerConfigurationResolver([
            (new FixerOptionBuilder(
                'side_effects',
                'Set forbidden functions and tokens, e.g. `["functions" => ["print_r"], "tokens" => [T_ECHO]]`.',
            ))
                ->setAllowedTypes(['array', 'bool'])
                ->getOption(),
            ])
        ;
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $sideEffects = 0;
        $symbols = 0;
        $count = $tokens->count();
        foreach ($tokens as $key => $token) {
            $bracketIndex = $tokens->getNextMeaningfulToken($key);
            if ($bracketIndex) {
                $bracketToken = $tokens[$bracketIndex];
                $sideEffects += $this->countSideEffects($token, $bracketToken);
            }
            $symbols += $this->countSymbols($token);

            if ($sideEffects > 0 && $symbols > 0) {
                if (!$tokens[$count - 1]->isGivenKind(T_COMMENT)) {
                    $tokens->insertSlices([
                        $count => [
                            new Token([T_WHITESPACE, "\n"]),
                            new Token([T_COMMENT, self::CONVENTION]),
                        ],
                    ]);
                }
                break;
            }
        }
    }

    private function countSymbols(Token $token): int
    {
        if ($token->isGivenKind([T_CLASS, T_INTERFACE, T_TRAIT, T_FUNCTION])) {
            return 1;
        }

        return 0;
    }

    private function countSideEffects(Token $token, Token $bracketToken): int
    {
        if (
            $token->isGivenKind(T_STRING)
            && in_array($token->getContent(), $this->forbiddenFunctions, true)
            && $bracketToken->equals('(')
            || $token->isGivenKind($this->forbiddenTokens)
        ) {
            return 1;
        }

        return 0;
    }
}
