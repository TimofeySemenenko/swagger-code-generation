<?php declare(strict_types=1);

namespace Src\SwaggerCodeGeneration;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

/**
 * TODO: вынести генерацию
 * TODO: сделать версионирование и "начальную точку" в отдельных неймспейсах
 * TODO: refactor
 * Class ControllerCodeGenerator
 * @package App\Services\Src
 */
class ControllerCodeGenerator
{
	/**
	 * Вынести в конфиг
	 */
	const BASEPATH = __DIR__ . '/../../Http/Controllers/';
	const BASE_NAMESPACE = 'App\Http\Controllers\\';
	const SERVICES_NAMESPACE = 'App\Services\ApiServices\\'; //? привязать class map в composer.json
	const EXTENDS = \App\Http\Controllers\Controller::class;
	const CLASS_POSTFIX = 'Controller';
	const ROOT_CONTROLLER_NAME = 'RootApiController';
	const METHOD_PATTERN = '%s%s';
	const GEN_METHOD_BY_PATTERN = false;
	const RESPONSE_HANDLER = \Illuminate\Http\JsonResponse::class;

	protected $validatorGen;
	protected $scheme;

    /**
     * ControllerCodeGenerator constructor.
     * @param ValidatorGenerator $validatorGenerator
     */
    public function __construct(ValidatorGenerator $validatorGenerator)
	{
		$this->validatorGen = $validatorGenerator;
	}

    /**
     * @param array $scheme
     * @return ControllerCodeGenerator
     */
    public function setScheme(array $scheme): ControllerCodeGenerator
    {
        $this->scheme = $scheme;
        return $this;
    }

    /**
     * Генерация кода
     * @throws \DomainException
     */
    public function generate()
    {
        if(!empty($this->scheme)){
            $controllers = $this->getClassesParams($this->scheme['paths']);
            foreach ($controllers as $class => $methods){
                $finalClassName = static::BASE_NAMESPACE . $class;
                $classFileData = $this->getClass($finalClassName, $methods);
                $this->saveClassToFile($finalClassName, $classFileData);
            }
        } else {
            throw new \DomainException('Swagger схема не инициализирована');
        }
    }

    /**
     * @param string $route
     * @param string $method
     * @param array $pathParams
     * @return array
     */
    public function getMethodInfo(string $route, string $method, array $pathParams): array
    {
        //TODO: refactor
        $methodInfo = [
            'name' => $pathParams['operationId'],
            'params' => $this->getMethodArgs($route, $method, $pathParams)
        ];
        return $methodInfo; // name => %method%, args => [%args_1%, %args_n%]
    }

    /**
     * @param string $path
     * @param bool $byNS
     * @return string
     */
    public function getClassName(string $path, bool $byNS = false): string
    {
        if($this->isRootPoint($path)){
            $controllerName = static::ROOT_CONTROLLER_NAME;
        } else {
            $path = (substr($path, -1) === '/' && strlen($path) > 1) ? substr($path, 0, -1) : $path;
            $pathSegments = $this->getCleanedPathParams($path);

            $replacedChars = ['_','-', ' ', "\t", "\r", "\n", "\f" , "\v"];
            $controllerName = str_replace($replacedChars, '', ucwords(array_pop($pathSegments), " \t\r\n\f\v_-")).static::CLASS_POSTFIX;
        }

        return ($byNS) ? sprintf('%s\\%s', $this->getNamespace($path, false), $controllerName) : $controllerName;
    }

    /**
     * @param string $className
     * @param array $methods
     * @var $classGen ClassType
     * @return string
     */
    protected function getClass(string $className, array $methods): PhpNamespace
    {
        $nameSpace = explode('\\', $className);
        $realClassName = array_pop($nameSpace);
        $nameSpace = implode('\\', $nameSpace);

        $responseHandlerClass = ($pos = strrpos(static::RESPONSE_HANDLER, '\\')) ? substr(static::RESPONSE_HANDLER, $pos + 1) : static::RESPONSE_HANDLER;

        $ns = new PhpNamespace($nameSpace);
        $ns->addUse(static::RESPONSE_HANDLER);
        $classGen = $ns->addClass($realClassName);
        $classGen->setExtends(static::EXTENDS);
        $methodList = array_pop($methods);
        foreach($methodList as $method){
            $tmpMethod = $classGen->addMethod($method['name']);
            foreach ($method['params'] as $param){
                $tmpMethod->addParameter($param['name'])->setTypeHint($param['type']);
            }

            $serviceName = static::SERVICES_NAMESPACE . str_replace(static::CLASS_POSTFIX, '', $realClassName) . 'BXService';
            $replacedArgs = [
                $responseHandlerClass,
                'responseConverter',
                $serviceName,
                $method['name']
            ];
            $tmpMethod->addBody(sprintf('return new %s(%s(%s::%s($this->request)));', ...$replacedArgs));
        }

        return $ns;
    }

    /**
     * @param string $className
     * @param string $fileBody
     */
    protected function saveClassToFile(string $className, PhpNamespace $fileBody)
    {
        //TODO:refactor
        $classDirs = explode('\\', str_replace(static::BASE_NAMESPACE, '', $className));
        $fileName = array_pop($classDirs) . '.php';
        $dirPath = static::BASEPATH . implode('/', $classDirs);
        if(!file_exists($dirPath) && !mkdir($conDirectory = $dirPath, 0777, true) && !is_dir($conDirectory)) {
            throw new \RuntimeException(sprintf('Не удалось создать директорию: "%s"', $conDirectory));
        }
        $fileName = realpath($dirPath) .'/'. $fileName;
        if(file_put_contents($fileName, "<?php\n". $fileBody) === 0){
            throw new \RuntimeException('Не удалось записать контроллер ' . $fileName);
        }

        chmod($fileName, 0777);
    }

    /**
     * @param array $paths
     * @return array
     */
    protected function getClassesParams(array $paths): array
    {
        $classes = [];

        foreach($paths as $route => $path){
            foreach($path as $method => $pathParams){
                $tmpClassName = $this->getClassName($route, true);
                $classes[$tmpClassName]['methods'][] = $this->getMethodInfo($route, $method, $pathParams);
            }
        }

        return $classes;
    }

    /**
     * @param string $route
     * @param string $method
     * @param array $pathParams
     * @return array
     */
    protected function getMethodArgs(string $route, string $method, array $pathParams): array
	{
        if(!empty($pathParams['parameters'])){

			//TODO: refactor
                $methodArgs[] = [
                    'type' => $this->getValidatorInfo($route, $method, $pathParams)['nameByNS'],
                    'name' => 'actionValidator'//validatorName$pathParams['name']
                ];

			return $methodArgs;
		}

		return [];
	}

    /**
     * @param string $path
     * @param bool $byRoot
     * @return string
     */
    protected function getNamespace(string $path, bool $byRoot = true): string
	{
		$namespace = '';
		if(!$this->isRootPoint($path)){
			$pathSegments = array_filter(explode('/', $path), function($el){ return !empty($el); });
			$firstSegment = array_shift($pathSegments);
			if(!empty($firstSegment)){
				$replacedChars = ['_','-', ' ', "\t", "\r", "\n", "\f" , "\v"];
				$namespace = ((!$byRoot) ? '' : '\\') . str_replace($replacedChars, '', ucwords($firstSegment, " \t\r\n\f\v_-"));
			}
		}

		return $namespace;
	}

    /**
     * @param string $path
     * @return array
     */
    protected function getCleanedPathParams(string $path): array
    {
        $index = 0;
        $pathSegments = explode('/', $path);
        return array_filter($pathSegments, function($el) use (&$index){
            $isNotParam = (strpos($el, '{') === false);
            if($isNotParam){
                return (++$index % 2 === 0);
            }

            return $isNotParam;

        });
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function isRootPoint(string $path): bool
	{
		if(strlen($path) >= 1){
			$path = (substr($path, -1) === '/' && strlen($path) > 1) ? substr($path, 0, -1) : $path;
			$pathSegments = array_filter(explode('/', $path), function($el){ return !empty($el); });
			return !(count($pathSegments) >= 1);
		}
	}

    /**
     * @param string $route
     * @param string $method
     * @param array $pathParams
     * @return array
     */
    protected function getValidatorInfo(string $route, string $method, array $pathParams): array
	{
		return $this->validatorGen->getValidatorInfo($route, $method, $pathParams);
	}
}