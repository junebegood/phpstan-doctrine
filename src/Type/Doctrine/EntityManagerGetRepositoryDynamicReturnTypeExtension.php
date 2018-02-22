<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;

class EntityManagerGetRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

    /**
     * @var array
     */
    private $repositoryClass;

    public function __construct(array $repositoryClass)
    {
        $this->repositoryClass = $repositoryClass;
    }

    public function getClass(): string
    {
        return \Doctrine\Common\Persistence\ObjectManager::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getRepository';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type
    {
        if (count($methodCall->args) === 0) {
            return $methodReflection->getReturnType();
        }
        $arg = $methodCall->args[0]->value;
        if (!($arg instanceof \PhpParser\Node\Expr\ClassConstFetch)) {
            return $methodReflection->getReturnType();
        }

        $class = $arg->class;
        if (!($class instanceof \PhpParser\Node\Name)) {
            return $methodReflection->getReturnType();
        }

        $class = (string) $class;
        if ($class === 'static') {
            return $methodReflection->getReturnType();
        }

        if ($class === 'self') {
            $class = $scope->getClassReflection()->getName();
        }

        $className = substr($class, strrpos($class, '\\') + 1);
        $targetRepositoryClass = '';

        // find corresponding repository class by entity class name
        foreach ($this->repositoryClass as $repositoryClass){
            if (strpos($repositoryClass, $className)) {
                $targetRepositoryClass = $repositoryClass;
                break;
            }
        }

        if (empty($targetRepositoryClass)) {
            return $methodReflection->getReturnType();
        }

        return new EntityRepositoryType($class, $targetRepositoryClass);
    }

}
