# Traitor

A PHP package for automatically adding a `trait use statement` to a given class.

## Installation
Via composer:
```
composer require kkszymanowski/traitor
```

## Usage
- Basic usage:
```
(new TraitUseAdder)->add(FooTrait::class)->to(BarClass:class);
```
- Only generate output without changing files:
```
$handler = new AbstractTreeHandler(file($originalFilePath), FooTrait::class, BarClass::class);

$newContent = $handler->handle()->toString();
```
Note, that `AbstractTreeHandler` accepts input file as an array of lines, such as one produced from `file()` call.

## Behavior
Adding a new trait use statement does not change in any way formatting of your file(or at least it shouldn't).

If the trait is not present in the `use` section below the namespace declaration, it will be also added there, below any existing imports.

If it's not present in the `use` section in the class body, it will be added there below any existing use statements, on it's own line:
```
class Foo
{
    use PreviouslyExistingTrait;
    use NewlyAddedTrait;
}
```

## To do
- Add trait correctly when the class is empty, for example:
```
class Foo {}
```

- Add trait correctly when the class isn't namespaced.

