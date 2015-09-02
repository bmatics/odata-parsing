<?php
namespace Bmatics\Odata\QueryParsing;

use Bmatics\Odata\QueryParams\OdataQueryParamsInterface;

interface OdataQueryParserInterface
{
	/**
	 * Parse the Odata query
	 *
	 * @return stdClass  properties: filter,orderby,top,skip,select,expand
	 */
	public function parseQueryParams(OdataQueryParamsInterface $query);
}