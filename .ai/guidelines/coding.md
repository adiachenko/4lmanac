# General Coding Standards

These conventions provide additional coding standards beyond what is automatically enforced by Pint and Rector within this project.

## Class Defaults

You MUST NOT use the `final` keyword. If you see a `final` keyword in application code, you should remove it.

## Type Declarations

All class properties, method parameters and return arguments MUST have a type declaration.

## Docblocks

Use docblocks to expand on the type hints for complex types like arrays, collections, etc.

Don't use docblocks for methods that can be fully type hinted (unless you need a description). Only add a description when it provides more context than the method signature itself.

## Strings

Whenever possible, use string interpolation instead of `sprintf()` or the `.` concatenation operator. However, prefer concatenation for multi-line or complex strings when it improves readability.
