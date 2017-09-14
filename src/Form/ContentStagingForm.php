<?php

namespace Drupal\content_staging\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\content_staging\ContentStagingManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide the settings form for content staging.
 */
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

    $staging_directory = $this->contentStagingManager->getDirectory();
    $form['staging_directory'] = [
      '#type' => 'textfield',
      '#title' => t('Content staging directory'),
      '#description' => t('Directory where the content will be exported. This directory is relative to the drupal root.'),
      '#default_value' => ($staging_directory) ? $staging_directory : '../staging'
    ];

    $entity_types = $this->contentStagingManager->getEntityTypes();
    $form['entity_types'] = [
      '#type' => 'fieldset',
      '#title' => t('Entity types'),
      '#description' => t('Choose the entity types / bundles for which the content will be exported'),
    ];

    foreach ($this->contentStagingManager->getContentEntityTypes() as $entity_type_id => $entity_info) {

      $form['entity_types'][$entity_type_id]['enable'] = [
        '#type' => 'checkbox',
        '#title' => t('<strong>@entity_type</strong>', [
          '@entity_type' => $entity_info->getLabel(),
        ]),
        '#default_value' => (isset($entity_types[$entity_type_id]['enable'])) ? $entity_types[$entity_type_id]['enable'] : FALSE,
      ];

      if ($entity_info->hasKey('bundle')) {
        $bundles = $this->contentStagingManager->getBundles($entity_type_id);

        $form['entity_types'][$entity_type_id]['bundles'] = [
          '#type' => 'fieldset',
          '#title' => t('Bundles'),
          '#open' => TRUE,
          '#states' => [
            'visible' => [
              ':input[name="entity_types[' . $entity_type_id . '][enable]"]' => ['checked' => TRUE],
            ],
          ],
        ];

        foreach ($bundles as $bundle => $bundle_info) {
          $form['entity_types'][$entity_type_id]['bundles'][$bundle] = [
            '#type' => 'checkbox',
            '#title' => t('Allow export all <em>@entity_type - @bundle</em> entities', [
              '@entity_type' => $entity_info->getLabel(),
              '@bundle' => $bundle_info['label'],
            ]),
            '#default_value' => (isset($entity_types[$entity_type_id]['bundles'][$bundle])) ? $entity_types[$entity_type_id]['bundles'][$bundle] : FALSE,
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
    $this->contentStagingManager->setDirectory($form_state->getValue('staging_directory'));
    $this->contentStagingManager->setEntityTypes($form_state->getValue('entity_types'));
  }

}
