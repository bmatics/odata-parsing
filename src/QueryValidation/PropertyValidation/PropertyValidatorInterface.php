<?php
namespace Bmatics\Odata\QueryValidation\PropertyValidation;

interface PropertyValidatorInterface
{
	/**
	 * Validate a property path
	 *
	 * @param string property path
	 * @throws Bmatics\Odata\Exceptions\PropertyValidationException
	 */
	public function validatePropertyPath($propertyPath);

	/**
	 * Get the type of value at the property path
	 *
	 * @param string propertyPath
	 * @return string property type
	 * @throws Bmatics\Odata\Exceptions\PropertyValidationException
	 */
	public function getPropertyType($propertyPath);
}