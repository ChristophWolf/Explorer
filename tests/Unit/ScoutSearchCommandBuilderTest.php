<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JeroenG\Explorer\Application\SearchableFields;
use JeroenG\Explorer\Domain\Aggregations\TermsAggregation;
use JeroenG\Explorer\Domain\Query\QueryProperties\SourceFilter;
use JeroenG\Explorer\Domain\Syntax\Compound\BoolQuery;
use JeroenG\Explorer\Domain\Syntax\Compound\QueryType;
use JeroenG\Explorer\Domain\Syntax\MultiMatch;
use JeroenG\Explorer\Domain\Syntax\Sort;
use JeroenG\Explorer\Domain\Syntax\SyntaxInterface;
use JeroenG\Explorer\Domain\Syntax\Term;
use JeroenG\Explorer\Infrastructure\Scout\ScoutSearchCommandBuilder;
use Laravel\Scout\Builder;
use Mockery;
use PHPUnit\Framework\TestCase;

class ScoutSearchCommandBuilderTest extends TestCase
{
    private const TEST_INDEX = 'test_index';

    private const TEST_SEARCHABLE_FIELDS = [':field1:', ':field2:'];

    public function testWrapScoutBuilder(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);

        $builder->model->expects('searchableAs')->andReturn(self::TEST_INDEX);

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame(self::TEST_INDEX, $subject->getIndex());
    }

    public function testThrowExceptionOnNullIndex(): void
    {
        $builder = new ScoutSearchCommandBuilder();
        $this->expectException(InvalidArgumentException::class);
        $builder->getIndex();
    }

    public function testGetIndexFromScoutBuilder(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);

        $builder->index = self::TEST_INDEX;

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame(self::TEST_INDEX, $subject->getIndex());
    }

    public function testGetSearchableFields(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class, SearchableFields::class);

        $builder->index = self::TEST_INDEX;
        $builder->model->expects('getSearchableFields')->andReturn(self::TEST_SEARCHABLE_FIELDS);

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame(self::TEST_SEARCHABLE_FIELDS, $subject->getDefaultSearchFields());
    }

    /** @dataProvider buildCommandProvider */
    public function testSetDataFromScoutBuilder(string $method, mixed $expected): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->index = self::TEST_INDEX;

        $setter = lcfirst($method);
        $getter = "get{$method}";

        $builder->$setter = $expected;

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame($expected, $subject->$getter());
    }

    /** @dataProvider buildCommandProvider */
    public function testGettersAndSetters(string $method, mixed $expected): void
    {
        $command = new ScoutSearchCommandBuilder();

        $setter = "set{$method}";
        $getter = "get{$method}";

        self::assertEmpty($command->$getter());

        $command->$setter($expected);

        self::assertSame($expected, $command->$getter());
    }

    public function buildCommandProvider(): array
    {
        return [
            ['Must', [new Term('field', 'value')]],
            ['Should', [new Term('field', 'value')]],
            ['Filter', [new Term('field', 'value')]],
            ['Wheres', ['field' => 'value']],
            ['WhereIns', ['field' => ['value1', 'value2']]],
            ['Query', 'Lorem Ipsum'],
        ];
    }

    public function testSetSortOrder(): void
    {
        $command = new ScoutSearchCommandBuilder();

        self::assertFalse($command->hasSort());

        $command->setSort([new Sort('id')]);

        self::assertTrue($command->hasSort());
        self::assertSame([['id' => 'asc']], $command->getSort());

        $command->setSort([]);

        self::assertFalse($command->hasSort());
        self::assertSame([], $command->getSort());

        $command->setSort([new Sort('id', 'desc')]);

        self::assertTrue($command->hasSort());
        self::assertSame([['id' => 'desc']], $command->getSort());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected one of: "asc", "desc". Got: "invalid"');

        $command->setSort([new Sort('id', 'invalid')]);
    }

    public function testAcceptOnlySortClasses(): void
    {
        $command = new ScoutSearchCommandBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected an instance of JeroenG\Explorer\Domain\Syntax\Sort. Got: string');

        $command->setSort(['not' => 'a class']);
    }

    public function testSetFields(): void
    {
        $input = ['specific.field', '*.length'];
        $command = new ScoutSearchCommandBuilder();

        self::assertFalse($command->hasFields());
        self::assertSame([], $command->getFields());

        $command->setFields($input);

        self::assertTrue($command->hasFields());
        self::assertSame($input, $command->getFields());

        $command->setFields([]);
        self::assertFalse($command->hasFields());
        self::assertSame([], $command->getFields());
    }

    public function testGetSort(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);

        $builder->index = self::TEST_INDEX;
        $builder->orders = [[ 'column' => 'id', 'direction' => 'asc']];

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame([['id' => 'asc']], $subject->getSort());
    }

    public function testGetFieldsFromScoutBuilder(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);
        $input = ['my.field', 'your.field'];

        $builder->index = self::TEST_INDEX;
        $builder->fields = $input;

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame($input, $subject->getFields());
    }

    public function testSetLimit(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);
        $builder->index = self::TEST_INDEX;
        $builder->limit = 5;

        $subject = ScoutSearchCommandBuilder::wrap($builder);
        $query = $subject->buildQuery();

        $expected = [
            'size' => 5,
            'query' => ['bool' => ['must' => [], 'should' => [], 'filter' => []]]
        ];

        self::assertEquals($expected, $query);
    }

    public function testCustomCompound(): void
    {
        $command = new ScoutSearchCommandBuilder();
        $compound = new BoolQuery();

        $command->setBoolQuery($compound);

        self::assertSame($compound, $command->getBoolQuery());
    }

    public function testAcceptMinimumMatch(): void
    {
        $subject = new ScoutSearchCommandBuilder();

        $subject->setMinimumShouldMatch('50%');

        $query = $subject->buildQuery();

        $expectedQuery = [
            'query' => ['bool' => ['must' => [], 'should' => [], 'filter' => [], 'minimum_should_match' => '50%']],
        ];

        self::assertEquals($expectedQuery, $query);
    }

    public function testWrapsWithCustomCompound(): void
    {
        $compound = Mockery::mock(BoolQuery::class);
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);
        $builder->index = self::TEST_INDEX;
        $builder->compound = $compound;

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame($compound, $subject->getBoolQuery());
    }

    public function testDefaultBoolQueryCompound(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);
        $builder->index = self::TEST_INDEX;

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertInstanceOf(BoolQuery::class, $subject->getBoolQuery());
    }

    public function testBuildQuery(): void
    {
        $subject = new ScoutSearchCommandBuilder();

        $query = $subject->buildQuery();

        self::assertEquals(['query' => ['bool' => ['must' => [], 'should' => [], 'filter' => []]]], $query);
    }

    public function testBuildQueryWithInput(): void
    {
        $subject = new ScoutSearchCommandBuilder();
        $sort = new Sort('sortfield', Sort::DESCENDING);
        $fields = ['test.field', 'other.field'];

        $subject->setOffset(10);
        $subject->setLimit(30);
        $subject->setSort([$sort]);
        $subject->setFields($fields);

        $query = $subject->buildQuery();

        $expectedQuery = [
            'query' => ['bool' => ['must' => [], 'should' => [], 'filter' => []]],
            'from' => 10,
            'size' => 30,
            'sort' => [$sort->build()],
            'fields' => $fields
        ];

        self::assertEquals($expectedQuery, $query);
    }

    public function testAddScoutPropertiesToBoolQuery(): void
    {
        $boolQuery = Mockery::mock(BoolQuery::class);
        $subject = new ScoutSearchCommandBuilder();
        $term = new Term('field', 'value');
        $defaultFields = ['description', 'name'];
        $searchQuery = 'myQuery';
        $whereField = 'whereField';
        $whereValue = 'whereValue';
        $returnQuery = [ 'return' => 'query' ];

        $subject->setDefaultSearchFields($defaultFields);
        $subject->setQuery($searchQuery);
        $subject->setMust([$term]);
        $subject->setFilter([$term]);
        $subject->setShould([$term]);
        $subject->setBoolQuery($boolQuery);
        $subject->setWheres([ $whereField => $whereValue ]);
        $subject->setMinimumShouldMatch('50%');

        $boolQuery->expects('clone')->andReturn($boolQuery);
        $boolQuery->expects('addMany')->with(QueryType::MUST, [$term]);
        $boolQuery->expects('addMany')->with(QueryType::SHOULD, [$term]);
        $boolQuery->expects('addMany')->with(QueryType::FILTER, [$term]);
        $boolQuery->expects('minimumShouldMatch')->with('50%');
        $boolQuery->expects('build')->andReturn($returnQuery);

        $boolQuery->expects('add')
            ->withArgs(function (string $type, SyntaxInterface $query) {
                return $type === 'must'
                    && $query instanceof MultiMatch;
            });

        $boolQuery->expects('add')
            ->withArgs(function (string $type, SyntaxInterface $query) {
                return $type === 'filter'
                    && $query instanceof Term;
            });

        $query = $subject->buildQuery();

        self::assertSame([ 'query' => $returnQuery ], $query);
    }

    public function testBuildQueryWithAggregations(): void
    {
        $subject = new ScoutSearchCommandBuilder();
        $aggregation = new TermsAggregation(':field:');

        $subject->setAggregations([':name:' => $aggregation]);

        $query = $subject->buildQuery();

        $expectedQuery = [
            'query' => ['bool' => ['must' => [], 'should' => [], 'filter' => []]],
            'aggs' => [':name:' => ['terms' => ['field' => ':field:', 'size' => 10]]]
        ];

        self::assertEquals($expectedQuery, $query);
    }

    public function testBuildQueryWithQueryProperty(): void
    {
        $subject = new ScoutSearchCommandBuilder();
        $queryProperty = SourceFilter::empty()->include('*');

        $subject->addQueryProperties($queryProperty);

        $query = $subject->buildQuery();

        $expectedQuery = [
            'query' => ['bool' => ['must' => [], 'should' => [], 'filter' => []]],
            '_source' => ['include' => ['*'] ]
        ];

        self::assertEquals($expectedQuery, $query);
    }

    public function testWrapScoutBuilderAggregations(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);
        $input = [':name:' => ['terms' => ['field' => ':field:', 'size' => 10]]];

        $builder->index = self::TEST_INDEX;
        $builder->aggregations = $input;

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame($input, $subject->getAggregations());
    }

    public function testWrapScoutBuilderMinimumShouldMatch(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);
        $minimumShouldMatch = '50%';

        $builder->index = self::TEST_INDEX;
        $builder->minimumShouldMatch = $minimumShouldMatch;

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame($minimumShouldMatch, $subject->getMinimumShouldMatch());
    }

    public function testWrapScoutBuilderQueryProperties(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->queryProperties = [SourceFilter::empty()->exclude('*.id')];
        $builder->model = Mockery::mock(Model::class);
        $builder->index = self::TEST_INDEX;

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame($builder->queryProperties, $subject->getQueryProperties());
    }

    public function testCallBuilderCallback(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = Mockery::mock(Model::class);
        $builder->index = self::TEST_INDEX;
        $limit  = random_int(1, 1000);
        $offset = random_int(1, 1000);

        $builder->callback = function (ScoutSearchCommandBuilder $builder) use ($limit, $offset){
            $builder->setLimit($limit);
            $builder->setOffset($offset);
        };

        $subject = ScoutSearchCommandBuilder::wrap($builder);

        self::assertSame($limit, $subject->getLimit());
        self::assertSame($offset, $subject->getOffset());
    }
}
