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
use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Namespace_;

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

    /** @var string */
    protected $prefix;

    /**
     * @param array $content
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

        $namespace = explode('\\', $this->trait, 3);
        $this->prefix = $namespace[0] . $namespace[1];
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
     * @return $this
     */
    public function handleRemove()
    {
        $this->buildSyntaxTree()
            ->removeTraitImport()
            ->buildSyntaxTree()
            ->removeTraiUseStatement();

        return $this;
    }

    /**
     * @return $this
     */
    public function handleInterface()
    {
        $this->buildInterfaceSyntaxTree()
            ->addTraitImport()
            ->buildInterfaceSyntaxTree()
            ->addExtendedInterface();

        return $this;
    }

    /**
     * @return $this
     */
    public function handleRemoveInterface()
    {
        $this->buildInterfaceSyntaxTree()
            ->removeTraitImport()
            ->buildInterfaceSyntaxTree()
            ->removeExtendedImportStatement();

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
     * @return $this
     * @throws Exception
     *
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
     * @throws Exception
     *
     */
    protected function buildInterfaceSyntaxTree()
    {
        $this->parseContent()
            ->retrieveNamespace()
            ->retrieveImports()
            ->retrieveInterface()
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

        $startLine = null;
        $endLine = null;

        $lastImport = $this->getLastImport();

        if ($lastImport === false) {
            $lineNumber = $this->classAbstractTree->getLine() - 1;
            $newImport = 'use ' . $this->trait . ' as ' . $this->prefix . $this->traitShortName . ';' . $this->lineEnding;

            $startLine = $lineNumber;

            if ($this->hasClassDocBlock()) {
                /** @var array $docBlocks */
                $docBlocks = $this->retrieveClassDocBlocks();

                $startLine = $docBlocks[0]->getStartLine();
                $endLine = $docBlocks[0]->getEndLine();
            }

            array_splice($this->content, $startLine - 1, 0, $this->lineEnding);
        } else {
            $lineNumber = $this->getLastImport()->getAttribute('endLine');
            $newImport = 'use ' . $this->trait . ' as ' . $this->prefix . $this->traitShortName . ';' . $this->lineEnding;
        }

        array_splice($this->content, $lineNumber, 0, $newImport);

        return $this;
    }

    /**
     * @return $this
     */
    protected function removeTraitImport()
    {
        if (!$this->hasTraitImport()) {
            return $this;
        }

        foreach ($this->importStatements as $statement) {
            if ($statement->uses[0]->name->toString() == $this->trait) {
                unset($this->content[$statement->getLine() - 1]);
            }
        }

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

        $newTraitUse = static::getIndentation($this->content[$line]) . 'use ' . $this->prefix . $this->traitShortName . ';' . $this->lineEnding;

        array_splice($this->content, $line, 0, $newTraitUse);

        return $this;
    }

    /**
     * @return $this
     */
    protected function addExtendedInterface()
    {
        $line = $this->getInterfaceLine();

        if ($this->alreadyExtendsInterface()) {
            return $this;
        }

        $newInterfaceExtend = substr($this->content[$line], 0, -1) . ' extends ' . $this->prefix . $this->traitShortName . "\n";

        $interfaceLineLength = strlen($this->content[$line]);

        $newCommaSeparator = substr_replace($this->content[$line], ',', $interfaceLineLength - 1, 0);

        if (false !== strpos($this->content[$line], 'extends')) {
            $newInterfaceExtend = $this->prefix . $this->traitShortName . "\n";

            array_splice($this->content, $line, 0, $newCommaSeparator);
            array_splice($this->content, $line + 1, 0, $newInterfaceExtend);
            unset($this->content[$line + 2]);

            return $this;
        }

        array_splice($this->content, $line + 1, 0, $newInterfaceExtend);
        unset($this->content[$line]);

        return $this;
    }

    /**
     * @return $this
     */
    protected function removeTraiUseStatement()
    {
        if (!$this->alreadyUsesTrait()) {
            return $this;
        }

        $traitUses = array_filter($this->classAbstractTree->stmts, function ($statement) {
            return $statement instanceof TraitUse;
        });

        /** @var TraitUse $statement */
        foreach ($traitUses as $statement) {
            foreach ($statement->traits as $traitUse) {
                if ($traitUse->toString() == $this->trait
                    || $traitUse->toString() == $this->prefix . $this->traitShortName
                ) {
                    unset($this->content[$traitUse->getLine()]);
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function removeExtendedImportStatement()
    {
        $extendedImports = array_filter($this->classAbstractTree->extends, function ($statement) {
            return $statement instanceof Name;
        });

        if (!$this->alreadyExtendsInterface()) {
            return $this;
        }

        /** @var Name $statement */
        foreach ($extendedImports as $statement) {
            foreach ($statement->parts as $extendImport) {
                if ($extendImport == $this->trait
                    || $extendImport == $this->prefix . $this->traitShortName
                ) {
                    $previousExtendImport = $this->content[$statement->getLine() - 1];

                    if (1 === count($extendedImports)) {
                        $interfaceWithoutExtendedInterface = str_replace($extendImport, "", $this->content[$statement->getLine()]);
                        $interfaceWithoutExtend = str_replace('extends', "", $interfaceWithoutExtendedInterface);

                        $this->content[$statement->getLine()] = trim($interfaceWithoutExtend) . "\n";
                    } else if (substr($previousExtendImport, -2) == ",\n") {
                        $newPreviousExtendImport = substr($this->content[$statement->getLine() - 1], 0, -2) . "\n";

                        unset($this->content[$statement->getLine()]);
                        unset($this->content[$statement->getLine() - 1]);

                        array_splice($this->content, $statement->getLine() - 2, 0, $newPreviousExtendImport);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     *
     */
    protected function parseContent()
    {
        $flatContent = implode($this->content);

        try {
            $parser = $this->getParser();
            $this->syntaxTree = $parser->parse($flatContent);
        } catch (Error $e) {
            throw new Exception('Error on parsing ' . $this->classShortName . " class\n" . $e->getMessage());
        }

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     *
     */
    protected function retrieveNamespace()
    {
        $syntaxTree = $this->hasDeclare() ? $this->syntaxTree[1] : $this->syntaxTree[0];

        if (!isset($syntaxTree) || !($syntaxTree instanceof Namespace_)) {
            throw new Exception("Could not locate namespace definition for class '" . $this->classShortName . "'");
        }

        $this->namespace = $syntaxTree;

        return $this;
    }

    /**
     * @return bool
     */
    private function hasDeclare()
    {
        if ($this->syntaxTree[0] instanceof Declare_) {
            return true;
        }

        return false;
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
     * @return $this
     */
    protected function retrieveInterface()
    {
        $this->classes = array_filter($this->namespace->stmts, function ($statement) {
            return $statement instanceof Interface_;
        });

        return $this;
    }

    /**
     * @return array
     */
    protected function retrieveClassDocBlocks()
    {
        $attributes = $this->classes[0]->getAttributes();

        /** @var array $docBlocks */
        $docBlocks = array_filter($attributes['comments'], function ($statement) {
            return $statement instanceof Doc;
        });

        return $docBlocks;
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
    protected function hasClassDocBlock()
    {
        $attributes = $this->classes[0]->getAttributes();

        if (isset($attributes['comments'])) {
            /** @var array $docblock */
            $docBlocks = array_filter($attributes['comments'], function ($statement) {
                return $statement instanceof Doc;
            });

            return !empty($docBlocks);
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
                    || $traitUse->toString() == $this->prefix . $this->traitShortName
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function alreadyExtendsInterface()
    {
        $extendedImports = array_filter($this->classAbstractTree->extends, function ($statement) {
            return $statement instanceof Name;
        });

        /** @var Name $statement */
        foreach ($extendedImports as $statement) {
            foreach ($statement->parts as $extendImport) {
                if ($extendImport == $this->trait
                    || $extendImport == $this->prefix . $this->traitShortName
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return $this
     * @throws Exception
     *
     */
    protected function findClassDefinition()
    {
        foreach ($this->classes as $class) {
            if ($class->name == $this->classShortName) {
                $this->classAbstractTree = $class;

                return $this;
            }
        }

        throw new Exception('Class ' . $this->classShortName . ' not found');
    }

    /**
     * @return int
     * @throws Exception
     */
    protected function getNewTraitUseLine()
    {
        // If the first statement is a trait use, insert the new trait use before it.
        if (isset($this->classAbstractTree->stmts[0])) {
            $firstStatement = $this->classAbstractTree->stmts[0];

            if ($firstStatement instanceof TraitUse) {
                return $firstStatement->getLine() - 1;
            }
        }

        // If the first statement is not a trait use, insert the new one just after the opening bracket.
        for ($line = $this->classAbstractTree->getLine() - 1; $line < count($this->content); $line++) {
            if (strpos($this->content[$line], '{') !== false) {
                return $line + 1;
            }
        }

        throw new Exception("Opening bracket not found in class [$this->classShortName]");
    }

    /**
     * @return int
     * @throws Exception
     */
    protected function getInterfaceLine()
    {
        for ($line = 0; $line < count($this->content); $line++) {
            if (strpos($this->content[$line], '{') !== false) {
                return $line - 1;
            }
        }

        throw new Exception("Interface not found in class [$this->classShortName]");
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

        if (!$refParser->isInterface()) {
            // If we are running nikic/php-parser 1.*
            return new \PhpParser\Parser(new Lexer());
        } else {
            // If we are running nikic/php-parser 2.*, 3.* or 4.*
            return (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::PREFER_PHP7);
        }
    }
}
