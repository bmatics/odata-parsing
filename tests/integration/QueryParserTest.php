<?php


class QueryParserTest extends \Codeception\TestCase\Test
{
    /**
     * @var \IntegrationTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
        Mockery::close();
    }

    // tests
    public function testParsingLaravelRequestQuery()
    {
        $request = Mockery::mock('Illuminate\Http\Request');
        $request->shouldReceive('query')->once()->with('$filter', '')->andReturn('user/fname eq \'Bob\'');
        $request->shouldReceive('query')->once()->with('$orderby', '')->andReturn('user/lname desc');
        $request->shouldReceive('query')->once()->with('$select', '')->andReturn('*');
        $request->shouldReceive('query')->once()->with('$expand', '')->andReturn('user/address, user/phone');
        $request->shouldReceive('query')->once()->with('$skip', '')->andReturn('2');
        $request->shouldReceive('query')->once()->with('$top', '')->andReturn('3');

        $query = new Bmatics\Odata\Query\LaravelRequestWrapper($request);

        $parser = new Bmatics\Odata\QueryParser\OdataProducerQueryParser($query);

        $result = (array)$parser->parse();

        ksort($result);

        $expected = [
            'expand' => [
                'user.address',
                'user.phone',
            ],
            'filter' => [
                'type' => 'eq',
                'left' => [
                    'type' => 'property',
                    'value' => 'user.fname',
                ],
                'right' => [
                    'type' => 'literal',
                    'value' => 'Bob',
                ],
            ],
            'orderby' => [
                ['property'=>'user.lname', 'direction'=>'desc']
            ],
            'select' => ['*'],
            'skip' => 2,
            'top' => 3,
        ];

        $this->assertSame($expected, $result);
    }

}