<?php

namespace Drupal\custom_field_ai\Plugin\FieldWidgetAction;

use Drupal\ai_automators\Plugin\FieldWidgetAction\AutomatorBaseAction;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The field widget action for "Custom Field".
 */
#[FieldWidgetAction(
  id: 'automator_custom_field',
  label: new TranslatableMarkup('Automator Custom Field'),
  widget_types: [
    'custom_stacked',
    'custom_flex',
  ],
  field_types: [
    'custom',
  ],
  category: new TranslatableMarkup('AI Automators'),
)]
class CustomField extends AutomatorBaseAction {

  /**
   * The custom field type manager.
   *
   * @var \Drupal\custom_field\Plugin\CustomFieldTypeManagerInterface
   */
  protected CustomFieldTypeManagerInterface $customFieldTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->customFieldTypeManager = $container->get('plugin.manager.custom_field_type');
    return $instance;
  }

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state): array {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    // @todo Best practice.
    $form_key = $array_parents[0];
    $key = $array_parents[2] ?? 0;
    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    $items = $entity->get($form_key);
    $settings = $items->getFieldDefinition()->getSettings();
    $custom_field_items = $this->customFieldTypeManager->getCustomFieldItems($settings);

    // Process either a single item or all items based on $key.
    $deltas = $key !== NULL ? [$key] : array_keys(iterator_to_array($items));

    foreach ($deltas as $delta) {
      if (!isset($items[$delta])) {
        continue;
      }

      $item = $items[$delta];
      foreach ($custom_field_items as $name => $custom_field_item) {
        if (!$item->get($name)) {
          continue;
        }

        $value = $item->get($name)->getValue();
        $data_type = $custom_field_item->getDataType();
        if (in_array($data_type, ['uri', 'link'])) {
          $form[$form_key]['widget'][$delta][$name]['uri']['#value'] = $value;
        }
        elseif ($data_type === 'boolean') {
          // For boolean fields, set both value and checked state.
          $form[$form_key]['widget'][$delta][$name]['#value'] = $value ? 1 : 0;
          $form[$form_key]['widget'][$delta][$name]['#checked'] = (bool) $value;
          $form[$form_key]['widget'][$delta][$name]['#default_value'] = $value ? 1 : 0;
        }
        else {
          $form[$form_key]['widget'][$delta][$name]['#value'] = $value;
        }
      }
    }

    return $form[$form_key];
  }

}
