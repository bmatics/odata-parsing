<?php
namespace Bmatics\Odata\QueryValidation\FunctionValidation;

use Bmatics\Odata\Exceptions\FunctionValidationException;

class FunctionValidator implements FunctionValidationInterface
{
	public function getFunctionReturnType($function, $paramTypes)
	{
		throw new FunctionValidationException('Functions not supported');
	}
}