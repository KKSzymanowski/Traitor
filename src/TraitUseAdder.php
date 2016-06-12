<?php

namespace Traitor;

use Exception;
use PhpParser\Error;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;
use ReflectionClass;
use Traitor\Handlers\AbstractTreeHandler;

class TraitUseAdder
{

    /** @var  string */
    protected $filePath;

    /** @var  ReflectionClass */
    protected $classReflection;

    /** @var  ReflectionClass */
    protected $traitReflection;

    /** @var  array */
    protected $content = null;

    public function add($trait)
    {
        $this->traitReflection = new ReflectionClass($trait);

        return $this;
    }

    public function to($class)
    {
        $this->retrieveFilePath($class)
             ->retrieveFileContent();

        $handler = new AbstractTreeHandler(
            $this->content,
            $this->classReflection->getName(),
            $this->traitReflection->getName());

        $this->content = $handler->handle()->toArray();

        $this->saveFile();

        return $this;
    }

    protected function saveFile()
    {
        if (file_put_contents($this->filePath, implode($this->content)) === false) {
            throw new Exception("Error on writing to file " . $this->filePath);
        }
    }

    protected function buildSyntaxTree()
    {
        $this->parseContent()
             ->retrieveNamespace()
             ->retrieveImports()
             ->retrieveClasses()
             ->findClassDefinition();

        return $this;
    }

    protected function retrieveFileContent()
    {
        $this->content = file($this->filePath);

        return $this;
    }

    protected function retrieveFilePath($class)
    {
        $this->classReflection = new ReflectionClass($class);

        $this->filePath = $this->classReflection->getFileName();

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

        $lineNumber = $this->getLastImport()->getAttribute('endLine');

        $newImport = "use " . $this->traitReflection->getName() . ";\n";

        array_splice($this->content, $lineNumber, 0, $newImport);

        return $this;
    }

    /**
     * @return $this
     */
    protected function addTraitUseStatement()
    {

        $line = $this->getFirstTraitUseLine();

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
         * we need to add the use statement one line further
         */
        if(strpos($this->content[$this->classAbstractTree->getLine() - 1], '{') === false) {
            $line++;
        }

        $newTraitUse = static::getIndentation($this->content[$line]) . "use " . $this->traitReflection->getShortName() . ";\n";

        array_splice($this->content, $line, 0, $newTraitUse);

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function parseContent()
    {
        $flatContent = implode('', $this->content);

        try {
            $this->syntaxTree = (new ParserFactory)
                ->create(ParserFactory::PREFER_PHP7)
                ->parse($flatContent);
        } catch (Error $e) {
            throw new Exception('Error on parsing ' . $this->classReflection->getShortName() . ' class
                (using file ' . $this->filePath . ")\n" . $e->getMessage());
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function retrieveNamespace()
    {
        if (!isset($this->syntaxTree[0]) || !($this->syntaxTree[0] instanceof Namespace_)) {
            throw new Exception("Could not locate namespace definition in ' . $this->classReflection->getShortName() . ' class");
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
            if ($statement->uses[0]->name->toString() == $this->traitReflection->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function findClassDefinition()
    {
        foreach ($this->classes as $class) {
            if ($class->name == $this->classReflection->getShortName()) {
                $this->classAbstractTree = $class;

                return $this;
            }
        }

        throw new Exception("Class " . $this->classReflection->getShortName() . " not found in " . $this->filePath);
    }

    /**
     * @return \PhpParser\Node\Stmt\TraitUse
     */
    protected function getFirstTraitUseLine()
    {
        if (count($this->classAbstractTree->stmts) == 0) {
            return $this->classAbstractTree->getLine();
        }

        return array_first($this->classAbstractTree->stmts)->getLine() - 1;
    }

    /**
     * @param $line
     * @return string
     */
    protected static function getIndentation($line)
    {
        preg_match('/^\s*/', $line, $match);

        if (isset($match[0])) {
            $match[0] = trim($match[0], "\n\r");

            if (strlen($match[0]) > 0)
                return $match[0];
        }

        return '    ';
    }

}
