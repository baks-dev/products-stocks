<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

//use App\Module\Product\Type\Category\Id\CategoryUidConverter;
//use BaksDev\Users\Entity\User;
//use App\Module\Product\Entity;
//use App\Module\Product\EntityListeners;

return static function(ContainerConfigurator $configurator) {
	$services = $configurator->services()
		->defaults()
		->autowire()
		->autoconfigure()
	;
	
	$namespace = 'BaksDev\Products\Stocks';
	
//	$services->load($namespace.'\Controller\\', __DIR__.'/../../Controller')
//		->tag('controller.service_arguments')
//	;
//	
//	$services->load($namespace.'\Repository\\', __DIR__.'/../../Repository')
//		->exclude(__DIR__.'/../../Repository/**/*DTO.php')
//	;
//	//->tag('controller.service_arguments');
//	
//	$services->load($namespace.'\UseCase\\', __DIR__.'/../../UseCase')
//		->exclude(__DIR__.'/../../UseCase/**/*DTO.php')
//	;
//	
//	$services->load($namespace.'\DataFixtures\\', __DIR__.'/../../DataFixtures')
//		->exclude(__DIR__.'/../../DataFixtures/**/*DTO.php')
//	;
//	
//	$services->load($namespace.'\Forms\\', __DIR__.'/../../Forms');

};

