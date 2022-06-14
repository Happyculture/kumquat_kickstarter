![Logo Kumquat Kickstarter](kumquat_kickstarter.png)

* **[Kumquat Kickstarter](#intro)**
* **[Installation](#installation)**
* **[Basic usage](#usage)**
* **[Advanced usage](#advanced)**
* **[Troubleshooting](#troubleshooting)**
* **[Credits](#credits)**

# <a name="intro"></a>Kumquat Kickstarter

Kumquat kickstarter is a drupal module that helps you start your Drupal projects faster by migrating entity bundles and fields from a spreadsheet.

## <a name="installation"></a>Installation

- `composer require happyculture/kumquat_kickstarter --dev`
- `drush en kumquat_kickstarter`

## <a name="usage"></a>Basic usage

1. Copy this [Google Sheet](https://docs.google.com/spreadsheets/d/1KbKfP7XtuMgsBn1FMvYmsM6hEQKQ294JponWlYt3Goo/edit?usp=sharing) into your own Drive and fill it with data
    1. Each bundle you want to create must have an entry in the `Bundles` worksheet
    1. Each bundle for which you want fields must have its own woksheet named `Field: [BUNDLE LABEL]` based on the `Fields: Actualités` worksheet
1. Export it as ODS format, name it `site_builder.ods` and place it in your `../config/` directory
1. Migrate the bundles: `drush migrate:import --tag kumquat_kickstarter_entity_bundles`
1. Migrate the fields: `drush migrate:import --tag kumquat_kickstarter_fields`
1. Migrate the default form modes: `drush migrate:import --tag kumquat_kickstarter_default_form_mode`
1. Export the created configuration

## <a name="advanced"></a>Advanced usage

### Running all fields migrations of a specific bundle

Fields migrations are generated using a custom migration tag that allows to run all fields migration of a specific bundle at once. This tags is the form of `kumquat_kickstarter_fields:ENTITY_TYPE__BUNBLE`.

For example, to run the fields migrations of the `news_categories` vocabulary, you can run `drush migrate:import --tag kumquat_kickstarter_fields:taxonomy_term__news_categories`.

### Prepopulating migrate maps after site reinstall

It can help a lot to prepopulate the migrate maps when you reinstall your site from scratch to prevent future migrations to override your existing entities.

To do so, you can use the `drush kumquat_kickstarter:prepropulate --tag kumquat_kickstarter` command.

### Translating the spreasheet in your language

You may have noticed but the first line of each worksheet is hidden. It contains the name used by the script to migrate the data. The headers line that you can see is dedicated to end users and can be translated.

The names of the entity and field types cannot be translated, though. They are used by the migration to get important settings from the code.

### Translating the configuration that you migrate

Configuration is imported using the site's default language. To be able to also import the translated version of the configuration for your other enabled languages you'll need to add some columns in the document.

The additional columns have to be named like the reference column (see below) appended by an uppercased langcode.

Reference columns for bundles:
- Name
- Description

Reference columns for fields (include label override):
- Field label
- Help text

For example, if your default language is French and if you want the configuration to be translated in English and Spanish, you'll need to add "Name EN", "Name SP", "Description EN" and "Description SP" columns to your Bundles worksheet. You'll also need to add "Field label EN", "Field label SP", "Help text EN" and "Help text SP" columns to your fields worksheets.

### Using contributed and custom entities

To add an entity type:

1. Enable the module that carries the entity type
1. Find the PHP Class that define the bundle entity ('bundle of' in the Annotation)
1. Copy the exact label value of your bundle entity
1. Paste it in the A column of the `Settings` worksheet
1. You can start using this new type in the `Bundles` worksheet and run migrations to get those bundles migrated

## <a name="troubleshooting"></a>Troubleshooting

### Errors when defining fields settings

#### Invalid YAML

Most of the time, invalid YAML will not throw any useful error. Be careful to respect indentation and wrap keys or values including spaces into some quotes.

#### Field types that convert settings

The settings columns need to be filled with YAML. Most of the time, you can take the YAML from the settings a field created in the UI, but sometimes, for example for list fields, it won't work. The thing is that some field types implements a `storageSettingsFromConfigData()` or a `fieldSettingsFromConfigData()` static method that converts the settings.

For example, for a *List (text)* field, you would find the following in the field storage configuration object:

```
allowed_values:
  -
    value: my_key
    label: 'My label'
  -
    value: my_other_key
    label: 'My other label'
```

But because of the `ListItemBase::storageSettingsFromConfigData()` method, you will need to put the following in the migration source file:

```
allowed_values:
  my_key: 'My label'
  my_other_key: 'My other label'
```

# <a name="credits"></a>Credits

The font used for the logo is [Smooth Butter from PutraCetol Studio](https://putracetol.com/product/smooth-butter/).
