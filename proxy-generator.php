<?php
function php_generate_prototype($className, $innerProperty, array $visibilities, $methodBefore, $methodAfter, array $ignoreMethods)
{
    $class = new ReflectionClass($className);

    echo "/**\n* BEGIN AUTOGENERATED CODE\n*/\n";
    printf(
        "%s%s %s\n{\n",
        ($class->isAbstract() && !$class->isInterface() ? 'abstract ' : ''),
        ($class->isInterface() ? 'interface' : 'class'),
        $class->getName()
    );
    foreach ($class->getMethods() as $method) {
        if (in_array(strtolower($method->getName()), $ignoreMethods)) {
            continue;
        }

        $visibility = array_pop(
            array_keys(
                array_filter(
                    array(
                     'public'    => $method->isPublic(),
                     'private'   => $method->isPrivate(),
                     'protected' => $method->isProtected(),
                    )
                )
            )
        );

        if (!in_array($visibility, $visibilities)) {
            continue;
        }

        $parameters = array();
        $callParameters = array();
        foreach ($method->getParameters() as $parameter) {
            $parameterString = '';

            if ($parameter->getClass()) {
                $parameterString .= $parameter->getClass()->getName() . ' ';
            } elseif ($parameter->isArray()) {
                $parameterString .= 'array ';
            }

            if ($parameter->isPassedByReference()) {
                $parameterString .= '&';
            }

            $parameterString .= '$' . $parameter->getName();
            $callParameters[] = '$' . $parameter->getName();

            if ($parameter->allowsNull()) {
                $parameterString .= ' = null';
            } elseif ($parameter->isDefaultValueAvailable() || $parameter->isOptional()) {
                try {
                    $defaultValue = $parameter->getDefaultValue();
                } catch (ReflectionException $e) {
                    $defaultValue = null;
                }
                if (is_array($defaultValue) && empty($defaultValue)) {
                    $defaultValueString = 'array()';
                } else {
                    $defaultValueString = var_export($defaultValue, true);
                }
                $parameterString .= ' = ' . $defaultValueString;
            }

            $parameters[] = $parameterString;
        }

        $methodString = '    ' . $visibility . ' ';
        if ($method->isStatic()) {
            $methodString .= 'static ';
        }

        $methodString .= 'function ';

        if ($method->returnsReference()) {
            $methodString .= '&';
        }

        $methodString .= $method->getName();
        $methodString .= '(';
        $methodString .= join(', ', $parameters);
        $methodString .= ')';
        $methodString .= "\n";
        $methodString .= "    {";
        $methodString .= "\n";

        if ($methodBefore) {
            $methodString .= sprintf('        $this->%s(__FUNCTION__, func_get_args());', $methodBefore);
            $methodString .= "\n";
        }

        if ($innerProperty) {
            if ($method->getName() == '__call') {
                $methodString .= sprintf(
                                    '        return call_user_func_array(array($this->%s, $%s), $%s);',
                                    $innerProperty,
                                    reset($method->getParameters())->getName(),
                                    end($method->getParameters())->getName()
                                 );
            } elseif ($method->getName() == '__get') {
                $methodString .= sprintf(
                                    '        return $this->%s->$%s;',
                                    $innerProperty,
                                    reset($method->getParameters())->getName()
                                 );
            } elseif ($method->getName() == '__isset' || $method->getName() == '__unset') {
                $functionName = trim($method->getName(), '_');
                $return = $functionName == 'isset' ? 'return ' : '';
                $methodString .= sprintf(
                                    '        %s%s($this->%s->$%s);',
                                    $return,
                                    $functionName,
                                    $innerProperty,
                                    reset($method->getParameters())->getName()
                                 );
            } elseif ($method->getName() == '__set') {
                $methodString .= sprintf(
                                    '        return $this->%s->$%s = $%s;',
                                    $innerProperty,
                                    reset($method->getParameters())->getName(),
                                    end($method->getParameters())->getName()
                                 );
            } else {
                $methodString .= sprintf(
                                    '        return $this->%s->%s(%s);',
                                    $innerProperty,
                                    $method->getName(),
                                    join(', ', $callParameters)
                                 );
            }
            $methodString .= "\n";
        }

        if ($methodAfter) {
            $methodString .= sprintf('       $this->%s(__FUNCTION__, func_get_args());', $methodAfter);
            $methodString .= "\n";
        }

        $methodString .= "    }";
        $methodString .= "\n\n";

        echo $methodString;
    }
    echo "}\n";
    echo "/**\n* END AUTOGENERATED CODE\n*/\n";
}

if ($_SERVER['argc'] < 3) {
    printf("Usage: %s <files> <classNames> [<innerProperty> <visibilities> <methodBefore>, <methodAfter>, <ignoreMethods>]\n", basename($_SERVER['argv'][0]));
    exit(11);
}
list(, $files, $classNames) = $_SERVER['argv'];

$innerProperty = false;
if (isset($_SERVER['argv'][3])) {
    $innerProperty = $_SERVER['argv'][3];
}

$visibilities = array('public');
if (isset($_SERVER['argv'][4])) {
    $visibilities = explode(',', $visibilities);
    $visibilities = array_map('strtolower', $visibilities);
}

$methodBefore = '';
if (isset($_SERVER['argv'][5])) {
    $methodBefore = $_SERVER['argv'][5];
}

$methodAfter = '';
if (isset($_SERVER['argv'][6])) {
    $methodAfter = $_SERVER['argv'][6];
}

$ignoreMethods = array();
if (isset($_SERVER['argv'][7])) {
    $ignoreMethods = explode(',', $_SERVER['argv'][7]);
}
$ignoreMethods = array_map('strtolower', $ignoreMethods);


if ($files) {
    foreach (explode(",", $files) as $file) {
        include($file);
    }
}

$classNames = explode(',', $classNames);
foreach ($classNames as $key => $className) {
    if (substr($className, 0, 4) === 'ext:') {
        $extension = new ReflectionExtension(substr($className, 4));
        foreach ($extension->getClasses() as $class) {
            $classNames[] = $class->getName();
        }
        unset($classNames[$key]);
    }
}
foreach ($classNames as $className) {
    if (!class_exists($className, false) && !interface_exists($className, false)) {
        printf("Class %s not found in files %s\n", $className, $files);
        exit(12);
    }
}

foreach ($classNames as $className) {
    php_generate_prototype($className, $innerProperty, $visibilities, $methodBefore, $methodAfter, $ignoreMethods);
}
