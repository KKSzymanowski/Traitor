<?php

/*
 * KKSzymanowski/Traitor
 * Add a trait use statement to existing class
 *
 * @package KKSzymanowski/Traitor
 * @author Kuba Szymanowski <kuba.szymanowski@inf24.pl>
 * @link https://github.com/kkszymanowski/traitor
 * @license MIT
 */

namespace Traitor\Handlers;

use Exception;
use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;

class AbstractTreeHandler implements Handler
{
    /** @var array */
    protected $content;

    /** @var string */
    protected $trait;

    /** @var string */
    protected $traitShortName;

    /** @var string */
    protected $class;

    /** @var string */
    protected $classShortName;

    /** @var array */
    protected $syntaxTree;

    /** @var Namespace_ */
    protected $namespace;

    /** @var array */
    protected $importStatements;

    /** @var array */
    protected $classes;

    /** @var Class_ */
    protected $classAbstractTree;

    /** @var string */
    protected $lineEnding = "\n";

    /**
     * @param array  $content
     * @param string $trait
     * @param string $class
     */
    public function __construct($content, $trait, $class)
    {
        $this->content = $content;

        $this->determineLineEnding();

        $this->trait = $trait;
        $traitParts = explode('\\', $trait);
        $this->traitShortName = array_pop($traitParts);

        $this->class = $class;
        $classParts = explode('\\', $class);
        $this->classShortName = array_pop($classParts);
    }

    /**
     * @return $this
     */
    public function handle()
    {
        $this->buildSyntaxTree()
             ->addTraitImport()
             ->buildSyntaxTree()
             ->addTraitUseStatement();

        return $this;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return implode($this->content);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->content;
    }

    /**
     * @throws Exception
     *
     * @return $this
     */
    protected function buildSyntaxTree()
    {
        $this->parseContent()
             ->retrieveNamespace()
             ->retrieveImports()
             ->retrieveClasses()
             ->findClassDefinition();

        return $this;
    }

    /**
     * @return $this
     */
    protected function addTraitImport()
    {
        if ($this->hasTraitImport()) {
            return $this;
        }

        $lastImport = $this->getLastImport();
        if ($lastImport === false) {
            $lineNumber = $this->classAbstractTree->getLine() - 1;
            $newImport = 'use '.$this->trait.';'.$this->lineEnding;

            array_splice($this->content, $lineNumber, 0, $this->lineEnding);
        } else {
            $lineNumber = $this->getLastImport()->getAttribute('endLine');
            $newImport = 'use '.$this->trait.';'.$this->lineEnding;
        }

        array_splice($this->content, $lineNumber, 0, $newImport);

        return $this;
    }

    /**
     * @return $this
     */
    protected function addTraitUseStatement()
    {
        if ($this->alreadyUsesTrait()) {
            return $this;
        }

        $line = $this->getNewTraitUseLine();

        $newTraitUse = static::getIndentation($this->content[$line]).'use '.$this->traitShortName.';'.$this->lineEnding;

        array_splice($this->content, $line, 0, $newTraitUse);

        return $this;
    }

    /**
     * @throws Exception
     *
     * @return $this
     */
    protected function parseContent()
    {
        $flatContent = implode($this->content);

        try {
            $parser = $this->getParser();
            $this->syntaxTree = $parser->parse($flatContent);
        } catch (Error $e) {
            throw new Exception('Error on parsing '.$this->classShortName." class\n".$e->getMessage());
        }

        return $this;
    }

    /**
     * @throws Exception
     *
     * @return $this
     */
    protected function retrieveNamespace()
    {
        if (! isset($this->syntaxTree[0]) || ! ($this->syntaxTree[0] instanceof Namespace_)) {
            throw new Exception("Could not locate namespace definition for class '".$this->classShortName."'");
        }

        $this->namespace = $this->syntaxTree[0];

        return $this;
    }

    /**
     * @return $this
     */
    protected function retrieveImports()
    {
        $this->importStatements = array_filter($this->namespace->stmts, function ($statement) {
            return $statement instanceof Use_;
        });

        return $this;
    }

    /**
     * @return $this
     */
    protected function retrieveClasses()
    {
        $this->classes = array_filter($this->namespace->stmts, function ($statement) {
            return $statement instanceof Class_;
        });

        return $this;
    }

    /**
     * @return \PhpParser\Node\Stmt\Use_
     */
    protected function getLastImport()
    {
        return end($this->importStatements);
    }

    /**
     * @return bool
     */
    protected function hasTraitImport()
    {
        foreach ($this->importStatements as $statement) {
            if ($statement->uses[0]->name->toString() == $this->trait) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function alreadyUsesTrait()
    {
        $traitUses = array_filter($this->classAbstractTree->stmts, function ($statement) {
            return $statement instanceof TraitUse;
        });

        /** @var TraitUse $statement */
        foreach ($traitUses as $statement) {
            foreach ($statement->traits as $traitUse) {
                if ($traitUse->toString() == $this->trait
                    || $traitUse->toString() == $this->traitShortName
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @throws Exception
     *
     * @return $this
     */
    protected function findClassDefinition()
    {
        foreach ($this->classes as $class) {
            if ($class->name == $this->classShortName) {
                $this->classAbstractTree = $class;

                return $this;
            }
        }

        throw new Exception('Class '.$this->classShortName.' not found');
    }

    /**
     * @return int
     */
    protected function getNewTraitUseLine()
    {
        $line = $this->classAbstractTree->getLine() + 1;

       /* // -1 because we want place it before
        // another -1 because phpParser counts lines from 1
        $line = array_values($this->classAbstractTree->stmts)[0]->getLine() - 2;*/

        /*
         * If class definition is like this:
         *
         * class Foo
         * {
         *     // Content
         * }
         *
         * not like this:
         *
         * class Foo {
         *     // Content
         * }
         *
         * we need to subtract one line
         */
        if (strpos($this->content[$this->classAbstractTree->getLine() - 1], '{') !== false) {
            --$line;
        }

        return $line;
    }

    /**
     * Default line ending is set to LF.
     *
     * If there is at least one line in the provided file
     * and it contains CR+LF, change line ending CR+LF.
     *
     * @return $this
     */
    protected function determineLineEnding()
    {
        if (isset($this->content[0]) && strpos($this->content[0], "\r\n") !== false) {
            $this->lineEnding = "\r\n";
        }

        return $this;
    }

    /**
     * @param $line
     *
     * @return string
     */
    protected static function getIndentation($line)
    {
        preg_match('/^\s*/', $line, $match);

        if (isset($match[0])) {
            $match[0] = trim($match[0], "\n\r");

            if (strlen($match[0]) > 0) {
                return $match[0];
            }
        }

        return '    ';
    }

    protected function getParser()
    {
        $refParser = new \ReflectionClass('\PhpParser\Parser');

        if (! $refParser->isInterface()) {
            /*
             * If we are running nikic/php-parser 1.*
             */
            return new \PhpParser\Parser(new Lexer());
        } else {
            /*
             * If we are running nikic/php-parser 2.*
             */
            return (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::PREFER_PHP7);
        }
    }
}
