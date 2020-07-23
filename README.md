[Insert logo here]

* **[Kumquat Kickstarter](#intro)**
* **[Installation](#installation)**
* **[Basic usage](#usage)**
* **[Advanced usage](#advanced)**

# <a name="intro"></a>Kumquat Kickstarter

Kumquat kickstarter is a drupal module that helps you start your Drupal projects faster by migrating entity bundles and fields from a spreadsheet.

## <a name="installation"></a>Installation

- `composer require happyculture/kumquat_kickstarter --dev`
- `drush en kumquat_kickstarter`

## <a name="usage"></a>Basic usage

1. Copy this [Google Sheet](https://docs.google.com/spreadsheets/d/1KbKfP7XtuMgsBn1FMvYmsM6hEQKQ294JponWlYt3Goo/edit?usp=sharing) into your own Drive and fill it with data
    1. Each bundle you want to create must have an entry in the `Bundles` worksheet
    1. Each bundle for which you want fields must have its own woksheet named `Field: [BUNDLE LABEL]` based on the `Fields: Actualités` worksheet 
1. Export it as XLSX format, name it `site_builder.xlsx` and place it in your `../config/` directory
1. Migrate the bundles: `drush migrate:import --group kumquat_kickstarter_entity_bundles`
1. Migrate the fields: `drush migrate:import --group kumquat_kickstarter_fields`
1. Export the created configuration

## <a name="advanced"></a>Advanced usage

### Translating the spreasheet in your language

You may have noticed but the first line of each worksheet is hidden. It contains the name used by the script to migrate the data. The headers line that you can see is dedicated to end users and can be translated.

The names of the entity and field types cannot be translated, though. They are used by the migration to get important settings from the code.

### Using contributed and custom entities

To add an entity type:

1. Enable the module that carries the entity type
1. Find the PHP Class that define the bundle entity ('bundle of' in the Annotation)
1. Copy the exact label value of your bundle entity
1. Paste it in the A column of the `Settings` worksheet
1. You can start using this new type in the `Bundles` worksheet and run migrations to get those bundles migrated
