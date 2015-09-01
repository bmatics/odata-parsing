<?php
namespace Bmatics\Odata\QueryValidation;

use Bmatics\Odata\Exceptions\QueryValidationException;
use Bmatics\Odata\QueryValidation\PropertyValidation\PropertyValidatorInterface;
use Bmatics\Odata\QueryValidation\FunctionValidation\FunctionValidatorInterface;

class QueryValidator
{
	protected $propertyValidator;

	protected $functionValidator;

	public function __construct(PropertyValidatorInterface $propertyValidator, FunctionValidatorInterface $functionValidator)
	{
		$this->propertyValidator = $propertyValidator;
		$this->functionValidator = $functionValidator;
	}


	public function validateQuery($query)
	{
		foreach (['filter', 'orderby', 'top', 'skip', 'select', 'expand'] as $queryPart) {
			$this->{'validate'.$queryPart}($query->$queryPart);
		}
	}


	protected function validateTop($top)
	{
		if (!is_int($top) || $top < 0)
		{
			throw new QueryValidationException('Invalid top setting: Top must be a positive integer');
		}
	}


	protected function validateSkip($skip)
	{
		if (!is_int($skip) || $skip < 0)
		{
			throw new QueryValidationException('Invalid skip setting: Skip must be a positive integer');
		}
	}


	protected function validateOrderBy(Array $orderBys)
	{
		try {
			foreach ($orderBys as $orderBy) {
				$this->propertyValidator->validateProperty($orderBy);
			}
		} catch (PropertyValidationException $e) {
			throw new QueryValidationException('Invalid orderBy setting: '.$e->getMessage());
		}
	}


	protected function validateSelect(Array $selects)
	{
		try {
			foreach ($selects as $select) {
				if ($select === '*') {
					continue;
				}
				$this->properyValidator->validateProperty($select);
			}
		} catch (PropertyValidationException $e) {
			throw new QueryValidationException('Invalid select setting: '.$e->getMessage());
		}
	}


	protected function validateExpand(Array $expands)
	{
		try {
			foreach ($expands as $expand) {
				$this->propertyValidator->validateProperty($expand);
			}
		} catch (PropertyValidationException $e) {
			throw new QueryValidationException('Invalid expand setting: '.$e->getMessage());
		}
	}


	protected function validateFilter(Array $filter)
	{
		try {
			if ($filter) {
				if ($this->getResultType($filter) !== 'boolean') {
					throw new QueryValidationException('Expression does not resolve to a boolean');
				}
			}			
		} catch (QueryValidationException $e) {
			throw new QueryValidationException('Invalid filter setting: '.$e->getMessage());
		}
	}


	protected function getResultType($expression)
	{
		$expression = (object)$expression;

		switch ($expression->type) {
			case 'and':
			case 'or':
				return $this->getBinaryLogicalResultType($expression);

			case 'not':
				return $this->getUnaryLogicalResultType($expression);

			case 'gt':
			case 'lt':
			case 'ge':
			case 'le':
			case 'eq':
			case 'ne':
				return $this->getComparisonResultType($expression);

			case 'neg':
				// return $this->getUnaryArithmeticResultType($expression);

			case 'add':
			case 'sub':
			case 'mul':
			case 'div':
			case 'mod':
				// return $this->getBinaryArithmeticResultType($expression);
				throw new QueryValidationException('Arithmetic expressions not supported');

			case 'function':
				return $this->getFunctionReturnType($expression);

			case 'property':
				return $this->getPropertyType($expression);

			case 'literal':
				return $this->getLiteralType($expression);

			default:
				throw new QueryValidationException('Unknown expression type:'. $expression->type);

		}
	}


	protected function getBinaryLogicalResultType($expression)
	{
		if ($this->getResultType($expression->left) != 'boolean') {
			throw new QueryValidationException('Logical expression operand is not a boolean');
		}

		if ($this->getResultType($expression->right) != 'boolean') {
			throw new QueryValidationException('Logical expression operand is not a boolean');
		}

		return 'boolean';
	}

	protected function getUnaryLogicalResultType($expression)
	{
		if ($this->getResultType($expression->child) != 'boolean') {
			throw new QueryValidationException('Logical expression operand is not a boolean');
		}

		return 'boolean';
	}


	// protected function getBinaryArithmeticResultType($expression)
	// {
	// 	$leftType = $this->getResultType($expression->left);
	// 	if ($leftType != 'number') {
	// 		throw new QueryValidationException('Cannot perform arithmetic on '.$leftType);
	// 	}

	// 	$rightType = $this->getResultType($expression->right);
	// 	if ($rightType != 'number') {
	// 		throw new QueryValidationException('Cannot perform arithmetic on '.$rightType);
	// 	}

	// 	return 'number';
	// }


	// protected function getUnaryArithmeticResultType($expression)
	// {
	// 	$childType = $this->getResultType($expression->child);
	// 	if ($childType != 'number') {
	// 		throw new QueryValidationException('Cannot perform arithmetic on '.$childType);
	// 	}

	// 	return 'number';
	// }


	protected function getComparisonResultType($expression)
	{
		if ($expression->right['type'] === 'property') {
			$property = $expression->right['value'];
			if (strpos('*', $property) !== false) {
				throw new QueryValidationException('Cannot have property within array as the right operand of a comparison.');
			}
		}


		$leftType = $this->getExpressionResultType($expression->left);

		$rightType = $this->getExpressionResultType($expression->right);

		if ($leftType  === 'null' || $rightType === 'null') {
			if ($expression->type === 'eq' || $expression->type === 'ne') {
				return 'boolean';
			}
		}

		$validComparisons = [
			['number', 'number'],
			['string', 'string'],
			['boolean', 'boolean'],
			['boolean_literal', 'boolean_literal'],
		];

		if (!in_array([$leftType, $rightType], $validComparisons)) {
			throw new QueryValidationException('Cannot compare '.$left_type.' '.$expression->type.' '.$right_type);
		}

		return 'boolean';
	}


	protected function getFunctionReturnType($expression)
	{
		$function = $expression->function;
		$params = $expression->params;

		$paramTypes = [];
		foreach ($params as $param) {
			$paramTypes[] = $this->getResultType($param);
		}

		return $this->functionValidator->getReturnType($function, $paramTypes);
	}

	protected function getPropertyType($expression)
	{
		$type = $this->propertyValidator->getPropertyType($expression->property);

		// Avoiding issues caused by trying to compare a json boolean with a sql boolean
		// You must instead compare the boolean property to another literal boolean
		if ($type === 'boolean') {
			$type = 'boolean_literal';
		}

		return $type;
	}

	protected function getLiteralType($expression)
	{
		$type = gettype($expression->value);

		if (in_array($type, ['integer', 'double'])) {
			$type = 'number';
		}

		if (!in_array($type, ['boolean', 'number', 'string', 'null'])) {
			throw new QueryValidationException($type.' is not a valid literal type');
		}

		if ($type === 'boolean') {
			$type = 'boolean_literal';
		}

		return $type;
	}

}