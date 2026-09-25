<?php

namespace Drupal\ubc_htmx_routes\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HtmxEntityController extends ControllerBase {

  protected RendererInterface $renderer;
  protected ConfigFactoryInterface $htmxConfigFactory;
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    ConfigFactoryInterface $config_factory,
    EntityDisplayRepositoryInterface $entity_display_repository
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->htmxConfigFactory = $config_factory;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * Loads the entity, or throws a 404 if the type/ID is invalid.
   */
  protected function loadEntity(string $entity_type, string $entity_id) {
    if (!$this->entityTypeManager()->hasDefinition($entity_type)) {
      throw new NotFoundHttpException();
    }

    // Only expose entity types explicitly allow-listed in config.
    $allowed = $this->getConfiguredEntityTypes();
    if (!isset($allowed[$entity_type])) {
      throw new NotFoundHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage($entity_type);
    $entity = $storage->load($entity_id);
    if (!$entity) {
      throw new NotFoundHttpException();
    }

    return $entity;
  }

  /**
   * Returns [entity_type_id => view_mode] from config.
   */
  protected function getConfiguredEntityTypes(): array {
    $config = $this->htmxConfigFactory->get('ubc_htmx_routes.settings');
    $result = [];
    foreach ($config->get('entity_types') ?: [] as $row) {
      $result[$row['entity_type']] = $row['view_mode'];
    }
    return $result;
  }

  public function access(AccountInterface $account, string $entity_type, string $entity_id) {
    try {
      $entity = $this->loadEntity($entity_type, $entity_id);
    }
    catch (NotFoundHttpException $e) {
      return AccessResult::forbidden();
    }

    return $entity->access('view', $account, TRUE);
  }

  public function view(string $entity_type, string $entity_id) {
    $entity = $this->loadEntity($entity_type, $entity_id);
    $allowed = $this->getConfiguredEntityTypes();
    $view_mode = $allowed[$entity_type] ?? 'default';

    // Guard against a stale/removed view mode in config.
    $available = $this->entityDisplayRepository->getViewModeOptions($entity_type);
    if (!isset($available[$view_mode])) {
      $view_mode = 'default';
    }

    $build = $this->entityTypeManager()
      ->getViewBuilder($entity_type)
      ->view($entity, $view_mode);

    $context = new RenderContext();
    $markup = $this->renderer->executeInRenderContext($context, function () use ($build) {
      return $this->renderer->render($build);
    });

    $response = new CacheableResponse((string) $markup);
    $response->addCacheableDependency($this->htmxConfigFactory->get('ubc_htmx_routes.settings'));
    $response->addCacheableDependency($entity);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }
    return $response;
  }

}