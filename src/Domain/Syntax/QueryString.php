<?php

declare(strict_types=1);


namespace JeroenG\Explorer\Domain\Syntax;

use Webmozart\Assert\Assert;

class QueryString implements SyntaxInterface
{
    public const OP_AND = 'AND';

    public const OP_OR = 'OR';

    protected string $queryString;

    protected float $boost;

    protected array $fields = [];

    protected string $defaultOperator;

    public function __construct(string $queryString, string $defaultOperator = self::OP_OR, float $boost = 1.0, array $fields = [])
    {
        Assert::oneOf($defaultOperator, [self::OP_OR, self::OP_AND]);

        $this->queryString = $queryString;
        $this->boost = $boost;
        $this->defaultOperator = $defaultOperator;
        $this->fields = $fields;
    }

    public function build(): array
    {
        $query = [
            'query_string' => [
                'query' => $this->queryString,
                'default_operator' => $this->defaultOperator,
                'boost' => $this->boost,
            ],
        ];
        if (!empty($this->fields)) {
            $query['query_string']['fields'] = $this->fields;
        }
        return $query;
    }
}
