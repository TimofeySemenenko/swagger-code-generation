<?php declare(strict_types=1);

namespace Src\SwaggerCodeGeneration;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Class SwaggerValidation
 *
 * @package App\Services\Src
 */
abstract class SwaggerValidation
{
	protected $validationRules = [];
	//protected $validationErrors = []; //?
	//protected $needAuth = null;
	//protected $role = null;
	//protected $permissions = null;
	//protected $authErrors = null; //?

    /**
     * SwaggerValidation constructor.
     *
     * @param Request $request
     *
     * @throws ValidationException
     */
    public function __construct(Request $request)
	{
        $this->validate($request->toArray());
	}

    /**
     * @param array $request
     *
     * @throws ValidationException
     */
    protected function validate(array $request)
    {
        $validateResult = validator($request, $this->getValidationRules());

        if($validateResult->fails()){
            $this->throwValidationException($validateResult);
        }
    }

    /**
     * @return array
     */
    protected function getValidationRules() : array
	{
		return $this->validationRules;
	}

    /**
     * @param $validator
     *
     * @throws ValidationException
     */
    protected function throwValidationException($validator)
    {
        throw new ValidationException($validator, $this->buildFailedResponse(
            $validator->errors()->getMessages()
        ));
    }

    /**
     * @param array $errors
     *
     * @return JsonResponse
     */
    protected function buildFailedResponse(array $errors): JsonResponse
    {
        return new JsonResponse($errors, 422);
    }
}