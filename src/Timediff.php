<?php
/**
 * @Creator Alexey Kutuzov <lexus27.khv@gmail.com>
 * @Author: Alexey Kutuzov <lexus27.khv@gmai.com>
 * @Project: ceive.text-timediff
 */

namespace Ceive\Text;

/**
 * @Author: Alexey Kutuzov <lexus27.khv@gmail.com>
 * Class Timediff
 * @package Ceive\Text
 */
class Timediff{
	
	protected static $_checker_meta_cache = [];
	
	
	public static $default_static_result = [
		'years'     => ['* год'     , '* года'      , '* лет'],
		'months'    => ['* месяц'   , '* месяца'    , '* месяцев'],
		'days'      => ['* день'    , '* дня'       , '* дней'],
		'hours'     => ['* час'     , '* часа'      , '* часов'],
		'minutes'   => ['* минута'  , '* минуты'    , '* минут'],
		'seconds'   => ['* секунда' , '* секунды'   , '* секунд'],
	];
	
	protected $default_result;
	
	protected $cases = [];
	
	/**
	 * Timediff constructor.
	 * @param null $default_result
	 * @param null $delimiter
	 */
	public function __construct($default_result = null, $delimiter = null){
		$this->default_result = $default_result;
		$this->delimiter = $delimiter?:' ';
	}
	
	public function getDefaultResult(){
		return $this->default_result?:self::$default_static_result;
	}
	/**
	 * @param array $cases
	 * @param bool $merge
	 * @return $this
	 * @throws \Exception
	 */
	public function setCases(array $cases, $merge = false){
		if(!$merge)$this->cases = [];
		foreach($cases as $case){
			if(!isset($case[0],$case[1])){
				throw new \Exception('Case invalid');
			}
			$this->cases[] = [$case[0], $case[1]];
		}
		return $this;
	}
	
	public function prependCase($checker, $result){
		array_splice($this->cases, 0,0, [ [$checker, $result] ] );
		return $this;
	}
	
	/**
	 * @param $checker ' > 30min && <= 60min'
	 * @param $result
	 * @return $this
	 */
	public function appendCase($checker, $result){
		$this->cases[] = [$checker, $result];
		return $this;
	}
	
	/**
	 * @param \DateInterval $delta
	 * @return int
	 */
	public function getIntervalTimestamp(\DateInterval $delta){
		return ($delta->s)
		       + ($delta->i * 60)
		       + ($delta->h * 60 * 60)
		       + ($delta->d * 60 * 60 * 24)
		       + ($delta->m * 60 * 60 * 24 * 30)
		       + ($delta->y * 60 * 60 * 24 * 365);
	}
	
	/**
	 * @param $diff
	 * @return array|mixed|string
	 * @throws \Exception
	 */
	public function resolve($diff){
		
		if($diff instanceof \DateInterval){
			$interval = $diff;
			$diff = $this->getIntervalTimestamp($diff);
		}else{
			$n = new \DateTime('now');
			$p = new \DateTime();
			$p->setTimestamp($n->getTimestamp() + $diff);
			$interval = $n->diff($p);
		}
		if(!is_int($diff)){
			throw new \Exception('Wrong interval passed "'.gettype($interval).'"');
		}
		
		foreach($this->cases as list($checker, $result)){
			if($this->_matchCase($diff, $checker)){
				return $this->resolveCheckedResult($interval, $diff, $result);
			}
		}
		
		return $this->resolveCheckedResult($interval, $diff, $this->getDefaultResult());
	}
	
	/**
	 * @param \DateInterval $interval
	 * @param $timestamp
	 * @param $result
	 * @return array|mixed|string
	 */
	public function resolveCheckedResult(\DateInterval $interval, $timestamp, $result){
		$data = [
			'years'     => $interval->y,
			'months'    => $interval->m,
			'days'      => $interval->d,
			'hours'     => $interval->h,
			'minutes'   => $interval->i,
			'seconds'   => $interval->s,
		];
		
		if(is_string($result)){
			return $this->processTpl($result, $data);
		}elseif(is_callable($result)){
			return call_user_func($result, $interval,$timestamp, $data);
		}elseif(is_array($result)){
			$a = [];
			if(isset($result[0])){
				$c = [
					
					[ '{years}' , [ '* год' , '* года' , '* лет' ]]
				
				];
				foreach($result as $item){
					
					if(is_string($item)){
						$a[] = $this->processTpl($item,$data);
					}elseif(is_array($item)){
						
						if(isset($item[0])){
							// ['attribute', [single, several, many, zero], '> 0']
							list($attribute, $renderOptions, $checker) = array_replace([null,null, null], $item);
						}else{
							$item = array_replace([
								'attribute' => null, // 'attribute'
								'check'     => null, // '> 0'
								'render'    => null  // [single, several, many, zero]
							],$item);
							$attribute = $item['attribute'];
							$checker = $item['check'];
							$renderOptions = $item['render'];
						}
						
						if($attribute && $renderOptions){
							$attribute = trim($attribute,'{} ');
							$attribute = $data[$attribute];
							
							if(!$checker || $this->_matchCase($attribute, $checker)){
								if(is_array($renderOptions)){
									list($single, $several, $many, $zero) = array_replace([null,null,null, null], $renderOptions);
								}else{
									$several = $many = $zero = null;
									$single = $renderOptions;
								}
								$a[] = PluralTemplate::morph($attribute, $single, $several, $many, $zero);
							}
						}
					}
				}
				return implode($this->delimiter, $a);
			}else{
				$dr = $this->getDefaultResult();
				foreach(array_intersect_key($result, $data) as $key => $_){
					if(isset($data[$key]) && $data[$key]){
						if($_ === true || is_int($_)){
							$_ = $dr[$key];
						}elseif(is_string($_)){
							$_ = [$_];
						}
						list($single, $several, $many) = array_replace([null,null,null],(array)$_);
						if($single){
							$a[] = PluralTemplate::morph($data[$key], $single, $several, $many);
						}
					}
				}
				return implode($this->delimiter,$a);
			}
		}
		return null;
	}
	
	/**
	 * @param $template
	 * @param array $context
	 */
	protected function processTpl($template, array $context){
		$replacer = Replacer::getStaticReplacer('{','}','[^\}]+');
		return $replacer->replace($template, function($placeholder) use($context){
			if(isset($context[$placeholder])){
				return $context[$placeholder];
			}
			return null;
		});
	}
	
	/**
	 * @param $actual
	 * @param $checker
	 * @return bool
	 * @throws \Exception
	 */
	protected function _matchCase($actual, $checker){
		$k = md5(serialize($checker));
		if(!array_key_exists($k, self::$_checker_meta_cache)){
			if(preg_match_all('@([^w]+?)\s+(\w+(?:\s+\w+)*)(?:\s+(&&|\|\|))?@',$checker, $m)>0){
				$op = ['&&'];
				$a = [];
				foreach($m[0] as $i => $_){
					$comparator = trim($m[1][$i]);
					$expressionValue = trim($m[2][$i]);
					$a[$i] = [$comparator, $this->_parseValue($expressionValue)];
					if($m[3][$i]){
						$op[$i+1] = trim($m[3][$i]);
					}
				}
				self::$_checker_meta_cache[$k] = [$op,$a];
			}else{
				self::$_checker_meta_cache[$k] = false;
			}
		}
		if(self::$_checker_meta_cache[$k] === false){
			throw new \Exception('Error on parse "'.$checker.'"');
		}
		
		
		
		list($blockOperators, $conditions) = self::$_checker_meta_cache[$k];
		$value = false;
		foreach($conditions as $i => list($comparator, $expected)){
			$blockOperator = $blockOperators[$i];
			if($i<=0){
				$value = $this->_callOperator($comparator, $actual, $expected);
			}else{
				if($blockOperator === '&&'){
					$value = $value && $this->_callOperator($comparator, $actual, $expected);
				}else{
					$value = $value || $this->_callOperator($comparator, $actual, $expected);
				}
			}
		}
		return $value;
	}
	
	
	/**
	 * @param $operator
	 * @param $actual
	 * @param $expected
	 * @return bool
	 * @TODO LogicConstruction/Operator
	 */
	protected function _callOperator($operator, $actual, $expected){
		
		switch($operator){
			
			case '>':
				return $actual > $expected;
				break;
			case '>=':
				return $actual >= $expected;
				break;
			case '<':
				return $actual < $expected;
				break;
			case '<=':
				return $actual <= $expected;
				break;
			case '<>':
			case '!=':
				return $actual != $expected;
				break;
			
			case '=':
			case '==':
				return $actual == $expected;
				break;
			case '===':
				return $actual === $expected;
				break;
			case '!==':
				return $actual !== $expected;
				break;
		}
		
		return false;
	}
	
	/**
	 * @param $amount
	 * @param $coefficient
	 * @return null
	 * @TODO: Smart/Value/Measurement
	 */
	protected function _multiplyTime($amount, $coefficient = null){
		
		switch($coefficient){
			default:
			case 'sec':
			case 'seconds':
			case 'second':
				return $amount;
				break;
			case 'min':
			case 'minutes':
			case 'minute':
				return $amount * 60;
				break;
			case 'h':
			case 'hours':
			case 'hour':
				return $amount * 60 * 60;
				break;
			case 'd':
			case 'days':
			case 'day':
				return $amount * 60 * 60 * 24;
				break;
			
			case 'w':
			case 'weeks':
			case 'week':
				return $amount * 60 * 60 * 24 * 7;
				break;
			
			
			case 'm':
			case 'months':
			case 'month':
				return $amount * 60 * 60 * 24 * 30;
				break;
			
			case 'y':
			case 'year':
			case 'years':
				return $amount * 60 * 60 * 24 * 365;
				break;
		}
		return null;
	}
	
	/**
	 * @param $elements
	 * @return int|null
	 */
	protected function _parseValue($elements){
		$elements = explode(' ',$elements);
		$value = 0;
		foreach($elements as $element){
			$element = trim($element," \r\n\t.");
			if(preg_match('@(\d+)(\D+)?@', $element,$m) > 0){
				$value += $this->_multiplyTime(intval($m[1]),isset($m[2])?$m[2]:null);
			}
		}
		return $value;
	}
	
	
}


