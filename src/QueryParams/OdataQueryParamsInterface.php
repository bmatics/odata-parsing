<?php
namespace Bmatics\Odata\QueryParams;

interface OdataQueryParamsInterface
{
	/**
	 * Retrieve the $orderby parameter form the query
	 *
	 * @return string
	 */
	public function getOrderBy();
	
	/**
	 * Retrieve the $skip parameter form the query
	 *
	 * @return string
	 */
	public function getSkip();

	/**
	 * Retrieve the $top parameter form the query
	 *
	 * @return string
	 */
	public function getTop();

	/**
	 * Retrieve the $select parameter form the query
	 *
	 * @return string
	 */
	public function getSelect();

	/**
	 * Retrieve the $expand parameter form the query
	 *
	 * @return string
	 */
	public function getExpand();

	/**
	 * Retrieve the $filter parameter form the query
	 *
	 * @return string
	 */
	public function getFilter();
}