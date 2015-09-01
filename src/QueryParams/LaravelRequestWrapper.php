<?php
namespace Bmatics\Odata\QueryParams;

use Illuminate\Http\Request;

class LaravelRequestWrapper implements QueryInterface
{
	protected $request;

	public function __construct(Request $request)
	{
		$this->request = $request;
	}

	public function getOrderBy()
	{
		return $this->getFromRequest('$orderby');
	}

	public function getSkip()
	{
		return $this->getFromRequest('$skip');
	}

	public function getTop()
	{
		return $this->getFromRequest('$top');
	}

	public function getSelect()
	{
		return $this->getFromRequest('$select');
	}

	public function getExpand()
	{
		return $this->getFromRequest('$expand');
	}

	public function getFilter()
	{
		return $this->getFromRequest('$filter');
	}

	private function getFromRequest($key)
	{
		return $this->request->query($key, '');
	}

}