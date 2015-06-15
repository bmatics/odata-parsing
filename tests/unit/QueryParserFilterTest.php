<?php


class QueryParserFilterTest extends \Codeception\TestCase\Test
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


    public function testBadSyntaxFilter()
    {
        $filter = '(user/address/state \'MD\')';

        $this->setExpectedException('Bmatics\\Odata\\QueryParser\\QueryParserException');

        $parsed = $this->parser->parseFilter($filter);        
    }

}