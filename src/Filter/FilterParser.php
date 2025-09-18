<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter;

use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\AttributePath;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ComparisonExpression;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Conjunction;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Disjunction;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Filter;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Negation;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Path;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\Term;
use ArieTimmerman\Laravel\SCIMServer\Filter\Ast\ValuePath;
use ArieTimmerman\Laravel\SCIMServer\Filter\Exception\FilterException;

class FilterParser
{
    /** @var Token[] */
    private array $tokens = [];

    private int $position = 0;

    private int $lastIndex = 0;

    public function parseFilter(string $input): Filter
    {
        $this->prepare($input);

        $node = $this->parseDisjunction();

        $this->expect('EOF');

        return $node;
    }

    public function parsePath(string $input): Path
    {
        $this->prepare($input);

        $attributePath = $this->parseAttributePath();

        if ($this->check('LBRACKET')) {
            $valuePath = $this->parseValuePathFollowing($attributePath);
            $attributePathSuffix = null;

            if ($this->check('DOT')) {
                $this->advance();
                $attributePathSuffix = $this->parseAttributePath();
            }

            $this->expect('EOF');

            return Path::fromValuePath($valuePath, $attributePathSuffix);
        }

        $this->expect('EOF');

        return Path::fromAttributePath($attributePath);
    }

    private function parseDisjunction(): Term
    {
        $terms = [];
        $terms[] = $this->parseConjunction();

        while ($this->matchesKeyword('or')) {
            $terms[] = $this->parseConjunction();
        }

        if (count($terms) === 1) {
            return $terms[0];
        }

        return new Disjunction($terms);
    }

    private function parseConjunction(): Filter
    {
        $factors = [];
        $factors[] = $this->parseFactor();

        while ($this->matchesKeyword('and')) {
            $factors[] = $this->parseFactor();
        }

        if (count($factors) === 1) {
            return $factors[0];
        }

        return new Conjunction($factors);
    }

    private function parseFactor(): Filter
    {
        if ($this->matchesKeyword('not')) {
            $this->expect('LPAREN');
            $filter = $this->parseDisjunction();
            $this->expect('RPAREN');

            return new Negation($filter);
        }

        if ($this->match('LPAREN')) {
            $filter = $this->parseDisjunction();
            $this->expect('RPAREN');

            return $filter;
        }

        $attributePath = $this->parseAttributePath();

        if ($this->check('LBRACKET')) {
            return $this->parseValuePathFollowing($attributePath);
        }

        return $this->parseComparisonFollowing($attributePath);
    }

    private function parseValuePathFollowing(AttributePath $attributePath): ValuePath
    {
        $this->expect('LBRACKET');
        $filter = $this->parseDisjunction();
        $this->expect('RBRACKET');

        return new ValuePath($attributePath, $filter);
    }

    private function parseComparisonFollowing(AttributePath $attributePath): ComparisonExpression
    {
        $operatorToken = $this->expect('IDENT');
        $operator = strtolower($operatorToken->value);

        if (!in_array($operator, ['pr', 'eq', 'ne', 'co', 'sw', 'ew', 'gt', 'lt', 'ge', 'le'], true)) {
            throw FilterException::syntaxError('Unknown comparison operator ' . $operatorToken->value, $this->getInput(), $operatorToken->position);
        }

        $compareValue = null;
        if ($operator !== 'pr') {
            $compareValue = $this->parseCompareValue();
        }

        return new ComparisonExpression($attributePath, $operator, $compareValue);
    }

    private function parseCompareValue(): mixed
    {
        $token = $this->advance();

        return match ($token->type) {
            'STRING' => $this->decodeString($token),
            'NUMBER' => $this->decodeNumber($token),
            'IDENT' => $this->decodeIdentifierValue($token),
            default => throw FilterException::syntaxError('Expected comparison value', $this->getInput(), $token->position),
        };
    }

    private function decodeString(Token $token): mixed
    {
        $decoded = json_decode($token->value, associative: false);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw FilterException::syntaxError('Invalid string literal', $this->getInput(), $token->position);
        }

        return $decoded;
    }

    private function decodeNumber(Token $token): mixed
    {
        return str_contains($token->value, '.') ? (float)$token->value : (int)$token->value;
    }

    private function decodeIdentifierValue(Token $token): mixed
    {
        $value = strtolower($token->value);

        return match ($value) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => throw FilterException::syntaxError(
                'Comparison values must be quoted strings, numbers, booleans, or null',
                $this->getInput(),
                $token->position
            ),
        };
    }

    private function parseAttributePath(): AttributePath
    {
        $token = $this->current();
        if (!in_array($token->type, ['IDENT', 'NUMBER'], true)) {
            throw FilterException::syntaxError('Expected attribute path', $this->getInput(), $token->position);
        }

        $startPosition = $token->position;
        $raw = '';

        $expectSegment = true;

        while (true) {
            $token = $this->current();

            if (in_array($token->type, ['IDENT', 'NUMBER'], true)) {
                if (!$expectSegment) {
                    break;
                }

                $raw .= $token->value;
                $this->advance();
                $expectSegment = false;
                continue;
            }

            if (in_array($token->type, ['COLON', 'DOT'], true)) {
                if ($expectSegment) {
                    throw FilterException::syntaxError('Invalid attribute path', $this->getInput(), $token->position);
                }

                $raw .= $token->value;
                $this->advance();
                $expectSegment = true;
                continue;
            }

            break;
        }

        $raw = trim($raw);
        if ($raw === '' || $expectSegment) {
            throw FilterException::syntaxError('Invalid attribute path', $this->getInput(), $startPosition);
        }

        $schema = null;
        $attributeString = $raw;

        $lastColonPos = strrpos($raw, ':');
        if ($lastColonPos !== false) {
            $schemaCandidate = substr($raw, 0, $lastColonPos);
            $attributeString = substr($raw, $lastColonPos + 1);
            if ($schemaCandidate !== '') {
                $schema = $schemaCandidate;
            }
        }

        $attributeNames = $attributeString === ''
            ? []
            : array_values(array_filter(explode('.', $attributeString), fn ($part) => $part !== ''));

        if ($schema === null && empty($attributeNames)) {
            throw FilterException::syntaxError('Invalid attribute path', $this->getInput(), $startPosition);
        }

        $this->assertValidAttributeNames($attributeNames, $startPosition);

        return new AttributePath($schema, $attributeNames);
    }

    private function prepare(string $input): void
    {
        $this->tokens = (new Tokenizer($input))->tokenize();
        $this->position = 0;
        $this->lastIndex = max(count($this->tokens) - 1, 0);
        $this->inputCache = $input;
    }

    private function advance(): Token
    {
        $token = $this->current();

        if ($this->position < $this->lastIndex) {
            $this->position++;
        }

        return $token;
    }

    private function current(): Token
    {
        return $this->tokens[$this->position] ?? $this->tokens[$this->lastIndex];
    }

    private function match(string $type): bool
    {
        if ($this->check($type)) {
            $this->advance();

            return true;
        }

        return false;
    }

    private function check(string $type): bool
    {
        return $this->current()->type === $type;
    }

    private function expect(string $type): Token
    {
        $token = $this->current();
        if ($token->type !== $type) {
            throw FilterException::syntaxError('Expected ' . $type, $this->getInput(), $token->position);
        }

        $this->advance();

        return $token;
    }

    private function matchesKeyword(string $keyword): bool
    {
        $token = $this->current();
        if ($token->type !== 'IDENT') {
            return false;
        }

        if (strcasecmp($token->value, $keyword) !== 0) {
            return false;
        }

        $this->advance();

        return true;
    }

    private function getInput(): string
    {
        return $this->inputCache ?? '';
    }

    private string $inputCache = '';

    private function assertValidAttributeNames(array $names, int $position): void
    {
        foreach ($names as $name) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
                throw FilterException::syntaxError(
                    sprintf('Invalid attribute name "%s"', $name),
                    $this->getInput(),
                    $position
                );
            }
        }
    }
}
