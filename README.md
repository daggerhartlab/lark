# Lark

Lark provides the functionality to export and import entities along with their
dependencies from one Drupal site to another.

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Setup

Visit the Lark settings page and set a directory for the Lark Source plugin to
store exported entities. Ideally, this directory should be outside of the
document root of the Drupal site.

The default Lark Source stores exported entities outside the document root in
the `content/lark-exports` folder.

To configure your own Lark Source plugin, create a `*.lark_sources.yml` file
within your module or theme and define the directory path for the Lark Source.
You can make your exports directory relative to a module or theme by providing
the module or theme name within brackets in the directory path.

### Source Examples

```yaml
# Directory outside of the webroot.
example_lark_source:
  label: 'Example Lark Source'
  directory: '../content'

# Directory with the source provider module or theme.
example_relative_to_provider:
  label: 'Example Extension Relative'
  directory: '[some_module_name]/content'
```

## Exporting Entities

Entities can be exported from the entity view page by clicking the
"Lark Export" tab. This will provide a form where you can easily choose
the Source plugin and export the entity and its dependencies.

To export an entity using drush, the following command can be used:

```bash
drush lark:export-entity <source_plugin_id> <entity type> <entity id>
```

## Importing Entities

Exported entities can be imported using the Export Manager UI, located at
`/admin/config/development/lark/entity-exports`. This page will list all
exports available within all sources, along with their current status.

To import entities using drush, the following commands can be used:

```bash
# Import all entities from all Lark Sources.
drush lark:import-all-entities

# Import all entities within a given source.
drush lark:import-source <source_plugin_id>

# Import a single entity and its dependencies from a specific Lark Source.
drush lark:import-entity <source_plugin_id> <uuid>
```

## User Interface:

* `/admin/config/development/lark/entity-exports` - List of all exported entities and their status.
* `/node/{node}/lark-export` - Export a single node entity.
* `/media/{media}/lark-export` - Export a single media entity.


# Attributions

* <a href="https://www.flaticon.com/free-icons/yaml" title="yaml icons">Yaml icons created by IYIKON - Flaticon</a>
* <a href="https://www.flaticon.com/free-icons/question-mark" title="question mark icons">Question mark icons created by Fathema Khanom - Flaticon</a>
* <a href="https://www.flaticon.com/free-icons/alert" title="alert icons">Alert icons created by Good Ware - Flaticon</a>
