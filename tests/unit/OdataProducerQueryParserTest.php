<?php


class OdataProducerQueryParserTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $parser;

    protected function _before()
    {
        $request = Mockery::mock('Illuminate\Http\Request');

        $query = new Bmatics\Odata\Query\LaravelRequestWrapper($request);

        $this->parser = new Bmatics\Odata\QueryParser\OdataProducerQueryParser($query);
    }

    protected function _after()
    {
        Mockery::close();
    }

    // tests
    public function testSimpleEqFilter()
    {
        $filter = 'user/id eq 7';
        $parsed = $this->parser->parseFilter($filter);

        $expected = [
            'type' => 'eq',
            'left' => [
                'type' => 'property',
                'value' => 'user.id'
            ],
            'right' => [
                'type' => 'literal',
                'value' => 7
            ]
        ];
        $this->assertSame($expected, $parsed);
    }

    public function testFunctionCallFilter()
    {
        $filter = 'startsWith(manager/name, \'bob\') eq true';
        $parsed = $this->parser->parseFilter($filter);

        $expected = [
            'type' => 'eq',
            'left' => [
                'type' => 'function',
                'function' => 'startsWith', 'params' => [
                    ['type' => 'property', 'value' => 'manager.name'],
                    ['type' => 'literal', 'value' => 'bob']
                ]
            ],
            'right' => [
                'type' => 'literal',
                'value' => true
            ]
        ];
        $this->assertSame($expected, $parsed);
    }

    public function testGroupedFilter()
    {
        $filter = '(user/address/state eq \'MD\' or user/address/state eq \'VA\') and -(user/activated eq false)';

        $parsed = $this->parser->parseFilter($filter);

        $expected = [
            'type' => 'and',
            'left' => [
                'type' => 'or',
                'left' => [
                    'type' => 'eq',
                    'left' => [
                        'type' => 'property',
                        'value' => 'user.address.state'
                    ],
                    'right' => [
                        'type' => 'literal',
                        'value' => 'MD'
                    ],
                ],
                'right' => [
                    'type' => 'eq',
                    'left' => [
                        'type' => 'property',
                        'value' => 'user.address.state'
                    ],
                    'right' => [
                        'type' => 'literal',
                        'value' => 'VA'
                    ]
                ]
            ],
            'right' => [
                'type' => 'neg',
                'child' => [
                    'type' => 'eq',
                    'left' => [
                        'type' => 'property',
                        'value' => 'user.activated',
                    ],
                    'right' => [
                        'type' => 'literal',
                        'value' => false,
                    ]
                ]
            ]
        ];

        $this->assertSame($expected, $parsed);
    }

    public function testEmptyFilter()
    {
        $filter = '  ';

        $parsed = $this->parser->parseFilter($filter);

        $expected = [];

        $this->assertSame($expected, $parsed);
    }


    public function testBadSyntaxFilter()
    {
        $filter = '(user/address/state \'MD\')';

        $this->setExpectedException('Bmatics\\Odata\\QueryParser\\QueryParserException');

        $parsed = $this->parser->parseFilter($filter);        
    }


    public function testSimpleOrderBy()
    {
        $orderBy = 'user/lname';

        $expected = [['property' => 'user.lname', 'direction' => 'asc']];

        $parsed = $this->parser->parseOrderBy($orderBy);

        $this->assertSame($expected, $parsed);
    }

    public function testMultipleOrderBy()
    {
        $orderBy = ' user/lname desc , user/fname ';

        $expected = [['property' => 'user.lname', 'direction'=>'desc'],['property'=>'user.fname', 'direction'=>'asc']];

        $parsed = $this->parser->parseOrderBy($orderBy);

        $this->assertSame($expected, $parsed);
    }

    public function testEmptyOrderBy()
    {
        $orderBy = '  ';

        $expected = [];

        $parsed = $this->parser->parseOrderBy($orderBy);

        $this->assertSame($expected, $parsed); 
    }

    public function testBadSyntaxOrderBy()
    {
        $orderBy = 'foo bar';

        $this->setExpectedException('Bmatics\\Odata\\QueryParser\\QueryParserException');

        $this->parser->parseOrderBy($orderBy);
    }

    public function testSkip()
    {
        $skip = ' 30';

        $expected = 30;

        $parsed = $this->parser->parseSkip($skip);

        $this->assertSame($expected, $parsed);
    }

    public function testEmptySkip()
    {
        $skip = '  ';

        $expected = null;

        $parsed = $this->parser->parseSkip($skip);

        $this->assertSame($expected, $parsed);
    }

    public function testBadSyntaxSkip()
    {
        $skip = 'blah';

        $this->setExpectedException('Bmatics\\Odata\\QueryParser\\QueryParserException');

        $this->parser->parseSkip($skip);
    }

    public function testTop()
    {
        $top = '100 ';

        $expected = 100;

        $parsed = $this->parser->parseTop($top);

        $this->assertSame($expected, $parsed);
    }

    public function testEmptyTop()
    {
        $top = '  ';

        $expected = null;

        $parsed = $this->parser->parseTop($top);

        $this->assertSame($expected, $parsed);
    }

    public function testBadSyntaxTop()
    {
        $top = 'blah';

        $this->setExpectedException('Bmatics\\Odata\\QueryParser\\QueryParserException');

        $this->parser->parseTop($top);
    }

    public function testSimpleSelect()
    {
        $select = '*';

        $expected = ['*'];

        $parsed = $this->parser->parseSelect($select);

        $this->assertSame($expected, $parsed);
    }

    public function testComplexSelect()
    {
        $select = ' user/lname , *, user/fname';

        $expected = ['user.lname', '*', 'user.fname'];

        $parsed = $this->parser->parseSelect($select);

        $this->assertSame($expected, $parsed);
    }

    public function testEmptySelect()
    {
        $select = '  ';

        $expected = [];

        $parsed = $this->parser->parseSelect($select);

        $this->assertSame($expected, $parsed);
    }

    public function testBadSyntaxSelect()
    {
        $select = 'bad data';

        $this->setExpectedException('Bmatics\\Odata\\QueryParser\\QueryParserException');

        $this->parser->parseSelect($select);
    }

    public function testSimpleExpand()
    {
        $expand = 'user/address';

        $expected = ['user.address'];

        $parsed = $this->parser->parseExpand($expand);

        $this->assertSame($expected, $parsed);
    }

    public function testComplexExpand()
    {
        $expand = ' user/address ,a/b/c';

        $expected = ['user.address', 'a.b.c'];

        $parsed = $this->parser->parseExpand($expand);

        $this->assertSame($expected, $parsed);
    }

    public function testEmptyExpand()
    {
        $expand = '  ';
        
        $expected = [];

        $parsed = $this->parser->parseExpand($expand);

        $this->assertSame($expected, $parsed);
    }

    public function testBadSyntaxExpand()
    {
        $expand = '\'user\'';

        $this->setExpectedException('Bmatics\\Odata\\QueryParser\\QueryParserException');

        $this->parser->parseExpand($expand);

    }

}