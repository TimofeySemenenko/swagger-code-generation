<?php declare(strict_types=1);

namespace Src\SwaggerCodeGeneration;

/**
 * TODO: сделать вынос версионирования и начальной точки в отдельные префиксные группы
 * Class RoutingCodeGenerator
 * @package App\Services\Src
 */
class RoutingCodeGenerator
{
	/**
	 * Вынести в конфиг
	 */
	const INPUT_FILE = '.routing.example';
	const ROUTE_LINE = '$router->%s(\'%s\', \'%s@%s\');';
	const ROUTE_FOLDER = '/../../../routes/';
	const FINAL_ROUTE_NAME = 'web.php';
	const OLD_ROUTE_NAME = 'web.php.test.old';

    /**
     * @var array
     */
	protected $routeLines;
    /**
     * @var ControllerCodeGenerator
     */
	protected $controllerGenerator;

    /**
     * RoutingCodeGenerator constructor.
     * @param ControllerCodeGenerator $controllerGenerator
     */
    public function __construct(ControllerCodeGenerator $controllerGenerator)
	{
		$this->controllerGenerator = $controllerGenerator;
	}

    /**
     * @param array $routePaths
     * @return RoutingCodeGenerator
     */
    public function convertPathToRoute(array $routePaths) : RoutingCodeGenerator
	{
		foreach ($routePaths as $route => $params) {
            array_map(function($method, $pathParams) use ($route){
                $this->routeLines[] = $this->generateRoutingLine($route, $method, $pathParams);
            }, array_keys($params), $params);
		}

		return $this;
	}

    /**
     * @param string $route
     * @param string $method
     * @param array $pathParams
     * @return string
     */
    protected function generateRoutingLine(string $route, string $method, array $pathParams): string
	{
		return sprintf(static::ROUTE_LINE, ...[
			strtolower($method),
			$route,
			$this->controllerGenerator->getClassName($route, true),
			$this->controllerGenerator->getMethodInfo($route, $method, $pathParams)['name'],
		]);
	}

    /**
     * @param string|null $path
     * @return RoutingCodeGenerator
     */
    public function saveFile(string $path = null): RoutingCodeGenerator
	{
		if(!empty($this->routeLines) && is_array($this->routeLines)){
			$exampleFilePath = sprintf('%s%s%s', __DIR__, '/', static::INPUT_FILE);
			$filePath = sprintf('%s%s%s', __DIR__, static::ROUTE_FOLDER, static::FINAL_ROUTE_NAME);
			$fileOldPath = sprintf('%s%s%s', __DIR__, static::ROUTE_FOLDER, static::OLD_ROUTE_NAME);

			if(file_exists($filePath)){
				//удаляем старый файл
				if(file_exists($fileOldPath)){
					unlink($fileOldPath);
				}
				rename($filePath, $fileOldPath);
			}

			$finalRouting = file_get_contents($exampleFilePath) . str_repeat("\n", 3) . implode("\n\n", $this->routeLines);

            if(file_put_contents($filePath, $finalRouting) === 0){
                throw new \DomainException('Не удалось записать роутер ' . $filePath);
            }
            chmod($filePath, 0777);

            return $this;
		}

		throw new \RuntimeException('Роутинг не может быть пустым');
	}
}