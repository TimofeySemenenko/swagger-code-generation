<?php declare(strict_types=1);

namespace Src\SwaggerCodeGeneration;

/**
 * TODO:refactor
 * Class ValidationSwaggerRules
 *
 * @package App\Services\Src
 */
class ValidationSwaggerRules
{

    /**
     * Вынести в конфиг
     */
	const SIMPLE_RULE = 'simple';
	const STRUCT_RULE = 'struct';
	const ARRAY_RULE = 'array';
	const OBJECT_RULE = 'object';
	const MODEL_RULE = 'model';

    /**
     * @var array $rulesMapping
     */
	protected $rulesMapping = [
		'simple' => [
			'required' => 'required', //если нет, то nullable
			'string' => 'string',
			'minLength' => 'min:%s',
			'maxLength' => 'max:%s',
			'date' => 'date_format:Y-m-d',
			'date-time' => 'date_format:Y-m-d\TH:i:s', //date_format:Y-m-d\TH:i:sP - если нужно с поясом
			'password' => 'string', //? или должны быть правила?
			'binary' => 'file', //binary file contents
			//'byte' => '', //? custom rule _ base64-encoded file contents
			'email' => 'email',
			'uuid' => 'regex:/[a-f0-9]{8}\-[a-f0-9]{4}\-4[a-f0-9]{3}\-(8|9|a|b)[a-f0-9]{3‌​}\-[a-f0-9]{12}/', //вынести в отдельное правило
			'uri' => 'url',
			//'hostname' => '', //? custom rule
			'ipv4' => 'ipv4',
			'ipv6' => 'ipv6',
			'pattern' => 'regex:%s',

			'boolean' => 'boolean',
			'null' => 'nullable',

			'integer' => 'integer',
			'number' => 'integer',
			'float' => 'numeric',
			'double' => 'numeric',
			'int32' => 'integer', //? custom rule
			'int64' => 'integer', //? custom rule
			'minimum' => 'min:%s',
			'maximum' => 'max:%s',
			'exclusiveMinimum' => '1', //+ 1 minimum
			'exclusiveMaximim' => '1', //- 1 maximum
		],
	];

    /**
     * @param array $paramsData
     *
     * @return array
     */
	public function getPreparedRules(array $paramsData): array
	{
		if(!empty($paramsData)){

			$rules = [];

			foreach($paramsData as $paramData){

				if(!empty($paramData['in']) && $paramData['in'] !== 'header'){ //пока не проверяем параметры приходящие в заголовках

					$simpleTypes = ['number', 'string', 'boolean', 'integer'];

					if(!empty($paramData['schema']['type']) && in_array($paramData['schema']['type'], $simpleTypes)){
						$type = static::SIMPLE_RULE;
					} else {
						$type = (!empty($paramData['schema']['type'])) ? $paramData['schema']['type'] : static::MODEL_RULE;
					}

					switch ($type) {
						case static::SIMPLE_RULE:
							$rules[$paramData['name']] = array_values(array_unique($this->getSimpleRule($paramData)));
							break;
					}

				}

			}
			return $rules;
		}

		throw new \RuntimeException('Массив с параметрами для роута не может быть пустым');
	}

    /**
     * @param array $paramData
     *
     * @return array
     */
	protected function getSimpleRule(array $paramData): array
	{
		$rules = [];
		$paramSchema = $paramData['schema'];

		if(isset($paramData['required'])){
			$rules[] = ((bool)$paramData['required']) ? $this->rulesMapping[static::SIMPLE_RULE]['required'] : 'nullable';
		}

		if(!empty($paramSchema)){

			foreach($paramSchema as $k => $v){
                $findType = (in_array($k, ['type', 'format'])) ? $v : $k;
				if(!isset($this->rulesMapping[static::SIMPLE_RULE][$findType]))
					continue;
				$ruleVal = $this->rulesMapping[static::SIMPLE_RULE][$findType];

				switch ($k) {
					case 'type':
					case 'format':
						$rules[$k] = $ruleVal;
						break;
					case 'pattern':
					case 'minLength':
					case 'maxLength':
					case 'minimum':
					case 'maximum':
						$rules[$k] = sprintf($ruleVal, $v);
						break;
					//TODO: refactor надо отвязать от min и max
					case 'exclusiveMinimum':
						if(isset($rules['minimum'])){
							$curVal = explode(':', $rules['minimum'])[1];
							$rules['minimum'] = sprintf('min:%s', (((is_double($curVal)) ? (double)$curVal: (int)$curVal) + 1) );
						}
						break;
					case 'exclusiveMaximim':
						if(isset($rules['maximum'])){
							$curVal = explode(':', $rules['maximum'])[1];
							$rules['maximum'] = sprintf('max:%s', (((is_double($curVal)) ? (double)$curVal: (int)$curVal) -1) );
						}
						break;
				}
			}

			return $rules;
		}

		throw new \RuntimeException('Массив с типом для параметра');
	}
}