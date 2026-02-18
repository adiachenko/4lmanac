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

## Method Complexity and Extraction

Keep methods short and focused on a single level of abstraction. Each method should read as a high-level description of _what_ happens, not _how_. Extract implementation details into named private methods, dedicated action classes, or service layers.

**Rule of thumb:** if a method exceeds ~20 lines of logic (excluding validation arrays and return statements), it likely mixes concerns and needs extraction.

**Signs a method needs breaking up:**

- Nested closures or callbacks with their own branching logic
- Multiple try/catch blocks or catch-and-retry patterns
- Inline ID generation, key derivation, or payload transformation
- Sequential steps that each deserve a descriptive name

**Extract into:**

- Private methods when logic is only relevant to the current class
- Other classes when logic is reusable or independently testable

**Goal:** every method should operate at a single level of abstraction. A reader should understand the flow without scrolling or mentally parsing nested structures.
