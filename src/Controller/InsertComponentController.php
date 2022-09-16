<?php

namespace Drupal\layout_paragraphs_skip_form\Controller;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\layout_paragraphs\Utility\Dialog;
use Symfony\Component\HttpFoundation\Request;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\Ajax\LayoutParagraphsEventCommand;
use Drupal\layout_paragraphs\Controller\ComponentFormController;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutRefreshTrait;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\paragraphs\ParagraphInterface;

/**
 * InsertComponentController class definition.
 */
class InsertComponentController extends ComponentFormController {

  use LayoutParagraphsLayoutRefreshTrait;

  /**
   * The tempstore service.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository
   */
  protected $tempstore;

  /**
   * {@inheritDoc}
   */
  public function __construct(LayoutParagraphsLayoutTempstoreRepository $tempstore) {
    $this->tempstore = $tempstore;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function skipInsertForm(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout, ParagraphsTypeInterface $paragraph_type) {
    $skip_for_types = $this->config('layout_paragraphs_skip_form.settings')->get('skip_create_form');
    if (isset($skip_for_types[$paragraph_type->id()])) {
      $response = new AjaxResponse();
      $this->setLayoutParagraphsLayout($layout_paragraphs_layout);

      $parent_uuid = $request->query->get('parent_uuid');
      $region = $request->query->get('region');
      $sibling_uuid = $request->query->get('sibling_uuid');
      $placement = $request->query->get('placement');

      $entity_type = $this->entityTypeManager()->getDefinition('paragraph');
      $bundle_key = $entity_type->getKey('bundle');
      /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
      $paragraph = $this->entityTypeManager->getStorage('paragraph')
        ->create([$bundle_key => $paragraph_type->id()]);

      $this->populateDefaultTextValues($paragraph);

      if ($sibling_uuid && $placement) {
        switch ($placement) {
          case 'before':
            $this->layoutParagraphsLayout->insertBeforeComponent($sibling_uuid, $paragraph);
            break;

          case 'after':
            $this->layoutParagraphsLayout->insertAfterComponent($sibling_uuid, $paragraph);
            break;
        }
      }
      elseif ($parent_uuid && $region) {
        $this->layoutParagraphsLayout->insertIntoRegion($parent_uuid, $region, $paragraph);
      }
      else {
        $this->layoutParagraphsLayout->appendComponent($paragraph);
      }

      $this->tempstore->set($this->layoutParagraphsLayout);
      $rendered_component = [
        '#type' => 'layout_paragraphs_builder',
        '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
        '#uuid' => $paragraph->uuid(),
      ];

      $response->addCommand(new CloseDialogCommand(Dialog::dialogSelector($this->layoutParagraphsLayout)));
      if ($this->needsRefresh()) {
        return $this->refreshLayout($response);
      }

      if ($placement == 'before') {
        $response->addCommand(new BeforeCommand('[data-uuid="' . $sibling_uuid . '"]', $rendered_component));
      }
      elseif ($placement == 'after') {
        $response->addCommand(new AfterCommand('[data-uuid="' . $sibling_uuid . '"]', $rendered_component));
      }
      elseif ($parent_uuid && $region) {
        $response->addCommand(new AppendCommand('[data-region-uuid="' . $parent_uuid . '-' . $region . '"]', $rendered_component));
      }
      $response->addCommand(new LayoutParagraphsEventCommand($this->layoutParagraphsLayout, $paragraph->uuid(), 'component:insert'));
      return $response;

    }
    return parent::insertForm($request, $layout_paragraphs_layout, $paragraph_type);
  }

  /**
   * Populates a paragraph entity's text fields with their default values.
   *
   * For text fields without default values, this will insert an HTML comment
   * placeholder to make sure the field is rendered so inline editing works.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   */
  protected function populateDefaultTextValues(ParagraphInterface &$paragraph) {

    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('paragraph', $paragraph->bundle());

    // Build an array of default field values keyed by field name.
    $field_defaults = array_map(
      function ($def) use ($paragraph) {
        return $def->getDefaultValue($paragraph);
      },
      array_filter(
        $field_definitions,
        function ($def) use ($paragraph) {
          return !empty($def->getDefaultValue($paragraph));
        }
      ));
    // Build an array of text field names.
    $field_keys = array_keys(
      array_filter(
        $field_definitions,
        function ($def) use ($paragraph) {
          return (strpos($def->getType(), 'text') === 0) && empty($def->getDefaultValue($paragraph));
        }
      ));
    foreach ($field_defaults as $field_name => $default_value) {
      $paragraph->set($field_name, $default_value);
    }
  }

}
