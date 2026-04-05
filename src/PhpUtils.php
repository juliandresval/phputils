<?php

namespace Juliandresval\PhpUtils;

class PhpUtils
{
    public static function isCli(): bool
    {
        return (
            \php_sapi_name() === 'cli'
            || !isset($_SERVER['HTTP_HOST'])
            || !isset($_SERVER['REMOTE_ADDR'])
            || !isset($_SERVER['SERVER_ADDR'])
        );
    }

    public static function isHttp(): bool
    {
        return !self::isCli();
    }

    public static function print(mixed $value): void
    {
        $stringValue = \print_r(self::processValue($value), true);
        if (self::isCli()) {
            echo $stringValue . PHP_EOL;
        } else {
            echo "<pre style='text-align: initial; border-radius: 4px; border: 1px #0c2b45 solid; padding: 4px'>" . \htmlentities($stringValue) . "</pre>" . PHP_EOL;
        }
    }

    private static function processValue(mixed $var, bool $recursive = true): mixed
    {
        if (\is_array($var)) {
            $newVar = self::processArray($var, $recursive);
        } elseif (\is_object($var)) {
            $newVar = self::processObject($var);
        } elseif (\is_bool($var)) {
            $newVar = ($var === false) ? 'false' : 'true';
        } else {
            $newVar = $var === null ? 'null' : $var;
        }
        return $newVar;
    }

    private static function processArray(array $var, bool $recursive = true): mixed
    {
        if (\count($var) > 0) {
            foreach ($var as $key => $value) {
                if (\is_object($value)) {
                    $newVar[$key] = 'Object of class: ' . \get_class($value);
                } elseif (\is_array($value)) {
                    $newVar[$key] = $recursive
                    ? self::processArray($value, false)
                    : 'Array(' . \count($value) . ')';
                } else {
                    $newVar[$key] = $value;
                }
            }
        } else {
            $newVar = $var;
        }

        return $newVar;
    }

    private static function processObject(object $var): mixed
    {
        $classname = \get_class($var);
        $properties = \get_class_vars($classname);
        $properties = (array)$var;
        $methods = \get_class_methods($var);
        $newVar[$classname]['parents'] = \class_parents($var);
        foreach ($properties as $property => $value) {
            if (\is_object($value)) {
                $newVar[$classname]['properties'][$property] = 'Object of class: ' . \get_class($value);
            } elseif (\is_array($value)) {
                $newVar[$classname]['properties'][$property] = 'Array(' . \count($value) . ')';
            } else {
                $newVar[$classname]['properties'][$property] = self::processValue($value);
            }
        }
        $newVar[$classname]['methods'] = $methods;

        return $newVar;
    }
}
