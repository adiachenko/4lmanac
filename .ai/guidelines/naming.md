# Naming Conventions

Module names should not include prefixes or suffixes that denote the type of the module, such as `Abstract`, `Interface`, `Contract`, or `Trait`.

## Commands

Command classes should reflect their Artisan signature with an `app:` prefix and be organized within appropriate subdirectories. For multi-word command signatures, always use kebab-case.

E.g. `php artisan app:inventory:flush-records` corresponds to `App\Console\Commands\Inventory\FlushRecordsCommand`.

## Jobs

A job's name should describe its action with a `Job` suffix.

E.g. `CreateUserJob` or `PerformDatabaseCleanupJob`.

## Events

Events will often be fired before or after the actual event. This should be very clear by the tense used in their name.

E.g. `RequestSending` before the action is completed and `Registered` after the action is completed.

## Listeners

Listeners will perform an action based on an incoming event. Their name should reflect that action with a `Listener` suffix.

E.g. `SendInvitationMailListener`.

## Mailables

Suffix mailables with `Mail` to avoid naming collisions. Stick with nouns.

E.g. `OrderConfirmationMail`.

## Notifications

Use past tense to name notifications and suffix them with `Notification`.

E.g. `EmployeeAccountCreatedNotification`.

## Facades

Facades should use singular nouns.

E.g. `Inventory` or `Geocoder`.

## Enums

Enums don't need to be prefixed—in most cases it is clear by reading the name that it is an enum.

E.g. `OrderStatus` or `SubscriptionType`.
