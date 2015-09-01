<?php
namespace Bmatics\Odata\QueryValidation\PropertyValidation;

use Bmatics\Odata\Exceptions\PropertyValidationException;

class PropertyValidator implements PropertyValidatorInterface
{
	public function __construct()
	{

	}

	public function validatePropertyPath($propertyPath)
	{
		throw new PropertyValidationException('Properties not supported');
	}


	public function getPropertyType($propertyPath)
	{
		throw new PropertyValidationException('Properties not supported');
	}

}