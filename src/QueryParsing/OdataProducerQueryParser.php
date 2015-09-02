<?php
namespace Bmatics\Odata\QueryParsing;

use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\ExpressionParserSimple;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\ExpressionType;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\BinaryExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\UnaryExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\FunctionCallExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\SimplePropertyAccessExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\ConstantExpression;
use ODataProducer\UriProcessor\QueryProcessor\ExpressionParser\Expressions\AbstractExpression;
use ODataProducer\Common\ODataException;
use Bmatics\Odata\QueryParams\OdataQueryParamsInterface;
use Bmatics\Odata\Exceptions\QueryParsingException;


class OdataProducerQueryParser implements OdataQueryParserInterface
{
	/**
	 * Parse the Odata query
	 *
	 * @return stdClass  properties: filter,orderby,top,skip,select,expand
	 */
	public function parseQueryParams(OdataQueryParamsInterface $queryParams)
	{
		foreach(['filter', 'orderby', 'top', 'skip', 'select', 'expand'] as $queryPart) {
			$raw = $this->queryParams->{'get'.$queryPart}();
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
    protected function parseOrderBy($rawOrderBy)
    {
		$orderings = explode(',', $rawOrderBy);

		$parsedOrderings = [];

		foreach ($orderings as $ordering) {
			$ordering = trim($ordering);
			if (strlen($ordering) == 0) {
				continue;
			}
			if (!preg_match('@^([a-z0-9]+(?:/[a-z0-9]+)*)(?:\s+(asc|desc))?$@i', $ordering, $matches)) {
				throw new QueryParsingException('Unable to parse orderby setting');
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
	protected function parseSkip($rawSkip)
	{
		$rawSkip = trim($rawSkip);
		if ($rawSkip === '') {
			return null;
		}

		$skip = filter_var($rawSkip, FILTER_VALIDATE_INT, ['min_range'=>0]);

		if ($skip === false) {
			throw new QueryParsingException('Unable to parse skip setting');
		}

		return $skip;
	}

	/**
	 * Parse an Odata $top string into a positive integer
	 *
	 * @param string $rawTop
	 * @return int|null
	 */
	protected function parseTop($rawTop)
	{
		$rawTop = trim($rawTop);
		if ($rawTop === '') {
			return null;
		}

		$top = filter_var($rawTop, FILTER_VALIDATE_INT, ['min_range'=>1]);

		if ($top === false) {
			throw new QueryParsingException('Unable to parse top setting');
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
	protected function parseExpand($rawExpand)
	{
		$expands = explode(',', $rawExpand);

		$parsedExpands = [];

		foreach ($expands as $expand) {
			$expand = trim($expand);
			if ($expand == '') {
				continue;
			}

			if (!preg_match('@^[A-z0-9]+(?:/[A-z0-9]+)*$@', $expand, $matches)) {
				throw new QueryParsingException('Unable to parse expand setting');
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
	protected function parseSelect($rawSelect)
	{
		$selects = explode(',', $rawSelect);

		$parsedSelects = [];

		foreach ($selects as $select) {
			$select = trim($select);
			if ($select == '') {
				continue;
			}

			if (!preg_match('@^(?:(?:[A-z0-9]+(?:/[A-z0-9]+)*)|\*)$@', $select, $matches)) {
				throw new QueryParsingException('Unable to parse select setting');
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
    protected function parseFilter($rawFilter)
    {
    	$rawFilter = trim($rawFilter);
		if ($rawFilter === '') {
			return [];
		}

        try {
       		$parser = new ExpressionParserSimple($rawFilter);      
            $expression = $parser->parseFilter();
        } catch (ODataException $e) {
            throw new QueryParsingException('Unable to parse filter setting: '.$e->getMessage());
        }        

        try {
			return $this->expressionToArray($expression);
        } catch (QueryParsingException $e) {
        	throw new QueryParsingException('Unable to parse filter setting: '.$e->getMessage());
        }
        
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
            throw new QueryParsingException('Unsupported expression type in filter');
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
                throw new QueryParsingException('Unsupported binary expression type');
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
                throw new QueryParsingException('Unsupported unary expression type');
        }
    }

}