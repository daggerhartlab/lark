# Lark

Lark provides the functionality to export and import content entities along with
their dependencies from one Drupal site to another.

## Setup

Visit the Lark Sources page (`/admin/lark/source`), and create a new Source for
where you will export your content. Ideally, this directory should be outside
the document root of the Drupal site.

You can make your exports directory relative to a module or theme by providing
the module or theme name within brackets in the directory path.

Example: `[my_custom_module]/content` will be converted into
`modules/custom/my_custom_module/content`.

Visit the Lark settings page and set default Lark Source.

## Exporting Entities

Entities can be exported from the entity's view/edit page by clicking the "Lark"
tab. This will provide a form where you can easily export the entity and its
dependencies. The export will be saved to the selected Lark Source, or can be
downloaded as a `.tar.gz` file.

To export an entity using drush, the following command can be used:

```bash
drush lark:export-entity <source id> <entity type> <entity id>
```

## Importing Entities

Exported entities can be imported using the Source's UI, located at
`/admin/lark/source/<source id>`. This page will list all  exports available
within the source along with their export status.

If the entity already exists on the site, the entity can be re-imported by
visiting the entity's edit page and clicking the "Lark" tab.

To import entities using drush, the following commands can be used:

```bash
# Import all entities from all Lark Sources.
drush lark:import-all-entities

# Import all entities within a given source.
drush lark:import-source <source id>

# Import a single entity and its dependencies from a specific Lark Source.
drush lark:import-entity <source id> <uuid>
```

## User Interface

* `/admin/lark/source` - List of all sources. View a source to see its export contents.
* `/lark/export/node/{node}` - Export a single node entity.
* `/lark/export/media/{media}` - Export a single media entity.
