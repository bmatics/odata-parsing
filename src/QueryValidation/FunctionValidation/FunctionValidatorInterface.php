<?php
namespace Bmatics\Odata\QueryValidation\FunctionValidation;

interface FunctionValidatorInterface
{
	/**
	 * Get the return type of the function when passed parameters of a given type
	 *
	 * @param string $function  The name of the function
	 * @param string[] $paramTypes  Array of the types of parameters
	 * @throws Bmatics\Odata\Exceptions\FunctionValidationException
	 */
	public function getFunctionReturnType($function, $paramTypes);
}