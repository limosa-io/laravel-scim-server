<?php

namespace ArieTimmerman\Laravel\SCIMServer\Filter;

use ArieTimmerman\Laravel\SCIMServer\Filter\Exception\FilterException;

class Tokenizer
{
    private string $input;

    private int $length;

    private int $position = 0;

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->length = strlen($input);
    }

    /**
     * @return Token[]
     */
    public function tokenize(): array
    {
        $tokens = [];
        do {
            $token = $this->nextToken();
            $tokens[] = $token;
        } while ($token->type !== 'EOF');

        return $tokens;
    }

    private function nextToken(): Token
    {
        $this->skipWhitespace();

        if ($this->position >= $this->length) {
            return new Token('EOF', '', $this->position);
        }

        $char = $this->input[$this->position];

        switch ($char) {
            case '(':
                return $this->consumeSingle('LPAREN');
            case ')':
                return $this->consumeSingle('RPAREN');
            case '[':
                return $this->consumeSingle('LBRACKET');
            case ']':
                return $this->consumeSingle('RBRACKET');
            case '.':
                return $this->consumeSingle('DOT');
            case ':':
                return $this->consumeSingle('COLON');
            default:
                break;
        }

        if ($char === '"') {
            return $this->consumeString();
        }

        if ($char === '-' && $this->peekIsDigit(1)) {
            return $this->consumeNumber();
        }

        if ($this->isDigit($char)) {
            return $this->consumeNumber();
        }

        if ($this->isIdentifierStart($char)) {
            return $this->consumeIdentifier();
        }

        throw FilterException::syntaxError('Unexpected character', $this->input, $this->position);
    }

    private function consumeSingle(string $type): Token
    {
        $token = new Token($type, $this->input[$this->position], $this->position);
        $this->position++;

        return $token;
    }

    private function consumeString(): Token
    {
        $start = $this->position;
        $this->position++; // skip opening quote
        $escaped = false;
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            if ($escaped) {
                $escaped = false;
                $this->position++;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $this->position++;
                continue;
            }

            if ($char === '"') {
                $literal = substr($this->input, $start, $this->position - $start + 1);
                $this->position++;
                return new Token('STRING', $literal, $start);
            }

            $this->position++;
        }

        throw FilterException::syntaxError('Unterminated string literal', $this->input, $start);
    }

    private function consumeNumber(): Token
    {
        $start = $this->position;
        if ($this->input[$this->position] === '-') {
            $this->position++;
        }

        while ($this->position < $this->length && $this->isDigit($this->input[$this->position])) {
            $this->position++;
        }

        if ($this->position < $this->length && $this->input[$this->position] === '.') {
            $this->position++;
            while ($this->position < $this->length && $this->isDigit($this->input[$this->position])) {
                $this->position++;
            }
        }

        $value = substr($this->input, $start, $this->position - $start);

        return new Token('NUMBER', $value, $start);
    }

    private function consumeIdentifier(): Token
    {
        $start = $this->position;
        $this->position++;

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            if (!$this->isIdentifierChar($char)) {
                break;
            }
            $this->position++;
        }

        $value = substr($this->input, $start, $this->position - $start);

        return new Token('IDENT', $value, $start);
    }

    private function skipWhitespace(): void
    {
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            if (!ctype_space($char)) {
                break;
            }
            $this->position++;
        }
    }

    private function peekIsDigit(int $offset): bool
    {
        $pos = $this->position + $offset;
        if ($pos >= $this->length) {
            return false;
        }

        return $this->isDigit($this->input[$pos]);
    }

    private function isDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || in_array($char, ['_', '-', '.', ':', '/', '$']);
    }

    private function isIdentifierStart(string $char): bool
    {
        return $this->isIdentifierChar($char);
    }
}
