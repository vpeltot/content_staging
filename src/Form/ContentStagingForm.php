<?php

/**
 * Provide the settings form for content staging.
 */

namespace Drupal\content_staging\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\content_staging\ContentStagingManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContentStagingForm extends ConfigFormBase implements ContainerInjectionInterface {

  /**
   * @var \Drupal\content_staging\ContentStagingManager
   */
  protected $contentStagingManager;

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\content_staging\ContentStagingManager $content_staging_manager
   */
  public function __construct(ConfigFactoryInterface $config_factory, ContentStagingManager $content_staging_manager) {
    parent::__construct($config_factory);

    $this->contentStagingManager = $content_staging_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('content_staging.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['content_staging.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_staging_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $staging_directory = $this->contentStagingManager->getConfigStagingDirectory();
    $form['staging_directory'] = [
      '#type' => 'textfield',
      '#title' => t('Content staging directory'),
      '#description' => t('Directory where the content will be exported. This directory is relative to the drupal root.'),
      '#default_value' => ($staging_directory) ? $staging_directory : '../staging'
    ];

    $entity_types = $this->contentStagingManager->getConfigStagingEntityTypes();
    $form['entity_types'] = [
      '#type' => 'details',
      '#title' => t('Entity types'),
      '#description' => t('Choose the entity types / bundles to which the content will be exported'),
      '#open' => FALSE,
    ];

    foreach ($this->contentStagingManager->getContentEntityTypes() as $entity_type => $entity_info) {
      $form['entity_types'][$entity_type] = [
        '#type' => 'fieldset',
        '#title' => $entity_info->getLabel(),
      ];

      $form['entity_types'][$entity_type]['enable'] = [
        '#type' => 'checkbox',
        '#title' => t('Allow export all <em>@entity_type</em> entities', [
          '@entity_type' => $entity_info->getLabel(),
        ]),
        '#default_value' => (isset($entity_types[$entity_type]['enable'])) ? $entity_types[$entity_type]['enable'] : FALSE,
      ];

      if ($entity_info->hasKey('bundle')) {
        $bundles = $this->contentStagingManager->getContentEntityTypesBundles($entity_type);

        $form['entity_types'][$entity_type]['bundles'] = [
          '#type' => 'details',
          '#title' => t('Bundles'),
          '#open' => TRUE,
        ];

        foreach ($bundles as $bundle => $bundle_info) {
          $form['entity_types'][$entity_type]['bundles'][$bundle] = [
            '#type' => 'checkbox',
            '#title' => t('Allow export all <em>@entity_type - @bundle</em> entities', [
              '@entity_type' => $entity_info->getLabel(),
              '@bundle' => $bundle_info['label'],
            ]),
            '#default_value' => (isset($entity_types[$entity_type]['bundles'][$bundle])) ? $entity_types[$entity_type]['bundles'][$bundle] : FALSE,
          ];
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->contentStagingManager->setConfigStagingDirectory($form_state->getValue('staging_directory'));
    $this->contentStagingManager->setConfigStagingEntityTypes($form_state->getValue('entity_types'));
  }

}
