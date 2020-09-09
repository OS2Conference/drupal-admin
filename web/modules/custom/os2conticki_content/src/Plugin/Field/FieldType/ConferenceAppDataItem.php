<?php

namespace Drupal\os2conticki_content\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\os2conticki_content\ConferenceAppComputed;

/**
 * Plugin implementation of the 'pretix_date' field type.
 *
 * @FieldType(
 *   id = "os2conticki_conference_app_data",
 *   label = @Translation("Conference app data"),
 *   description = @Translation("Conference app data"),
 *   default_widget = "os2conticki_conference_app_data_widget",
 *   default_formatter = "os2conticki_conference_app_data_default"
 * )
 *
 * @property string app_logo
 * @property string custom_app_url
 * @property string google_analytics_id
 */
class ConferenceAppDataItem extends FieldItemBase {

  /**
   *
   */
  public static function propertyDefinitions(
    FieldStorageDefinitionInterface $field_definition
  ) {
    $properties['app_logo'] = DataDefinition::create('integer')
      ->setLabel(t('App logo'));
    $properties['custom_app_url'] = DataDefinition::create('string')
      ->setLabel(t('Custom app url'));
    $properties['google_analytics_id'] = DataDefinition::create('string')
      ->setLabel(t('Google Analytics id'));
    $properties['icons'] = DataDefinition::create('map')
      ->setLabel(t('Icons'))
      ->setDescription(t('The computed icons.'))
      ->setComputed(TRUE)
      ->setReadOnly(TRUE)
      // Is it a bug that we need this (cf. https://www.drupal.org/project/jsonapi/issues/3055163)?
      ->setInternal(FALSE)
      ->setClass(ConferenceAppComputed::class)
      ->setSetting('property', 'icons');

    return $properties;
  }

  // Public function isEmpty() {
  //    return parent::isEmpty(); // TODO: Change the autogenerated stub
  //  }.

  /**
   *
   */
  public static function schema(
    FieldStorageDefinitionInterface $field_definition
  ) {
    return [
      'columns' => [
        'app_logo' => [
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'custom_app_url' => [
          'type' => 'varchar',
          'length' => 255,
        ],
        'google_analytics_id' => [
          'type' => 'varchar',
          'length' => 255,
        ],
      ],
    ];
  }

}
