<?php

namespace Drupal\Tests\entity_pilot_err\Kernel;


use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Class EntityPilotErrKernelTestBase.
 */
abstract class EntityPilotErrKernelTestBase extends KernelTestBase {

  const NODE_TYPE = 'article';
  const PARAGRAPH_TYPE = 'test_text';
  const NODE_PARA_FIELD = 'node_paragraph_field';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'entity_pilot_err',
    'entity_pilot',
    'paragraphs',
    'entity_reference_revisions',
    'serialization',
    'rest',
    'hal',
    'node',
    'user',
    'system',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setUpNodeAndParagraphTypes();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Sets up a node type with a paragraph field.
   */
  protected function setUpNodeAndParagraphTypes() {
    // Create paragraphs and article content types.
    $values = ['type' => 'article', 'name' => 'Article'];
    $node_type = NodeType::create($values);
    $node_type->save();

    // Create the paragraph type.
    $paragraph_id = 'test_text';
    $paragraph_type = ParagraphsType::create([
      'label' => 'Test text',
      'id' => $paragraph_id,
    ]);
    $paragraph_type->save();
    $node_para_field = 'node_paragraph_field';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $node_para_field,
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => '-1',
      'settings' => [
        'target_type' => 'paragraph',
      ],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
    ]);
    $field->save();
  }

}