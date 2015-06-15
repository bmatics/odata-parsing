<?php
namespace Bmatics\Odata;

use Illuminate\Support\ServiceProvider;

class LaravelServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('Bmatics\\Odata\\QueryParser\\QueryParserInterface', 'Bmatics\\Odata\\QueryParser\\OdataProducerQueryParser');
		$this->app->bind('Bmatics\\Odata\\Query\\QueryInterface', 'Bmatics\\Odata\\Query\\LaravelRequestWrapper');
	}

	public function provides()
	{
		return [
			'Bmatics\\Odata\\QueryParser\\QueryParserInterface',
			'Bmatics\\Odata\\Query\\QueryInterface',
		];
	}
}