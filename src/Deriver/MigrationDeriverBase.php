<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MigrationDeriverBase extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The website default langcode.
   *
   * @var string
   */
  protected $defaultLangcode;

  /**
   * The website additional langcodes.
   *
   * @var string[]
   */
  protected array $additionalLangcodes;

  /**
   * The translatable fields for this migration.
   *
   * @var array
   */
  protected $translatableFields = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->fileSystem = $container->get('file_system');

    $instance->defaultLangcode = $instance->languageManager->getDefaultLanguage()->getId();
    $instance->additionalLangcodes = array_keys($instance->languageManager->getLanguages());
    $instance->additionalLangcodes = array_combine($instance->additionalLangcodes, $instance->additionalLangcodes);
    unset($instance->additionalLangcodes[$instance->defaultLangcode]);

    return $instance;
  }

  /**
   * Check if the worksheet has translations.
   *
   * @param string $source_file_path
   *   The XLS source file path.
   * @param string $worksheet_name
   *   The worksheet name.
   * @param string $base_column_name
   *   The base column name to which to append the langcode to check if the
   *   translation is enabled.
   * @param array $langcode
   *   The language codes.
   *
   * @return array
   *   An array indicating whether the given langcode has translations in the
   *   source file.
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
   */
  protected function getEnabledTranslations($source_file_path, $worksheet_name, $base_column_name, array $langcodes) {
    /** @var \PhpOffice\PhpSpreadsheet\Reader\BaseReader $reader */
    $reader = IOFactory::createReader(IOFactory::identify($source_file_path));
    $reader->setLoadAllSheets();
    /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet $workbook */
    $workbook = $reader->load($source_file_path);
    /** @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet */
    $sheet = $workbook->getSheetByName($worksheet_name);

    $result = array_fill_keys($langcodes, FALSE);
    for ($col = 1, $max = Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) ; $col <= $max ; ++$col) {
      $col_letter = Coordinate::stringFromColumnIndex($col);
      $cell = $sheet->getCell($col_letter . "1", FALSE);
      $value = trim($cell->getValue());

      foreach ($langcodes as $langcode) {
        if ($value === $base_column_name . ' ' . strtoupper($langcode)) {
          $result[$langcode] = TRUE;
          break;
        }
      }
    }

    return array_filter($result);
  }

  /**
   * Create all derivative definitions per lang and per field.
   *
   * @param array $current_derivatives
   *   Existing migration derivatives.
   * @param array $enabled_translations
   *   Enabled translations.
   *
   * @return array
   *   All derivatives old and new ones.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getTranslationDerivativeDefinitions(array $current_derivatives, array $enabled_translations) {
    foreach ($current_derivatives as $derivative_id => $definition) {
      $entityTypeId = $definition['source']['constants']['entity_type'];
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);

      foreach ($this->translatableFields as $field_name) {
        if (substr($field_name, 0, 1) === '_') {
          $field_name = $entityType->getKey(substr($field_name, 1));
        }

        foreach ($enabled_translations as $langcode) {
          $derivative = $this->getTranslationDerivativeValues($derivative_id, $definition, $entityType, $field_name, $langcode);
          $this->derivatives[$derivative_id . ':' . $langcode . ':' . $field_name] = $derivative;
        }
      }
    }

    return $this->derivatives;
  }

  /**
   * Creates a derivative definition for the given langcode.
   *
   * @param string $derivative_id
   *   The derivative_id of the migration we are translating.
   * @param array $base_plugin_definition
   *   The definition of the base plugin from which the derivative plugin
   *   is derived. It is maybe an entire object or just some array, depending
   *   on the discovery mechanism.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   * @param string $field_name
   *   The field name to translate.
   * @param string $langcode
   *   The langcode to derivate.
   *
   * @return array
   *   return the definition of the base plugin.
   */
  protected function getTranslationDerivativeValues($derivative_id, array $base_plugin_definition, EntityTypeInterface $entityType, $field_name, $langcode) {
    $definition = $base_plugin_definition;

    // Add tags for easier targeting.
    $definition['migration_tags'][] = 'kumquat_kickstarter_translations';
    $definition['migration_tags'][] = $base_plugin_definition['id'] . '_translations';
    $definition['migration_tags'][] = $derivative_id . ':translations';

    // Set current langcode.
    $definition['source']['constants']['langcode'] = $langcode;

    // Declare this migration as a translations migration.
    $definition['destination']['translations'] = TRUE;

    // Define the translated property.
    $definition['source']['constants']['translated_property'] = $field_name;
    $definition['process']['property'] = 'constants/translated_property';

    // Define the translation source column.
    $definition['process']['translation'] = $base_plugin_definition['process'][$field_name] . ' ' . strtoupper($langcode);
    $definition['source']['columns'][] = $definition['process']['translation'];

    // Add dependency on the base migration.
    $definition['migration_dependencies']['required'][] = $base_plugin_definition['id'] . ':' . $derivative_id;

    return $definition;
  }

}
