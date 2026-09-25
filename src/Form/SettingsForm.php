<?php

namespace Drupal\ubc_htmx_routes\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase {

  protected EntityDisplayRepositoryInterface $entityDisplayRepository;
  protected EntityTypeManagerInterface $entityTypeManagerService;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManagerService = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager')
    );
  }

  protected function getEditableConfigNames() {
    return ['ubc_htmx_routes.settings'];
  }

  public function getFormId() {
    return 'ubc_htmx_routes_settings_form';
  }

  /**
   * Returns content entity types that support view modes/builders.
   */
  protected function getEligibleEntityTypes(): array {
    $options = [];
    foreach ($this->entityTypeManagerService->getDefinitions() as $id => $definition) {
      if ($definition->entityClassImplements('Drupal\Core\Entity\ContentEntityInterface')
        && $definition->hasViewBuilderClass()) {
        $options[$id] = $definition->getLabel();
      }
    }
    asort($options);
    return $options;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ubc_htmx_routes.settings');
    $configured = [];
    foreach ($config->get('entity_types') ?: [] as $row) {
      $configured[$row['entity_type']] = $row['view_mode'];
    }

    $form['entity_types'] = [
      '#type' => 'table',
      '#header' => [$this->t('Expose'), $this->t('Entity type'), $this->t('View mode')],
      '#tree' => TRUE,
    ];

    foreach ($this->getEligibleEntityTypes() as $entity_type_id => $label) {
      $enabled = isset($configured[$entity_type_id]);
      $view_modes = $this->entityDisplayRepository->getViewModeOptions($entity_type_id);

      $form['entity_types'][$entity_type_id]['enabled'] = [
        '#type' => 'checkbox',
        '#default_value' => $enabled,
      ];
      $form['entity_types'][$entity_type_id]['label'] = [
        '#markup' => $label,
      ];
      $form['entity_types'][$entity_type_id]['view_mode'] = [
        '#type' => 'select',
        '#options' => $view_modes,
        '#default_value' => $configured[$entity_type_id] ?? 'default',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('entity_types');
    $entity_types = [];

    foreach ($values as $entity_type_id => $row) {
      if (!empty($row['enabled'])) {
        $entity_types[] = [
          'entity_type' => $entity_type_id,
          'view_mode' => $row['view_mode'],
        ];
      }
    }

    $this->config('ubc_htmx_routes.settings')
      ->set('entity_types', $entity_types)
      ->save();

    parent::submitForm($form, $form_state);
  }

}