<?php declare(strict_types=1);

namespace Src\SwaggerCodeGeneration;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * TODO: вынести версионирование и "начальную точку" в отдельные неймспейсы
 * Class ValidatorGenerator
 * @package App\Services\Src
 */
class ValidatorGenerator
{
	/**
	 * вынести в конфиг
	 */

    const ROOT_FILEPATH = __DIR__ . '/../../Http/Validators/';
	const ROOT_NAMESPACE = 'App\Http\Validators\\';
	const CLASS_NAME_PATTERN = '%s_%s_validator'; //method _ operationId
    const EXTENDS = SwaggerValidation::class;

    /**
     * @var array $preparedRules
     */
	protected $preparedRules;

    /**
     * @var array $schema
     */
	protected $schema;

    /**
     * @var ValidationSwaggerRules $ruleConverter
     */
	protected $ruleConverter;

    /**
     * ValidatorGenerator constructor.
     *
     * @param ValidationSwaggerRules $rules
     */
    public function __construct(ValidationSwaggerRules $rules)
	{
		$this->ruleConverter = $rules;
		if(!file_exists(static::ROOT_FILEPATH)){
            if (!mkdir($conDirectory = static::ROOT_FILEPATH, 0755) && !is_dir($conDirectory)) {
                throw new \RuntimeException(sprintf('Не удалось создать директорию: "%s"', $conDirectory));
            }
        }
	}

    /**
     * @param array $schema
     *
     * @return ValidatorGenerator
     */
    public function setRouteScheme(array $schema) : ValidatorGenerator
	{
		$this->schema = $schema;
		return $this;
	}

    /**
     * Генерация кода
     */
    public function generate()
    {
        if(!empty($this->schema)){
            $classesForGen = $this->getValidators();

            foreach($classesForGen as $className => $rules){
                $tmpClassData = $this->getClass($className, $rules);
                $this->saveClassToFile($className, $tmpClassData);
            }
        } else {
            throw new \DomainException('Swagger схема не инициализирована');
        }
	}

    /**
     * @return array
     */
    public function getValidators(): array
	{
		if(!empty($this->schema)){
			return $this->getRoutesFromSchema($this->schema);
		}
	}

    /**
     * @param string $route
     * @param string $method
     * @param array $pathParams
     *
     * @return array
     */
	public function getValidatorInfo(string $route, string $method, array $pathParams): array
	{
		if(!empty($route) && !empty($method)){
			return [
				'name' => $this->getValidatorName($route, $method, $pathParams),
				'nameByNS' => $this->getNamespace($route) . '\\' . $this->getValidatorName($route, $method, $pathParams),
			];
		}

		throw new \RuntimeException('Переданы некорректный роут или метод');
	}

    /**
     * @param string $className
     * @param array $rules
     *
     * @return string
     */
	protected function getClass(string $className, array $rules): PhpNamespace
    {
        $nameSpace = explode('\\', $className);
        $realClassName = array_pop($nameSpace);
        $nameSpace = implode('\\', $nameSpace);

        $ns = new PhpNamespace($nameSpace);
        $classGen = $ns->addClass($realClassName);
        $classGen->setExtends(static::EXTENDS)->addProperty('validationRules', $rules)->setVisibility('protected');

        return $ns;
    }

    /**
     * @param string $className
     * @param string $fileBody
     */
    protected function saveClassToFile(string $className, PhpNamespace $fileBody)
    {
        //TODO:refactor
        $classDirs = explode('\\', str_replace(static::ROOT_NAMESPACE, '', $className));
        $fileName = array_pop($classDirs) . '.php';
        $dirPath = static::ROOT_FILEPATH . implode('/', $classDirs);
        if(!file_exists($dirPath) && !mkdir($conDirectory = $dirPath, 0777, true) && !is_dir($conDirectory)) {
            throw new \RuntimeException(sprintf('Не удалось создать директорию: "%s"', $conDirectory));
        }
        $fileName = realpath($dirPath) .'/'. $fileName;

        if(file_put_contents($fileName, "<?php\n". $fileBody) === 0){
            throw new \RuntimeException('Не удалось записать валидатор ' . $fileName);
        }
        chmod($fileName, 0777);
    }

    /**
     * @param array $schema
     *
     * @return array
     */
	protected function getRoutesFromSchema(array $schema) : array
	{

		if(!empty($schema['paths'])){
			$result = [];

			foreach ($schema['paths'] as $path => $methods) {
				foreach($methods as $method => $pathParams){
					if(!empty($pathParams['parameters'])){
						$validatorName = sprintf('%s\\%s', $this->getNamespace($path), $this->getValidatorName($path, $method, $pathParams));
						$this->preparedRules[$validatorName] = $this->ruleConverter->getPreparedRules($pathParams['parameters']);
					}
				}
			}

			return $this->preparedRules;
		}

		throw new \RuntimeException('Массив с роутами не может быть пустым'); //TODO: заменить
	}

    /**
     * @param string $route
     * @param string $method
     * @param array $pathParams
     *
     * @return string
     */
	protected function getValidatorName(string $route, string $method, array $pathParams): string
	{

		if(!empty($pathParams['operationId'])){
			$validatorName = sprintf(static::CLASS_NAME_PATTERN, $method, $pathParams['operationId']);
			$replacedChars = ['_','-', ' ', "\t", "\r", "\n", "\f" , "\v"];
			return str_replace($replacedChars, '', ucwords($validatorName, " \t\r\n\f\v_-"));
		}
	}

    /**
     * @param string $path
     *
     * @return string
     */
	protected function getNamespace(string $path): string
	{
		$namespace = '';
		if(!$this->isRootPoint($path)){
			$pathSegments = array_filter(explode('/', $path), function($el){ return !empty($el); });
			$firstSegment = array_shift($pathSegments);
			if(!empty($firstSegment)){
				$replacedChars = ['_','-', ' ', "\t", "\r", "\n", "\f" , "\v"];
				$namespace = static::ROOT_NAMESPACE . str_replace($replacedChars, '', ucwords($firstSegment, " \t\r\n\f\v_-"));
			}
		}

		return $namespace;
	}

    /**
     * @param string $path
     *
     * @return bool
     */
	protected function isRootPoint(string $path): bool
	{
		if(strlen($path) >= 1){
			$path = (substr($path, -1) === '/' && strlen($path) > 1) ? substr($path, 0, -1) : $path;
			$pathSegments = array_filter(explode('/', $path), function($el){ return !empty($el); });
			return !(count($pathSegments) >= 1);
		}
		throw new \RuntimeException('Путь не может быть пустым');
	}
}