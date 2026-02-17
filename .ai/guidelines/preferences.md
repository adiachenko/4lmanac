# Project Preferences

## Controllers

Use single-action (invokable) controllers.

Choose clear, descriptive names that reflect the intended action, suffixed with `Controller`. Favor RESTful convention-inspired naming when practical.

Controller naming rules:

- Use **verb-first** action names (`Show`, `Store`, `Update`, `Delete`, `Handle`, `Login`, etc.).
- Pattern: `VerbNounController` (or `VerbNounContextController` when needed).
- Avoid noun-first or topic-first names that hide the action.

Examples:

- Good: `StorePostController`, `LoginUserController`
- Bad: `PostController`, `UserLoginController`
