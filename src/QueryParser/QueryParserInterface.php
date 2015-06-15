<?php
namespace Bmatics\Odata\QueryParser;

interface QueryParserInterface
{
	/**
	 * Parse the query
	 *
	 * @return stdClass  properties: filter,orderby,top,skip,select,expand
	 */
	public function parse();
}