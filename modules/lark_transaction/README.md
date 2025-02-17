# Lark Transaction - Deprecated, do not use

## Drush commands

* `drush lark:execute-transaction <plugin id>` - Execute a single transaction by plugin id.
* `drush lark:execute-all-transactions` - Execute all transactions.
* `drush generate lark:transaction:plugin` - Generate a new Lark transaction plugin.

## User Interface:

* `/admin/config/development/lark/transactions` - List of all transactions.

## How to create a new transaction manually

See the `src/Plugin/LarkTransaction` directory in the `lark` module for examples.

1. Create a new plugin in the `src/Plugin/LarkTransaction` directory in your custom module that extends `Drupal\lark_transaction\Plugin\LarkTransaction\LarkTransactionPluginBase`.
2. Implement the `execute` method.
