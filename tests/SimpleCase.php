<?php
/**
 * @Creator Alexey Kutuzov <lexus27.khv@gmail.com>
 * @Author: Alexey Kutuzov <lexus27.khv@gmai.com>
 * @Project: ceive.text-timediff
 */

namespace Ceive\Text\Timediff\Tests;


use Ceive\Text\Timediff;

/**
 * @Author: Alexey Kutuzov <lexus27.khv@gmail.com>
 * Class SimpleCase
 * @package Ceive\Text\Timediff\Tests
 *
 *
 * time
 * сегодня в 13:50
 * вчера в 14:00
 * 27 октября 14:00
 *
 *
 * diff > 5 hours && date.isToday date.isYesterday
 *
 *
 * difference
 * 
 */
class SimpleCase extends \PHPUnit_Framework_TestCase{
	
	public function testDefaults(){
		
		
		$timediff = new Timediff();
		
		$result = $timediff->resolve(3600);// 1 hour
		$this->assertEquals('1 час',$result);
		
		$result = $timediff->resolve(3601);// 1 hour
		$this->assertEquals('1 час 1 секунда',$result);
		
		$result = $timediff->resolve(3600 + 60);// 1 hour
		$this->assertEquals('1 час 1 минута',$result);
		
		$result = $timediff->resolve(3600 + 63);// 1 hour
		$this->assertEquals('1 час 1 минута 3 секунды',$result);
		
	}
	
	public function testCases(){
		
		
		$timediff = new Timediff(null,', ');
		
		
		$timediff->setCases([
			
			['< 20sec', 'только что'],
			['>= 20sec && < 1min', 'менее минуты'],
			['>= 1min && < 60min', [
				'minutes'=>true,
			]],
			['>= 1hour && < 2hours', [
				'hours'     => 'час',
				'minutes'   => ['и * минута', 'и * минуты', 'и * минут'],
			]],
			['>= 2hours && < 24hours', [
				'hours'=>true,
				'minutes'=>true,
			]],
			['>= 1days', [
				'months'=>true,
				'days'=>true,
				'hours'=>true,
			]],
		]);
		$result = $timediff->resolve(54);// 54 sec
		$this->assertEquals('менее минуты',$result);
		
		$result = $timediff->resolve(3600);// 1 hour
		$this->assertEquals('час',$result);
		
		$result = $timediff->resolve(3600 + 60 + 3);// 1 hour
		$this->assertEquals('час, и 1 минута',$result);
		
		$result = $timediff->resolve(3601);// 1 hour
		$this->assertEquals('час',$result);
		
		$result = $timediff->resolve(3600 * 3);
		$this->assertEquals('3 часа',$result);
		
		$result = $timediff->resolve((3600 * 3) + (60 * 13));
		$this->assertEquals('3 часа, 13 минут',$result);
		
		$result = $timediff->resolve(((3600 * 24) * 3) + ((3600 * 3)) + (60 * 13)); // 3 дня 3 часа 13 минут
		$this->assertEquals('3 дня, 3 часа',$result);
		
	}
}


