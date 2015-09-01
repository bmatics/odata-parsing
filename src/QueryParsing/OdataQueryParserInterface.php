<?php
namespace Bmatics\Odata\QueryParser;

use Bmatics\Odata\Query\QueryParamsInterface;

interface OdataQueryParserInterface
{
	/**
	 * Parse the Odata query
	 *
	 * @return stdClass  properties: filter,orderby,top,skip,select,expand
	 */
	public function parseQueryParams(QueryParamsInterface $query);
}