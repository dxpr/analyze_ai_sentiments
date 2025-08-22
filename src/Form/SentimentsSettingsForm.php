<?php

namespace Drupal\analyze_ai_sentiments\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\analyze_ai_sentiments\Service\SentimentsStorageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure sentiments analysis settings.
 */
class SentimentsSettingsForm extends ConfigFormBase {
  /**
   * The sentiments storage service.
   */
  protected SentimentsStorageService $sentimentstorage;

  /**
   * Constructs a SentimentsSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\analyze_ai_sentiments\Service\SentimentsStorageService $sentiments_storage
   *   The sentiments storage service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    SentimentsStorageService $sentiments_storage,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->sentimentstorage = $sentiments_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
          $container->get('config.factory'),
          $container->get('config.typed'),
          $container->get('analyze_ai_sentiments.storage')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analyze_ai_sentiments_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    /** @var array<string> */
    return ['analyze_ai_sentiments.settings'];
  }

  /**
   * Gets the default sentiments configurations.
   *
   * @return array<string, array<string, mixed>>
   *   Array of default sentiments configurations.
   */
  public function getDefaultSentiments(): array {
    return [
      'sentiments' => [
        'label' => $this->t('Overall Sentiments'),
        'min_label' => $this->t('Negative'),
        'mid_label' => $this->t('Neutral'),
        'max_label' => $this->t('Positive'),
        'weight' => 0,
      ],
      'engagement' => [
        'label' => $this->t('Engagement Level'),
        'min_label' => $this->t('Passive'),
        'mid_label' => $this->t('Balanced'),
        'max_label' => $this->t('Interactive'),
        'weight' => 1,
      ],
      'trust' => [
        'label' => $this->t('Trust/Credibility'),
        'min_label' => $this->t('Promotional'),
        'mid_label' => $this->t('Balanced'),
        'max_label' => $this->t('Authoritative'),
        'weight' => 2,
      ],
      'objectivity' => [
        'label' => $this->t('Objectivity'),
        'min_label' => $this->t('Subjective'),
        'mid_label' => $this->t('Mixed'),
        'max_label' => $this->t('Objective'),
        'weight' => 3,
      ],
      'complexity' => [
        'label' => $this->t('Technical Complexity'),
        'min_label' => $this->t('Basic'),
        'mid_label' => $this->t('Moderate'),
        'max_label' => $this->t('Complex'),
        'weight' => 4,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed> $form */
    $config = $this->config('analyze_ai_sentiments.settings');
    $sentiments = $config->get('sentiments') ?: $this->getDefaultSentiments();

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['sentiments-description']],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Configure the sentiments metrics used to analyze content. Each sentiments has a scale from -1.0 to +1.0 with customizable labels.'),
      ],
    ];

    $form['table'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['sentiments-table-container']],
    ];

    $form['table']['sentiments'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Sentiments'),
        $this->t('Labels'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#tabledrag' => [
      [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'sentiments-weight',
      ],
      ],
    ];

    // Sort sentiments by weight.
    uasort($sentiments, function ($a, $b) {
        return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    // Add existing sentiments to the table.
    foreach ($sentiments as $id => $sentiments) {
      $form['table']['sentiments'][$id] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        'label' => [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#title_display' => 'invisible',
          '#default_value' => $sentiments['label'],
          '#required' => TRUE,
        ],
        'labels' => [
          '#type' => 'details',
          '#title' => $this->t('Range Labels'),
          'min_label' => [
            '#type' => 'textfield',
            '#title' => $this->t('Minimum'),
            '#default_value' => $sentiments['min_label'],
            '#required' => TRUE,
          ],
          'mid_label' => [
            '#type' => 'textfield',
            '#title' => $this->t('Middle'),
            '#default_value' => $sentiments['mid_label'],
            '#required' => TRUE,
          ],
          'max_label' => [
            '#type' => 'textfield',
            '#title' => $this->t('Maximum'),
            '#default_value' => $sentiments['max_label'],
            '#required' => TRUE,
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $sentiments['weight'],
          '#attributes' => ['class' => ['sentiments-weight']],
        ],
        'operations' => [
          '#type' => 'operations',
          '#links' => [
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('analyze_ai_sentiments.delete_sentiments', ['sentiments_id' => $id]),
              'attributes' => [
                'class' => ['button', 'button--danger', 'button--small'],
              ],
            ],
          ],
        ],
      ];
    }

    // Help text for drag-and-drop.
    if (!empty($sentiments)) {
      $form['table_help'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Drag and drop rows to reorder the sentiments. This order will be reflected in the analysis display.'),
        '#attributes' => ['class' => ['sentiments-help-text', 'description']],
        '#weight' => 5,
      ];
    }

    $form = parent::buildForm($form, $form_state);

    // Improve the save button.
    $form['actions']['submit']['#value'] = $this->t('Save changes');
    $form['actions']['submit']['#attributes']['class'][] = 'button--primary';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var array<string, mixed> $form */
    $sentiments = [];
    foreach ($form_state->getValue('sentiments') as $id => $values) {
      $sentiments[$id] = [
        'label' => $values['label'],
        'min_label' => $values['labels']['min_label'],
        'mid_label' => $values['labels']['mid_label'],
        'max_label' => $values['labels']['max_label'],
        'weight' => $values['weight'],
      ];
    }

    $this->config('analyze_ai_sentiments.settings')
      ->set('sentiments', $sentiments)
      ->save();

    // Invalidate all cached sentiments analysis results since configuration
    // changed.
    $this->sentimentstorage->invalidateConfigCache();

    parent::submitForm($form, $form_state);
  }

}
