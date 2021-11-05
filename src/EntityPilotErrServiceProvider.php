<?php

namespace Drupal\entity_pilot_err;

use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Creates a service modifier to hijack the transport service.
 */
class EntityPilotErrServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');

    // Add paragraphs normalizer.
    if (isset($modules['paragraphs'])) {
      // Add a normalizer service for paragraph entities.
      $service_definition = new Definition('Drupal\entity_pilot_err\Normalizer\ParagraphNormalizer', array(
        new Reference('hal.link_manager'),
        new Reference('entity_type.manager'),
        new Reference('module_handler'),
        new Reference('entity_type.repository'),
        new Reference('entity_field.manager'),
        new Reference('entity.repository'),
        new Reference('entity_pilot.resolver.unsaved_uuid'),
        new Reference('serializer.normalizer.entity_reference_item.hal'),
      ));
      // The priority must be higher than that of
      // serializer.normalizer.entity.hal in hal.services.yml.
      $service_definition->addTag('normalizer', array('priority' => 50));
      $container->setDefinition('entity_pilot_err.normalizer.paragraphs.hal', $service_definition);
    }
  }

}
