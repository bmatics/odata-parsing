<?php
namespace Bmatics\Odata\QueryParser;

use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\ExpressionParserSimple;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\ExpressionType;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\BinaryExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\UnaryExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\FunctionCallExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\SimplePropertyAccessExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\ConstantExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\AbstractExpression;
use ODataProducer\Common\ODataException;
use Bmatics\Odata\Query\QueryInterface;


class OdataProducerQueryParser implements QueryParserInterface
{
	protected $query;

	/**
	 * Construct a new parser for an Odata query
	 *
	 * @param QueryInterface $query
	 */
	public function __construct(QueryInterface $query)
	{
		$this->query = $query;
	}

	/**
	 * Parse the Odata query
	 *
	 * @return stdClass  properties: filter,orderby,top,skip,select,expand
	 */
	public function parse()
	{
		foreach(['filter', 'orderby', 'top', 'skip', 'select', 'expand'] as $queryPart) {
			$raw = $this->query->{'get'.$queryPart}();
			$parsed[$queryPart] = $this->{'parse'.$queryPart}($raw);
		}
		return (object)$parsed;
	}

    /**
	 * Parse on Odata $orderby string into an array
	 *
	 * Example:
	 * input: "user/fname asc, user/lname desc"
	 * output: [["property"=>"user.fname", "direction"=>"asc"], ["property"=>"user.lname", "direction"=>"desc"]]
	 * 
	 * @param string $rawOrderBy
	 * @return array
   	 */
    public function parseOrderBy($rawOrderBy)
    {
		$orderings = explode(',', $rawOrderBy);

		$parsedOrderings = [];

		foreach ($orderings as $ordering) {
			$ordering = trim($ordering);
			if (strlen($ordering) == 0) {
				continue;
			}
			if (!preg_match('@^([A-z0-9]+(?:/[A-z0-9]+)*)(?:\s+(asc|desc))?$@i', $ordering, $matches)) {
				throw new QueryParserException('Syntax error in orderby');
			}

			$property = str_replace('/', '.', $matches[1]);
			$direction = empty($matches[2])? 'asc' : strtolower($matches[2]);

			$parsedOrderings[] = compact('property', 'direction');
		}

		return $parsedOrderings;
	}

	/**
	 * Parse an Odata $skip string into a positive integer
	 *
	 * @param string $rawSkip
	 * @return int|null
	 */
	public function parseSkip($rawSkip)
	{
		$rawSkip = trim($rawSkip);
		if ($rawSkip === '') {
			return null;
		}

		$skip = filter_var($rawSkip, FILTER_VALIDATE_INT, ['min_range'=>0]);

		if ($skip === false) {
			throw new QueryParserException('Syntax error in skip');
		}

		return $skip;
	}

	/**
	 * Parse an Odata $top string into a positive integer
	 *
	 * @param string $rawTop
	 * @return int|null
	 */
	public function parseTop($rawTop)
	{
		$rawTop = trim($rawTop);
		if ($rawTop === '') {
			return null;
		}

		$top = filter_var($rawTop, FILTER_VALIDATE_INT, ['min_range'=>1]);

		if ($top === false) {
			throw new QueryParserException('Syntax error in top');
		}

		return $top;
	}

    /**
	 * Parse on Odata $expand string into an array
	 *
	 * Example:
	 * input: "foo/bar, foo/bar2"
	 * output: ["foo.bar", "foo.bar2"]
	 * 
	 * @param string $rawExpand
	 * @return array
   	 */
	public function parseExpand($rawExpand)
	{
		$expands = explode(',', $rawExpand);

		$parsedExpands = [];

		foreach ($expands as $expand) {
			$expand = trim($expand);
			if ($expand == '') {
				continue;
			}

			if (!preg_match('@^[A-z0-9]+(?:/[A-z0-9]+)*$@', $expand, $matches)) {
				throw new QueryParserException('Syntax error in expand');
			}

			$parsedExpands[] = str_replace('/', '.', $matches[0]);
		}

		return $parsedExpands;
	}

    /**
	 * Parse on Odata $select string into an array
	 *
	 * Example:
	 * input: "*, foo/bar2"
	 * output: ["*", "foo.bar2"]
	 * 
	 * @param string $rawSelect
	 * @return array
   	 */
	public function parseSelect($rawSelect)
	{
		$selects = explode(',', $rawSelect);

		$parsedSelects = [];

		foreach ($selects as $select) {
			$select = trim($select);
			if ($select == '') {
				continue;
			}

			if (!preg_match('@^(?:(?:[A-z0-9]+(?:/[A-z0-9]+)*)|\*)$@', $select, $matches)) {
				throw new QueryParserException('Syntax error in select');
			}

			$parsedSelects[] = str_replace('/', '.', $matches[0]);
		}

		return $parsedSelects;
	}


	/**
     * Parse an Odata $filter string into an array
     * 
	 * Example:
	 * input: "user/fname eq \"Bob\""
	 * output: ["type"=>"eq","left"=>["type"=>"property","value"=>"user.fname"],"right"=>["type"=>"literal", "value"=>"Bob"]]
	 *
     * @param string $rawFilter
     * @return array
     */
    public function parseFilter($rawFilter)
    {
        $parser = new ExpressionParserSimple($rawFilter);

        try {            
            $expression = $parser->parseFilter();
        } catch (ODataException $e) {
            throw new QueryParserException('Syntax error in filter');
        }        

        return $this->expressionToArray($expression);
    }


    private function expressionToArray(AbstractExpression $expression)
    {
        if ($expression instanceof FunctionCallExpression) {
            return $this->functionCallExpressionToArray($expression);

        } elseif ($expression instanceof SimplePropertyAccessExpression) {
            return $this->simplePropertyAccessExpressionToArray($expression);

        } elseif ($expression instanceof ConstantExpression) {
            return $this->constantExpressionToArray($expression);

 		} elseif ($expression instanceof BinaryExpression) {
            return $this->binaryExpressionToArray($expression);

        } elseif ($expression instanceof UnaryExpression) {
            return $this->unaryExpressionToArray($expression);

        } else {
            throw new QueryParserException('Unsupported Expression Type');
        }
    }

    private function functionCallExpressionToArray(FunctionCallExpression $expression)
    {
        $type = 'function';
        $function = $expression->getFunctionDescription()->functionName;
        $params = [];
        foreach($expression->getParamExpressions() as $parameter) {
            $params[] = $this->expressionToArray($parameter);
        }

        return compact('type', 'function', 'params');

    }

    private function simplePropertyAccessExpressionToArray(SimplePropertyAccessExpression $expression)
    {
        $type = 'property';
        $path = [];
        do {
            $path[] = $expression->getPropertyName();
        } while ($expression = $expression->getParent());
        $value = implode('.', array_reverse($path));

        return compact('type', 'value');
    }

    private function constantExpressionToArray(ConstantExpression $expression)
    {
        $type = 'literal';
        $value = $expression->getValue();
        $value = $expression->getType()->convert($value);

        return compact('type', 'value');
    }

    private function binaryExpressionToArray(BinaryExpression $expression)
    {
        $type = $this->getBinaryExpressionType($expression);
        $left = $this->expressionToArray($expression->getLeft());
        $right = $this->expressionToArray($expression->getRight());

        return compact('type', 'left', 'right');
    }

    private function getBinaryExpressionType(BinaryExpression $expression)
    {
        switch ($expression->getNodeType()) {
            case ExpressionType::ADD:
                return 'add';
            case ExpressionType::AND_LOGICAL:
                return 'and';
            case ExpressionType::DIVIDE:
                return 'div';
            case ExpressionType::EQUAL:
                return 'eq';
            case ExpressionType::GREATERTHAN:
                return 'gt';
            case ExpressionType::GREATERTHAN_OR_EQUAL:
                return 'ge';
            case ExpressionType::LESSTHAN:
                return 'lt';
            case ExpressionType::LESSTHAN_OR_EQUAL:
                return 'le';
            case ExpressionType::MODULO:
                return 'mod';
            case ExpressionType::MULTIPLY:
                return 'mul';
            case ExpressionType::NOTEQUAL:
                return 'ne';
            case ExpressionType::OR_LOGICAL:
                return 'or'; 
            case ExpressionType::SUBTRACT:
                return 'sub';
            default:
                throw new QueryParserException('Unsupported Binary Expression Type');
        }
    }

    private function unaryExpressionToArray(UnaryExpression $expression)
    {
        $type = $this->getUnaryExpressionType($expression);
        $child = $this->expressionToArray($expression->getChild());

        return compact('type', 'child');
    }

    private function getUnaryExpressionType(UnaryExpression $expression)
    {
        switch ($expression->getNodeType()) {
            case ExpressionType::NOT_LOGICAL:
                return 'not';
            case ExpressionType::NEGATE:
                return 'neg';
            default:
                throw new QueryParserException('Unsupported Unary Expression Type');
        }
    }

}