<?php

namespace LLA\DoctrineGraphQL\Util;

class AliasManager
{
    private $aliases = array();

    public function getAlias($path) {
        if (array_key_exists($path, $this->aliases)) {
            return $this->aliases[$path];
        }
        $pathExploded = explode('.', $path);
        $relationshipName = $pathExploded[count($pathExploded) - 1];

        //This assumes CamelCase is being used!
        preg_match_all('/[A-Z]/', ucfirst($relationshipName), $matches, PREG_OFFSET_CAPTURE);

        $firstLetters = array_map(
            function($match) { return $match[0]; },
            $matches[0]
        );

        $aliasAttempt = implode('', $firstLetters);

        $i=0;
        do {
            $aliasAlreadyExists = false;
            foreach ($this->aliases as $alias) {
                if ($alias == ($aliasAttempt . $i)) {
                    $aliasAlreadyExists = true;
                }
            }
            if ($aliasAlreadyExists) $i++;
        } while ($aliasAlreadyExists);

        $alias = $aliasAttempt . $i;
        $this->aliases[$path] = $alias;

        return $alias;
    }
}